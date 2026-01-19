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
$pageTitle = 'Relatórios';
$pageSubtitle = 'Análises e insights do seu negócio';

// Período de análise
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes_atual';
$dataInicio = '';
$dataFim = '';

switch ($periodo) {
    case 'hoje':
        $dataInicio = date('Y-m-d');
        $dataFim = date('Y-m-d');
        break;
    case 'semana':
        $dataInicio = date('Y-m-d', strtotime('monday this week'));
        $dataFim = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes_atual':
        $dataInicio = date('Y-m-01');
        $dataFim = date('Y-m-t');
        break;
    case 'mes_anterior':
        $dataInicio = date('Y-m-01', strtotime('first day of last month'));
        $dataFim = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'ano':
        $dataInicio = date('Y-01-01');
        $dataFim = date('Y-12-31');
        break;
    case 'personalizado':
        $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
        $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
        break;
}

try {
    $db = getDB();
    
    // ============================================
    // MÉTRICAS GERAIS
    // ============================================
    
    // Faturamento do período
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(valor_final), 0) as total 
        FROM pedidos 
        WHERE DATE(data_pedido) BETWEEN ? AND ?
        AND status != 'cancelado'
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $faturamento = $stmt->fetch()['total'];
    
    // Quantidade de pedidos
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM pedidos 
        WHERE DATE(data_pedido) BETWEEN ? AND ?
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $totalPedidos = $stmt->fetch()['total'];
    
    // Ticket médio
    $ticketMedio = $totalPedidos > 0 ? $faturamento / $totalPedidos : 0;
    
    // Lucro líquido do período
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'receita' AND status = 'pago' THEN valor ELSE 0 END), 0) as receitas,
            COALESCE(SUM(CASE WHEN tipo = 'despesa' AND status = 'pago' THEN valor ELSE 0 END), 0) as despesas
        FROM financeiro
        WHERE DATE(data_vencimento) BETWEEN ? AND ?
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $financeiro = $stmt->fetch();
    $lucroLiquido = $financeiro['receitas'] - $financeiro['despesas'];
    
    // Novos clientes no período
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM clientes 
        WHERE DATE(data_cadastro) BETWEEN ? AND ?
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $novosClientes = $stmt->fetch()['total'];
    
    // ============================================
    // VENDAS POR CATEGORIA
    // ============================================
    
    $stmt = $db->prepare("
        SELECT 
            c.nome as categoria,
            c.icone,
            c.cor,
            COUNT(DISTINCT p.id) as quantidade_pedidos,
            SUM(pi.quantidade) as quantidade_produtos,
            SUM(pi.subtotal) as valor_total
        FROM pedidos_itens pi
        INNER JOIN produtos prod ON pi.produto_id = prod.id
        INNER JOIN categorias c ON prod.categoria_id = c.id
        INNER JOIN pedidos p ON pi.pedido_id = p.id
        WHERE DATE(p.data_pedido) BETWEEN ? AND ?
        AND p.status != 'cancelado'
        GROUP BY c.id, c.nome, c.icone, c.cor
        ORDER BY valor_total DESC
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $vendasPorCategoria = $stmt->fetchAll();
    
    // ============================================
    // PRODUTOS MAIS VENDIDOS
    // ============================================
    
    $stmt = $db->prepare("
        SELECT 
            prod.id,
            prod.nome,
            prod.preco_venda,
            c.nome as categoria,
            SUM(pi.quantidade) as quantidade_vendida,
            SUM(pi.subtotal) as valor_total
        FROM pedidos_itens pi
        INNER JOIN produtos prod ON pi.produto_id = prod.id
        INNER JOIN categorias c ON prod.categoria_id = c.id
        INNER JOIN pedidos p ON pi.pedido_id = p.id
        WHERE DATE(p.data_pedido) BETWEEN ? AND ?
        AND p.status != 'cancelado'
        GROUP BY prod.id, prod.nome, prod.preco_venda, c.nome
        ORDER BY quantidade_vendida DESC
        LIMIT 10
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $produtosMaisVendidos = $stmt->fetchAll();
    
    // ============================================
    // TOP CLIENTES
    // ============================================
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.nome,
            c.telefone,
            c.email,
            COUNT(p.id) as quantidade_pedidos,
            SUM(p.valor_final) as valor_total
        FROM clientes c
        INNER JOIN pedidos p ON c.id = p.cliente_id
        WHERE DATE(p.data_pedido) BETWEEN ? AND ?
        AND p.status != 'cancelado'
        GROUP BY c.id, c.nome, c.telefone, c.email
        ORDER BY valor_total DESC
        LIMIT 10
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $topClientes = $stmt->fetchAll();
    
    // ============================================
    // STATUS DOS PEDIDOS
    // ============================================
    
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as quantidade,
            SUM(valor_final) as valor_total
        FROM pedidos
        WHERE DATE(data_pedido) BETWEEN ? AND ?
        GROUP BY status
        ORDER BY quantidade DESC
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $statusPedidos = $stmt->fetchAll();
    
    // ============================================
    // FORMAS DE PAGAMENTO
    // ============================================
    
    $stmt = $db->prepare("
        SELECT 
            forma_pagamento,
            COUNT(*) as quantidade,
            SUM(valor_final) as valor_total
        FROM pedidos
        WHERE DATE(data_pedido) BETWEEN ? AND ?
        AND status != 'cancelado'
        GROUP BY forma_pagamento
        ORDER BY quantidade DESC
    ");
    $stmt->execute([$dataInicio, $dataFim]);
    $formasPagamento = $stmt->fetchAll();
    
    // ============================================
    // EVOLUÇÃO DE VENDAS (últimos 12 meses)
    // ============================================
    
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(data_pedido, '%Y-%m') as mes,
            COUNT(*) as quantidade,
            SUM(valor_final) as valor_total
        FROM pedidos
        WHERE data_pedido >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND status != 'cancelado'
        GROUP BY DATE_FORMAT(data_pedido, '%Y-%m')
        ORDER BY mes ASC
    ");
    $evolucaoVendas = $stmt->fetchAll();
    
    // ============================================
    // PRODUTOS COM ESTOQUE BAIXO
    // ============================================
    
    $stmt = $db->query("
        SELECT 
            p.id,
            p.nome,
            p.estoque_atual,
            p.estoque_minimo,
            c.nome as categoria
        FROM produtos p
        INNER JOIN categorias c ON p.categoria_id = c.id
        WHERE p.ativo = 1 
        AND p.estoque_atual <= p.estoque_minimo
        ORDER BY p.estoque_atual ASC
        LIMIT 10
    ");
    $estoqueBaixo = $stmt->fetchAll();
    
    // ============================================
    // COMPARATIVO COM PERÍODO ANTERIOR
    // ============================================
    
    // Calcular período anterior
    $dias = (strtotime($dataFim) - strtotime($dataInicio)) / (60 * 60 * 24);
    $dataInicioAnterior = date('Y-m-d', strtotime($dataInicio . " -" . ($dias + 1) . " days"));
    $dataFimAnterior = date('Y-m-d', strtotime($dataFim . " -" . ($dias + 1) . " days"));
    
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(valor_final), 0) as total,
            COUNT(*) as pedidos
        FROM pedidos 
        WHERE DATE(data_pedido) BETWEEN ? AND ?
        AND status != 'cancelado'
    ");
    $stmt->execute([$dataInicioAnterior, $dataFimAnterior]);
    $periodoAnterior = $stmt->fetch();
    
    $variacaoFaturamento = 0;
    $variacaoPedidos = 0;
    
    if ($periodoAnterior['total'] > 0) {
        $variacaoFaturamento = (($faturamento - $periodoAnterior['total']) / $periodoAnterior['total']) * 100;
    }
    
    if ($periodoAnterior['pedidos'] > 0) {
        $variacaoPedidos = (($totalPedidos - $periodoAnterior['pedidos']) / $periodoAnterior['pedidos']) * 100;
    }
    
} catch (PDOException $e) {
    $erro = "Erro ao carregar dados: " . $e->getMessage();
}

// Função para obter cor da variação
function getVariacaoCor($valor) {
    if ($valor > 0) return 'text-green-600';
    if ($valor < 0) return 'text-red-600';
    return 'text-gray-600';
}

// Função para obter ícone da variação
function getVariacaoIcone($valor) {
    if ($valor > 0) return '<i class="fas fa-arrow-up"></i>';
    if ($valor < 0) return '<i class="fas fa-arrow-down"></i>';
    return '<i class="fas fa-minus"></i>';
}

require_once 'header.php';
?>

<!-- Seletor de Período -->
<div class="white-card mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Período de Análise</label>
            <select name="periodo" id="periodoSelect" onchange="toggleCustomDates()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="hoje" <?php echo $periodo === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                <option value="semana" <?php echo $periodo === 'semana' ? 'selected' : ''; ?>>Esta Semana</option>
                <option value="mes_atual" <?php echo $periodo === 'mes_atual' ? 'selected' : ''; ?>>Mês Atual</option>
                <option value="mes_anterior" <?php echo $periodo === 'mes_anterior' ? 'selected' : ''; ?>>Mês Anterior</option>
                <option value="ano" <?php echo $periodo === 'ano' ? 'selected' : ''; ?>>Este Ano</option>
                <option value="personalizado" <?php echo $periodo === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
            </select>
        </div>
        
        <div id="customDates" class="md:col-span-2 grid grid-cols-2 gap-4" style="display: <?php echo $periodo === 'personalizado' ? 'grid' : 'none'; ?>;">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Início</label>
                <input type="date" name="data_inicio" value="<?php echo $dataInicio; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Fim</label>
                <input type="date" name="data_fim" value="<?php echo $dataFim; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="w-full bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition font-semibold">
                <i class="fas fa-sync-alt"></i> Atualizar
            </button>
        </div>
    </form>
    
    <div class="mt-4 text-center">
        <p class="text-sm text-gray-600">
            <i class="fas fa-calendar"></i> 
            Período: <strong><?php echo formatarData($dataInicio); ?></strong> até <strong><?php echo formatarData($dataFim); ?></strong>
        </p>
    </div>
</div>

<!-- Cards de Métricas Principais -->
<div class="grid grid-cols-4 gap-4 mb-8" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <!-- Faturamento -->
    <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-dollar-sign text-4xl"></i>
            </div>
            <p class="text-yellow-100 text-base font-bold mb-3">Faturamento</p>
            <p class="text-3xl font-bold mb-2"><?php echo formatarMoeda($faturamento); ?></p>
            <p class="text-yellow-100 text-xs font-semibold flex items-center gap-1">
                <?php echo getVariacaoIcone($variacaoFaturamento); ?>
                <span><?php echo number_format(abs($variacaoFaturamento), 1); ?>%</span>
                vs anterior
            </p>
        </div>
    </div>

    <!-- Pedidos -->
    <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-shopping-bag text-4xl"></i>
            </div>
            <p class="text-blue-100 text-base font-bold mb-3">Total de Pedidos</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalPedidos; ?></p>
            <p class="text-blue-100 text-xs font-semibold flex items-center gap-1">
                <?php echo getVariacaoIcone($variacaoPedidos); ?>
                <span><?php echo number_format(abs($variacaoPedidos), 1); ?>%</span>
                vs anterior
            </p>
        </div>
    </div>

    <!-- Ticket Médio -->
    <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-chart-line text-4xl"></i>
            </div>
            <p class="text-green-100 text-base font-bold mb-3">Ticket Médio</p>
            <p class="text-3xl font-bold mb-2"><?php echo formatarMoeda($ticketMedio); ?></p>
            <p class="text-green-100 text-xs font-semibold">Por Pedido</p>
        </div>
    </div>

    <!-- Lucro Líquido -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-wallet text-4xl"></i>
            </div>
            <p class="text-purple-100 text-base font-bold mb-3">Lucro Líquido</p>
            <p class="text-3xl font-bold mb-2"><?php echo formatarMoeda($lucroLiquido); ?></p>
            <p class="text-purple-100 text-xs font-semibold">
                Margem: <?php echo $faturamento > 0 ? number_format(($lucroLiquido / $faturamento) * 100, 1) : 0; ?>%
            </p>
        </div>
    </div>
</div>

<!-- Gráficos e Análises -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <!-- Vendas por Categoria -->
    <div class="white-card">
        <h3 class="text-xl font-bold text-gray-900 mb-6">
            <i class="fas fa-chart-pie text-purple-600"></i> Vendas por Categoria
        </h3>
        
        <?php if (empty($vendasPorCategoria)): ?>
            <p class="text-gray-500 text-center py-8">Nenhuma venda no período</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php 
                $totalVendas = array_sum(array_column($vendasPorCategoria, 'valor_total'));
                foreach ($vendasPorCategoria as $venda): 
                    $percentual = $totalVendas > 0 ? ($venda['valor_total'] / $totalVendas) * 100 : 0;
                ?>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-700 font-medium">
                                <?php echo $venda['icone']; ?> <?php echo htmlspecialchars($venda['categoria']); ?>
                            </span>
                            <div class="text-right">
                                <span class="text-gray-900 font-bold"><?php echo formatarMoeda($venda['valor_total']); ?></span>
                                <span class="text-gray-600 text-sm ml-2">(<?php echo number_format($percentual, 1); ?>%)</span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div 
                                class="bg-gradient-to-r from-purple-500 to-pink-500 h-3 rounded-full transition-all"
                                style="width: <?php echo $percentual; ?>%"
                            ></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600 mt-1">
                            <span><?php echo $venda['quantidade_produtos']; ?> produtos</span>
                            <span><?php echo $venda['quantidade_pedidos']; ?> pedidos</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Status dos Pedidos -->
    <div class="white-card">
        <h3 class="text-xl font-bold text-gray-900 mb-6">
            <i class="fas fa-tasks text-blue-600"></i> Status dos Pedidos
        </h3>
        
        <?php if (empty($statusPedidos)): ?>
            <p class="text-gray-500 text-center py-8">Nenhum pedido no período</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php 
                $statusLabels = [
                    'aguardando' => ['label' => 'Aguardando', 'color' => 'from-yellow-400 to-orange-400'],
                    'em_producao' => ['label' => 'Em Produção', 'color' => 'from-blue-400 to-cyan-400'],
                    'pronto' => ['label' => 'Pronto', 'color' => 'from-green-400 to-emerald-400'],
                    'entregue' => ['label' => 'Entregue', 'color' => 'from-gray-400 to-gray-500'],
                    'cancelado' => ['label' => 'Cancelado', 'color' => 'from-red-400 to-pink-400'],
                ];
                
                foreach ($statusPedidos as $status): 
                    $percentual = $totalPedidos > 0 ? ($status['quantidade'] / $totalPedidos) * 100 : 0;
                    $statusInfo = $statusLabels[$status['status']] ?? ['label' => $status['status'], 'color' => 'from-gray-400 to-gray-500'];
                ?>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-700 font-medium"><?php echo $statusInfo['label']; ?></span>
                            <div class="text-right">
                                <span class="text-gray-900 font-bold"><?php echo $status['quantidade']; ?> pedidos</span>
                                <span class="text-gray-600 text-sm ml-2">(<?php echo number_format($percentual, 1); ?>%)</span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div 
                                class="bg-gradient-to-r <?php echo $statusInfo['color']; ?> h-3 rounded-full transition-all"
                                style="width: <?php echo $percentual; ?>%"
                            ></div>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            Valor: <?php echo formatarMoeda($status['valor_total']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Produtos Mais Vendidos e Top Clientes -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <!-- Produtos Mais Vendidos -->
    <div class="white-card">
        <h3 class="text-xl font-bold text-gray-900 mb-6">
            <i class="fas fa-trophy text-yellow-600"></i> Top 10 Produtos Mais Vendidos
        </h3>
        
        <?php if (empty($produtosMaisVendidos)): ?>
            <p class="text-gray-500 text-center py-8">Nenhum produto vendido no período</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($produtosMaisVendidos as $index => $produto): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($produto['nome']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($produto['categoria']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-purple-600"><?php echo formatarMoeda($produto['valor_total']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo $produto['quantidade_vendida']; ?> unidades</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Clientes -->
    <div class="white-card">
        <h3 class="text-xl font-bold text-gray-900 mb-6">
            <i class="fas fa-crown text-yellow-600"></i> Top 10 Clientes
        </h3>
        
        <?php if (empty($topClientes)): ?>
            <p class="text-gray-500 text-center py-8">Nenhum cliente no período</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($topClientes as $index => $cliente): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-cyan-400 rounded-full flex items-center justify-center text-white font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($cliente['nome']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo formatarTelefone($cliente['telefone']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-blue-600"><?php echo formatarMoeda($cliente['valor_total']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo $cliente['quantidade_pedidos']; ?> pedidos</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Formas de Pagamento e Alertas de Estoque -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <!-- Formas de Pagamento -->
    <div class="white-card">
        <h3 class="text-xl font-bold text-gray-900 mb-6">
            <i class="fas fa-credit-card text-green-600"></i> Formas de Pagamento
        </h3>
        
        <?php if (empty($formasPagamento)): ?>
            <p class="text-gray-500 text-center py-8">Nenhum pagamento no período</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php 
                $totalPagamentos = array_sum(array_column($formasPagamento, 'valor_total'));
                foreach ($formasPagamento as $forma): 
                    $percentual = $totalPagamentos > 0 ? ($forma['valor_total'] / $totalPagamentos) * 100 : 0;
                ?>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-700 font-medium capitalize">
                                <i class="fas fa-money-bill-wave text-green-500"></i> 
                                <?php echo ucfirst($forma['forma_pagamento']); ?>
                            </span>
                            <div class="text-right">
                                <span class="text-gray-900 font-bold"><?php echo formatarMoeda($forma['valor_total']); ?></span>
                                <span class="text-gray-600 text-sm ml-2">(<?php echo number_format($percentual, 1); ?>%)</span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div 
                                class="bg-gradient-to-r from-green-500 to-emerald-500 h-3 rounded-full transition-all"
                                style="width: <?php echo $percentual; ?>%"
                            ></div>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            <?php echo $forma['quantidade']; ?> transações
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Alertas de Estoque -->
    <div class="white-card">
        <h3 class="text-xl font-bold text-gray-900 mb-6">
            <i class="fas fa-exclamation-triangle text-red-600"></i> Alertas de Estoque Baixo
        </h3>
        
        <?php if (empty($estoqueBaixo)): ?>
            <p class="text-green-600 text-center py-8">
                <i class="fas fa-check-circle text-4xl mb-2"></i><br>
                Todos os produtos estão com estoque adequado!
            </p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($estoqueBaixo as $produto): ?>
                    <div class="flex items-center justify-between p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
                        <div>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($produto['nome']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($produto['categoria']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-red-600 font-bold"><?php echo $produto['estoque_atual']; ?> un.</p>
                            <p class="text-xs text-gray-600">Mín: <?php echo $produto['estoque_minimo']; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <a href="produtos.php?estoque=baixo" class="text-purple-600 hover:text-purple-700 font-semibold text-sm">
                    <i class="fas fa-arrow-right"></i> Ver todos os produtos com estoque baixo
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resumo Adicional -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <!-- Card de Novos Clientes -->
    <div class="white-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-gray-600 text-sm">Novos Clientes</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $novosClientes; ?></p>
            </div>
            <div class="bg-blue-100 p-3 rounded-lg">
                <i class="fas fa-user-plus text-blue-600 text-2xl"></i>
            </div>
        </div>
        <p class="text-xs text-gray-600">Cadastrados no período</p>
    </div>

    <!-- Card de Taxa de Conversão -->
    <div class="white-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-gray-600 text-sm">Pedidos Concluídos</p>
                <?php
                $pedidosConcluidos = 0;
                foreach ($statusPedidos as $status) {
                    if ($status['status'] === 'entregue') {
                        $pedidosConcluidos = $status['quantidade'];
                        break;
                    }
                }
                $taxaConclusao = $totalPedidos > 0 ? ($pedidosConcluidos / $totalPedidos) * 100 : 0;
                ?>
                <p class="text-3xl font-bold text-green-600"><?php echo number_format($taxaConclusao, 1); ?>%</p>
            </div>
            <div class="bg-green-100 p-3 rounded-lg">
                <i class="fas fa-check-double text-green-600 text-2xl"></i>
            </div>
        </div>
        <p class="text-xs text-gray-600"><?php echo $pedidosConcluidos; ?> de <?php echo $totalPedidos; ?> pedidos</p>
    </div>

    <!-- Card de Produtos Vendidos -->
    <div class="white-card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-gray-600 text-sm">Produtos Diferentes</p>
                <?php
                $totalProdutosDiferentes = count($produtosMaisVendidos);
                ?>
                <p class="text-3xl font-bold text-purple-600"><?php echo $totalProdutosDiferentes; ?></p>
            </div>
            <div class="bg-purple-100 p-3 rounded-lg">
                <i class="fas fa-boxes text-purple-600 text-2xl"></i>
            </div>
        </div>
        <p class="text-xs text-gray-600">Vendidos no período</p>
    </div>
</div>

<script>
function toggleCustomDates() {
    const periodo = document.getElementById('periodoSelect').value;
    const customDates = document.getElementById('customDates');
    
    if (periodo === 'personalizado') {
        customDates.style.display = 'grid';
    } else {
        customDates.style.display = 'none';
    }
}

// Imprimir relatório
function imprimirRelatorio() {
    window.print();
}
</script>

<style>
@media print {
    .sidebar, header, footer, button, .no-print {
        display: none !important;
    }
    
    body {
        background: white !important;
    }
    
    .white-card {
        box-shadow: none !important;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
}
</style>

<?php require_once 'footer.php'; ?>