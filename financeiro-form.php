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
$transacao = null;

if (isset($_GET['id'])) {
    $modo = 'editar';
    $transacaoId = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM financeiro WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$transacaoId, $empresaId]);
        $transacao = $stmt->fetch();
        
        if (!$transacao) {
            $_SESSION['error'] = 'Transação não encontrada';
            header('Location: financeiro.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar transação';
        header('Location: financeiro.php');
        exit;
    }
}

$pageTitle = $modo === 'criar' ? 'Nova Transação' : 'Editar Transação';
$pageSubtitle = $modo === 'criar' ? 'Registre uma nova transação financeira' : 'Atualize a transação financeira';

// Buscar categorias de transações
$categorias = [
    'receita' => [
        'Venda de Produtos',
        'Serviços Prestados',
        'Comissões',
        'Investimentos',
        'Outras Receitas'
    ],
    'despesa' => [
        'Aluguel',
        'Salários',
        'Fornecedores',
        'Energia',
        'Água',
        'Internet',
        'Telefone',
        'Material de Escritório',
        'Manutenção',
        'Impostos',
        'Taxas Bancárias',
        'Marketing',
        'Transporte',
        'Outras Despesas'
    ]
];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tipo = sanitize($_POST['tipo']);
        $categoria = sanitize($_POST['categoria']);
        $descricao = sanitize($_POST['descricao']);
        $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'])));
        $data_vencimento = sanitize($_POST['data_vencimento']);
        $status = sanitize($_POST['status']);
        $data_pagamento = null;
        
        if ($status === 'pago') {
            $data_pagamento = sanitize($_POST['data_pagamento'] ?? date('Y-m-d'));
        }
        
        // Validações
        if (!in_array($tipo, ['receita', 'despesa'])) {
            throw new Exception('Tipo de transação inválido');
        }
        
        if (empty($descricao)) {
            throw new Exception('Descrição é obrigatória');
        }
        
        if ($valor <= 0) {
            throw new Exception('Valor deve ser maior que zero');
        }
        
        if (empty($data_vencimento)) {
            throw new Exception('Data de vencimento é obrigatória');
        }
        
        if ($modo === 'criar') {
            $stmt = $db->prepare("
                INSERT INTO financeiro (
                    tipo, categoria, descricao, valor, data_vencimento,
                    data_pagamento, status, empresa_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tipo, $categoria, $descricao, $valor, $data_vencimento,
                $data_pagamento, $status, $empresaId
            ]);
            
            $transacaoId = $db->lastInsertId();
            
            $tipoLabel = $tipo === 'receita' ? 'Receita' : 'Despesa';
            logActivity("$tipoLabel registrada: " . $descricao, 'financeiro', $transacaoId);
            
            $_SESSION['success'] = "Transação registrada com sucesso!";
            
        } else {
            $stmt = $db->prepare("
                UPDATE financeiro SET
                    tipo = ?, categoria = ?, descricao = ?, valor = ?,
                    data_vencimento = ?, data_pagamento = ?, status = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([
                $tipo, $categoria, $descricao, $valor, $data_vencimento,
                $data_pagamento, $status, $transacaoId, $empresaId
            ]);
            
            logActivity('Transação atualizada: ' . $descricao, 'financeiro', $transacaoId);
            
            $_SESSION['success'] = "Transação atualizada com sucesso!";
        }
        
        header('Location: financeiro.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao salvar transação: ' . $e->getMessage();
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
/* Responsivo Mobile - Formulário Financeiro */
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
    
    .tipo-buttons {
        flex-direction: column !important;
    }
    
    .tipo-button {
        width: 100% !important;
    }
}
</style>

<!-- Cabeçalho do Formulário -->
<div class="mb-4 md:mb-6">
    <div class="form-header flex items-center justify-between">
      <!--  <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
                <?php echo $modo === 'criar' ? 'Nova Transação' : 'Editar Transação'; ?>
            </h2>
            <p class="text-gray-600 mt-1 text-sm md:text-base">
                <?php echo $modo === 'criar' ? 'Registre uma nova receita ou despesa' : 'Atualize os dados da transação'; ?>
            </p>
        </div> -->
        <a href="financeiro.php" class="hidden md:inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-arrow-left mr-2"></i> Voltar
        </a>
    </div>
</div>

<!-- Formulário -->
<form method="POST" action="" id="formFinanceiro" class="space-y-4 md:space-y-6">
    
    <!-- Seção: Tipo de Transação -->
    <div class="white-card">
        <div class="px-4 md:px-6 py-4 md:py-6">
            <label class="block text-sm font-semibold text-gray-700 mb-3">Tipo de Transação *</label>
            <div class="tipo-buttons flex gap-3 md:gap-4">
                <button type="button" id="btnReceita" onclick="selecionarTipo('receita')"
                        class="tipo-button flex-1 px-4 md:px-6 py-3 md:py-4 border-2 rounded-xl transition font-semibold text-sm md:text-base <?php echo (!isset($transacao['tipo']) || $transacao['tipo'] === 'receita') ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'; ?>">
                    <i class="fas fa-arrow-up text-xl md:text-2xl mb-2 block"></i>
                    Receita
                </button>
                <button type="button" id="btnDespesa" onclick="selecionarTipo('despesa')"
                        class="tipo-button flex-1 px-4 md:px-6 py-3 md:py-4 border-2 rounded-xl transition font-semibold text-sm md:text-base <?php echo (isset($transacao['tipo']) && $transacao['tipo'] === 'despesa') ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'; ?>">
                    <i class="fas fa-arrow-down text-xl md:text-2xl mb-2 block"></i>
                    Despesa
                </button>
            </div>
            <input type="hidden" name="tipo" id="tipoInput" value="<?php echo $transacao['tipo'] ?? 'receita'; ?>" required>
        </div>
    </div>
    
    <!-- Seção: Informações da Transação -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-purple-600 to-pink-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-info-circle"></i> Informações da Transação
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria *</label>
                    <select name="categoria" id="categoriaSelect" required
                            class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                        <option value="">Selecione uma categoria</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição *</label>
                    <textarea name="descricao" required rows="3"
                              class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                              placeholder="Descreva a transação..."><?php echo htmlspecialchars($transacao['descricao'] ?? ''); ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Valor *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 -translate-y-1/2 text-gray-500">R$</span>
                        <input type="text" name="valor" required
                               value="<?php echo isset($transacao['valor']) ? number_format($transacao['valor'], 2, ',', '.') : ''; ?>"
                               class="w-full pl-12 pr-3 md:pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                               placeholder="0,00"
                               data-mask="money">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Data de Vencimento *</label>
                    <input type="date" name="data_vencimento" required
                           value="<?php echo $transacao['data_vencimento'] ?? date('Y-m-d'); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Status do Pagamento -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-blue-600 to-cyan-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-check-circle"></i> Status do Pagamento
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                    <select name="status" id="statusSelect" required onchange="toggleDataPagamento()"
                            class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                        <option value="pendente" <?php echo (!isset($transacao['status']) || $transacao['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo (isset($transacao['status']) && $transacao['status'] === 'pago') ? 'selected' : ''; ?>>Pago</option>
                        <option value="vencido" <?php echo (isset($transacao['status']) && $transacao['status'] === 'vencido') ? 'selected' : ''; ?>>Vencido</option>
                    </select>
                </div>
                
                <div id="divDataPagamento" class="md:col-span-2" style="display: <?php echo (isset($transacao['status']) && $transacao['status'] === 'pago') ? 'block' : 'none'; ?>;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Data do Pagamento</label>
                    <input type="date" name="data_pagamento" id="dataPagamento"
                           value="<?php echo $transacao['data_pagamento'] ?? date('Y-m-d'); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Prévia do Lançamento -->
    <div class="white-card">
        <div class="px-4 md:px-6 py-4 md:py-6">
            <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4 md:p-6 border-2 border-purple-200">
                <h4 class="font-bold text-gray-900 mb-4 text-base md:text-lg">
                    <i class="fas fa-eye"></i> Prévia do Lançamento
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Tipo</p>
                        <p class="font-semibold text-sm md:text-base" id="prevTipo">Receita</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Categoria</p>
                        <p class="font-semibold text-sm md:text-base" id="prevCategoria">-</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Valor</p>
                        <p class="font-bold text-lg md:text-xl text-purple-600" id="prevValor">R$ 0,00</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Status</p>
                        <p class="font-semibold text-sm md:text-base" id="prevStatus">Pendente</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botões de Ação -->
    <div class="form-actions flex gap-3 pt-4">
        <a href="financeiro.php" class="flex-1 md:flex-none px-4 md:px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-times mr-2"></i> Cancelar
        </a>
        <button type="submit" class="flex-1 md:flex-none btn btn-primary text-sm md:text-base">
            <i class="fas fa-save mr-2"></i>
            <?php echo $modo === 'criar' ? 'Registrar Transação' : 'Salvar Alterações'; ?>
        </button>
    </div>
</form>

<script>
const categorias = <?php echo json_encode($categorias); ?>;

function selecionarTipo(tipo) {
    document.getElementById('tipoInput').value = tipo;
    
    // Atualizar botões
    document.getElementById('btnReceita').className = tipo === 'receita' 
        ? 'tipo-button flex-1 px-4 md:px-6 py-3 md:py-4 border-2 border-green-500 bg-green-50 text-green-700 rounded-xl transition font-semibold text-sm md:text-base'
        : 'tipo-button flex-1 px-4 md:px-6 py-3 md:py-4 border-2 border-gray-300 bg-white text-gray-700 hover:border-gray-400 rounded-xl transition font-semibold text-sm md:text-base';
    
    document.getElementById('btnDespesa').className = tipo === 'despesa'
        ? 'tipo-button flex-1 px-4 md:px-6 py-3 md:py-4 border-2 border-red-500 bg-red-50 text-red-700 rounded-xl transition font-semibold text-sm md:text-base'
        : 'tipo-button flex-1 px-4 md:px-6 py-3 md:py-4 border-2 border-gray-300 bg-white text-gray-700 hover:border-gray-400 rounded-xl transition font-semibold text-sm md:text-base';
    
    // Atualizar categorias
    atualizarCategorias(tipo);
    
    // Atualizar prévia
    atualizarPrevia();
}

function atualizarCategorias(tipo) {
    const select = document.getElementById('categoriaSelect');
    const categoriaAtual = select.value;
    
    select.innerHTML = '<option value="">Selecione uma categoria</option>';
    
    categorias[tipo].forEach(cat => {
        const option = document.createElement('option');
        option.value = cat;
        option.textContent = cat;
        if (cat === categoriaAtual) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function toggleDataPagamento() {
    const status = document.getElementById('statusSelect').value;
    const div = document.getElementById('divDataPagamento');
    
    if (status === 'pago') {
        div.style.display = 'block';
        document.getElementById('dataPagamento').value = document.getElementById('dataPagamento').value || '<?php echo date('Y-m-d'); ?>';
    } else {
        div.style.display = 'none';
    }
    
    atualizarPrevia();
}

function atualizarPrevia() {
    const tipo = document.getElementById('tipoInput').value;
    const categoria = document.getElementById('categoriaSelect').value;
    const valor = document.querySelector('input[name="valor"]').value;
    const status = document.getElementById('statusSelect').value;
    
    document.getElementById('prevTipo').textContent = tipo === 'receita' ? 'Receita' : 'Despesa';
    document.getElementById('prevCategoria').textContent = categoria || '-';
    document.getElementById('prevValor').textContent = valor ? 'R$ ' + valor : 'R$ 0,00';
    
    const statusLabels = {
        'pendente': 'Pendente',
        'pago': 'Pago',
        'vencido': 'Vencido'
    };
    document.getElementById('prevStatus').textContent = statusLabels[status] || 'Pendente';
}

// Event listeners
document.getElementById('categoriaSelect').addEventListener('change', atualizarPrevia);
document.querySelector('input[name="valor"]').addEventListener('input', atualizarPrevia);

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    const tipoAtual = document.getElementById('tipoInput').value;
    atualizarCategorias(tipoAtual);
    
    <?php if (isset($transacao['categoria'])): ?>
        document.getElementById('categoriaSelect').value = '<?php echo addslashes($transacao['categoria']); ?>';
    <?php endif; ?>
    
    atualizarPrevia();
});
</script>

<?php require_once 'footer.php'; ?>