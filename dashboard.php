<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'config_multitenant.php'; // MULTI-TENANT

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

define('INCLUDED', true);
$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral do seu negócio';

// MULTI-TENANT: Obter empresa do usuário logado
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
    
    // MULTI-TENANT: Total de pedidos ativos DA EMPRESA
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM pedidos 
        WHERE status != 'cancelado' AND status != 'entregue' AND empresa_id = ?
    ");
    $stmt->execute([$empresaId]);
    $pedidosAtivos = $stmt->fetch()['total'];
    
    // MULTI-TENANT: Faturamento do mês DA EMPRESA
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
    
    // MULTI-TENANT: Lucro líquido do mês DA EMPRESA
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
    
    // MULTI-TENANT: Total de produtos DA EMPRESA
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalProdutos = $stmt->fetch()['total'];
    
    // MULTI-TENANT: Produtos com estoque baixo DA EMPRESA
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM produtos 
        WHERE estoque_atual <= estoque_minimo AND ativo = 1 AND empresa_id = ?
    ");
    $stmt->execute([$empresaId]);
    $produtosEstoqueBaixo = $stmt->fetch()['total'];
    
    // MULTI-TENANT: Pedidos recentes DA EMPRESA
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
    
    // MULTI-TENANT: Produtos mais vendidos DA EMPRESA
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
    
    // MULTI-TENANT: Clientes top DA EMPRESA
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

// Mostrar erro se houver
if (isset($erro)) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">';
    echo '<p class="font-medium">⚠️ ' . htmlspecialchars($erro) . '</p>';
    echo '</div>';
}

// Função para obter cor do status
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

<!-- Cards de Métricas - FORMATO 4x1 QUADRADO -->
<div class="grid grid-cols-4 gap-4 mb-6" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    
    <!-- Card 1: Faturamento (Roxo/Rosa) -->
    <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-dollar-sign text-4xl"></i>
            </div>
            <p class="text-yellow-100 text-base font-bold mb-3">Faturamento Mês</p>
            <p class="text-4xl font-bold mb-2"><?php echo formatarMoeda($faturamentoMes); ?></p>
            <p class="text-yellow-100 text-base font-semibold">
                <i class="fas fa-arrow-up"></i> +12% vs anterior
            </p>
        </div>
    </div>

    <!-- Card 2: Pedidos Ativos (Azul/Cyan) -->
    <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-shopping-bag text-4xl"></i>
            </div>
            <p class="text-blue-100 text-base font-bold mb-3">Pedidos Ativos</p>
            <p class="text-4xl font-bold mb-2"><?php echo $pedidosAtivos; ?></p>
            <p class="text-blue-100 text-base font-semibold">Em andamento</p>
        </div>
    </div>

    <!-- Card 3: Lucro Líquido (Verde/Esmeralda) -->
    <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-chart-line text-4xl"></i>
            </div>
            <p class="text-green-100 text-base font-bold mb-3">Lucro Líquido</p>
            <p class="text-4xl font-bold mb-2"><?php echo formatarMoeda($lucroLiquido); ?></p>
            <p class="text-green-100 text-base font-semibold">
                Margem: <?php echo $faturamentoMes > 0 ? number_format(($lucroLiquido / $faturamentoMes) * 100, 1) : 0; ?>%
            </p>
        </div>
    </div>

    <!-- Card 4: Produtos (Amarelo/Laranja) -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5  text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-box text-4xl"></i>
            </div>
            <p class="text-purple-100 text-base font-bold mb-3">Produtos</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalProdutos; ?></p>
            <p class="text-purple-100 text-base font-semibold">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $produtosEstoqueBaixo; ?> estoque baixo
            </p>
        </div>
    </div>
</div>

<!-- Seção Pedidos Recentes e Produtos Populares -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <!-- Pedidos Recentes -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900">Pedidos Recentes</h3>
            <a href="pedidos.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="space-y-4">
            <?php if (empty($pedidosRecentes)): ?>
                <p class="text-gray-500 text-center py-8">Nenhum pedido encontrado</p>
            <?php else: ?>
                <?php foreach ($pedidosRecentes as $pedido): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($pedido['cliente_nome']); ?></p>
                            <p class="text-sm text-gray-600">Pedido #<?php echo $pedido['numero_pedido']; ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-gray-900"><?php echo formatarMoeda($pedido['valor_final']); ?></p>
                            <?php echo getStatusBadge($pedido['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Produtos Populares -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900">Produtos Populares</h3>
            <a href="produtos.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="space-y-4">
            <?php if (empty($produtosPopulares)): ?>
                <p class="text-gray-500 text-center py-8">Nenhum produto encontrado</p>
            <?php else: ?>
                <?php foreach ($produtosPopulares as $produto): ?>
                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-box text-purple-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($produto['nome']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($produto['categoria']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-purple-600"><?php echo formatarMoeda($produto['preco_venda']); ?></p>
                            <p class="text-xs text-gray-600">Estoque: <?php echo $produto['estoque_atual']; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top Clientes -->
<div class="card p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-xl font-bold text-gray-900">Top 5 Clientes</h3>
        <a href="clientes.php" class="text-purple-600 hover:text-purple-700 text-sm font-medium">
            Ver todos <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <div class="space-y-4">
        <?php if (empty($topClientes)): ?>
            <p class="text-gray-500 text-center py-8">Nenhum cliente encontrado</p>
        <?php else: ?>
            <?php foreach ($topClientes as $index => $cliente): ?>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white font-bold">
                            <?php echo $index + 1; ?>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($cliente['nome']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo $cliente['quantidade_pedidos']; ?> pedidos</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-purple-600"><?php echo formatarMoeda($cliente['total_compras']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>