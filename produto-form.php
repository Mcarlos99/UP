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

$empresaId = getEmpresaId();
$db = getDB();

// Modo: criar ou editar
$modo = 'criar';
$produto = null;

if (isset($_GET['id'])) {
    $modo = 'editar';
    $produtoId = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM produtos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$produtoId, $empresaId]);
        $produto = $stmt->fetch();
        
        if (!$produto) {
            $_SESSION['error'] = 'Produto não encontrado';
            header('Location: produtos.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar produto';
        header('Location: produtos.php');
        exit;
    }
}

$pageTitle = $modo === 'criar' ? 'Novo Produto' : 'Editar Produto';
$pageSubtitle = $modo === 'criar' ? 'Cadastre um novo produto' : 'Atualize as informações do produto';

// Buscar categorias
try {
    $stmt = $db->prepare("SELECT * FROM categorias WHERE empresa_id = ? ORDER BY nome ASC");
    $stmt->execute([$empresaId]);
    $categorias = $stmt->fetchAll();
} catch (PDOException $e) {
    $categorias = [];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = sanitize($_POST['nome']);
        $descricao = sanitize($_POST['descricao'] ?? '');
        $categoria_id = (int)$_POST['categoria_id'];
        $preco_custo = floatval(str_replace(',', '.', str_replace('.', '', $_POST['preco_custo'])));
        $preco_venda = floatval(str_replace(',', '.', str_replace('.', '', $_POST['preco_venda'])));
        $estoque_atual = (int)$_POST['estoque_atual'];
        $estoque_minimo = (int)$_POST['estoque_minimo'];
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        // Validações
        if (empty($nome)) {
            throw new Exception('Nome é obrigatório');
        }
        
        if ($categoria_id <= 0) {
            throw new Exception('Selecione uma categoria');
        }
        
        if ($preco_venda <= 0) {
            throw new Exception('Preço de venda deve ser maior que zero');
        }
        
        // Upload de imagem
        $imagem = $produto['imagem'] ?? null;
        
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/produtos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($extensao, $extensoesPermitidas)) {
                throw new Exception('Formato de imagem não permitido');
            }
            
            $nomeArquivo = uniqid() . '.' . $extensao;
            $caminhoCompleto = $uploadDir . $nomeArquivo;
            
            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoCompleto)) {
                // Remover imagem antiga
                if ($imagem && file_exists($imagem)) {
                    unlink($imagem);
                }
                $imagem = $caminhoCompleto;
            }
        }
        
        if ($modo === 'criar') {
            $stmt = $db->prepare("
                INSERT INTO produtos (
                    nome, descricao, categoria_id, preco_custo, preco_venda,
                    estoque_atual, estoque_minimo, imagem, ativo, empresa_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nome, $descricao, $categoria_id, $preco_custo, $preco_venda,
                $estoque_atual, $estoque_minimo, $imagem, $ativo, $empresaId
            ]);
            
            $produtoId = $db->lastInsertId();
            logActivity('Produto criado: ' . $nome, 'produtos', $produtoId);
            
            $_SESSION['success'] = "Produto '$nome' cadastrado com sucesso!";
            
        } else {
            $stmt = $db->prepare("
                UPDATE produtos SET
                    nome = ?, descricao = ?, categoria_id = ?, preco_custo = ?,
                    preco_venda = ?, estoque_atual = ?, estoque_minimo = ?,
                    imagem = ?, ativo = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([
                $nome, $descricao, $categoria_id, $preco_custo, $preco_venda,
                $estoque_atual, $estoque_minimo, $imagem, $ativo,
                $produtoId, $empresaId
            ]);
            
            logActivity('Produto editado: ' . $nome, 'produtos', $produtoId);
            
            $_SESSION['success'] = "Produto '$nome' atualizado com sucesso!";
        }
        
        header('Location: produtos.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao salvar produto: ' . $e->getMessage();
    }
}

require_once 'header.php';

// Mensagens
if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 md:p-4 mb-4 md:mb-6 rounded">';
    echo '<p class="font-medium text-sm md:text-base"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</p>';
    echo '</div>';
    unset($_SESSION['error']);
}
?>

<style>
/* Responsivo Mobile - Formulários */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }
    
    .form-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem !important;
    }
    
    .form-actions {
        flex-direction: column-reverse !important;
        gap: 0.75rem !important;
    }
    
    .form-actions button,
    .form-actions a {
        width: 100% !important;
        text-align: center !important;
    }
    
    .section-title {
        font-size: 1rem !important;
        padding: 0.75rem !important;
    }
    
    .image-preview {
        max-width: 200px !important;
        margin: 0 auto !important;
    }
}
</style>

<!-- Cabeçalho do Formulário -->
<div class="mb-4 md:mb-6">
    <div class="form-header flex items-center justify-between">
<!--        <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
                <?php echo $modo === 'criar' ? 'Novo Produto' : 'Editar Produto'; ?>
            </h2>
            <p class="text-gray-600 mt-1 text-sm md:text-base">
                <?php echo $modo === 'criar' ? 'Preencha os dados para cadastrar um novo produto' : 'Atualize as informações do produto'; ?>
            </p>
        </div>  -->
        <a href="produtos.php" class="hidden md:inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-arrow-left mr-2"></i> Voltar
        </a>
    </div>
</div>

<!-- Formulário -->
<form method="POST" action="" enctype="multipart/form-data" class="space-y-4 md:space-y-6">
    
    <!-- Seção: Informações Básicas -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-purple-600 to-pink-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-box"></i> Informações Básicas
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Produto *</label>
                    <input type="text" name="nome" required
                           value="<?php echo htmlspecialchars($produto['nome'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Nome do produto">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                    <textarea name="descricao" rows="3"
                              class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                              placeholder="Descrição detalhada do produto"><?php echo htmlspecialchars($produto['descricao'] ?? ''); ?></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria *</label>
                    <select name="categoria_id" required
                            class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                        <option value="">Selecione uma categoria</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo (isset($produto['categoria_id']) && $produto['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['icone'] . ' ' . $cat['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($categorias)): ?>
                        <p class="text-xs text-orange-600 mt-1">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <a href="categorias.php" class="underline">Cadastre categorias</a> antes de criar produtos
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Preços -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-green-600 to-emerald-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-dollar-sign"></i> Preços
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Preço de Custo</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 -translate-y-1/2 text-gray-500">R$</span>
                        <input type="text" name="preco_custo"
                               value="<?php echo isset($produto['preco_custo']) ? number_format($produto['preco_custo'], 2, ',', '.') : ''; ?>"
                               class="w-full pl-12 pr-3 md:pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                               placeholder="0,00"
                               data-mask="money">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Preço de Venda *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 -translate-y-1/2 text-gray-500">R$</span>
                        <input type="text" name="preco_venda" required
                               value="<?php echo isset($produto['preco_venda']) ? number_format($produto['preco_venda'], 2, ',', '.') : ''; ?>"
                               class="w-full pl-12 pr-3 md:pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                               placeholder="0,00"
                               data-mask="money">
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <div id="margemLucro" class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded hidden">
                        <p class="text-sm text-blue-800 font-semibold">
                            <i class="fas fa-info-circle"></i> Margem de Lucro: <span id="margemValor">0%</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Estoque -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-blue-600 to-cyan-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-warehouse"></i> Estoque
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Estoque Atual</label>
                    <input type="number" name="estoque_atual" min="0"
                           value="<?php echo $produto['estoque_atual'] ?? 0; ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="0">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Estoque Mínimo</label>
                    <input type="number" name="estoque_minimo" min="0"
                           value="<?php echo $produto['estoque_minimo'] ?? 5; ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="5">
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Alerta quando atingir este valor
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Imagem -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-orange-600 to-red-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-image"></i> Imagem do Produto
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <?php if (isset($produto['imagem']) && $produto['imagem']): ?>
                <div class="mb-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Imagem Atual:</p>
                    <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" 
                         alt="Imagem do produto" 
                         class="image-preview max-w-xs rounded-lg shadow-md">
                </div>
            <?php endif; ?>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <?php echo isset($produto['imagem']) ? 'Nova Imagem (deixe em branco para manter a atual)' : 'Imagem do Produto'; ?>
                </label>
                <input type="file" name="imagem" accept="image/*"
                       class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                <p class="text-xs text-gray-500 mt-1">
                    <i class="fas fa-info-circle"></i> Formatos aceitos: JPG, PNG, GIF, WEBP
                </p>
            </div>
        </div>
    </div>
    
    <!-- Seção: Status -->
    <div class="white-card">
        <div class="px-4 md:px-6 py-4 md:py-6">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="ativo" value="1" 
                       <?php echo (!isset($produto['ativo']) || $produto['ativo']) ? 'checked' : ''; ?>
                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <span class="ml-3 text-sm md:text-base font-semibold text-gray-700">
                    <i class="fas fa-check-circle text-green-600"></i> Produto Ativo
                </span>
            </label>
            <p class="text-xs text-gray-500 mt-2 ml-8">
                Produtos inativos não aparecem na listagem principal
            </p>
        </div>
    </div>
    
    <!-- Botões de Ação -->
    <div class="form-actions flex gap-3 pt-4">
        <a href="produtos.php" class="flex-1 md:flex-none px-4 md:px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-times mr-2"></i> Cancelar
        </a>
        <button type="submit" class="flex-1 md:flex-none btn btn-primary text-sm md:text-base">
            <i class="fas fa-save mr-2"></i>
            <?php echo $modo === 'criar' ? 'Cadastrar Produto' : 'Salvar Alterações'; ?>
        </button>
    </div>
</form>

<script>
// Calcular margem de lucro
function calcularMargem() {
    const custoProduto = document.querySelector('input[name="preco_custo"]');
    const vendaProduto = document.querySelector('input[name="preco_venda"]');
    
    if (!custoProduto || !vendaProduto) return;
    
    const custo = parseFloat(custoProduto.value.replace(/\./g, '').replace(',', '.')) || 0;
    const venda = parseFloat(vendaProduto.value.replace(/\./g, '').replace(',', '.')) || 0;
    
    if (custo > 0 && venda > 0) {
        const margem = ((venda - custo) / custo * 100).toFixed(2);
        document.getElementById('margemValor').textContent = margem + '%';
        document.getElementById('margemLucro').classList.remove('hidden');
        
        // Mudar cor baseado na margem
        const margemDiv = document.getElementById('margemLucro');
        margemDiv.className = 'p-3 rounded border-l-4 ';
        
        if (margem < 10) {
            margemDiv.classList.add('bg-red-50', 'border-red-500');
            document.getElementById('margemValor').parentElement.className = 'text-sm text-red-800 font-semibold';
        } else if (margem < 30) {
            margemDiv.classList.add('bg-yellow-50', 'border-yellow-500');
            document.getElementById('margemValor').parentElement.className = 'text-sm text-yellow-800 font-semibold';
        } else {
            margemDiv.classList.add('bg-green-50', 'border-green-500');
            document.getElementById('margemValor').parentElement.className = 'text-sm text-green-800 font-semibold';
        }
    }
}

document.querySelector('input[name="preco_custo"]')?.addEventListener('input', calcularMargem);
document.querySelector('input[name="preco_venda"]')?.addEventListener('input', calcularMargem);

// Calcular na carga da página se houver valores
document.addEventListener('DOMContentLoaded', calcularMargem);
</script>

<?php require_once 'footer.php'; ?>