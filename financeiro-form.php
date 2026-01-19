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

$transacaoId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = !empty($transacaoId);
$tipoInicial = isset($_GET['tipo']) ? $_GET['tipo'] : 'receita';

$pageTitle = $isEdit ? 'Editar Transação' : 'Nova Transação Financeira';
$pageSubtitle = $isEdit ? 'Edite a transação' : 'Adicione uma receita ou despesa';

$db = getDB();

// Buscar transação se for edição
$transacao = null;
if ($isEdit) {
    validarAcessoEmpresa('financeiro', $transacaoId);
    try {
        $stmt = $db->prepare("SELECT * FROM financeiro WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$transacaoId, $empresaId]);
        $transacao = $stmt->fetch();
        
        if (!$transacao) {
            $_SESSION['error'] = 'Transação não encontrada!';
            header('Location: financeiro.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar transação: ' . $e->getMessage();
        header('Location: financeiro.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tipo = $_POST['tipo'];
        $categoria = sanitize($_POST['categoria']);
        $descricao = sanitize($_POST['descricao']);
        $valor = (float)$_POST['valor'];
        $dataVencimento = $_POST['data_vencimento'];
        $status = $_POST['status'];
        $formaPagamento = sanitize($_POST['forma_pagamento'] ?? '');
        $dataPagamento = !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null;
        $clienteId = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
        $pedidoId = !empty($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : null;
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        // Validações
        if (empty($categoria)) {
            throw new Exception('Categoria é obrigatória');
        }
        
        if (empty($descricao)) {
            throw new Exception('Descrição é obrigatória');
        }
        
        if ($valor <= 0) {
            throw new Exception('Valor deve ser maior que zero');
        }
        
        if ($isEdit) {
            validarAcessoEmpresa('financeiro', $transacaoId);
            // Atualizar transação
            $stmt = $db->prepare("
                UPDATE financeiro SET 
                    tipo = ?, categoria = ?, descricao = ?, valor = ?,
                    data_vencimento = ?, data_pagamento = ?, status = ?,
                    forma_pagamento = ?, cliente_id = ?, pedido_id = ?,
                    observacoes = ?
                WHERE id = ? AND empresa_id = ?
            ");
            
            $stmt->execute([
                $tipo, $categoria, $descricao, $valor,
                $dataVencimento, $dataPagamento, $status,
                $formaPagamento, $clienteId, $pedidoId,
                $observacoes, $transacaoId, $empresaId
            ]);
            
            logActivity('Transação atualizada', 'financeiro', $transacaoId);
            $_SESSION['success'] = 'Transação atualizada com sucesso!';
            
        } else {
            // Criar transação
            $stmt = $db->prepare("
                INSERT INTO financeiro (
                    empresa_id, tipo, categoria, descricao, valor,
                    data_vencimento, data_pagamento, status,
                    forma_pagamento, cliente_id, pedido_id,
                    observacoes, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresaId, $tipo, $categoria, $descricao, $valor,
                $dataVencimento, $dataPagamento, $status,
                $formaPagamento, $clienteId, $pedidoId,
                $observacoes, $_SESSION['user_id']
            ]);
            
            $novaTransacaoId = $db->lastInsertId();
            
            logActivity('Transação criada', 'financeiro', $novaTransacaoId);
            $_SESSION['success'] = 'Transação criada com sucesso!';
        }
        
        header('Location: financeiro.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao salvar transação: ' . $e->getMessage();
    }
}

// Buscar clientes para select
$clientes = [];
try {
    $stmt = $db->prepare("SELECT id, nome FROM clientes WHERE ativo = 1 AND empresa_id = ? ORDER BY nome");
    $stmt->execute([$empresaId]);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    $erro = "Erro ao carregar clientes";
}

// Categorias padrão
$categoriasReceita = [
    'Venda de Produtos',
    'Serviços Prestados',
    'Pedidos',
    'Outras Receitas'
];

$categoriasDespesa = [
    'Aluguel',
    'Água/Luz/Internet',
    'Fornecedores',
    'Matéria-prima',
    'Salários',
    'Impostos',
    'Marketing',
    'Manutenção',
    'Transporte',
    'Outras Despesas'
];

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="white-card">
        <form method="POST" action="" id="financeiroForm">
            
            <!-- Tipo de Transação -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-exchange-alt"></i> Tipo de Transação
                </h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer">
                        <input 
                            type="radio" 
                            name="tipo" 
                            value="receita" 
                            <?php echo (!$transacao || $transacao['tipo'] === 'receita' || $tipoInicial === 'receita') ? 'checked' : ''; ?>
                            onchange="atualizarCategorias()"
                            class="hidden peer"
                        >
                        <div class="border-2 border-gray-300 rounded-lg p-4 text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition">
                            <i class="fas fa-plus-circle text-3xl text-green-600 mb-2"></i>
                            <p class="font-bold text-gray-900">Receita</p>
                            <p class="text-xs text-gray-600">Dinheiro que entra</p>
                        </div>
                    </label>
                    
                    <label class="cursor-pointer">
                        <input 
                            type="radio" 
                            name="tipo" 
                            value="despesa" 
                            <?php echo ($transacao && $transacao['tipo'] === 'despesa') || $tipoInicial === 'despesa' ? 'checked' : ''; ?>
                            onchange="atualizarCategorias()"
                            class="hidden peer"
                        >
                        <div class="border-2 border-gray-300 rounded-lg p-4 text-center peer-checked:border-red-500 peer-checked:bg-red-50 transition">
                            <i class="fas fa-minus-circle text-3xl text-red-600 mb-2"></i>
                            <p class="font-bold text-gray-900">Despesa</p>
                            <p class="text-xs text-gray-600">Dinheiro que sai</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Informações da Transação -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-info-circle"></i> Informações da Transação
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria *</label>
                        <select 
                            name="categoria" 
                            required
                            id="categoriaSelect"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Valor *</label>
                        <input 
                            type="number" 
                            name="valor" 
                            step="0.01"
                            required
                            value="<?php echo $transacao['valor'] ?? ''; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="0.00"
                        >
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição *</label>
                        <input 
                            type="text" 
                            name="descricao" 
                            required
                            value="<?php echo htmlspecialchars($transacao['descricao'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Ex: Compra de papel"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Datas -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-calendar"></i> Datas
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data de Vencimento *</label>
                        <input 
                            type="date" 
                            name="data_vencimento" 
                            required
                            value="<?php echo $transacao['data_vencimento'] ?? date('Y-m-d'); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data de Pagamento</label>
                        <input 
                            type="date" 
                            name="data_pagamento" 
                            value="<?php echo $transacao['data_pagamento'] ?? ''; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            id="dataPagamento"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Status e Pagamento -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-check-circle"></i> Status e Forma de Pagamento
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                        <select 
                            name="status" 
                            required
                            id="statusSelect"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            onchange="atualizarDataPagamento()"
                        >
                            <option value="pendente" <?php echo (!$transacao || $transacao['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                            <option value="pago" <?php echo ($transacao && $transacao['status'] === 'pago') ? 'selected' : ''; ?>>Pago</option>
                            <option value="vencido" <?php echo ($transacao && $transacao['status'] === 'vencido') ? 'selected' : ''; ?>>Vencido</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Forma de Pagamento</label>
                        <select 
                            name="forma_pagamento"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                            <option value="">Selecione...</option>
                            <option value="Dinheiro" <?php echo ($transacao && $transacao['forma_pagamento'] === 'Dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                            <option value="PIX" <?php echo ($transacao && $transacao['forma_pagamento'] === 'PIX') ? 'selected' : ''; ?>>PIX</option>
                            <option value="Cartão de Crédito" <?php echo ($transacao && $transacao['forma_pagamento'] === 'Cartão de Crédito') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                            <option value="Cartão de Débito" <?php echo ($transacao && $transacao['forma_pagamento'] === 'Cartão de Débito') ? 'selected' : ''; ?>>Cartão de Débito</option>
                            <option value="Boleto" <?php echo ($transacao && $transacao['forma_pagamento'] === 'Boleto') ? 'selected' : ''; ?>>Boleto</option>
                            <option value="Transferência" <?php echo ($transacao && $transacao['forma_pagamento'] === 'Transferência') ? 'selected' : ''; ?>>Transferência</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Vínculos -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-link"></i> Vínculos (Opcional)
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cliente</label>
                        <select 
                            name="cliente_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                            <option value="">Nenhum</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($transacao && $transacao['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">ID do Pedido</label>
                        <input 
                            type="number" 
                            name="pedido_id" 
                            value="<?php echo $transacao['pedido_id'] ?? ''; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="ID do pedido vinculado"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Observações -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-sticky-note"></i> Observações
                </label>
                <textarea 
                    name="observacoes" 
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Observações adicionais..."
                ><?php echo htmlspecialchars($transacao['observacoes'] ?? ''); ?></textarea>
            </div>
            
            <!-- Botões -->
            <div class="flex gap-3 pt-6 border-t">
                <a href="financeiro.php" class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 transition text-center">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="flex-1 btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $isEdit ? 'Salvar Alterações' : 'Criar Transação'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const categoriasReceita = <?php echo json_encode($categoriasReceita); ?>;
const categoriasDespesa = <?php echo json_encode($categoriasDespesa); ?>;
const categoriaAtual = '<?php echo $transacao['categoria'] ?? ''; ?>';

function atualizarCategorias() {
    const tipo = document.querySelector('input[name="tipo"]:checked').value;
    const select = document.getElementById('categoriaSelect');
    const categorias = tipo === 'receita' ? categoriasReceita : categoriasDespesa;
    
    select.innerHTML = '<option value="">Selecione...</option>';
    categorias.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat;
        option.textContent = cat;
        if (cat === categoriaAtual) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function atualizarDataPagamento() {
    const status = document.getElementById('statusSelect').value;
    const dataPagamento = document.getElementById('dataPagamento');
    
    if (status === 'pago' && !dataPagamento.value) {
        dataPagamento.value = new Date().toISOString().split('T')[0];
    }
}

// Inicializar ao carregar
document.addEventListener('DOMContentLoaded', function() {
    atualizarCategorias();
});
</script>

<?php require_once 'footer.php'; ?>
