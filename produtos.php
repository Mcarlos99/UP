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
$pageSubtitle = 'Gerencie seu catálogo de produtos';

// Processar ações
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

// Ordenação
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

// Paginação
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
    
    // Buscar categorias para filtro
    $stmt = $db->prepare("SELECT * FROM categorias WHERE ativo = 1 AND empresa_id = ? ORDER BY ordem, nome");
    $stmt->execute([$empresaId]);
    $categorias = $stmt->fetchAll();
    
    // Estatísticas
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

// Calcular paginação
$totalPaginas = ceil($totalProdutos / $itensPorPagina);

require_once 'header.php';

// Mostrar mensagens
if (isset($_SESSION['success'])) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 md:p-4 mb-4 md:mb-6 rounded alert-auto-close">';
    echo '<p class="font-medium text-sm md:text-base"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</p>';
    echo '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 md:p-4 mb-4 md:mb-6 rounded alert-auto-close">';
    echo '<p class="font-medium text-sm md:text-base"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</p>';
    echo '</div>';
}
?>

<style>
/* Responsivo Mobile - Produtos */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem !important;
    }
    
    .stat-card {
        padding: 1rem !important;
    }
    
    .stat-card .icon-box {
        width: 40px !important;
        height: 40px !important;
        padding: 0.5rem !important;
    }
    
    .stat-card .icon-box i {
        font-size: 1.25rem !important;
    }
    
    .stat-card .stat-title {
        font-size: 0.7rem !important;
        margin-bottom: 0.25rem !important;
    }
    
    .stat-card .stat-value {
        font-size: 1.5rem !important;
        margin-bottom: 0.25rem !important;
    }
    
    .stat-card .stat-subtitle {
        font-size: 0.65rem !important;
    }
    
    /* Filtros em coluna no mobile */
    .filters-grid {
        grid-template-columns: 1fr !important;
    }
    
    /* Grid de produtos 2 colunas no mobile */
    .produtos-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem !important;
    }
    
    /* Cards de produtos mais compactos */
    .produto-card {
        padding: 0.75rem !important;
    }
    
    .produto-card .produto-imagem {
        height: 100px !important;
    }
    
    .produto-card .categoria-badge {
        font-size: 0.65rem !important;
        padding: 0.125rem 0.5rem !important;
    }
    
    .produto-card .produto-nome {
        font-size: 0.75rem !important;
        line-height: 1rem !important;
        height: 2rem !important;
    }
    
    .produto-card .produto-preco {
        font-size: 0.875rem !important;
    }
    
    .produto-card .produto-estoque {
        font-size: 1rem !important;
    }
    
    .produto-card .produto-status {
        font-size: 0.65rem !important;
        padding: 0.25rem 0.5rem !important;
    }
    
    .produto-card .produto-actions {
        gap: 0.25rem !important;
    }
    
    .produto-card .produto-actions a,
    .produto-card .produto-actions button {
        padding: 0.5rem !important;
        font-size: 0.75rem !important;
    }
}

@media (max-width: 480px) {
    .stat-card .stat-value {
        font-size: 1.25rem !important;
    }
}
</style>

<!-- Cabeçalho -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-4 mb-4 md:mb-6">

    <div class="flex gap-2 md:gap-3 w-full md:w-auto">
        
        <a href="categorias.php" class="flex-1 md:flex-none btn btn-primary text-center text-sm md:text-base"">
            
            <i class="fas fa-tags"></i> <span class="hidden sm:inline">Categorias</span> Categorias
        </a>
        
        
        <a href="produto-form.php" class="flex-1 md:flex-none btn btn-primary text-center text-sm md:text-base">
            
            <i class="fas fa-plus"></i> <span class="hidden sm:inline"
                 >Novo</span> Produto
        </a>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 md:mb-6">
    <!-- Total de Produtos -->
    <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-box text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-yellow-100 text-sm md:text-base font-bold mb-2">Total de Produtos</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalAtivos; ?></p>
            <p class="stat-subtitle text-yellow-100 text-xs md:text-base font-semibold">Cadastrados</p>
        </div>
    </div>
    
    <!-- Estoque Baixo -->
    <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-exclamation-triangle text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-blue-100 text-sm md:text-base font-bold mb-2">Estoque Baixo</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $estoqueBaixo; ?></p>
            <p class="stat-subtitle text-blue-100 text-xs md:text-base font-semibold">Atenção</p>
        </div>
    </div>
    
    <!-- Estoque Zerado -->
    <div class="stat-card bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-times-circle text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-green-100 text-sm md:text-base font-bold mb-2">Estoque Zerado</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $estoqueZerado; ?></p>
            <p class="stat-subtitle text-green-100 text-xs md:text-base font-semibold">Sem Estoque</p>
        </div>
    </div>
    
    <!-- Em Destaque -->
    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-star text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-purple-100 text-sm md:text-base font-bold mb-2">Em Destaque</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalDestaques; ?></p>
            <p class="stat-subtitle text-purple-100 text-xs md:text-base font-semibold">Favoritos</p>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="white-card mb-4 md:mb-6 p-3 md:p-4">
    <form method="GET" action="" class="filters-grid grid grid-cols-1 md:grid-cols-5 gap-3 md:gap-4">
        <!-- Busca -->
        <div class="md:col-span-2">
            <input 
                type="text" 
                name="busca" 
                placeholder="Buscar produtos..." 
                value="<?php echo htmlspecialchars($busca); ?>"
                class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
            >
        </div>
        
        <!-- Categoria -->
        <div>
            <select name="categoria" class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
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
            <select name="estoque" class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                <option value="todos" <?php echo $filtroEstoque === 'todos' ? 'selected' : ''; ?>>Todos Estoques</option>
                <option value="baixo" <?php echo $filtroEstoque === 'baixo' ? 'selected' : ''; ?>>Estoque Baixo</option>
                <option value="zerado" <?php echo $filtroEstoque === 'zerado' ? 'selected' : ''; ?>>Zerado</option>
            </select>
        </div>
        
        <!-- Botão -->
        <div>
            <button type="submit" class="w-full bg-purple-600 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm md:text-base">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
    </form>
    
    <!-- Ordenação -->
    <div class="mt-3 md:mt-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-2 md:gap-3">
        <div class="flex items-center gap-2 w-full md:w-auto">
            <span class="text-xs md:text-sm text-gray-600">Ordenar:</span>
            <select name="ordem" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['ordem' => ''])); ?>&ordem=' + this.value" class="flex-1 md:flex-none px-2 md:px-3 py-1 border border-gray-300 rounded-lg text-xs md:text-sm">
                <option value="nome_asc" <?php echo $ordenacao === 'nome_asc' ? 'selected' : ''; ?>>Nome A-Z</option>
                <option value="nome_desc" <?php echo $ordenacao === 'nome_desc' ? 'selected' : ''; ?>>Nome Z-A</option>
                <option value="preco_asc" <?php echo $ordenacao === 'preco_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                <option value="preco_desc" <?php echo $ordenacao === 'preco_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                <option value="estoque_asc" <?php echo $ordenacao === 'estoque_asc' ? 'selected' : ''; ?>>Menor Estoque</option>
                <option value="estoque_desc" <?php echo $ordenacao === 'estoque_desc' ? 'selected' : ''; ?>>Maior Estoque</option>
                <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais Recentes</option>
            </select>
        </div>
        
        <span class="text-xs md:text-sm text-gray-600">
            <?php echo $totalProdutos; ?> produto(s) encontrado(s)
        </span>
    </div>
</div>

<!-- Grid de Produtos -->
<?php if (empty($produtos)): ?>
    <div class="white-card text-center py-8 md:py-12">
        <i class="fas fa-box-open text-5xl md:text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-base md:text-lg">Nenhum produto encontrado</p>
        <p class="text-gray-500 text-xs md:text-sm mt-2">Crie seu primeiro produto clicando no botão "Novo Produto"</p>
    </div>
<?php else: ?>
    <div class="produtos-grid grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-3 gap-4 md:gap-6">
        <?php foreach ($produtos as $produto): ?>
            <div class="produto-card white-card overflow-hidden group relative">
                
                <!-- Badge Destaque -->
                <?php if ($produto['destaque']): ?>
                    <div class="absolute top-2 right-2 z-10">
                        <span class="bg-yellow-400 text-yellow-900 px-2 py-1 rounded-full text-xs font-bold">
                            <i class="fas fa-star"></i>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="h-full flex flex-col p-3">
                    
                    <!-- Imagem -->
                    <div class="produto-imagem relative bg-gradient-to-br from-purple-100 to-pink-100 rounded-lg flex items-center justify-center mb-2 overflow-hidden" style="height: 45%;">
                        <?php if (!empty($produto['imagem']) && file_exists(UPLOAD_DIR . $produto['imagem'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="max-w-full max-h-full object-contain p-2">
                        <?php else: ?>
                            <div class="text-3xl md:text-4xl text-purple-400">
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
                    
                    <!-- Informações -->
                    <div class="flex-1 flex flex-col justify-between">
                        
                        <!-- Categoria -->
                        <span class="categoria-badge inline-block text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-semibold mb-1 self-start">
                            <?php echo $produto['categoria_icone'] ?? '📦'; ?> <?php echo htmlspecialchars($produto['categoria_nome']); ?>
                        </span>
                        
                        <!-- Nome -->
                        <h3 class="produto-nome text-xs font-bold text-gray-900 mb-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 2em; line-height: 1em;">
                            <?php echo htmlspecialchars($produto['nome']); ?>
                        </h3>
                        
                        <!-- Preço -->
                        <div class="mb-1">
                            <p class="text-xs text-gray-600">Preço</p>
                            <p class="produto-preco text-sm font-bold text-purple-600"><?php echo formatarMoeda($produto['preco_venda']); ?></p>
                        </div>
                        
                        <!-- Estoque -->
                        <div class="mb-1">
                            <p class="text-xs text-gray-600">Estoque</p>
                            <p class="produto-estoque text-lg font-bold <?php 
                                if ($produto['estoque_atual'] == 0) echo 'text-red-600';
                                elseif ($produto['estoque_atual'] <= $produto['estoque_minimo']) echo 'text-yellow-600';
                                else echo 'text-green-600';
                            ?>">
                                <?php echo $produto['estoque_atual']; ?>
                            </p>
                        </div>
                        
                        <!-- Status -->
                        <?php if ($produto['estoque_atual'] == 0): ?>
                            <div class="produto-status bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-semibold text-center mb-1">
                                <i class="fas fa-times-circle"></i> Sem
                            </div>
                        <?php elseif ($produto['estoque_atual'] <= $produto['estoque_minimo']): ?>
                            <div class="produto-status bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded text-xs font-semibold text-center mb-1">
                                <i class="fas fa-exclamation-triangle"></i> Baixo
                            </div>
                        <?php else: ?>
                            <div class="produto-status bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-semibold text-center mb-1">
                                <i class="fas fa-check-circle"></i> OK
                            </div>
                        <?php endif; ?>
                        
                        <!-- Botões -->
                        <div class="produto-actions flex gap-1">
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
    <div class="flex justify-center items-center gap-2 mt-6 md:mt-8 flex-wrap">
        <?php if ($paginaAtual > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual - 1])); ?>" class="px-3 md:px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-xs md:text-base">
                <i class="fas fa-chevron-left"></i> <span class="hidden md:inline">Anterior</span>
            </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
               class="px-3 md:px-4 py-2 <?php echo $i === $paginaAtual ? 'bg-purple-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg text-xs md:text-base">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($paginaAtual < $totalPaginas): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual + 1])); ?>" class="px-3 md:px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-xs md:text-base">
                <span class="hidden md:inline">Próxima</span> <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>