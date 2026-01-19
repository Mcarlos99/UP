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

$produtoId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = !empty($produtoId);

$pageTitle = $isEdit ? 'Editar Produto' : 'Novo Produto';
$pageSubtitle = $isEdit ? 'Edite as informações do produto' : 'Adicione um novo produto ao catálogo';

$db = getDB();

// Buscar produto se for edição
$produto = null;
if ($isEdit) {
    try {
        validarAcessoEmpresa('produtos', $produtoId);
        $stmt = $db->prepare("SELECT * FROM produtos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$produtoId, $empresaId]);
        $produto = $stmt->fetch();
        
        if (!$produto) {
            $_SESSION['error'] = 'Produto não encontrado!';
            header('Location: produtos.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar produto: ' . $e->getMessage();
        header('Location: produtos.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $categoriaId = (int)$_POST['categoria_id'];
        $nome = sanitize($_POST['nome']);
        $descricao = sanitize($_POST['descricao'] ?? '');
        $codigoSku = sanitize($_POST['codigo_sku'] ?? '');
        $precoCusto = (float)$_POST['preco_custo'];
        $precoVenda = (float)$_POST['preco_venda'];
        $estoqueAtual = (int)$_POST['estoque_atual'];
        $estoqueMinimo = (int)$_POST['estoque_minimo'];
        $unidadeMedida = sanitize($_POST['unidade_medida'] ?? 'un');
        $destaque = isset($_POST['destaque']) ? 1 : 0;
        
        // Calcular margem de lucro
        $margemLucro = 0;
        if ($precoCusto > 0) {
            $margemLucro = (($precoVenda - $precoCusto) / $precoCusto) * 100;
        }
        
        // Upload de imagem
        $imagemPath = $produto['imagem'] ?? '';
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            try {
                // Deletar imagem antiga se existir
                if (!empty($produto['imagem'])) {
                    deleteFile($produto['imagem']);
                }
                $imagemPath = uploadFile($_FILES['imagem'], 'produtos');
            } catch (Exception $e) {
                $_SESSION['error'] = 'Erro ao fazer upload da imagem: ' . $e->getMessage();
            }
        }
        
        if ($isEdit) {
            validarAcessoEmpresa('produtos', $produtoId);
            // Atualizar produto
            $stmt = $db->prepare("
                UPDATE produtos SET 
                    categoria_id = ?, nome = ?, descricao = ?, codigo_sku = ?,
                    preco_custo = ?, preco_venda = ?, margem_lucro = ?,
                    estoque_atual = ?, estoque_minimo = ?, unidade_medida = ?,
                    imagem = ?, destaque = ?
                WHERE id = ? AND empresa_id = ?
            ");
            
            $stmt->execute([
                $categoriaId, $nome, $descricao, $codigoSku,
                $precoCusto, $precoVenda, $margemLucro,
                $estoqueAtual, $estoqueMinimo, $unidadeMedida,
                $imagemPath, $destaque, $produtoId, $empresaId
            ]);
            
            logActivity('Produto atualizado', 'produtos', $produtoId);
            $_SESSION['success'] = 'Produto atualizado com sucesso!';
            
        } else {
            // Criar produto
            $stmt = $db->prepare("
                INSERT INTO produtos (
                    empresa_id, categoria_id, nome, descricao, codigo_sku,
                    preco_custo, preco_venda, margem_lucro,
                    estoque_atual, estoque_minimo, unidade_medida,
                    imagem, destaque, ativo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $empresaId, $categoriaId, $nome, $descricao, $codigoSku,
                $precoCusto, $precoVenda, $margemLucro,
                $estoqueAtual, $estoqueMinimo, $unidadeMedida,
                $imagemPath, $destaque
            ]);
            
            $novoProdutoId = $db->lastInsertId();
            
            // Registrar movimentação inicial de estoque
            if ($estoqueAtual > 0) {
                $stmt = $db->prepare("
                    INSERT INTO movimentacoes_estoque 
                    (produto_id, tipo, quantidade, estoque_anterior, estoque_atual, motivo, usuario_id)
                    VALUES (?, 'entrada', ?, 0, ?, 'Estoque inicial', ?)
                ");
                $stmt->execute([$novoProdutoId, $estoqueAtual, $estoqueAtual, $_SESSION['user_id']]);
            }
            
            logActivity('Produto criado', 'produtos', $novoProdutoId);
            $_SESSION['success'] = 'Produto criado com sucesso!';
        }
        
        header('Location: produtos.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao salvar produto: ' . $e->getMessage();
    }
}

// Buscar categorias
$categorias = [];
try {
    $stmt = $db->prepare("SELECT * FROM categorias WHERE ativo = 1 AND empresa_id = ? ORDER BY nome");
    $stmt->execute([$empresaId]); // <- ADICIONAR
    $categorias = $stmt->fetchAll();
} catch (PDOException $e) {
    $erro = "Erro ao carregar categorias";
}

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="white-card">
        <form method="POST" action="" enctype="multipart/form-data" id="produtoForm">
            
            <!-- Informações Básicas -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-info-circle"></i> Informações Básicas
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Produto *</label>
                        <input 
                            type="text" 
                            name="nome" 
                            required
                            value="<?php echo htmlspecialchars($produto['nome'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Ex: Caderno Personalizado A5"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria *</label>
                        <select name="categoria_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($produto && $produto['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['icone']; ?> <?php echo htmlspecialchars($cat['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Código SKU</label>
                        <input 
                            type="text" 
                            name="codigo_sku" 
                            value="<?php echo htmlspecialchars($produto['codigo_sku'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Ex: CAD-001"
                        >
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                        <textarea 
                            name="descricao" 
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Descrição detalhada do produto..."
                        ><?php echo htmlspecialchars($produto['descricao'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Preços -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-dollar-sign"></i> Preços e Margem
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preço de Custo</label>
                        <input 
                            type="number" 
                            name="preco_custo" 
                            step="0.01"
                            value="<?php echo $produto['preco_custo'] ?? 0; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            id="precoCusto"
                            onchange="calcularMargem()"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preço de Venda *</label>
                        <input 
                            type="number" 
                            name="preco_venda" 
                            step="0.01"
                            required
                            value="<?php echo $produto['preco_venda'] ?? 0; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            id="precoVenda"
                            onchange="calcularMargem()"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Margem de Lucro</label>
                        <div class="flex items-center gap-2">
                            <input 
                                type="text" 
                                id="margemLucro"
                                readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 font-bold text-green-600"
                                value="0%"
                            >
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estoque -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-boxes"></i> Controle de Estoque
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Estoque Atual *</label>
                        <input 
                            type="number" 
                            name="estoque_atual" 
                            required
                            value="<?php echo $produto['estoque_atual'] ?? 0; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Estoque Mínimo *</label>
                        <input 
                            type="number" 
                            name="estoque_minimo" 
                            required
                            value="<?php echo $produto['estoque_minimo'] ?? 5; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                        <p class="text-xs text-gray-600 mt-1">Alerta quando atingir este valor</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Unidade de Medida</label>
                        <select name="unidade_medida" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="un" <?php echo ($produto && $produto['unidade_medida'] == 'un') ? 'selected' : ''; ?>>Unidade (un)</option>
                            <option value="cx" <?php echo ($produto && $produto['unidade_medida'] == 'cx') ? 'selected' : ''; ?>>Caixa (cx)</option>
                            <option value="pct" <?php echo ($produto && $produto['unidade_medida'] == 'pct') ? 'selected' : ''; ?>>Pacote (pct)</option>
                            <option value="kg" <?php echo ($produto && $produto['unidade_medida'] == 'kg') ? 'selected' : ''; ?>>Quilograma (kg)</option>
                            <option value="m" <?php echo ($produto && $produto['unidade_medida'] == 'm') ? 'selected' : ''; ?>>Metro (m)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Imagem -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-image"></i> Imagem do Produto
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Selecionar Imagem</label>
                        <input 
                            type="file" 
                            name="imagem" 
                            accept="image/*"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            onchange="previewImage(this)"
                        >
                        <p class="text-xs text-gray-600 mt-1">JPG, PNG ou GIF (máx. 5MB)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preview</label>
                        <div id="imagePreview" class="border-2 border-dashed border-gray-300 rounded-lg p-4 flex items-center justify-center h-32">
                            <?php if (!empty($produto['imagem']) && file_exists(UPLOAD_DIR . $produto['imagem'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($produto['imagem']); ?>" alt="Preview" class="max-h-full max-w-full object-contain">
                            <?php else: ?>
                                <p class="text-gray-400 text-sm">Nenhuma imagem selecionada</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Opções Adicionais -->
            <div class="mb-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="destaque" value="1" <?php echo ($produto && $produto['destaque']) ? 'checked' : ''; ?> class="w-5 h-5 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                    <span class="font-semibold text-gray-900">
                        <i class="fas fa-star text-yellow-500"></i> Produto em Destaque
                    </span>
                </label>
                <p class="text-sm text-gray-600 ml-7">Produtos em destaque aparecem em posição de destaque no catálogo</p>
            </div>
            
            <!-- Botões -->
            <div class="flex gap-3 pt-6 border-t">
                <a href="produtos.php" class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 transition text-center">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="flex-1 btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $isEdit ? 'Salvar Alterações' : 'Criar Produto'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function calcularMargem() {
    const custo = parseFloat(document.getElementById('precoCusto').value) || 0;
    const venda = parseFloat(document.getElementById('precoVenda').value) || 0;
    
    if (custo > 0) {
        const margem = ((venda - custo) / custo) * 100;
        document.getElementById('margemLucro').value = margem.toFixed(2) + '%';
        
        if (margem < 0) {
            document.getElementById('margemLucro').className = 'w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 font-bold text-red-600';
        } else if (margem < 20) {
            document.getElementById('margemLucro').className = 'w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 font-bold text-yellow-600';
        } else {
            document.getElementById('margemLucro').className = 'w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 font-bold text-green-600';
        }
    } else {
        document.getElementById('margemLucro').value = '0%';
    }
}

function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="max-h-full max-w-full object-contain">';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Calcular margem ao carregar
document.addEventListener('DOMContentLoaded', calcularMargem);
</script>

<?php require_once 'footer.php'; ?>
