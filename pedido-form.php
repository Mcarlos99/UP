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
$pedido = null;
$itens = [];

if (isset($_GET['id'])) {
    $modo = 'editar';
    $pedidoId = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$pedidoId, $empresaId]);
        $pedido = $stmt->fetch();
        
        if (!$pedido) {
            $_SESSION['error'] = 'Pedido não encontrado';
            header('Location: pedidos.php');
            exit;
        }
        
        // Buscar itens do pedido
        $stmt = $db->prepare("
            SELECT pi.*, p.nome as produto_nome, p.preco_venda
            FROM pedidos_itens pi
            INNER JOIN produtos p ON pi.produto_id = p.id
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedidoId]);
        $itens = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar pedido';
        header('Location: pedidos.php');
        exit;
    }
}

$pageTitle = $modo === 'criar' ? 'Novo Pedido' : 'Editar Pedido';
$pageSubtitle = $modo === 'criar' ? 'Cadastre um novo pedido' : 'Atualize as informações do pedido';

// Buscar clientes
try {
    $stmt = $db->prepare("SELECT id, nome, telefone FROM clientes WHERE empresa_id = ? ORDER BY nome ASC");
    $stmt->execute([$empresaId]);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    $clientes = [];
}

// Buscar produtos
try {
    $stmt = $db->prepare("
        SELECT p.*, c.nome as categoria_nome 
        FROM produtos p
        INNER JOIN categorias c ON p.categoria_id = c.id
        WHERE p.ativo = 1 AND p.empresa_id = ?
        ORDER BY p.nome ASC
    ");
    $stmt->execute([$empresaId]);
    $produtos = $stmt->fetchAll();
} catch (PDOException $e) {
    $produtos = [];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cliente_id = (int)$_POST['cliente_id'];
        $data_pedido = sanitize($_POST['data_pedido']);
        $data_entrega = sanitize($_POST['data_entrega']);
        $status = sanitize($_POST['status']);
        $forma_pagamento = sanitize($_POST['forma_pagamento']);
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        $desconto = floatval(str_replace(',', '.', str_replace('.', '', $_POST['desconto'] ?? '0')));
        
        // Validações
        if ($cliente_id <= 0) {
            throw new Exception('Selecione um cliente');
        }
        
        if (empty($data_pedido)) {
            throw new Exception('Data do pedido é obrigatória');
        }
        
        // Validar itens
        $itens_post = json_decode($_POST['itens_json'], true);
        
        if (empty($itens_post)) {
            throw new Exception('Adicione pelo menos um produto ao pedido');
        }
        
        $db->beginTransaction();
        
        // Calcular valor total
        $valor_produtos = 0;
        foreach ($itens_post as $item) {
            $valor_produtos += $item['subtotal'];
        }
        $valor_final = $valor_produtos - $desconto;
        
        if ($modo === 'criar') {
            // Inserir pedido
            $stmt = $db->prepare("
                INSERT INTO pedidos (
                    cliente_id, data_pedido, data_entrega, status, 
                    forma_pagamento, valor_produtos, desconto, valor_final,
                    observacoes, empresa_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $cliente_id, $data_pedido, $data_entrega, $status,
                $forma_pagamento, $valor_produtos, $desconto, $valor_final,
                $observacoes, $empresaId
            ]);
            
            $pedidoId = $db->lastInsertId();
            
            // Inserir itens
            $stmt = $db->prepare("
                INSERT INTO pedidos_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($itens_post as $item) {
                $stmt->execute([
                    $pedidoId,
                    $item['produto_id'],
                    $item['quantidade'],
                    $item['preco_unitario'],
                    $item['subtotal']
                ]);
                
                // Atualizar estoque
                $stmtEstoque = $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?");
                $stmtEstoque->execute([$item['quantidade'], $item['produto_id']]);
            }
            
            logActivity('Pedido criado', 'pedidos', $pedidoId);
            $_SESSION['success'] = "Pedido #$pedidoId criado com sucesso!";
            
        } else {
            // Atualizar pedido
            $stmt = $db->prepare("
                UPDATE pedidos SET
                    cliente_id = ?, data_pedido = ?, data_entrega = ?, status = ?,
                    forma_pagamento = ?, valor_produtos = ?, desconto = ?, valor_final = ?,
                    observacoes = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([
                $cliente_id, $data_pedido, $data_entrega, $status,
                $forma_pagamento, $valor_produtos, $desconto, $valor_final,
                $observacoes, $pedidoId, $empresaId
            ]);
            
            // Restaurar estoque dos itens antigos
            $stmt = $db->prepare("SELECT produto_id, quantidade FROM pedidos_itens WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);
            $itensAntigos = $stmt->fetchAll();
            
            foreach ($itensAntigos as $itemAntigo) {
                $stmtEstoque = $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?");
                $stmtEstoque->execute([$itemAntigo['quantidade'], $itemAntigo['produto_id']]);
            }
            
            // Excluir itens antigos
            $stmt = $db->prepare("DELETE FROM pedidos_itens WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);
            
            // Inserir novos itens
            $stmt = $db->prepare("
                INSERT INTO pedidos_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($itens_post as $item) {
                $stmt->execute([
                    $pedidoId,
                    $item['produto_id'],
                    $item['quantidade'],
                    $item['preco_unitario'],
                    $item['subtotal']
                ]);
                
                // Atualizar estoque
                $stmtEstoque = $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?");
                $stmtEstoque->execute([$item['quantidade'], $item['produto_id']]);
            }
            
            logActivity('Pedido editado', 'pedidos', $pedidoId);
            $_SESSION['success'] = "Pedido #$pedidoId atualizado com sucesso!";
        }
        
        $db->commit();
        
        header('Location: pedidos.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Erro ao salvar pedido: ' . $e->getMessage();
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
/* Responsivo Mobile - Formulário de Pedidos */
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
    
    /* Produtos */
    .produtos-grid {
        grid-template-columns: 1fr !important;
    }
    
    .produto-card {
        padding: 0.75rem !important;
    }
    
    /* Itens do pedido */
    .item-pedido {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.75rem !important;
    }
    
    .item-info {
        width: 100% !important;
    }
    
    .item-actions {
        width: 100% !important;
        justify-content: space-between !important;
    }
    
    .quantidade-controls {
        gap: 0.5rem !important;
    }
    
    .quantidade-controls button {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.875rem !important;
    }
    
    .quantidade-controls input {
        width: 60px !important;
        font-size: 0.875rem !important;
        padding: 0.25rem !important;
    }
    
    /* Resumo */
    .resumo-valores {
        font-size: 0.875rem !important;
    }
}
</style>

<!-- Cabeçalho do Formulário -->
<div class="mb-4 md:mb-6">
    <div class="form-header flex items-center justify-between">
    <!--    <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
                <?php echo $modo === 'criar' ? 'Novo Pedido' : 'Editar Pedido #' . $pedidoId; ?>
            </h2>
            <p class="text-gray-600 mt-1 text-sm md:text-base">
                <?php echo $modo === 'criar' ? 'Preencha os dados para criar um novo pedido' : 'Atualize as informações do pedido'; ?>
            </p>
        </div> -->
        <a href="pedidos.php" class="hidden md:inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-arrow-left mr-2"></i> Voltar
        </a>
    </div>
</div>

<!-- Formulário -->
<form method="POST" action="" id="formPedido" class="space-y-4 md:space-y-6">
    <input type="hidden" name="itens_json" id="itensJson">
    
    <!-- Seção: Cliente e Datas -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-purple-600 to-pink-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-user"></i> Cliente e Datas
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
                <div class="md:col-span-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Cliente *</label>
                    <select name="cliente_id" required
                            class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clientes as $cli): ?>
                            <option value="<?php echo $cli['id']; ?>"
                                    <?php echo (isset($pedido['cliente_id']) && $pedido['cliente_id'] == $cli['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cli['nome']) . ' - ' . formatarTelefone($cli['telefone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($clientes)): ?>
                        <p class="text-xs text-orange-600 mt-1">
                            <i class="fas fa-exclamation-triangle"></i>
                            <a href="clientes.php" class="underline">Cadastre clientes</a> antes de criar pedidos
                        </p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Data do Pedido *</label>
                    <input type="date" name="data_pedido" required
                           value="<?php echo $pedido['data_pedido'] ?? date('Y-m-d'); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Data de Entrega</label>
                    <input type="date" name="data_entrega"
                           value="<?php echo $pedido['data_entrega'] ?? ''; ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                    <select name="status" required
                            class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                        <?php
                        $statusOpcoes = [
                            'aguardando' => 'Aguardando',
                            'em_producao' => 'Em Produção',
                            'pronto' => 'Pronto',
                            'entregue' => 'Entregue',
                            'cancelado' => 'Cancelado'
                        ];
                        foreach ($statusOpcoes as $valor => $label):
                            $selected = (isset($pedido['status']) && $pedido['status'] === $valor) || (!isset($pedido['status']) && $valor === 'aguardando') ? 'selected' : '';
                        ?>
                            <option value="<?php echo $valor; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Produtos -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-blue-600 to-cyan-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-box"></i> Produtos do Pedido
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <!-- Lista de Produtos Disponíveis -->
            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-700 mb-3">Adicionar Produto:</p>
                <div class="produtos-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($produtos as $prod): ?>
                        <div class="produto-card bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition cursor-pointer border-2 border-transparent hover:border-purple-500"
                             onclick="adicionarProduto(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nome']); ?>', <?php echo $prod['preco_venda']; ?>, <?php echo $prod['estoque_atual']; ?>)">
                            <div class="flex items-center gap-3">
                                <?php if ($prod['imagem']): ?>
                                    <img src="<?php echo htmlspecialchars($prod['imagem']); ?>" alt="<?php echo htmlspecialchars($prod['nome']); ?>" class="w-12 h-12 object-cover rounded">
                                <?php else: ?>
                                    <div class="w-12 h-12 bg-purple-100 rounded flex items-center justify-center text-purple-600 text-xl">
                                        <i class="fas fa-box"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($prod['nome']); ?></p>
                                    <p class="text-xs text-gray-600"><?php echo formatarMoeda($prod['preco_venda']); ?></p>
                                    <p class="text-xs text-gray-500">Estoque: <?php echo $prod['estoque_atual']; ?></p>
                                </div>
                                <i class="fas fa-plus-circle text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($produtos)): ?>
                    <p class="text-center text-gray-500 py-6 text-sm md:text-base">
                        <i class="fas fa-box-open text-3xl mb-2 block"></i>
                        Nenhum produto cadastrado.
                        <a href="produtos.php" class="text-purple-600 underline">Cadastre produtos</a>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Itens Adicionados -->
            <div id="itensPedido" class="space-y-3">
                <p class="text-sm font-semibold text-gray-700 mb-3">Itens do Pedido:</p>
                <div id="listaItens" class="space-y-2">
                    <?php if (empty($itens)): ?>
                        <p class="text-center text-gray-400 py-6 text-sm" id="mensagemVazio">
                            Nenhum produto adicionado ainda
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Pagamento e Resumo -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-green-600 to-emerald-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-dollar-sign"></i> Pagamento e Resumo
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Forma de Pagamento *</label>
                    <select name="forma_pagamento" required
                            class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                        <?php
                        $formasPgto = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'credito' => 'Cartão Crédito', 'debito' => 'Cartão Débito', 'transferencia' => 'Transferência'];
                        foreach ($formasPgto as $valor => $label):
                            $selected = (isset($pedido['forma_pagamento']) && $pedido['forma_pagamento'] === $valor) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $valor; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Desconto</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">R$</span>
                        <input type="text" name="desconto" id="desconto"
                               value="<?php echo isset($pedido['desconto']) ? number_format($pedido['desconto'], 2, ',', '.') : '0,00'; ?>"
                               class="w-full pl-12 pr-3 md:pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                               placeholder="0,00"
                               data-mask="money"
                               onchange="atualizarResumo()">
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                    <textarea name="observacoes" rows="3"
                              class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                              placeholder="Observações sobre o pedido..."><?php echo htmlspecialchars($pedido['observacoes'] ?? ''); ?></textarea>
                </div>
                
                <!-- Resumo -->
                <div class="md:col-span-2 bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4 md:p-6 border-2 border-purple-200">
                    <h4 class="font-bold text-gray-900 mb-4 text-base md:text-lg">Resumo do Pedido</h4>
                    <div class="resumo-valores space-y-2 text-sm md:text-base">
                        <div class="flex justify-between">
                            <span class="text-gray-700">Subtotal (Produtos):</span>
                            <span class="font-semibold" id="resumoSubtotal">R$ 0,00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-700">Desconto:</span>
                            <span class="font-semibold text-red-600" id="resumoDesconto">- R$ 0,00</span>
                        </div>
                        <div class="flex justify-between text-lg md:text-xl font-bold text-purple-600 pt-2 border-t-2 border-purple-300">
                            <span>Total:</span>
                            <span id="resumoTotal">R$ 0,00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botões de Ação -->
    <div class="form-actions flex gap-3 pt-4">
        <a href="pedidos.php" class="flex-1 md:flex-none px-4 md:px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-times mr-2"></i> Cancelar
        </a>
        <button type="submit" class="flex-1 md:flex-none btn btn-primary text-sm md:text-base" id="btnSubmit">
            <i class="fas fa-save mr-2"></i>
            <?php echo $modo === 'criar' ? 'Criar Pedido' : 'Salvar Alterações'; ?>
        </button>
    </div>
</form>

<script>
let itens = <?php echo json_encode(array_map(function($item) {
    return [
        'produto_id' => $item['produto_id'],
        'produto_nome' => $item['produto_nome'],
        'quantidade' => $item['quantidade'],
        'preco_unitario' => $item['preco_unitario'],
        'subtotal' => $item['subtotal']
    ];
}, $itens)); ?>;

function adicionarProduto(id, nome, preco, estoque) {
    // Verificar se já existe
    const index = itens.findIndex(item => item.produto_id === id);
    
    if (index >= 0) {
        // Incrementar quantidade
        if (itens[index].quantidade < estoque) {
            itens[index].quantidade++;
            itens[index].subtotal = itens[index].quantidade * itens[index].preco_unitario;
        } else {
            alert('Estoque insuficiente!');
            return;
        }
    } else {
        // Adicionar novo
        itens.push({
            produto_id: id,
            produto_nome: nome,
            quantidade: 1,
            preco_unitario: preco,
            subtotal: preco,
            estoque_max: estoque
        });
    }
    
    renderizarItens();
    atualizarResumo();
}

function alterarQuantidade(index, delta) {
    const item = itens[index];
    const novaQtd = item.quantidade + delta;
    
    if (novaQtd <= 0) {
        removerItem(index);
        return;
    }
    
    if (novaQtd > item.estoque_max) {
        alert('Estoque insuficiente!');
        return;
    }
    
    item.quantidade = novaQtd;
    item.subtotal = item.quantidade * item.preco_unitario;
    
    renderizarItens();
    atualizarResumo();
}

function removerItem(index) {
    if (confirm('Remover este produto do pedido?')) {
        itens.splice(index, 1);
        renderizarItens();
        atualizarResumo();
    }
}

function renderizarItens() {
    const container = document.getElementById('listaItens');
    const mensagemVazio = document.getElementById('mensagemVazio');
    
    if (itens.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-6 text-sm" id="mensagemVazio">Nenhum produto adicionado ainda</p>';
        return;
    }
    
    if (mensagemVazio) mensagemVazio.remove();
    
    container.innerHTML = itens.map((item, index) => `
        <div class="item-pedido flex items-center justify-between bg-white border-2 border-gray-200 rounded-lg p-3 hover:border-purple-300 transition">
            <div class="item-info flex-1">
                <p class="font-semibold text-gray-900 text-sm md:text-base">${item.produto_nome}</p>
                <p class="text-xs md:text-sm text-gray-600">
                    ${formatarMoeda(item.preco_unitario)} × ${item.quantidade} = ${formatarMoeda(item.subtotal)}
                </p>
            </div>
            <div class="item-actions flex items-center gap-3">
                <div class="quantidade-controls flex items-center gap-2">
                    <button type="button" onclick="alterarQuantidade(${index}, -1)" 
                            class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition font-bold">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="number" value="${item.quantidade}" readonly
                           class="w-16 text-center border border-gray-300 rounded-lg py-1 font-semibold text-sm md:text-base">
                    <button type="button" onclick="alterarQuantidade(${index}, 1)"
                            class="w-8 h-8 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition font-bold">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <button type="button" onclick="removerItem(${index})"
                        class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function atualizarResumo() {
    const subtotal = itens.reduce((sum, item) => sum + item.subtotal, 0);
    const descontoInput = document.getElementById('desconto');
    const desconto = parseFloat(descontoInput.value.replace(/\./g, '').replace(',', '.')) || 0;
    const total = subtotal - desconto;
    
    document.getElementById('resumoSubtotal').textContent = formatarMoeda(subtotal);
    document.getElementById('resumoDesconto').textContent = '- ' + formatarMoeda(desconto);
    document.getElementById('resumoTotal').textContent = formatarMoeda(total);
}

function formatarMoeda(valor) {
    return 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

document.getElementById('formPedido').addEventListener('submit', function(e) {
    if (itens.length === 0) {
        e.preventDefault();
        alert('Adicione pelo menos um produto ao pedido!');
        return;
    }
    
    document.getElementById('itensJson').value = JSON.stringify(itens);
});

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    renderizarItens();
    atualizarResumo();
});
</script>

<?php require_once 'footer.php'; ?>