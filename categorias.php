<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'config_multitenant.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

define('INCLUDED', true);
$pageTitle = 'Categorias';
$pageSubtitle = 'Gerencie as categorias de produtos';

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
            
            // Verificar se já existe
            $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = ? AND ativo = 1");
            $stmt->execute([$nome]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma categoria com este nome');
            }
            
            $stmt = $db->prepare("
                INSERT INTO categorias (nome, descricao, icone, cor, ordem, ativo)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$nome, $descricao, $icone, $cor, $ordem]);
            
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
            $categoriaId = (int)$_POST['categoria_id'];
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao'] ?? '');
            $icone = sanitize($_POST['icone'] ?? '📦');
            $cor = sanitize($_POST['cor'] ?? '#8B5CF6');
            $ordem = (int)($_POST['ordem'] ?? 0);
            
            if (empty($nome)) {
                throw new Exception('Nome da categoria é obrigatório');
            }
            
            // Verificar se já existe outro com o mesmo nome
            $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = ? AND id != ? AND ativo = 1");
            $stmt->execute([$nome, $categoriaId]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe outra categoria com este nome');
            }
            
            $stmt = $db->prepare("
                UPDATE categorias SET 
                    nome = ?, descricao = ?, icone = ?, cor = ?, ordem = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$nome, $descricao, $icone, $cor, $ordem, $categoriaId]);
            
            logActivity('Categoria atualizada', 'categorias', $categoriaId);
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
            $categoriaId = (int)$_POST['categoria_id'];
            
            // Verificar se há produtos vinculados
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE categoria_id = ? AND ativo = 1");
            $stmt->execute([$categoriaId]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                throw new Exception("Não é possível excluir esta categoria pois existem $total produto(s) vinculado(s). Mova os produtos para outra categoria primeiro.");
            }
            
            $stmt = $db->prepare("UPDATE categorias SET ativo = 0 WHERE id = ?");
            $stmt->execute([$categoriaId]);
            
            logActivity('Categoria desativada', 'categorias', $categoriaId);
            $_SESSION['success'] = 'Categoria removida com sucesso!';
            header('Location: categorias.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao excluir categoria: ' . $e->getMessage();
        }
    }
    
    // Reordenar categorias
    if (isset($_POST['action']) && $_POST['action'] === 'reorder' && isset($_POST['ordem'])) {
        try {
            $ordem = json_decode($_POST['ordem'], true);
            
            $stmt = $db->prepare("UPDATE categorias SET ordem = ? WHERE id = ?");
            
            foreach ($ordem as $posicao => $categoriaId) {
                $stmt->execute([$posicao, $categoriaId]);
            }
            
            logActivity('Ordem das categorias atualizada', 'categorias');
            echo json_encode(['success' => true]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Buscar categorias
$categorias = [];
$estatisticas = [];

try {
    // Buscar todas as categorias ativas
    $stmt = $db->query("
        SELECT 
            c.*,
            COUNT(DISTINCT p.id) as total_produtos,
            COALESCE(SUM(p.estoque_atual), 0) as estoque_total
        FROM categorias c
        LEFT JOIN produtos p ON c.id = p.categoria_id AND p.ativo = 1
        WHERE c.ativo = 1
        GROUP BY c.id
        ORDER BY c.ordem ASC, c.nome ASC
    ");
    $categorias = $stmt->fetchAll();
    
    // Estatísticas gerais
    $stmt = $db->query("SELECT COUNT(*) as total FROM categorias WHERE ativo = 1");
    $totalCategorias = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1");
    $totalProdutos = $stmt->fetch()['total'];
    
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
    <div class="flex gap-3">
        <a href="produtos.php" class="bg-gray-600 text-white px-4 py-3 rounded-lg hover:bg-gray-700 transition font-semibold">
            <i class="fas fa-arrow-left"></i> Voltar para Produtos
        </a>
        <button onclick="abrirModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nova Categoria
        </button>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="grid grid-cols-4 gap-4 mb-6" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <!-- Total de Categorias -->
    <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-tags text-4xl"></i>
            </div>
            <p class="text-yellow-100 text-base font-bold mb-3">Total de Categorias</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalCategorias; ?></p>
            <p class="text-yellow-100 text-base font-semibold">Ativas</p>
        </div>
    </div>
    
    <!-- Produtos Cadastrados -->
    <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-box text-4xl"></i>
            </div>
            <p class="text-blue-100 text-base font-bold mb-3">Produtos Cadastrados</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalProdutos; ?></p>
            <p class="text-blue-100 text-base font-semibold">No Total</p>
        </div>
    </div>
    
    <!-- Média por Categoria -->
    <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-chart-line text-4xl"></i>
            </div>
            <p class="text-green-100 text-base font-bold mb-3">Média por Categoria</p>
            <p class="text-4xl font-bold mb-2">
                <?php echo $totalCategorias > 0 ? number_format($totalProdutos / $totalCategorias, 1) : 0; ?>
            </p>
            <p class="text-green-100 text-base font-semibold">Produtos</p>
        </div>
    </div>
    
    <!-- Categoria Maior -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-crown text-4xl"></i>
            </div>
            <p class="text-purple-100 text-base font-bold mb-3">Maior Categoria</p>
            <p class="text-4xl font-bold mb-2">
                <?php 
                if (!empty($categorias)) {
                    $maiorCategoria = array_reduce($categorias, function($max, $cat) {
                        return ($cat['total_produtos'] > $max['total_produtos']) ? $cat : $max;
                    }, $categorias[0]);
                    echo $maiorCategoria['total_produtos'];
                } else {
                    echo '0';
                }
                ?>
            </p>
            <p class="text-purple-100 text-base font-semibold">Produtos</p>
        </div>
    </div>
</div>

<!-- Info sobre reorganização -->
<div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded">
    <p class="font-medium">
        <i class="fas fa-info-circle"></i> Dica:
        <span class="font-normal">Você pode arrastar e soltar as categorias para reorganizá-las.</span>
    </p>
</div>

<!-- Lista de Categorias -->
<?php if (empty($categorias)): ?>
    <div class="white-card text-center py-12">
        <i class="fas fa-tags text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">Nenhuma categoria encontrada</p>
        <p class="text-gray-500 text-sm mt-2">Crie sua primeira categoria clicando no botão "Nova Categoria"</p>
    </div>
<?php else: ?>
    <div class="white-card" id="categoriasContainer">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase w-12">
                            <i class="fas fa-grip-vertical"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Categoria</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Descrição</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Produtos</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Estoque</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody id="sortableCategorias" class="divide-y divide-gray-200">
                    <?php foreach ($categorias as $categoria): ?>
                        <tr class="hover:bg-gray-50 transition cursor-move categoria-row" data-id="<?php echo $categoria['id']; ?>">
                            <td class="px-4 py-4 text-gray-400">
                                <i class="fas fa-grip-vertical"></i>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-2xl" 
                                         style="background-color: <?php echo htmlspecialchars($categoria['cor']); ?>20;">
                                        <?php echo $categoria['icone']; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($categoria['nome']); ?></p>
                                        <p class="text-xs text-gray-500">Ordem: <?php echo $categoria['ordem']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm text-gray-600">
                                    <?php echo !empty($categoria['descricao']) ? htmlspecialchars($categoria['descricao']) : '<em class="text-gray-400">Sem descrição</em>'; ?>
                                </p>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                    <i class="fas fa-box mr-1"></i>
                                    <?php echo $categoria['total_produtos']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="text-gray-900 font-semibold"><?php echo $categoria['estoque_total']; ?> un.</span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    <button 
                                        onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($categoria)); ?>)"
                                        class="text-blue-600 hover:text-blue-700 p-2 hover:bg-blue-50 rounded-lg transition"
                                        title="Editar"
                                    >
                                        <i class="fas fa-edit text-lg"></i>
                                    </button>
                                    
                                    <a 
                                        href="produtos.php?categoria=<?php echo $categoria['id']; ?>"
                                        class="text-green-600 hover:text-green-700 p-2 hover:bg-green-50 rounded-lg transition"
                                        title="Ver Produtos"
                                    >
                                        <i class="fas fa-eye text-lg"></i>
                                    </a>
                                    
                                    <form method="POST" action="" onsubmit="return confirmarExclusao('Tem certeza que deseja excluir esta categoria?')" class="inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="categoria_id" value="<?php echo $categoria['id']; ?>">
                                        <button 
                                            type="submit" 
                                            class="text-red-600 hover:text-red-700 p-2 hover:bg-red-50 rounded-lg transition"
                                            title="Excluir"
                                        >
                                            <i class="fas fa-trash text-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal de Criar/Editar Categoria -->
<div id="categoriaModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[75vh] overflow-hidden flex flex-col">
        <div class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Nova Categoria</h3>
            <button type="button" onclick="fecharModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="overflow-y-auto flex-1">
            <form method="POST" action="" id="categoriaForm" class="p-5">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="categoria_id" id="categoriaId" value="">
            
            <div class="space-y-4">
                <!-- Nome -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nome da Categoria *</label>
                    <input 
                        type="text" 
                        name="nome" 
                        id="categoriaNome"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Ex: Cadernos, Agendas, Canetas..."
                    >
                </div>
                
                <!-- Descrição -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Descrição</label>
                    <textarea 
                        name="descricao" 
                        id="categoriaDescricao"
                        rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Descrição opcional da categoria..."
                    ></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <!-- Ícone -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Ícone (Emoji)</label>
                        <input 
                            type="text" 
                            name="icone" 
                            id="categoriaIcone"
                            maxlength="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-center text-2xl"
                            placeholder="📦"
                        >
                        <p class="text-xs text-gray-600 mt-1 text-center">Use um emoji</p>
                    </div>
                    
                    <!-- Cor -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Cor</label>
                        <input 
                            type="color" 
                            name="cor" 
                            id="categoriaCor"
                            class="w-full h-10 border border-gray-300 rounded-lg cursor-pointer"
                        >
                    </div>
                    
                    <!-- Ordem -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Ordem</label>
                        <input 
                            type="number" 
                            name="ordem" 
                            id="categoriaOrdem"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="0"
                        >
                    </div>
                </div>
                
                <!-- Preview -->
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-700 mb-2">Preview:</p>
                    <div class="flex items-center gap-2">
                        <div id="previewIcone" class="w-12 h-12 rounded-lg flex items-center justify-center text-2xl" style="background-color: #8B5CF620;">
                            📦
                        </div>
                        <div>
                            <p id="previewNome" class="font-bold text-gray-900 text-sm">Nome da Categoria</p>
                            <p id="previewDescricao" class="text-xs text-gray-600">Descrição da categoria</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-2 mt-4 pt-4 border-t">
                <button 
                    type="button"
                    onclick="fecharModal()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition"
                >
                    Cancelar
                </button>
                <button 
                    type="submit"
                    class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:shadow-lg transition"
                >
                    <i class="fas fa-save"></i> <span id="btnSubmitText">Criar Categoria</span>
                </button>
            </div>
        </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
// Sortable para reordenar categorias
const sortable = new Sortable(document.getElementById('sortableCategorias'), {
    animation: 150,
    handle: '.categoria-row',
    ghostClass: 'bg-purple-50',
    onEnd: function(evt) {
        const ordem = [];
        document.querySelectorAll('.categoria-row').forEach((row, index) => {
            ordem.push(row.dataset.id);
        });
        
        // Salvar nova ordem
        fetch('categorias.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=reorder&ordem=' + encodeURIComponent(JSON.stringify(ordem))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Ordem atualizada com sucesso!', 'success');
            }
        });
    }
});

// Modal
function abrirModal() {
    const modal = document.getElementById('categoriaModal');
    modal.style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Nova Categoria';
    document.getElementById('formAction').value = 'create';
    document.getElementById('btnSubmitText').textContent = 'Criar Categoria';
    document.getElementById('categoriaForm').reset();
    document.getElementById('categoriaId').value = '';
    document.getElementById('categoriaIcone').value = '📦';
    document.getElementById('categoriaCor').value = '#8B5CF6';
    atualizarPreview();
    document.body.style.overflow = 'hidden'; // Prevenir scroll do body
}

function fecharModal() {
    const modal = document.getElementById('categoriaModal');
    modal.style.display = 'none';
    document.body.style.overflow = ''; // Restaurar scroll do body
}

function editarCategoria(categoria) {
    const modal = document.getElementById('categoriaModal');
    modal.style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Editar Categoria';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('btnSubmitText').textContent = 'Salvar Alterações';
    
    document.getElementById('categoriaId').value = categoria.id;
    document.getElementById('categoriaNome').value = categoria.nome;
    document.getElementById('categoriaDescricao').value = categoria.descricao || '';
    document.getElementById('categoriaIcone').value = categoria.icone;
    document.getElementById('categoriaCor').value = categoria.cor;
    document.getElementById('categoriaOrdem').value = categoria.ordem;
    
    document.body.style.overflow = 'hidden'; // Prevenir scroll do body
    atualizarPreview();
}

// Preview em tempo real
function atualizarPreview() {
    const nome = document.getElementById('categoriaNome').value || 'Nome da Categoria';
    const descricao = document.getElementById('categoriaDescricao').value || 'Descrição da categoria';
    const icone = document.getElementById('categoriaIcone').value || '📦';
    const cor = document.getElementById('categoriaCor').value || '#8B5CF6';
    
    document.getElementById('previewNome').textContent = nome;
    document.getElementById('previewDescricao').textContent = descricao;
    document.getElementById('previewIcone').textContent = icone;
    document.getElementById('previewIcone').style.backgroundColor = cor + '20';
}

// Event listeners para preview
document.getElementById('categoriaNome')?.addEventListener('input', atualizarPreview);
document.getElementById('categoriaDescricao')?.addEventListener('input', atualizarPreview);
document.getElementById('categoriaIcone')?.addEventListener('input', atualizarPreview);
document.getElementById('categoriaCor')?.addEventListener('input', atualizarPreview);

// Fechar modal ao clicar fora
document.getElementById('categoriaModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});

// Fechar modal com tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('categoriaModal');
        if (modal && modal.style.display === 'flex') {
            fecharModal();
        }
    }
});

// Toast notification
function showToast(message, type = 'success') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Sugestões de emojis comuns
const emojisComuns = ['📦', '📓', '📅', '📋', '🎁', '🖊️', '📝', '✉️', '✨', '🎨', '📚', '📌', '🏷️', '💼', '📁'];

// Mostrar sugestões de emoji ao clicar no campo
document.getElementById('categoriaIcone')?.addEventListener('focus', function() {
    // Você pode adicionar um seletor de emoji aqui se desejar
});
</script>

<style>
.categoria-row {
    transition: background-color 0.2s;
}

.categoria-row:hover {
    background-color: #f9fafb;
}

.sortable-ghost {
    opacity: 0.4;
    background-color: #f3f4f6;
}

/* Modal styles */
#categoriaModal {
    backdrop-filter: blur(4px);
}

#categoriaModal > div {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Garantir que o modal não seja afetado pelo Tailwind */
#categoriaModal[style*="display: flex"] {
    display: flex !important;
}

/* Scroll suave no conteúdo do modal */
#categoriaModal .overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: #a855f7 #f3f4f6;
}

#categoriaModal .overflow-y-auto::-webkit-scrollbar {
    width: 8px;
}

#categoriaModal .overflow-y-auto::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 10px;
}

#categoriaModal .overflow-y-auto::-webkit-scrollbar-thumb {
    background: #a855f7;
    border-radius: 10px;
}

#categoriaModal .overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9333ea;
}
</style>

<?php require_once 'footer.php'; ?>