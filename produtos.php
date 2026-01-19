<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'config_multitenant.php';

$empresaId = getEmpresaId();

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

define('INCLUDED', true);
$pageTitle = 'Produtos';
$pageSubtitle = 'Gerencie seu catalogo de produtos';

// Processar aÃ§Ãµes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    // Excluir produto
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['produto_id'])) {
        try {
            validarAcessoEmpresa('produtos', $_POST['produto_id']);
            $stmt = $db->prepare("UPDATE produtos SET ativo = 0 WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$_POST['produto_id'], $empresaId]);
            
            logActivity('Produto desativado', 'produtos', $_POST['produto_id']);
            $_SESSION['success'] = 'Produto removido com sucesso!';
            header('Location: produtos.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao remover produto: ' . $e->getMessage();
        }
    }
    
    // Alternar destaque
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_destaque' && isset($_POST['produto_id'])) {
        try {
            // MULTI-TENANT: Validar acesso
            validarAcessoEmpresa('produtos', $_POST['produto_id']);
            
            $stmt = $db->prepare("UPDATE produtos SET destaque = NOT destaque WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$_POST['produto_id'], $empresaId]);
            
            logActivity('Destaque do produto alterado', 'produtos', $_POST['produto_id']);
            header('Location: produtos.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao alterar destaque: ' . $e->getMessage();
        }
    }
}

// Filtros e busca
$filtroCategoria = isset($_GET['categoria']) ? $_GET['categoria'] : 'todas';
$filtroEstoque = isset($_GET['estoque']) ? $_GET['estoque'] : 'todos';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$ordenacao = isset($_GET['ordem']) ? $_GET['ordem'] : 'nome_asc';

// Construir query
$where = ["p.ativo = 1", "p.empresa_id = ?"];
$params = [$empresaId];

if ($filtroCategoria !== 'todas') {
    $where[] = "p.categoria_id = ?";
    $params[] = $filtroCategoria;
}

if ($filtroEstoque === 'baixo') {
    $where[] = "p.estoque_atual <= p.estoque_minimo";
} elseif ($filtroEstoque === 'zerado') {
    $where[] = "p.estoque_atual = 0";
}

if (!empty($busca)) {
    $where[] = "(p.nome LIKE ? OR p.descricao LIKE ? OR p.codigo_sku LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// OrdenaÃ§Ã£o
$orderBy = "p.nome ASC";
switch ($ordenacao) {
    case 'nome_desc':
        $orderBy = "p.nome DESC";
        break;
    case 'preco_asc':
        $orderBy = "p.preco_venda ASC";
        break;
    case 'preco_desc':
        $orderBy = "p.preco_venda DESC";
        break;
    case 'estoque_asc':
        $orderBy = "p.estoque_atual ASC";
        break;
    case 'estoque_desc':
        $orderBy = "p.estoque_atual DESC";
        break;
    case 'recentes':
        $orderBy = "p.data_criacao DESC";
        break;
}

// PaginaÃ§Ã£o
$itensPorPagina = 12;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Buscar produtos
$produtos = [];
$totalProdutos = 0;

try {
    $db = getDB();
    
    // Contar total
    $sql = "SELECT COUNT(*) as total FROM produtos p WHERE " . implode(" AND ", $where);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $totalProdutos = $stmt->fetch()['total'];
    
    // Buscar produtos
    $sql = "SELECT p.*, c.nome as categoria_nome, c.icone as categoria_icone
            FROM produtos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE " . implode(" AND ", $where) . "
            ORDER BY $orderBy
            LIMIT $itensPorPagina OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll();
    
    // MULTI-TENANT: Buscar categorias DA EMPRESA para filtro
    $stmt = $db->prepare("SELECT * FROM categorias WHERE ativo = 1 AND empresa_id = ? ORDER BY ordem, nome");
    $stmt->execute([$empresaId]);
    $categorias = $stmt->fetchAll();
    
    // MULTI-TENANT: Estatísticas DA EMPRESA (CORRIGIDO!)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalAtivos = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND estoque_atual <= estoque_minimo AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $estoqueBaixo = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND estoque_atual = 0 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $estoqueZerado = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND destaque = 1 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalDestaques = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $erro = "Erro ao carregar produtos: " . $e->getMessage();
}

// Calcular paginaÃ§Ã£o
$totalPaginas = ceil($totalProdutos / $itensPorPagina);

require_once 'header.php';

// Mostrar mensagens
if (isset($_SESSION['success'])) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded alert-auto-close">';
    echo '<p class="font-medium"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</p>';
    echo '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded alert-auto-close">';
    echo '<p class="font-medium"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</p>';
    echo '</div>';
}
?>

<!-- CabeÃ§alho -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-3xl font-bold text-gray-900">Produtos</h2>
        <p class="text-gray-600 mt-1">Gerencie seu catÃ¡logo de produtos</p>
    </div>
    <div class="flex gap-3">
        <a href="categorias.php" class="bg-gray-600 text-white px-4 py-3 rounded-lg hover:bg-gray-700 transition font-semibold">
            <i class="fas fa-tags"></i> Categorias
        </a>
        <a href="produto-form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Produto
        </a>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="grid grid-cols-4 gap-4 mb-6" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <!-- Total de Produtos -->
    <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-box text-4xl"></i>
            </div>
            <p class="text-yellow-100 text-base font-bold mb-3">Total de Produtos</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalAtivos; ?></p>
            <p class="text-yellow-100 text-base font-semibold">Cadastrados</p>
        </div>
    </div>
    
    <!-- Estoque Baixo -->
    <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-exclamation-triangle text-4xl"></i>
            </div>
            <p class="text-blue-100 text-base font-bold mb-3">Estoque Baixo</p>
            <p class="text-4xl font-bold mb-2"><?php echo $estoqueBaixo; ?></p>
            <p class="text-blue-100 text-base font-semibold">Atenção</p>
        </div>
    </div>
    
    <!-- Estoque Zerado -->
    <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-times-circle text-4xl"></i>
            </div>
            <p class="text-green-100 text-base font-bold mb-3">Estoque Zerado</p>
            <p class="text-4xl font-bold mb-2"><?php echo $estoqueZerado; ?></p>
            <p class="text-green-100 text-base font-semibold">Sem Estoque</p>
        </div>
    </div>
    
    <!-- Em Destaque -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-star text-4xl"></i>
            </div>
            <p class="text-purple-100 text-base font-bold mb-3">Em Destaque</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalDestaques; ?></p>
            <p class="text-purple-100 text-base font-semibold">Favoritos</p>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="white-card mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <!-- Busca -->
        <div class="md:col-span-2">
            <input 
                type="text" 
                name="busca" 
                placeholder="Buscar produtos..." 
                value="<?php echo htmlspecialchars($busca); ?>"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
        </div>
        
        <!-- Categoria -->
        <div>
            <select name="categoria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="todas">Todas Categorias</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $filtroCategoria == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Estoque -->
        <div>
            <select name="estoque" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="todos" <?php echo $filtroEstoque === 'todos' ? 'selected' : ''; ?>>Todos Estoques</option>
                <option value="baixo" <?php echo $filtroEstoque === 'baixo' ? 'selected' : ''; ?>>Estoque Baixo</option>
                <option value="zerado" <?php echo $filtroEstoque === 'zerado' ? 'selected' : ''; ?>>Zerado</option>
            </select>
        </div>
        
        <!-- BotÃ£o -->
        <div>
            <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
    </form>
    
    <!-- OrdenaÃ§Ã£o -->
    <div class="mt-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Ordenar por:</span>
            <select name="ordem" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['ordem' => ''])); ?>&ordem=' + this.value" class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                <option value="nome_asc" <?php echo $ordenacao === 'nome_asc' ? 'selected' : ''; ?>>Nome A-Z</option>
                <option value="nome_desc" <?php echo $ordenacao === 'nome_desc' ? 'selected' : ''; ?>>Nome Z-A</option>
                <option value="preco_asc" <?php echo $ordenacao === 'preco_asc' ? 'selected' : ''; ?>>Menor PreÃ§o</option>
                <option value="preco_desc" <?php echo $ordenacao === 'preco_desc' ? 'selected' : ''; ?>>Maior PreÃ§o</option>
                <option value="estoque_asc" <?php echo $ordenacao === 'estoque_asc' ? 'selected' : ''; ?>>Menor Estoque</option>
                <option value="estoque_desc" <?php echo $ordenacao === 'estoque_desc' ? 'selected' : ''; ?>>Maior Estoque</option>
                <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais Recentes</option>
            </select>
        </div>
        
        <span class="text-sm text-gray-600">
            <?php echo $totalProdutos; ?> produto(s) encontrado(s)
        </span>
    </div>
</div>

<!-- Grid de Produtos - FORMATO 4x1 HORIZONTAL (igual cards de estatísticas) -->
<?php if (empty($produtos)): ?>
    <div class="white-card text-center py-12">
        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">Nenhum produto encontrado</p>
        <p class="text-gray-500 text-sm mt-2">Crie seu primeiro produto clicando no botão "Novo Produto"</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-3 xl:grid-cols-3 gap-6">
        <?php foreach ($produtos as $produto): ?>
            <div class="white-card overflow-hidden group relative">
                
                
                    <!-- Badge Destaque -->
                    <?php if ($produto['destaque']): ?>
                     <div class="absolute top-2 right-2 z-10">
                        <span class="bg-yellow-400 text-yellow-900 px-2 py-1 rounded-full text-xs font-bold">
                            <i class="fas fa-star"></i>
                        </span>
                     </div>
                     <?php endif; ?>
                
                <div class="h-full flex flex-col p-3">
                    
                    <!-- Imagem (45% topo) -->
                    <div class="relative bg-gradient-to-br from-purple-100 to-pink-100 rounded-lg flex items-center justify-center mb-2 overflow-hidden" style="height: 45%;">
                        <?php if (!empty($produto['imagem']) && file_exists(UPLOAD_DIR . $produto['imagem'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="max-w-full max-h-full object-contain p-2">
                        <?php else: ?>
                            <div class="text-4xl text-purple-400">
                                <i class="fas fa-box"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Overlay hover -->
                        <div class="absolute inset-0 bg-black bg-opacity-60 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-1">
                            <a href="produto-form.php?id=<?php echo $produto['id']; ?>" class="bg-white text-gray-900 p-1.5 rounded-lg" title="Editar">
                                <i class="fas fa-edit text-xs"></i>
                            </a>
                            <form method="POST" action="" class="inline">
                                <input type="hidden" name="action" value="toggle_destaque">
                                <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                                <button type="submit" class="bg-white text-gray-900 p-1.5 rounded-lg" title="Destaque">
                                    <i class="fas fa-star text-xs <?php echo $produto['destaque'] ? 'text-yellow-500' : ''; ?>"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Informações (70% restante) -->
                    <div class="flex-1 flex flex-col justify-between">
                        
                        <!-- Categoria -->
                        <span class="inline-block text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-semibold mb-1 self-start">
                            <?php echo $produto['categoria_icone'] ?? '📦'; ?> <?php echo htmlspecialchars($produto['categoria_nome']); ?>
                        </span>
                        
                        <!-- Nome -->
                        <h3 class="text-xs font-bold text-gray-900 mb-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 2em; line-height: 1em;">
                            <?php echo htmlspecialchars($produto['nome']); ?>
                        </h3>
                        
                        <!-- Preço -->
                        <div class="mb-1">
                            <p class="text-xs text-gray-600">Preço</p>
                            <p class="text-sm font-bold text-purple-600"><?php echo formatarMoeda($produto['preco_venda']); ?></p>
                        </div>
                        
                        <!-- Estoque -->
                        <div class="mb-1">
                            <p class="text-xs text-gray-600">Estoque</p>
                            <p class="text-lg font-bold <?php 
                                if ($produto['estoque_atual'] == 0) echo 'text-red-600';
                                elseif ($produto['estoque_atual'] <= $produto['estoque_minimo']) echo 'text-yellow-600';
                                else echo 'text-green-600';
                            ?>">
                                <?php echo $produto['estoque_atual']; ?>
                            </p>
                        </div>
                        
                        <!-- Status -->
                        <?php if ($produto['estoque_atual'] == 0): ?>
                            <div class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-semibold text-center mb-1">
                                <i class="fas fa-times-circle"></i> Sem
                            </div>
                        <?php elseif ($produto['estoque_atual'] <= $produto['estoque_minimo']): ?>
                            <div class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded text-xs font-semibold text-center mb-1">
                                <i class="fas fa-exclamation-triangle"></i> Baixo
                            </div>
                        <?php else: ?>
                            <div class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-semibold text-center mb-1">
                                <i class="fas fa-check-circle"></i> OK
                            </div>
                        <?php endif; ?>
                        
                        <!-- Botões -->
                        <div class="flex gap-1">
                            <a href="produto-form.php?id=<?php echo $produto['id']; ?>" class="flex-1 bg-blue-500 text-white px-2 py-1 rounded-lg hover:bg-blue-600 transition text-center text-xs font-semibold">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="" onsubmit="return confirmarExclusao('Tem certeza que deseja remover este produto?')" class="flex-1">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                                <button type="submit" class="w-full bg-red-500 text-white px-2 py-1 rounded-lg hover:bg-red-600 transition text-xs font-semibold">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Paginação -->
<?php if ($totalPaginas > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-8">
        <?php if ($paginaAtual > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual - 1])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <i class="fas fa-chevron-left"></i> Anterior
            </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
               class="px-4 py-2 <?php echo $i === $paginaAtual ? 'bg-purple-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($paginaAtual < $totalPaginas): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual + 1])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                PrÃ³xima <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>