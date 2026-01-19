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

// Verifica se é edição ou criação
$pedidoId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = !empty($pedidoId);

$pageTitle = $isEdit ? 'Editar Pedido' : 'Novo Pedido';
$pageSubtitle = $isEdit ? 'Edite as informações do pedido' : 'Crie um novo pedido';

$db = getDB();

// Buscar dados do pedido se for edição
$pedido = null;
$itens = [];

if ($isEdit) {
    validarAcessoEmpresa('pedidos', $pedidoId);
    try {
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$pedidoId, $empresaId]);
        $pedido = $stmt->fetch();
        
        if (!$pedido) {
            $_SESSION['error'] = 'Pedido não encontrado!';
            header('Location: pedidos.php');
            exit;
        }
        
        // Buscar itens do pedido
        $stmt = $db->prepare("
            SELECT pi.*, p.nome as produto_nome 
            FROM pedidos_itens pi 
            INNER JOIN produtos p ON pi.produto_id = p.id 
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedidoId]);
        $itens = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar pedido: ' . $e->getMessage();
        header('Location: pedidos.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $clienteId = (int)$_POST['cliente_id'];
        $dataEntrega = $_POST['data_entrega'];
        $status = $_POST['status'];
        $formaPagamento = $_POST['forma_pagamento'];
        $statusPagamento = $_POST['status_pagamento'];
        $desconto = (float)$_POST['desconto'];
        $observacoes = $_POST['observacoes'] ?? '';
        $observacoesInternas = $_POST['observacoes_internas'] ?? '';
        
        // Calcular valor total dos itens
        $valorTotal = 0;
        if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
            foreach ($_POST['produtos'] as $item) {
                if (!empty($item['produto_id']) && !empty($item['quantidade'])) {
                    $quantidade = (int)$item['quantidade'];
                    $preco = (float)$item['preco'];
                    $valorTotal += $quantidade * $preco;
                }
            }
        }
        
        $valorFinal = $valorTotal - $desconto;
        
        if ($isEdit) {
            validarAcessoEmpresa('pedidos', $pedidoId);
            // Atualizar pedido existente
            $stmt = $db->prepare("
                UPDATE pedidos SET 
                    cliente_id = ?,
                    valor_total = ?,
                    desconto = ?,
                    valor_final = ?,
                    status = ?,
                    forma_pagamento = ?,
                    status_pagamento = ?,
                    data_entrega = ?,
                    observacoes = ?,
                    observacoes_internas = ?
                WHERE id = ? AND empresa_id = ?
            ");
            
            $stmt->execute([
                $clienteId,
                $valorTotal,
                $desconto,
                $valorFinal,
                $status,
                $formaPagamento,
                $statusPagamento,
                $dataEntrega,
                $observacoes,
                $observacoesInternas,
                $pedidoId,
                $empresaId
            ]);
            
            // Deletar itens antigos
            $stmt = $db->prepare("DELETE FROM pedidos_itens WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);
            
            $novoPedidoId = $pedidoId;
            $mensagem = 'Pedido atualizado com sucesso!';
            
        } else {
            // Criar novo pedido
            $numeroPedido = 'PED' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO pedidos (
                    empresa_id, numero_pedido, cliente_id, usuario_id, valor_total, desconto, valor_final,
                    status, forma_pagamento, status_pagamento, data_entrega, observacoes, observacoes_internas
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresaId, 
                $numeroPedido,
                $clienteId,
                $_SESSION['user_id'],
                $valorTotal,
                $desconto,
                $valorFinal,
                $status,
                $formaPagamento,
                $statusPagamento,
                $dataEntrega,
                $observacoes,
                $observacoesInternas
            ]);
            
            $novoPedidoId = $db->lastInsertId();
            $mensagem = 'Pedido criado com sucesso!';
        }
        
        // Inserir itens do pedido
        if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
            $stmt = $db->prepare("
                INSERT INTO pedidos_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal, personalizacao, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['produtos'] as $item) {
                if (!empty($item['produto_id']) && !empty($item['quantidade'])) {
                    $produtoId = (int)$item['produto_id'];
                    $quantidade = (int)$item['quantidade'];
                    $preco = (float)$item['preco'];
                    $subtotal = $quantidade * $preco;
                    $personalizacao = $item['personalizacao'] ?? '';
                    $obsItem = $item['observacoes'] ?? '';
                    
                    $stmt->execute([
                        $novoPedidoId,
                        $produtoId,
                        $quantidade,
                        $preco,
                        $subtotal,
                        $personalizacao,
                        $obsItem
                    ]);
                }
            }
        }
        
        $db->commit();
        
        logActivity($isEdit ? 'Pedido editado' : 'Pedido criado', 'pedidos', $novoPedidoId);
        
        $_SESSION['success'] = $mensagem;
        header('Location: pedidos.php');
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Erro ao salvar pedido: ' . $e->getMessage();
    }
}

// Buscar clientes
$clientes = [];
try {
    $stmt = $db->prepare("SELECT id, nome, telefone FROM clientes WHERE ativo = 1 AND empresa_id = ? ORDER BY nome");
    $stmt->execute([$empresaId]);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    $erro = "Erro ao carregar clientes";
}

// Buscar produtos
$produtos = [];
try {
    $stmt = $db->prepare("SELECT id, nome, preco_venda FROM produtos WHERE ativo = 1 AND empresa_id = ? ORDER BY nome");
    $stmt->execute([$empresaId]);
    $produtos = $stmt->fetchAll();
} catch (PDOException $e) {
    $erro = "Erro ao carregar produtos";
}

require_once 'header.php';
?>

<style>
.item-row {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
}
</style>

<div class="max-w-6xl mx-auto">
    <div class="white-card">
        <form method="POST" action="" id="pedidoForm">
            
            <!-- Informações do Cliente -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-user"></i> Informações do Cliente
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cliente *</label>
                        <select name="cliente_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Selecione um cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($pedido && $pedido['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome']); ?> - <?php echo formatarTelefone($cliente['telefone']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data de Entrega *</label>
                        <input 
                            type="date" 
                            name="data_entrega" 
                            required
                            value="<?php echo $pedido ? $pedido['data_entrega'] : date('Y-m-d', strtotime('+7 days')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Produtos -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-box"></i> Produtos do Pedido
                </h3>
                
                <div id="produtosContainer">
                    <?php if (!empty($itens)): ?>
                        <?php foreach ($itens as $index => $item): ?>
                            <div class="item-row produto-item">
                                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Produto</label>
                                        <select name="produtos[<?php echo $index; ?>][produto_id]" class="produto-select w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" onchange="atualizarPreco(this)">
                                            <option value="">Selecione...</option>
                                            <?php foreach ($produtos as $prod): ?>
                                                <option value="<?php echo $prod['id']; ?>" data-preco="<?php echo $prod['preco_venda']; ?>" <?php echo ($item['produto_id'] == $prod['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($prod['nome']); ?> - <?php echo formatarMoeda($prod['preco_venda']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Quantidade</label>
                                        <input type="number" name="produtos[<?php echo $index; ?>][quantidade]" min="1" value="<?php echo $item['quantidade']; ?>" class="quantidade-input w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="calcularTotal()">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preço Unit.</label>
                                        <input type="number" name="produtos[<?php echo $index; ?>][preco]" step="0.01" value="<?php echo $item['preco_unitario']; ?>" class="preco-input w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="calcularTotal()">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Personalização</label>
                                        <input type="text" name="produtos[<?php echo $index; ?>][personalizacao]" value="<?php echo htmlspecialchars($item['personalizacao'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Nome, texto...">
                                    </div>
                                    
                                    <div class="flex items-end">
                                        <button type="button" onclick="removerItem(this)" class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item-row produto-item">
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Produto</label>
                                    <select name="produtos[0][produto_id]" class="produto-select w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="atualizarPreco(this)">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($produtos as $prod): ?>
                                            <option value="<?php echo $prod['id']; ?>" data-preco="<?php echo $prod['preco_venda']; ?>">
                                                <?php echo htmlspecialchars($prod['nome']); ?> - <?php echo formatarMoeda($prod['preco_venda']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantidade</label>
                                    <input type="number" name="produtos[0][quantidade]" min="1" value="1" class="quantidade-input w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="calcularTotal()">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Preço Unit.</label>
                                    <input type="number" name="produtos[0][preco]" step="0.01" value="0" class="preco-input w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="calcularTotal()">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Personalização</label>
                                    <input type="text" name="produtos[0][personalizacao]" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Nome, texto...">
                                </div>
                                
                                <div class="flex items-end">
                                    <button type="button" onclick="removerItem(this)" class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" onclick="adicionarProduto()" class="mt-4 bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                    <i class="fas fa-plus"></i> Adicionar Produto
                </button>
            </div>
            
            <!-- Valores e Status -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Resumo Financeiro -->
                <div class="white-card bg-gray-50">
                    <h4 class="font-bold text-gray-900 mb-3">Resumo Financeiro</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span id="subtotal" class="font-bold">R$ 0,00</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Desconto:</span>
                            <input type="number" name="desconto" step="0.01" value="<?php echo $pedido['desconto'] ?? 0; ?>" class="w-24 px-2 py-1 border border-gray-300 rounded" onchange="calcularTotal()">
                        </div>
                        <div class="flex justify-between text-lg font-bold text-purple-600 pt-2 border-t">
                            <span>Total:</span>
                            <span id="total">R$ 0,00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Status do Pedido -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status do Pedido</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="aguardando" <?php echo ($pedido && $pedido['status'] == 'aguardando') ? 'selected' : ''; ?>>Aguardando</option>
                        <option value="em_producao" <?php echo ($pedido && $pedido['status'] == 'em_producao') ? 'selected' : ''; ?>>Em Produção</option>
                        <option value="pronto" <?php echo ($pedido && $pedido['status'] == 'pronto') ? 'selected' : ''; ?>>Pronto</option>
                        <option value="entregue" <?php echo ($pedido && $pedido['status'] == 'entregue') ? 'selected' : ''; ?>>Entregue</option>
                        <option value="cancelado" <?php echo ($pedido && $pedido['status'] == 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                    
                    <label class="block text-sm font-semibold text-gray-700 mb-2 mt-4">Forma de Pagamento</label>
                    <select name="forma_pagamento" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="dinheiro" <?php echo ($pedido && $pedido['forma_pagamento'] == 'dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                        <option value="pix" <?php echo ($pedido && $pedido['forma_pagamento'] == 'pix') ? 'selected' : ''; ?>>PIX</option>
                        <option value="credito" <?php echo ($pedido && $pedido['forma_pagamento'] == 'credito') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                        <option value="debito" <?php echo ($pedido && $pedido['forma_pagamento'] == 'debito') ? 'selected' : ''; ?>>Cartão de Débito</option>
                        <option value="boleto" <?php echo ($pedido && $pedido['forma_pagamento'] == 'boleto') ? 'selected' : ''; ?>>Boleto</option>
                    </select>
                </div>
                
                <!-- Status de Pagamento -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status de Pagamento</label>
                    <select name="status_pagamento" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="pendente" <?php echo ($pedido && $pedido['status_pagamento'] == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo ($pedido && $pedido['status_pagamento'] == 'pago') ? 'selected' : ''; ?>>Pago</option>
                        <option value="parcial" <?php echo ($pedido && $pedido['status_pagamento'] == 'parcial') ? 'selected' : ''; ?>>Parcial</option>
                    </select>
                    
                    <label class="block text-sm font-semibold text-gray-700 mb-2 mt-4">Observações</label>
                    <textarea name="observacoes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Observações para o cliente..."><?php echo htmlspecialchars($pedido['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Botões -->
            <div class="flex gap-3 pt-6 border-t">
                <a href="pedidos.php" class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 transition text-center">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="flex-1 btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $isEdit ? 'Salvar Alterações' : 'Criar Pedido'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let produtoIndex = <?php echo count($itens); ?>;

function adicionarProduto() {
    produtoIndex++;
    const container = document.getElementById('produtosContainer');
    const novoItem = document.createElement('div');
    novoItem.className = 'item-row produto-item';
    novoItem.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Produto</label>
                <select name="produtos[${produtoIndex}][produto_id]" class="produto-select w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="atualizarPreco(this)">
                    <option value="">Selecione...</option>
                    <?php foreach ($produtos as $prod): ?>
                        <option value="<?php echo $prod['id']; ?>" data-preco="<?php echo $prod['preco_venda']; ?>">
                            <?php echo htmlspecialchars($prod['nome']); ?> - <?php echo formatarMoeda($prod['preco_venda']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Quantidade</label>
                <input type="number" name="produtos[${produtoIndex}][quantidade]" min="1" value="1" class="quantidade-input w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="calcularTotal()">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Preço Unit.</label>
                <input type="number" name="produtos[${produtoIndex}][preco]" step="0.01" value="0" class="preco-input w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="calcularTotal()">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Personalização</label>
                <input type="text" name="produtos[${produtoIndex}][personalizacao]" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Nome, texto...">
            </div>
            <div class="flex items-end">
                <button type="button" onclick="removerItem(this)" class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(novoItem);
}

function removerItem(btn) {
    const items = document.querySelectorAll('.produto-item');
    if (items.length > 1) {
        btn.closest('.produto-item').remove();
        calcularTotal();
    } else {
        alert('É necessário ter pelo menos um produto no pedido!');
    }
}

function atualizarPreco(select) {
    const option = select.options[select.selectedIndex];
    const preco = option.getAttribute('data-preco') || 0;
    const row = select.closest('.produto-item');
    const precoInput = row.querySelector('.preco-input');
    precoInput.value = preco;
    calcularTotal();
}

function calcularTotal() {
    let subtotal = 0;
    
    document.querySelectorAll('.produto-item').forEach(item => {
        const quantidade = parseFloat(item.querySelector('.quantidade-input').value) || 0;
        const preco = parseFloat(item.querySelector('.preco-input').value) || 0;
        subtotal += quantidade * preco;
    });
    
    const desconto = parseFloat(document.querySelector('input[name="desconto"]').value) || 0;
    const total = subtotal - desconto;
    
    document.getElementById('subtotal').textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
    document.getElementById('total').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
}

// Calcular total ao carregar
document.addEventListener('DOMContentLoaded', calcularTotal);
</script>

<?php require_once 'footer.php'; ?>
