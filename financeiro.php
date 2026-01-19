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
$pageTitle = 'Financeiro';
$pageSubtitle = 'Controle suas receitas e despesas';


// Processar aÃ§Ãµes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
  

    // Excluir transaÃ§Ã£o

    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['transacao_id'])) {
        validarAcessoEmpresa('financeiro', $_POST['transacao_id']);
        try {
            $stmt = $db->prepare("DELETE FROM financeiro WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$_POST['transacao_id'], $empresaId]);

            

            logActivity('Transação excluida', 'financeiro', $_POST['transacao_id']);

            $_SESSION['success'] = 'Transação excluida com sucesso!';

            header('Location: financeiro.php');

            exit;

        } catch (PDOException $e) {

            $_SESSION['error'] = 'Erro ao excluir transaÃ§Ã£o: ' . $e->getMessage();

        }

    }

    

    // Marcar como pago

    if (isset($_POST['action']) && $_POST['action'] === 'marcar_pago' && isset($_POST['transacao_id'])) {

        try {

            $stmt = $db->prepare("UPDATE financeiro SET status = 'pago', data_pagamento = CURDATE() WHERE id = ?");

            $stmt->execute([$_POST['transacao_id']]);

            

            logActivity('TransaÃ§Ã£o marcada como paga', 'financeiro', $_POST['transacao_id']);

            $_SESSION['success'] = 'TransaÃ§Ã£o marcada como paga!';

            header('Location: financeiro.php');

            exit;

        } catch (PDOException $e) {

            $_SESSION['error'] = 'Erro ao atualizar status: ' . $e->getMessage();

        }

    }

}



// Filtros

$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';

$filtroStatus = isset($_GET['status']) ? $_GET['status'] : 'todos';

$filtroCategoria = isset($_GET['categoria']) ? $_GET['categoria'] : 'todas';

$mesAno = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

$busca = isset($_GET['busca']) ? $_GET['busca'] : '';



// Construir query
$where = ["1=1", "empresa_id = ?"];
$params = [$empresaId];

if ($filtroTipo !== 'todos') {
    $where[] = "tipo = ?";
    $params[] = $filtroTipo;
}



if ($filtroStatus !== 'todos') {
    $where[] = "status = ?";
    $params[] = $filtroStatus;
}



if ($filtroCategoria !== 'todas') {
    $where[] = "categoria = ?";
    $params[] = $filtroCategoria;
}



if (!empty($mesAno)) {
    $where[] = "DATE_FORMAT(data_vencimento, '%Y-%m') = ?";
    $params[] = $mesAno;
}



if (!empty($busca)) {
    $where[] = "(descricao LIKE ? OR categoria LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}



try {
    $db = getDB();
    // Buscar transaÃ§Ãµes

    $sql = "SELECT f.*, c.nome as cliente_nome, p.numero_pedido

            FROM financeiro f

            LEFT JOIN clientes c ON f.cliente_id = c.id

            LEFT JOIN pedidos p ON f.pedido_id = p.id

            WHERE " . implode(" AND ", $where) . "

            ORDER BY f.data_vencimento DESC, f.id DESC";

    

    $stmt = $db->prepare($sql);

    $stmt->execute($params);

    $transacoes = $stmt->fetchAll();

    

    // Calcular totais do mes

    $whereResumo = ["DATE_FORMAT(data_vencimento, '%Y-%m') = ?"];

    $paramsResumo = [$mesAno];

    

    $sqlResumo = "SELECT 

                    SUM(CASE WHEN tipo = 'receita' AND status = 'pago' THEN valor ELSE 0 END) as receitas_pagas,

                    SUM(CASE WHEN tipo = 'receita' AND status = 'pendente' THEN valor ELSE 0 END) as receitas_pendentes,

                    SUM(CASE WHEN tipo = 'despesa' AND status = 'pago' THEN valor ELSE 0 END) as despesas_pagas,

                    SUM(CASE WHEN tipo = 'despesa' AND status = 'pendente' THEN valor ELSE 0 END) as despesas_pendentes

                  FROM financeiro

                  WHERE " . implode(" AND ", $whereResumo);

    

    $stmt = $db->prepare($sqlResumo);

    $stmt->execute($paramsResumo, $empresaId);

    $resumo = $stmt->fetch();

    

    // Buscar categorias para filtro

    $sqlCategorias = "SELECT DISTINCT categoria FROM financeiro WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";

    $categorias = $db->query($sqlCategorias)->fetchAll(PDO::FETCH_COLUMN);

    

    // EstatÃ­sticas gerais

    $stmt = $db->query("SELECT COUNT(*) as total FROM financeiro WHERE status = 'pendente' AND data_vencimento < CURDATE()");

    $vencidas = $stmt->fetch()['total'];

    

    $stmt = $db->query("SELECT COUNT(*) as total FROM financeiro WHERE status = 'pendente' AND data_vencimento = CURDATE()");

    $vencem_hoje = $stmt->fetch()['total'];

    

} catch (PDOException $e) {

    $erro = "Erro ao carregar dados: " . $e->getMessage();

}



// Calcular saldos

$receitasPagas = $resumo['receitas_pagas'] ?? 0;

$receitasPendentes = $resumo['receitas_pendentes'] ?? 0;

$despesasPagas = $resumo['despesas_pagas'] ?? 0;

$despesasPendentes = $resumo['despesas_pendentes'] ?? 0;



$totalReceitas = $receitasPagas + $receitasPendentes;

$totalDespesas = $despesasPagas + $despesasPendentes;

$saldoRealizado = $receitasPagas - $despesasPagas;

$saldoPrevisto = $totalReceitas - $totalDespesas;



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

}

?>



<!-- CabeÃ§alho -->

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">

    <div>

        <h2 class="text-3xl font-bold text-gray-900">Financeiro</h2>

        <p class="text-gray-600 mt-1">Controle suas receitas e despesas</p>

    </div>

    <div class="flex gap-3">

        <a href="financeiro-form.php?tipo=despesa" class="bg-red-600 text-white px-4 py-3 rounded-lg hover:bg-red-700 transition font-semibold">

            <i class="fas fa-minus-circle"></i> Nova Despesa

        </a>

        <a href="financeiro-form.php?tipo=receita" class="bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 transition font-semibold">

            <i class="fas fa-plus-circle"></i> Nova Receita

        </a>

    </div>

</div>



<!-- Alertas -->

<?php if ($vencidas > 0 || $vencem_hoje > 0): ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

        <?php if ($vencidas > 0): ?>

            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">

                <div class="flex items-center gap-3">

                    <i class="fas fa-exclamation-triangle text-2xl"></i>

                    <div>

                        <p class="font-bold">AtenÃ§Ã£o!</p>

                        <p class="text-sm"><?php echo $vencidas; ?> transaÃ§Ã£o(Ãµes) vencida(s)</p>

                    </div>

                </div>

            </div>

        <?php endif; ?>

        

        <?php if ($vencem_hoje > 0): ?>

            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg">

                <div class="flex items-center gap-3">

                    <i class="fas fa-clock text-2xl"></i>

                    <div>

                        <p class="font-bold">AtenÃ§Ã£o!</p>

                        <p class="text-sm"><?php echo $vencem_hoje; ?> transaÃ§Ã£o(Ãµes) vence(m) hoje</p>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>



<!-- Cards de Resumo Financeiro -->

<div class="grid grid-cols-4 gap-4 mb-6" style="grid-template-columns: repeat(4, minmax(0, 1fr));">

    <!-- Receitas -->

    <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">

        <div class="h-full flex flex-col items-center justify-center text-center">

            <div class="bg-white/20 p-3 rounded-full mb-3">

                <i class="fas fa-arrow-up text-4xl"></i>

            </div>

            <p class="text-yellow-100 text-base font-bold mb-3">Receitas</p>

            <p class="text-3xl font-bold mb-2"><?php echo formatarMoeda($totalReceitas); ?></p>

            <div class="text-yellow-100 text-xs font-semibold space-y-1">

                <p>✓ Pagas: <?php echo formatarMoeda($receitasPagas); ?></p>

                <p>⏳ Pendentes: <?php echo formatarMoeda($receitasPendentes); ?></p>

            </div>

        </div>

    </div>

    

    <!-- Despesas -->

    <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">

        <div class="h-full flex flex-col items-center justify-center text-center">

            <div class="bg-white/20 p-3 rounded-full mb-3">

                <i class="fas fa-arrow-down text-4xl"></i>

            </div>

            <p class="text-blue-100 text-base font-bold mb-3">Despesas</p>

            <p class="text-3xl font-bold mb-2"><?php echo formatarMoeda($totalDespesas); ?></p>

            <div class="text-blue-100 text-xs font-semibold space-y-1">

                <p>✓ Pagas: <?php echo formatarMoeda($despesasPagas); ?></p>

                <p>⏳ Pendentes: <?php echo formatarMoeda($despesasPendentes); ?></p>

            </div>

        </div>

    </div>

    

    <!-- Saldo Realizado -->

    <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">

        <div class="h-full flex flex-col items-center justify-center text-center">

            <div class="bg-white/20 p-3 rounded-full mb-3">

                <i class="fas fa-wallet text-4xl"></i>

            </div>

            <p class="text-green-100 text-base font-bold mb-3">Saldo Realizado</p>

            <p class="text-3xl font-bold mb-2"><?php echo formatarMoeda($saldoRealizado); ?></p>

            <p class="text-green-100 text-xs font-semibold">Receitas - Despesas (pagas)</p>

        </div>

    </div>

    

    <!-- Saldo Previsto -->

    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">

        <div class="h-full flex flex-col items-center justify-center text-center">

            <div class="bg-white/20 p-3 rounded-full mb-3">

                <i class="fas fa-chart-line text-4xl"></i>

            </div>

            <p class="text-purple-100 text-base font-bold mb-3">Saldo Previsto</p>

            <p class="text-3xl font-bold mb-2"><?php echo formatarMoeda($saldoPrevisto); ?></p>

            <p class="text-purple-100 text-xs font-semibold">Total receitas - Total despesas</p>

        </div>

    </div>

</div>



<!-- Filtros -->

<div class="white-card mb-6">

    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-6 gap-4">

        <!-- MÃªs/Ano -->

        <div>

            <label class="block text-sm font-semibold text-gray-700 mb-2">MÃªs/Ano</label>

            <input 

                type="month" 

                name="mes" 

                value="<?php echo $mesAno; ?>"

                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"

            >

        </div>

        

        <!-- Tipo -->

        <div>

            <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo</label>

            <select name="tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">

                <option value="todos" <?php echo $filtroTipo === 'todos' ? 'selected' : ''; ?>>Todos</option>

                <option value="receita" <?php echo $filtroTipo === 'receita' ? 'selected' : ''; ?>>Receitas</option>

                <option value="despesa" <?php echo $filtroTipo === 'despesa' ? 'selected' : ''; ?>>Despesas</option>

            </select>

        </div>

        

        <!-- Status -->

        <div>

            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>

            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">

                <option value="todos" <?php echo $filtroStatus === 'todos' ? 'selected' : ''; ?>>Todos</option>

                <option value="pago" <?php echo $filtroStatus === 'pago' ? 'selected' : ''; ?>>Pago</option>

                <option value="pendente" <?php echo $filtroStatus === 'pendente' ? 'selected' : ''; ?>>Pendente</option>

                <option value="vencido" <?php echo $filtroStatus === 'vencido' ? 'selected' : ''; ?>>Vencido</option>

            </select>

        </div>

        

        <!-- Categoria -->

        <div>

            <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria</label>

            <select name="categoria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">

                <option value="todas">Todas</option>

                <?php foreach ($categorias as $cat): ?>

                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filtroCategoria === $cat ? 'selected' : ''; ?>>

                        <?php echo htmlspecialchars($cat); ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        

        <!-- Busca -->

        <div>

            <label class="block text-sm font-semibold text-gray-700 mb-2">Buscar</label>

            <input 

                type="text" 

                name="busca" 

                placeholder="DescriÃ§Ã£o..." 

                value="<?php echo htmlspecialchars($busca); ?>"

                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"

            >

        </div>

        

        <!-- BotÃ£o -->

        <div class="flex items-end">

            <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">

                <i class="fas fa-filter"></i> Filtrar

            </button>

        </div>

    </form>

</div>



<!-- Lista de TransaÃ§Ãµes -->

<?php if (empty($transacoes)): ?>

    <div class="white-card text-center py-12">

        <i class="fas fa-receipt text-6xl text-gray-300 mb-4"></i>

        <p class="text-gray-600 text-lg">Nenhuma transaÃ§Ã£o encontrada</p>

        <p class="text-gray-500 text-sm mt-2">Adicione receitas e despesas para controlar seu fluxo de caixa</p>

    </div>

<?php else: ?>

    <div class="white-card overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>

                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tipo</th>

                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Categoria</th>

                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">DescriÃ§Ã£o</th>

                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valor</th>

                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>

                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">AÃ§Ãµes</th>

                </tr>

            </thead>

            <tbody class="divide-y divide-gray-200">

                <?php foreach ($transacoes as $transacao): ?>

                    <?php

                    $isVencida = $transacao['status'] === 'pendente' && strtotime($transacao['data_vencimento']) < strtotime('today');

                    $rowClass = $isVencida ? 'bg-red-50' : '';

                    ?>

                    <tr class="<?php echo $rowClass; ?> hover:bg-gray-50">

                        <td class="px-4 py-3 text-sm">

                            <div>

                                <p class="font-semibold text-gray-900"><?php echo formatarData($transacao['data_vencimento']); ?></p>

                                <?php if ($transacao['status'] === 'pago' && !empty($transacao['data_pagamento'])): ?>

                                    <p class="text-xs text-green-600">Pago: <?php echo formatarData($transacao['data_pagamento']); ?></p>

                                <?php endif; ?>

                            </div>

                        </td>

                        <td class="px-4 py-3">

                            <?php if ($transacao['tipo'] === 'receita'): ?>

                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">

                                    <i class="fas fa-arrow-up"></i> Receita

                                </span>

                            <?php else: ?>

                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">

                                    <i class="fas fa-arrow-down"></i> Despesa

                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="px-4 py-3 text-sm text-gray-900">

                            <?php echo htmlspecialchars($transacao['categoria']); ?>

                        </td>

                        <td class="px-4 py-3">

                            <div>

                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($transacao['descricao']); ?></p>

                                <?php if (!empty($transacao['cliente_nome'])): ?>

                                    <p class="text-xs text-gray-600">Cliente: <?php echo htmlspecialchars($transacao['cliente_nome']); ?></p>

                                <?php endif; ?>

                                <?php if (!empty($transacao['numero_pedido'])): ?>

                                    <p class="text-xs text-purple-600">Pedido: #<?php echo htmlspecialchars($transacao['numero_pedido']); ?></p>

                                <?php endif; ?>

                            </div>

                        </td>

                        <td class="px-4 py-3 text-right">

                            <p class="text-lg font-bold <?php echo $transacao['tipo'] === 'receita' ? 'text-green-600' : 'text-red-600'; ?>">

                                <?php echo formatarMoeda($transacao['valor']); ?>

                            </p>

                        </td>

                        <td class="px-4 py-3 text-center">

                            <?php if ($transacao['status'] === 'pago'): ?>

                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">

                                    <i class="fas fa-check-circle"></i> Pago

                                </span>

                            <?php elseif ($isVencida): ?>

                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">

                                    <i class="fas fa-exclamation-circle"></i> Vencido

                                </span>

                            <?php else: ?>

                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">

                                    <i class="fas fa-clock"></i> Pendente

                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="px-4 py-3">

                            <div class="flex items-center justify-center gap-2">

                                <?php if ($transacao['status'] !== 'pago'): ?>

                                    <form method="POST" action="" class="inline">

                                        <input type="hidden" name="action" value="marcar_pago">

                                        <input type="hidden" name="transacao_id" value="<?php echo $transacao['id']; ?>">

                                        <button type="submit" class="text-green-600 hover:text-green-700" title="Marcar como Pago">

                                            <i class="fas fa-check-circle text-lg"></i>

                                        </button>

                                    </form>

                                <?php endif; ?>

                                

                                <a href="financeiro-form.php?id=<?php echo $transacao['id']; ?>" class="text-blue-600 hover:text-blue-700" title="Editar">

                                    <i class="fas fa-edit text-lg"></i>

                                </a>

                                

                                <form method="POST" action="" onsubmit="return confirmarExclusao('Tem certeza que deseja excluir esta transaÃ§Ã£o?')" class="inline">

                                    <input type="hidden" name="action" value="delete">

                                    <input type="hidden" name="transacao_id" value="<?php echo $transacao['id']; ?>">

                                    <button type="submit" class="text-red-600 hover:text-red-700" title="Excluir">

                                        <i class="fas fa-trash text-lg"></i>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

            <tfoot class="bg-gray-50 font-bold">

                <tr>

                    <td colspan="4" class="px-4 py-3 text-right text-gray-900">TOTAIS:</td>

                    <td class="px-4 py-3 text-right">

                        <p class="text-green-600">+ <?php echo formatarMoeda($totalReceitas); ?></p>

                        <p class="text-red-600">- <?php echo formatarMoeda($totalDespesas); ?></p>

                        <p class="text-lg border-t pt-2 <?php echo $saldoPrevisto >= 0 ? 'text-blue-600' : 'text-red-600'; ?>">

                            = <?php echo formatarMoeda($saldoPrevisto); ?>

                        </p>

                    </td>

                    <td colspan="2"></td>

                </tr>

            </tfoot>

        </table>

    </div>

<?php endif; ?>



<?php require_once 'footer.php'; ?>