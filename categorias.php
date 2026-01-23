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

$empresaId = getEmpresaId();
$db = getDB();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Criar categoria
    if (isset($_POST['action']) && $_POST['action'] === 'criar') {
        try {
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao'] ?? '');
            $icone = sanitize($_POST['icone'] ?? '📦');
            $cor = sanitize($_POST['cor'] ?? '#8B5CF6');
            
            if (empty($nome)) {
                throw new Exception('Nome da categoria é obrigatório');
            }
            
            // Verificar duplicidade
            $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = ? AND empresa_id = ?");
            $stmt->execute([$nome, $empresaId]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma categoria com este nome');
            }
            
            $stmt = $db->prepare("INSERT INTO categorias (nome, descricao, icone, cor, empresa_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $descricao, $icone, $cor, $empresaId]);
            
            $categoriaId = $db->lastInsertId();
            logActivity('Categoria criada: ' . $nome, 'categorias', $categoriaId);
            
            $_SESSION['success'] = "Categoria '$nome' criada com sucesso!";
            header('Location: categorias.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao criar categoria: ' . $e->getMessage();
        }
    }
    
    // Editar categoria
    if (isset($_POST['action']) && $_POST['action'] === 'editar') {
        try {
            $id = (int)$_POST['id'];
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao'] ?? '');
            $icone = sanitize($_POST['icone'] ?? '📦');
            $cor = sanitize($_POST['cor'] ?? '#8B5CF6');
            
            if (empty($nome)) {
                throw new Exception('Nome da categoria é obrigatório');
            }
            
            // Verificar duplicidade (exceto a própria categoria)
            $stmt = $db->prepare("SELECT id FROM categorias WHERE nome = ? AND empresa_id = ? AND id != ?");
            $stmt->execute([$nome, $empresaId, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma categoria com este nome');
            }
            
            $stmt = $db->prepare("UPDATE categorias SET nome = ?, descricao = ?, icone = ?, cor = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$nome, $descricao, $icone, $cor, $id, $empresaId]);
            
            logActivity('Categoria editada: ' . $nome, 'categorias', $id);
            
            $_SESSION['success'] = "Categoria '$nome' atualizada com sucesso!";
            header('Location: categorias.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao editar categoria: ' . $e->getMessage();
        }
    }
    
    // Excluir categoria
    if (isset($_POST['action']) && $_POST['action'] === 'excluir') {
        try {
            $id = (int)$_POST['id'];
            
            // Verificar se há produtos nesta categoria
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE categoria_id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresaId]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                throw new Exception("Não é possível excluir. Existem $count produto(s) nesta categoria.");
            }
            
            $stmt = $db->prepare("DELETE FROM categorias WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresaId]);
            
            logActivity('Categoria excluída', 'categorias', $id);
            
            $_SESSION['success'] = 'Categoria excluída com sucesso!';
            header('Location: categorias.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao excluir categoria: ' . $e->getMessage();
        }
    }
}

// Buscar categorias
try {
    $stmt = $db->prepare("
        SELECT 
            c.*,
            COUNT(p.id) as total_produtos,
            COALESCE(SUM(p.estoque_atual), 0) as estoque_total,
            COALESCE(SUM(p.preco_venda * p.estoque_atual), 0) as valor_estoque
        FROM categorias c
        LEFT JOIN produtos p ON c.id = p.categoria_id AND p.ativo = 1
        WHERE c.empresa_id = ?
        GROUP BY c.id
        ORDER BY c.nome ASC
    ");
    $stmt->execute([$empresaId]);
    $categorias = $stmt->fetchAll();
    
    // Estatísticas gerais
    $totalCategorias = count($categorias);
    $totalProdutos = array_sum(array_column($categorias, 'total_produtos'));
    $totalEstoque = array_sum(array_column($categorias, 'estoque_total'));
    $valorEstoque = array_sum(array_column($categorias, 'valor_estoque'));
    
} catch (PDOException $e) {
    $categorias = [];
    $totalCategorias = $totalProdutos = $totalEstoque = $valorEstoque = 0;
}

require_once 'header.php';

// Mensagens
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
    unset($_SESSION['error']);
}
?>

<style>
/* Responsivo Mobile - Categorias */
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
    
    /* Cards de categoria */
    .categorias-grid {
        grid-template-columns: 1fr !important;
    }
    
    .categoria-card {
        padding: 1rem !important;
    }
    
    .categoria-card .categoria-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem !important;
    }
    
    .categoria-card .categoria-icon {
        width: 56px !important;
        height: 56px !important;
        font-size: 1.75rem !important;
    }
    
    .categoria-card .categoria-stats {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem !important;
    }
    
    .categoria-card .categoria-actions {
        flex-direction: column !important;
        width: 100% !important;
        gap: 0.5rem !important;
    }
    
    .categoria-card .categoria-actions button {
        width: 100% !important;
    }
    
    /* Modal responsivo */
    .modal-content {
        max-height: 90vh !important;
        overflow-y: auto !important;
    }
    
    /* Seletor de ícone */
    .icon-grid {
        grid-template-columns: repeat(6, 1fr) !important;
    }
    
    .icon-option {
        width: 40px !important;
        height: 40px !important;
        font-size: 1.25rem !important;
    }
    
    /* Seletor de cor */
    .color-grid {
        grid-template-columns: repeat(5, 1fr) !important;
    }
}

@media (max-width: 480px) {
    .stat-card .stat-value {
        font-size: 1.25rem !important;
    }
    
    .icon-grid {
        grid-template-columns: repeat(5, 1fr) !important;
    }
    
    .color-grid {
        grid-template-columns: repeat(4, 1fr) !important;
    }
}
</style>

<!-- Cabeçalho -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-4 mb-4 md:mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-4">
        
        <a href="produtos.php" class="hidden md:inline-flex items-center px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">

            <i class="fas fa-arrow-left mr-2"></i> Voltar
        </a>

        <button onclick="abrirModal()" class="w-full md:w-auto btn btn-primary text-sm md:text-base">

            <i class="fas fa-plus"></i> Nova Categoria
        </button>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 md:mb-8">
    <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-th-large text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-yellow-100 text-sm md:text-base font-bold mb-3">Total Categorias</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalCategorias; ?></p>
            <p class="stat-subtitle text-yellow-100 text-xs md:text-base font-semibold">Cadastradas</p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-box text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-blue-100 text-sm md:text-base font-bold mb-3">Total Produtos</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalProdutos; ?></p>
            <p class="stat-subtitle text-blue-100 text-xs md:text-base font-semibold">Cadastrados</p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-warehouse text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-green-100 text-sm md:text-base font-bold mb-3">Estoque Total</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo number_format($totalEstoque, 0, ',', '.'); ?></p>
            <p class="stat-subtitle text-green-100 text-xs md:text-base font-semibold">Unidades</p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-dollar-sign text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-purple-100 text-sm md:text-base font-bold mb-3">Valor Estoque</p>
            <p class="stat-value text-2xl md:text-3xl font-bold mb-2"><?php echo formatarMoeda($valorEstoque); ?></p>
            <p class="stat-subtitle text-purple-100 text-xs md:text-base font-semibold">Em produtos</p>
        </div>
    </div>
</div>

<!-- Lista de Categorias -->
<?php if (empty($categorias)): ?>
    <div class="white-card text-center py-12 md:py-16">
        <i class="fas fa-th-large text-6xl md:text-7xl text-gray-300 mb-4"></i>
        <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Nenhuma categoria cadastrada</h3>
        <p class="text-gray-600 mb-6 text-sm md:text-base">Comece criando sua primeira categoria de produtos</p>
        <button onclick="abrirModal()" class="btn btn-primary text-sm md:text-base">
            <i class="fas fa-plus"></i> Criar Primeira Categoria
        </button>
    </div>
<?php else: ?>
    <div class="stats-grid grid grid-cols-3 md:grid-cols-3 gap-4 mb-6 md:mb-8">
        <?php foreach ($categorias as $categoria): ?>
            <div class="categoria-card white-card hover:shadow-xl transition">
                <div class="categoria-header flex items-center justify-between mb-4 md:mb-6">
                    <div class="flex items-center gap-3 md:gap-4 flex-1 min-w-0">
                        <div class="categoria-icon w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-3xl md:text-4xl shadow-lg flex-shrink-0" 
                             style="background: <?php echo htmlspecialchars($categoria['cor']); ?>20; color: <?php echo htmlspecialchars($categoria['cor']); ?>;">
                            <?php echo htmlspecialchars($categoria['icone']); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-1 truncate"><?php echo htmlspecialchars($categoria['nome']); ?></h3>
                            <?php if (!empty($categoria['descricao'])): ?>
                                <p class="text-xs md:text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="categoria-stats grid grid-cols-3 gap-3 md:gap-4 mb-4 md:mb-6">
                    <div class="bg-gray-50 rounded-lg p-2 md:p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">Produtos</p>
                        <p class="text-lg md:text-xl font-bold" style="color: <?php echo htmlspecialchars($categoria['cor']); ?>;">
                            <?php echo $categoria['total_produtos']; ?>
                        </p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2 md:p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">Estoque</p>
                        <p class="text-lg md:text-xl font-bold text-gray-900">
                            <?php echo number_format($categoria['estoque_total'], 0, ',', '.'); ?>
                        </p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2 md:p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">Valor</p>
                        <p class="text-sm md:text-base font-bold text-gray-900">
                            <?php echo formatarMoeda($categoria['valor_estoque']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="categoria-actions flex gap-2 pt-4 border-t">
                    <button onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($categoria)); ?>)" 
                            class="flex-1 bg-blue-500 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-blue-600 transition font-semibold text-xs md:text-sm">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button onclick="confirmarExclusao(<?php echo $categoria['id']; ?>, '<?php echo addslashes($categoria['nome']); ?>', <?php echo $categoria['total_produtos']; ?>)" 
                            class="flex-1 bg-red-500 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-red-600 transition font-semibold text-xs md:text-sm">
                        <i class="fas fa-trash"></i> Excluir
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: Criar/Editar Categoria -->
<div id="modalCategoria" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 z-50 overflow-y-auto">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full my-8 max-h-[90vh] flex flex-col">
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-4 md:p-6 rounded-t-2xl flex items-center justify-between flex-shrink-0">
            <h3 class="text-xl md:text-2xl font-bold" id="modalTitulo">
                <i class="fas fa-th-large"></i> Nova Categoria
            </h3>
            <button onclick="fecharModal()" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-xl md:text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" action="" id="formCategoria" class="overflow-y-auto flex-1">
            <div class="p-4 md:p-6">
            <input type="hidden" name="action" id="formAction" value="criar">
            <input type="hidden" name="id" id="categoriaId">
            
            <div class="space-y-4 md:space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome da Categoria *</label>
                    <input type="text" name="nome" id="categoriaNome" required
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Ex: Cadernos, Canetas, Papelaria">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                    <textarea name="descricao" id="categoriaDescricao" rows="3"
                              class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                              placeholder="Descrição opcional da categoria"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Ícone da Categoria</label>
                    <input type="hidden" name="icone" id="categoriaIcone" value="📦">
                    
                    <!-- Grid de ícones principais -->
                    <div id="iconGridPrincipal" class="icon-grid grid grid-cols-8 gap-2">
                        <?php 
                        $icones = ['📦', '📝', '✏️', '📚', '🎨', '✂️', '📎', '📌', '🖊️', '🖍️', '📐', '📏', '🗂️', '📋', '📄', '🏷️'];
                        foreach ($icones as $icone): 
                        ?>
                            <div class="icon-option w-10 h-10 md:w-12 md:h-12 bg-gray-100 hover:bg-purple-100 rounded-lg flex items-center justify-center cursor-pointer transition text-xl md:text-2xl border-2 border-transparent"
                                 onclick="selecionarIcone('<?php echo $icone; ?>', this)">
                                <?php echo $icone; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Grid expandido (oculto inicialmente) -->
                    <div id="iconGridExpandido" class="icon-grid grid grid-cols-8 gap-2 mt-2 hidden" style="display: none;">
                        <?php 
                        $iconesExtras = ['🥃','💼', '🎁', '🔖', '📮', '✉️', '📧', '🎒', '👜', '🖼️', '🎯', '💡', '🔧', '🔨', '⚙️', '🎪', '🎭', '🎬', '🎤', '🎧', '🎼', '🎹', '🎸', '🎺', '🎷', '📱', '💻', '⌨️', '🖱️', '🖨️', '📷', '📹', '📞', '☎️', '📟', '📠', '📡', '🔋', '🔌', '💾', '💿', '📀', '🎮', '🕹️', '🎲', '🧩', '🎰', '🏆', '🥇', '🥈', '🥉', '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱'];
                        foreach ($iconesExtras as $icone): 
                        ?>
                            <div class="icon-option w-10 h-10 md:w-12 md:h-12 bg-gray-100 hover:bg-purple-100 rounded-lg flex items-center justify-center cursor-pointer transition text-xl md:text-2xl border-2 border-transparent"
                                 onclick="selecionarIcone('<?php echo $icone; ?>', this)">
                                <?php echo $icone; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Botão Ver Mais -->
                    <button type="button" id="btnVerMaisIcones" 
                            onclick="toggleIconesExpandidos()" 
                            class="w-full mt-2 px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition font-semibold text-sm">
                        <i class="fas fa-chevron-down"></i> Ver Mais Ícones
                    </button>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Cor da Categoria</label>
                    <input type="hidden" name="cor" id="categoriaCor" value="#8B5CF6">
                    <div class="color-grid grid grid-cols-6 gap-2 md:gap-3">
                        <?php 
                        $cores = [
                            '#8B5CF6' => 'Roxo',
                            '#3B82F6' => 'Azul',
                            '#10B981' => 'Verde',
                            '#F59E0B' => 'Laranja',
                            '#EF4444' => 'Vermelho',
                            '#EC4899' => 'Rosa',
                            '#14B8A6' => 'Teal',
                            '#F97316' => 'Laranja Escuro',
                            '#6366F1' => 'Índigo',
                            '#84CC16' => 'Lima',
                            '#F43F5E' => 'Rose',
                            '#06B6D4' => 'Cyan'
                        ];
                        foreach ($cores as $cor => $nome): 
                        ?>
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-lg cursor-pointer transition border-4 border-transparent hover:scale-110"
                                 style="background-color: <?php echo $cor; ?>;"
                                 onclick="selecionarCor('<?php echo $cor; ?>', this)"
                                 title="<?php echo $nome; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-3 md:p-4">
                    <p class="text-xs md:text-sm text-gray-700 font-semibold mb-2">Prévia da Categoria:</p>
                    <div class="flex items-center gap-3">
                        <div id="previewIcone" class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl shadow-lg" 
                             style="background: #8B5CF620; color: #8B5CF6;">
                            📦
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 text-sm md:text-base" id="previewNome">Nome da Categoria</p>
                            <p class="text-xs md:text-sm text-gray-600" id="previewDesc">Descrição da categoria</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row gap-3 mt-6 pt-6 border-t">
                <button type="button" onclick="fecharModal()" 
                        class="flex-1 px-4 md:px-6 py-2 md:py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition text-sm md:text-base">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 btn btn-primary text-sm md:text-base" id="btnSubmit">
                    <i class="fas fa-save"></i> Salvar Categoria
                </button>
            </div>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- Modal: Confirmar Exclusão -->
<div id="modalExcluir" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-red-600 to-pink-600 text-white p-4 md:p-6 rounded-t-2xl">
            <h3 class="text-xl md:text-2xl font-bold">
                <i class="fas fa-exclamation-triangle"></i> Confirmar Exclusão
            </h3>
        </div>
        
        <div class="p-4 md:p-6">
            <p class="text-gray-700 mb-4 text-sm md:text-base" id="mensagemExclusao"></p>
            
            <form method="POST" action="" id="formExcluir">
                <input type="hidden" name="action" value="excluir">
                <input type="hidden" name="id" id="excluirId">
                
                <div class="flex flex-col md:flex-row gap-3">
                    <button type="button" onclick="fecharModalExcluir()" 
                            class="flex-1 px-4 md:px-6 py-2 md:py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition text-sm md:text-base">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 bg-red-600 text-white px-4 md:px-6 py-2 md:py-3 rounded-lg font-semibold hover:bg-red-700 transition text-sm md:text-base">
                        <i class="fas fa-trash"></i> Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let corSelecionada = '#8B5CF6';
let iconeSelecionado = '📦';

function abrirModal() {
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-th-large"></i> Nova Categoria';
    document.getElementById('formAction').value = 'criar';
    document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Salvar Categoria';
    document.getElementById('formCategoria').reset();
    document.getElementById('categoriaId').value = '';
    
    // Reset seleções
    corSelecionada = '#8B5CF6';
    iconeSelecionado = '📦';
    document.getElementById('categoriaIcone').value = iconeSelecionado;
    document.getElementById('categoriaCor').value = corSelecionada;
    
    // Reset bordas
    document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('border-purple-600'));
    document.querySelectorAll('.color-grid > div').forEach(el => el.classList.remove('border-purple-600', 'scale-110'));
    
    atualizarPreview();
    
    document.getElementById('modalCategoria').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Garantir que os ícones expandidos estejam ocultos ao abrir
    const gridExpandido = document.getElementById('iconGridExpandido');
    if (gridExpandido) {
        gridExpandido.style.display = 'none';
        gridExpandido.classList.add('hidden');
    }
    document.getElementById('btnVerMaisIcones').innerHTML = '<i class="fas fa-chevron-down"></i> Ver Mais Ícones';
}

function editarCategoria(categoria) {
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit"></i> Editar Categoria';
    document.getElementById('formAction').value = 'editar';
    document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Atualizar Categoria';
    
    document.getElementById('categoriaId').value = categoria.id;
    document.getElementById('categoriaNome').value = categoria.nome;
    document.getElementById('categoriaDescricao').value = categoria.descricao || '';
    
    corSelecionada = categoria.cor;
    iconeSelecionado = categoria.icone;
    document.getElementById('categoriaIcone').value = iconeSelecionado;
    document.getElementById('categoriaCor').value = corSelecionada;
    
    // Destacar ícone e cor selecionados
    document.querySelectorAll('.icon-option').forEach(el => {
        if (el.textContent.trim() === iconeSelecionado) {
            el.classList.add('border-purple-600');
        }
    });
    
    document.querySelectorAll('.color-grid > div').forEach(el => {
        if (el.style.backgroundColor === corSelecionada || rgbToHex(el.style.backgroundColor) === corSelecionada) {
            el.classList.add('border-purple-600', 'scale-110');
        }
    });
    
    atualizarPreview();
    
    document.getElementById('modalCategoria').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Garantir que os ícones expandidos estejam ocultos ao abrir
    const gridExpandido = document.getElementById('iconGridExpandido');
    if (gridExpandido) {
        gridExpandido.style.display = 'none';
        gridExpandido.classList.add('hidden');
    }
    document.getElementById('btnVerMaisIcones').innerHTML = '<i class="fas fa-chevron-down"></i> Ver Mais Ícones';
}

function fecharModal() {
    document.getElementById('modalCategoria').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function selecionarIcone(icone, elemento) {
    iconeSelecionado = icone;
    document.getElementById('categoriaIcone').value = icone;
    
    // Remove seleção anterior
    document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('border-purple-600'));
    // Adiciona seleção atual
    elemento.classList.add('border-purple-600');
    
    atualizarPreview();
}

function toggleIconesExpandidos() {
    const gridExpandido = document.getElementById('iconGridExpandido');
    const btnVerMais = document.getElementById('btnVerMaisIcones');
    
    if (gridExpandido.style.display === 'none' || !gridExpandido.style.display) {
        gridExpandido.style.display = 'grid';
        gridExpandido.classList.remove('hidden');
        btnVerMais.innerHTML = '<i class="fas fa-chevron-up"></i> Ver Menos Ícones';
    } else {
        gridExpandido.style.display = 'none';
        gridExpandido.classList.add('hidden');
        btnVerMais.innerHTML = '<i class="fas fa-chevron-down"></i> Ver Mais Ícones';
    }
}

function selecionarCor(cor, elemento) {
    corSelecionada = cor;
    document.getElementById('categoriaCor').value = cor;
    
    // Remove seleção anterior
    document.querySelectorAll('.color-grid > div').forEach(el => {
        el.classList.remove('border-purple-600', 'scale-110');
    });
    // Adiciona seleção atual
    elemento.classList.add('border-purple-600', 'scale-110');
    
    atualizarPreview();
}

function atualizarPreview() {
    const nome = document.getElementById('categoriaNome').value || 'Nome da Categoria';
    const descricao = document.getElementById('categoriaDescricao').value || 'Descrição da categoria';
    
    const preview = document.getElementById('previewIcone');
    preview.textContent = iconeSelecionado;
    preview.style.background = corSelecionada + '20';
    preview.style.color = corSelecionada;
    
    document.getElementById('previewNome').textContent = nome;
    document.getElementById('previewDesc').textContent = descricao;
}

function confirmarExclusao(id, nome, totalProdutos) {
    if (totalProdutos > 0) {
        document.getElementById('mensagemExclusao').innerHTML = 
            `<strong>Não é possível excluir a categoria "${nome}".</strong><br><br>` +
            `Esta categoria possui <strong>${totalProdutos} produto(s)</strong> cadastrado(s).<br><br>` +
            `Para excluir esta categoria, primeiro remova ou mova todos os produtos para outra categoria.`;
        document.getElementById('formExcluir').style.display = 'none';
    } else {
        document.getElementById('mensagemExclusao').innerHTML = 
            `Tem certeza que deseja excluir a categoria <strong>"${nome}"</strong>?<br><br>` +
            `Esta ação não pode ser desfeita.`;
        document.getElementById('formExcluir').style.display = 'block';
        document.getElementById('excluirId').value = id;
    }
    
    document.getElementById('modalExcluir').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function fecharModalExcluir() {
    document.getElementById('modalExcluir').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Atualizar preview ao digitar
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('categoriaNome')?.addEventListener('input', atualizarPreview);
    document.getElementById('categoriaDescricao')?.addEventListener('input', atualizarPreview);
});

// Fechar modais com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModal();
        fecharModalExcluir();
    }
});

// Fechar clicando fora
document.getElementById('modalCategoria')?.addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});

document.getElementById('modalExcluir')?.addEventListener('click', function(e) {
    if (e.target === this) fecharModalExcluir();
});

// Converter RGB para HEX
function rgbToHex(rgb) {
    const result = rgb.match(/\d+/g);
    if (!result) return rgb;
    return '#' + result.map(x => {
        const hex = parseInt(x).toString(16);
        return hex.length === 1 ? '0' + hex : hex;
    }).join('');
}

// Garantir que os ícones expandidos estejam ocultos ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    const gridExpandido = document.getElementById('iconGridExpandido');
    const btnVerMais = document.getElementById('btnVerMaisIcones');
    
    if (gridExpandido) {
        gridExpandido.style.display = 'none';
        gridExpandido.classList.add('hidden');
    }
    if (btnVerMais) {
        btnVerMais.innerHTML = '<i class="fas fa-chevron-down"></i> Ver Mais Ícones';
    }
});
</script>

<?php require_once 'footer.php'; ?>