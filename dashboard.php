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
$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral do seu negócio';

$empresaId = getEmpresaId();

// Buscar estatísticas
$pedidosAtivos = 0;
$faturamentoMes = 0;
$lucroLiquido = 0;
$totalProdutos = 0;
$produtosEstoqueBaixo = 0;
$pedidosRecentes = [];
$produtosPopulares = [];
$topClientes = [];

try {
    $db = getDB();
    
    // Total de pedidos ativos
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM pedidos 
        WHERE status != 'cancelado' AND status != 'entregue' AND empresa_id = ?
    ");
    $stmt->execute([$empresaId]);
    $pedidosAtivos = $stmt->fetch()['total'];
    
    // Faturamento do mês
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(valor_final), 0) as total 
        FROM pedidos 
        WHERE MONTH(data_pedido) = MONTH(CURRENT_DATE()) 
        AND YEAR(data_pedido) = YEAR(CURRENT_DATE())
        AND status != 'cancelado'
        AND empresa_id = ?
    ");
    $stmt->execute([$empresaId]);
    $faturamentoMes = $stmt->fetch()['total'];
    
    // Lucro líquido do mês
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) as receitas,
            COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) as despesas
        FROM financeiro
        WHERE MONTH(data_vencimento) = MONTH(CURRENT_DATE())
        AND YEAR(data_vencimento) = YEAR(CURRENT_DATE())
        AND empresa_id = ?
    ");
    $stmt->execute([$empresaId]);
    $financeiro = $stmt->fetch();
    $lucroLiquido = $financeiro['receitas'] - $financeiro['despesas'];
    
    // Total de produtos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalProdutos = $stmt->fetch()['total'];
    
    // Produtos com estoque baixo
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM produtos 
        WHERE estoque_atual <= estoque_minimo AND ativo = 1 AND empresa_id = ?
    ");
    $stmt->execute([$empresaId]);
    $produtosEstoqueBaixo = $stmt->fetch()['total'];
    
    // Pedidos recentes
    $stmt = $db->prepare("
        SELECT p.*, c.nome as cliente_nome, c.telefone as cliente_telefone
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        WHERE p.empresa_id = ?
        ORDER BY p.data_pedido DESC
        LIMIT 5
    ");
    $stmt->execute([$empresaId]);
    $pedidosRecentes = $stmt->fetchAll();
    
    // Produtos mais vendidos
    $stmt = $db->prepare("
        SELECT 
            prod.id,
            prod.nome,
            prod.preco_venda,
            prod.estoque_atual,
            cat.nome as categoria,
            COUNT(pi.id) as total_vendas
        FROM produtos prod
        LEFT JOIN pedidos_itens pi ON prod.id = pi.produto_id
        LEFT JOIN categorias cat ON prod.categoria_id = cat.id
        WHERE prod.ativo = 1 AND prod.empresa_id = ?
        GROUP BY prod.id
        ORDER BY total_vendas DESC
        LIMIT 4
    ");
    $stmt->execute([$empresaId]);
    $produtosPopulares = $stmt->fetchAll();
    
    // Clientes top
    $stmt = $db->prepare("
        SELECT * FROM clientes 
        WHERE ativo = 1 AND empresa_id = ?
        ORDER BY total_compras DESC 
        LIMIT 5
    ");
    $stmt->execute([$empresaId]);
    $topClientes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $erro = "Erro ao carregar dados: " . $e->getMessage();
    error_log("Erro no dashboard: " . $e->getMessage());
}

require_once 'header.php';

if (isset($erro)) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">';
    echo '<p class="font-medium">⚠️ ' . htmlspecialchars($erro) . '</p>';
    echo '</div>';
}

function getStatusBadge($status) {
    $badges = [
        'aguardando' => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">Aguardando</span>',
        'em_producao' => '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">Em Produção</span>',
        'pronto' => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Pronto</span>',
        'entregue' => '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-semibold">Entregue</span>',
        'cancelado' => '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">Cancelado</span>',
    ];
    return $badges[$status] ?? $status;
}
?>

<style>
/* Responsivo Mobile - Cards de Estatísticas */
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
    
    /* Seções de pedidos e produtos em coluna única */
    .content-grid {
        grid-template-columns: 1fr !important;
    }
    
    /* Cards de conteúdo mais compactos */
    .card {
        padding: 1rem !important;
    }
    
    .card h3 {
        font-size: 1rem !important;
        margin-bottom: 1rem !important;
    }
    
    /* Itens de lista mais compactos */
    .list-item {
        padding: 0.75rem !important;
        font-size: 0.875rem !important;
    }
    
    /* Top clientes em layout mobile */
    .top-cliente-item {
        flex-direction: column !important;
        gap: 0.5rem !important;
        align-items: flex-start !important;
    }
    
    .top-cliente-item .cliente-info {
        width: 100% !important;
    }
    
    .top-cliente-item .cliente-valor {
        width: 100% !important;
        text-align: left !important;
        padding-left: 2.5rem !important;
    }
}

@media (max-width: 480px) {
    .stat-card .stat-value {
        font-size: 1.25rem !important;
    }
    
    .stat-card .stat-title {
        font-size: 0.65rem !important;
    }
}
</style>

<!-- Cards de Métricas - RESPONSIVO -->
<div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    
    <!-- Card 1: Faturamento -->
    <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-dollar-sign text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-yellow-100 text-sm md:text-base font-bold mb-2">Faturamento Mês</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo formatarMoeda($faturamentoMes); ?></p>
            <p class="stat-subtitle text-yellow-100 text-xs md:text-base font-semibold">
                <i class="fas fa-arrow-up"></i> +12% vs anterior
            </p>
        </div>
    </div>

    <!-- Card 2: Pedidos Ativos -->
    <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-shopping-bag text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-blue-100 text-sm md:text-base font-bold mb-2">Pedidos Ativos</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $pedidosAtivos; ?></p>
            <p class="stat-subtitle text-blue-100 text-xs md:text-base font-semibold">Em andamento</p>
        </div>
    </div>

    <!-- Card 3: Lucro Líquido -->
    <div class="stat-card bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-chart-line text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-green-100 text-sm md:text-base font-bold mb-2">Lucro Líquido</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo formatarMoeda($lucroLiquido); ?></p>
            <p class="stat-subtitle text-green-100 text-xs md:text-base font-semibold">
                Margem: <?php echo $faturamentoMes > 0 ? number_format(($lucroLiquido / $faturamentoMes) * 100, 1) : 0; ?>%
            </p>
        </div>
    </div>

    <!-- Card 4: Produtos -->
    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-box text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-purple-100 text-sm md:text-base font-bold mb-2">Produtos</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalProdutos; ?></p>
            <p class="stat-subtitle text-purple-100 text-xs md:text-base font-semibold">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $produtosEstoqueBaixo; ?> estoque baixo
            </p>
        </div>
    </div>
</div>

<!-- Seção Pedidos Recentes e Produtos Populares -->
<div class="content-grid grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
    
    <!-- Pedidos Recentes -->
    <div class="card p-4 md:p-6 bg-white rounded-xl shadow-md">
        <div class="flex items-center justify-between mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold text-gray-900">Pedidos Recentes</h3>
            <a href="pedidos.php" class="text-purple-600 hover:text-purple-700 text-xs md:text-sm font-medium">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="space-y-3 md:space-y-4">
            <?php if (empty($pedidosRecentes)): ?>
                <p class="text-gray-500 text-center py-6 md:py-8 text-sm md:text-base">Nenhum pedido encontrado</p>
            <?php else: ?>
                <?php foreach ($pedidosRecentes as $pedido): ?>
                    <div class="list-item flex flex-col md:flex-row md:items-center md:justify-between p-3 md:p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition gap-2">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900 text-sm md:text-base"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></p>
                            <p class="text-xs md:text-sm text-gray-600">Pedido #<?php echo $pedido['numero_pedido']; ?></p>
                        </div>
                        <div class="text-left md:text-right">
                            <p class="font-bold text-gray-900 text-sm md:text-base"><?php echo formatarMoeda($pedido['valor_final']); ?></p>
                            <?php echo getStatusBadge($pedido['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Produtos Populares -->
    <div class="card p-4 md:p-6 bg-white rounded-xl shadow-md">
        <div class="flex items-center justify-between mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold text-gray-900">Produtos Populares</h3>
            <a href="produtos.php" class="text-purple-600 hover:text-purple-700 text-xs md:text-sm font-medium">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="space-y-3 md:space-y-4">
            <?php if (empty($produtosPopulares)): ?>
                <p class="text-gray-500 text-center py-6 md:py-8 text-sm md:text-base">Nenhum produto encontrado</p>
            <?php else: ?>
                <?php foreach ($produtosPopulares as $produto): ?>
                    <div class="list-item flex items-center gap-3 md:gap-4 p-3 md:p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                        <div class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-box text-purple-600 text-lg md:text-xl"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 text-sm md:text-base truncate"><?php echo htmlspecialchars($produto['nome']); ?></p>
                            <p class="text-xs md:text-sm text-gray-600 truncate"><?php echo htmlspecialchars($produto['categoria']); ?></p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="font-bold text-purple-600 text-sm md:text-base"><?php echo formatarMoeda($produto['preco_venda']); ?></p>
                            <p class="text-xs text-gray-600">Est: <?php echo $produto['estoque_atual']; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top Clientes -->
<div class="card p-4 md:p-6 bg-white rounded-xl shadow-md">
    <div class="flex items-center justify-between mb-4 md:mb-6">
        <h3 class="text-lg md:text-xl font-bold text-gray-900">Top 5 Clientes</h3>
        <a href="clientes.php" class="text-purple-600 hover:text-purple-700 text-xs md:text-sm font-medium">
            Ver todos <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <div class="space-y-3 md:space-y-4">
        <?php if (empty($topClientes)): ?>
            <p class="text-gray-500 text-center py-6 md:py-8 text-sm md:text-base">Nenhum cliente encontrado</p>
        <?php else: ?>
            <?php foreach ($topClientes as $index => $cliente): ?>
                <div class="top-cliente-item flex items-center justify-between p-3 md:p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                    <div class="cliente-info flex items-center gap-3 md:gap-4 flex-1">
                        <div class="w-8 h-8 md:w-10 md:h-10 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white font-bold text-sm md:text-base flex-shrink-0">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 text-sm md:text-base truncate"><?php echo htmlspecialchars($cliente['nome']); ?></p>
                            <p class="text-xs md:text-sm text-gray-600"><?php echo $cliente['quantidade_pedidos']; ?> pedidos</p>
                        </div>
                    </div>
                    <div class="cliente-valor text-right flex-shrink-0">
                        <p class="text-base md:text-lg font-bold text-purple-600"><?php echo formatarMoeda($cliente['total_compras']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>