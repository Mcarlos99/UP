<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'config_multitenant.php'; // MULTI-TENANT

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

define('INCLUDED', true);
$pageTitle = 'Categorias';
$pageSubtitle = 'Gerencie as categorias de produtos';

// MULTI-TENANT: Obter empresa do usuário logado
$empresaId = getEmpresaId();

$db = getDB();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Criar nova categoria
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        try {
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao'] ?? '');
            $icone = sanitize($_POST['icone'] ?? '📦');
            $cor = sanitize($_POST['cor'] ?? '#8B5CF6');
            $ordem = (int)($_POST['ordem'] ?? 0);
            
            if (empty($nome)) {
                throw new Exception('Nome da categoria é obrigatório');
            }
            
            // MULTI-TENANT: Verificar se já existe NA EMPRESA
            $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = ? AND ativo = 1 AND empresa_id = ?");
            $stmt->execute([$nome, $empresaId]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma categoria com este nome');
            }
            
            // MULTI-TENANT: Inserir com empresa_id
            $stmt = $db->prepare("
                INSERT INTO categorias (empresa_id, nome, descricao, icone, cor, ordem, ativo)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$empresaId, $nome, $descricao, $icone, $cor, $ordem]);
            
            logActivity('Categoria criada', 'categorias', $db->lastInsertId());
            $_SESSION['success'] = 'Categoria criada com sucesso!';
            header('Location: categorias.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao criar categoria: ' . $e->getMessage();
        }
    }
    
    // Editar categoria
    if (isset($_POST['action']) && $_POST['action'] === 'edit' && isset($_POST['categoria_id'])) {
        try {
            // MULTI-TENANT: Validar acesso
            validarAcessoEmpresa('categorias', $_POST['categoria_id']);
            
            $categoriaId = (int)$_POST['categoria_id'];
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao'] ?? '');
            $icone = sanitize($_POST['icone'] ?? '📦');
            $cor = sanitize($_POST['cor'] ?? '#8B5CF6');
            $ordem = (int)($_POST['ordem'] ?? 0);
            
            if (empty($nome)) {
                throw new Exception('Nome da categoria é obrigatório');
            }
            
            // MULTI-TENANT: Verificar se já existe outro com mesmo nome NA EMPRESA
            $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = ? AND id != ? AND ativo = 1 AND empresa_id = ?");
            $stmt->execute([$nome, $categoriaId, $empresaId]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe outra categoria com este nome');
            }
            
            // MULTI-TENANT: Atualizar com validação de empresa
            $stmt = $db->prepare("
                UPDATE categorias SET 
                    nome = ?, descricao = ?, icone = ?, cor = ?, ordem = ?
                WHERE id = ? AND empresa_id = ?
            ");
            
            $stmt->execute([$nome, $descricao, $icone, $cor, $ordem, $categoriaId, $empresaId]);
            
            logActivity('Categoria editada', 'categorias', $categoriaId);
            $_SESSION['success'] = 'Categoria atualizada com sucesso!';
            header('Location: categorias.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao editar categoria: ' . $e->getMessage();
        }
    }
    
    // Excluir categoria
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['categoria_id'])) {
        try {
            // MULTI-TENANT: Validar acesso
            validarAcessoEmpresa('categorias', $_POST['categoria_id']);
            
            $categoriaId = (int)$_POST['categoria_id'];
            
            // MULTI-TENANT: Verificar se há produtos nesta categoria NA EMPRESA
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE categoria_id = ? AND ativo = 1 AND empresa_id = ?");
            $stmt->execute([$categoriaId, $empresaId]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                throw new Exception("Não é possível excluir esta categoria pois existem $total produto(s) vinculado(s) a ela");
            }
            
            // MULTI-TENANT: Desativar com validação de empresa
            $stmt = $db->prepare("UPDATE categorias SET ativo = 0 WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$categoriaId, $empresaId]);
            
            logActivity('Categoria excluída', 'categorias', $categoriaId);
            $_SESSION['success'] = 'Categoria removida com sucesso!';
            header('Location: categorias.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao excluir categoria: ' . $e->getMessage();
        }
    }
}

// MULTI-TENANT: Buscar categorias DA EMPRESA
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$where = ["ativo = 1", "empresa_id = ?"];
$params = [$empresaId];

if (!empty($busca)) {
    $where[] = "(nome LIKE ? OR descricao LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

try {
    // Buscar categorias
    $sql = "SELECT * FROM categorias WHERE " . implode(" AND ", $where) . " ORDER BY ordem, nome";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $categorias = $stmt->fetchAll();
    
    // MULTI-TENANT: Estatísticas DA EMPRESA
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM categorias WHERE ativo = 1 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalCategorias = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM produtos p 
        INNER JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.ativo = 1 AND c.ativo = 1 AND p.empresa_id = ?
    ");
    $stmt->execute([$empresaId]);
    $totalProdutosCategorias = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM produtos 
        WHERE ativo = 1 AND (categoria_id IS NULL OR categoria_id NOT IN (SELECT id FROM categorias WHERE ativo = 1 AND empresa_id = ?)) AND empresa_id = ?
    ");
    $stmt->execute([$empresaId, $empresaId]);
    $produtosSemCategoria = $stmt->fetch()['total'];
    
    // Categoria com mais produtos
    $stmt = $db->prepare("
        SELECT c.nome, COUNT(p.id) as total 
        FROM categorias c 
        LEFT JOIN produtos p ON c.id = p.categoria_id AND p.ativo = 1 
        WHERE c.ativo = 1 AND c.empresa_id = ?
        GROUP BY c.id 
        ORDER BY total DESC 
        LIMIT 1
    ");
    $stmt->execute([$empresaId]);
    $categoriaTop = $stmt->fetch();
    $categoriaMaisProdutos = $categoriaTop ? $categoriaTop['nome'] : 'Nenhuma';
    $qtdCategoriaTop = $categoriaTop ? $categoriaTop['total'] : 0;
    
} catch (PDOException $e) {
    $erro = "Erro ao carregar categorias: " . $e->getMessage();
}

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
    unset($_SESSION['error']);
}
?>

<!-- Cabeçalho -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-3xl font-bold text-gray-900">Categorias</h2>
        <p class="text-gray-600 mt-1">Gerencie as categorias de produtos</p>
    </div>
    <button onclick="abrirModalCategoria()" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nova Categoria
    </button>
</div>

<!-- Cards de Estatísticas - FORMATO 4x1 QUADRADO -->
<div class="grid grid-cols-4 gap-4 mb-6" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <!-- Card 1: Total de Categorias (Amarelo/Laranja) -->
    <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-tags text-4xl"></i>
            </div>
            <p class="text-yellow-100 text-base font-bold mb-3">Total de Categorias</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalCategorias; ?></p>
            <p class="text-yellow-100 text-base font-semibold">Cadastradas</p>
        </div>
    </div>
    
    <!-- Card 2: Produtos Categorizados (Azul/Cyan) -->
    <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-boxes text-4xl"></i>
            </div>
            <p class="text-blue-100 text-base font-bold mb-3">Produtos Categorizados</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalProdutosCategorias; ?></p>
            <p class="text-blue-100 text-base font-semibold">Com categoria</p>
        </div>
    </div>
    
    <!-- Card 3: Sem Categoria (Verde/Esmeralda) -->
    <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-exclamation-triangle text-4xl"></i>
            </div>
            <p class="text-green-100 text-base font-bold mb-3">Sem Categoria</p>
            <p class="text-4xl font-bold mb-2"><?php echo $produtosSemCategoria; ?></p>
            <p class="text-green-100 text-base font-semibold">Produtos</p>
        </div>
    </div>
    
    <!-- Card 4: Categoria Mais Popular (Roxo/Rosa) -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-star text-4xl"></i>
            </div>
            <p class="text-purple-100 text-base font-bold mb-3">Mais Popular</p>
            <p class="text-2xl font-bold mb-2"><?php echo $categoriaMaisProdutos; ?></p>
            <p class="text-purple-100 text-base font-semibold"><?php echo $qtdCategoriaTop; ?> produtos</p>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="white-card mb-6">
    <form method="GET" action="" class="flex gap-4">
        <input 
            type="text" 
            name="busca" 
            placeholder="Buscar categorias..." 
            value="<?php echo htmlspecialchars($busca); ?>"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
        >
        <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
            <i class="fas fa-search"></i> Buscar
        </button>
    </form>
</div>

<!-- Lista de Categorias -->
<?php if (empty($categorias)): ?>
    <div class="white-card text-center py-12">
        <i class="fas fa-tags text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">Nenhuma categoria encontrada</p>
        <p class="text-gray-500 text-sm mt-2">Crie sua primeira categoria clicando no botão "Nova Categoria"</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($categorias as $categoria): ?>
            <?php
            // MULTI-TENANT: Contar produtos DA CATEGORIA na empresa
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE categoria_id = ? AND ativo = 1 AND empresa_id = ?");
            $stmt->execute([$categoria['id'], $empresaId]);
            $totalProdutos = $stmt->fetch()['total'];
            ?>
            
            <div class="white-card hover:shadow-lg transition">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="text-4xl"><?php echo htmlspecialchars($categoria['icone']); ?></div>
                        <div>
                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($categoria['nome']); ?></h3>
                            <p class="text-xs text-gray-600"><?php echo $totalProdutos; ?> produto(s)</p>
                        </div>
                    </div>
                    <div class="w-4 h-4 rounded-full" style="background-color: <?php echo htmlspecialchars($categoria['cor']); ?>"></div>
                </div>
                
                <?php if (!empty($categoria['descricao'])): ?>
                    <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                <?php endif; ?>
                
                <div class="flex gap-2 pt-3 border-t">
                    <button onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($categoria)); ?>)" class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 transition text-sm">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    
                    <form method="POST" action="" onsubmit="return confirmarExclusao('Tem certeza que deseja excluir esta categoria?')" class="flex-1">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="categoria_id" value="<?php echo $categoria['id']; ?>">
                        <button type="submit" class="w-full bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 transition text-sm">
                            <i class="fas fa-trash"></i> Excluir
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal Nova/Editar Categoria -->
<div id="modalCategoria" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-bold text-gray-900" id="modalTitulo">Nova Categoria</h3>
                <button onclick="fecharModalCategoria()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <form method="POST" action="" id="formCategoria" class="p-6">
            <input type="hidden" name="action" id="modalAction" value="create">
            <input type="hidden" name="categoria_id" id="modalCategoriaId">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome da Categoria *</label>
                    <input 
                        type="text" 
                        name="nome" 
                        id="modalNome"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Ex: Cadernos"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                    <textarea 
                        name="descricao" 
                        id="modalDescricao"
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Descrição da categoria..."
                    ></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ícone (Emoji)</label>
                        <input 
                            type="text" 
                            name="icone" 
                            id="modalIcone"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-center text-2xl"
                            placeholder="📦"
                            maxlength="4"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cor</label>
                        <input 
                            type="color" 
                            name="cor" 
                            id="modalCor"
                            class="w-full h-10 border border-gray-300 rounded-lg cursor-pointer"
                        >
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Ordem de Exibição</label>
                    <input 
                        type="number" 
                        name="ordem" 
                        id="modalOrdem"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="0"
                        value="0"
                    >
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="fecharModalCategoria()" class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 transition">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 btn btn-primary">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCategoria() {
    document.getElementById('modalCategoria').classList.remove('hidden');
    document.getElementById('modalCategoria').classList.add('flex');
    document.getElementById('modalTitulo').textContent = 'Nova Categoria';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('formCategoria').reset();
    document.getElementById('modalCategoriaId').value = '';
    document.getElementById('modalIcone').value = '📦';
    document.getElementById('modalCor').value = '#8B5CF6';
    document.getElementById('modalOrdem').value = '0';
}

function fecharModalCategoria() {
    document.getElementById('modalCategoria').classList.add('hidden');
    document.getElementById('modalCategoria').classList.remove('flex');
}

function editarCategoria(categoria) {
    document.getElementById('modalCategoria').classList.remove('hidden');
    document.getElementById('modalCategoria').classList.add('flex');
    document.getElementById('modalTitulo').textContent = 'Editar Categoria';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalCategoriaId').value = categoria.id;
    document.getElementById('modalNome').value = categoria.nome;
    document.getElementById('modalDescricao').value = categoria.descricao || '';
    document.getElementById('modalIcone').value = categoria.icone || '📦';
    document.getElementById('modalCor').value = categoria.cor || '#8B5CF6';
    document.getElementById('modalOrdem').value = categoria.ordem || '0';
}

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalCategoria();
    }
});

// Fechar modal clicando fora
document.getElementById('modalCategoria').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalCategoria();
    }
});
</script>

<?php require_once 'footer.php'; ?>