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
$pageTitle = 'Pedidos';
$pageSubtitle = 'Gerencie todos os seus pedidos';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    // Excluir pedido
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['pedido_id'])) {
        try {
            validarAcessoEmpresa('pedidos', $_POST['pedido_id']);
            $stmt = $db->prepare("DELETE FROM pedidos WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$_POST['pedido_id'], $empresaId]);
            
            logActivity('Pedido excluído', 'pedidos', $_POST['pedido_id']);
            $_SESSION['success'] = 'Pedido excluído com sucesso!';
            header('Location: pedidos.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao excluir pedido: ' . $e->getMessage();
        }
    }
    
    // Mudar status do pedido
    if (isset($_POST['action']) && $_POST['action'] === 'change_status' && isset($_POST['pedido_id']) && isset($_POST['status'])) {
        try {
            validarAcessoEmpresa('pedidos', $_POST['pedido_id']);
            $stmt = $db->prepare("UPDATE pedidos SET status = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$_POST['status'], $_POST['pedido_id'], $empresaId]);
            
            if ($_POST['status'] === 'entregue') {
                $stmt = $db->prepare("UPDATE pedidos SET data_entrega_realizada = NOW() WHERE id = ?");
                $stmt->execute([$_POST['pedido_id']]);
            }
            
            logActivity('Status do pedido alterado', 'pedidos', $_POST['pedido_id'], "Novo status: {$_POST['status']}");
            $_SESSION['success'] = 'Status atualizado com sucesso!';
            header('Location: pedidos.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao atualizar status: ' . $e->getMessage();
        }
    }
}

// Filtros e busca
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : 'todos';
$filtroPagamento = isset($_GET['pagamento']) ? $_GET['pagamento'] : 'todos';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$ordenacao = isset($_GET['ordem']) ? $_GET['ordem'] : 'data_desc';

// Construir query
$where = ["1=1", "p.empresa_id = ?"];
$params = [$empresaId];

if ($filtroStatus !== 'todos') {
    $where[] = "p.status = ?";
    $params[] = $filtroStatus;
}

if ($filtroPagamento !== 'todos') {
    $where[] = "p.status_pagamento = ?";
    $params[] = $filtroPagamento;
}

if (!empty($busca)) {
    $where[] = "(c.nome LIKE ? OR p.numero_pedido LIKE ? OR p.observacoes LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Ordenação
$orderBy = "p.data_pedido DESC";
switch ($ordenacao) {
    case 'data_asc':
        $orderBy = "p.data_pedido ASC";
        break;
    case 'valor_desc':
        $orderBy = "p.valor_final DESC";
        break;
    case 'valor_asc':
        $orderBy = "p.valor_final ASC";
        break;
    case 'entrega':
        $orderBy = "p.data_entrega ASC";
        break;
}

// Paginação
$itensPorPagina = 15;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Buscar pedidos
$pedidos = [];
$totalPedidos = 0;

try {
    $db = getDB();
    
    // Contar total
    $sql = "SELECT COUNT(*) as total FROM pedidos p 
            INNER JOIN clientes c ON p.cliente_id = c.id 
            WHERE " . implode(" AND ", $where);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $totalPedidos = $stmt->fetch()['total'];
    
    // Buscar pedidos
    $sql = "SELECT p.*, c.nome as cliente_nome, c.telefone as cliente_telefone,
            (SELECT COUNT(*) FROM pedidos_itens WHERE pedido_id = p.id) as total_itens
            FROM pedidos p 
            INNER JOIN clientes c ON p.cliente_id = c.id 
            WHERE " . implode(" AND ", $where) . "
            ORDER BY $orderBy
            LIMIT $itensPorPagina OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $erro = "Erro ao carregar pedidos: " . $e->getMessage();
}

// Calcular paginação
$totalPaginas = ceil($totalPedidos / $itensPorPagina);

// Função para obter badge de status
function getStatusBadge($status) {
    $badges = [
        'aguardando' => '<span class="badge badge-yellow"><i class="fas fa-clock"></i> Aguardando</span>',
        'em_producao' => '<span class="badge badge-blue"><i class="fas fa-tools"></i> Em Produção</span>',
        'pronto' => '<span class="badge badge-green"><i class="fas fa-check"></i> Pronto</span>',
        'entregue' => '<span class="badge badge-gray"><i class="fas fa-check-double"></i> Entregue</span>',
        'cancelado' => '<span class="badge badge-red"><i class="fas fa-times"></i> Cancelado</span>',
    ];
    return $badges[$status] ?? $status;
}

function getStatusPagamentoBadge($status) {
    $badges = [
        'pendente' => '<span class="badge badge-red"><i class="fas fa-exclamation-circle"></i> Pendente</span>',
        'pago' => '<span class="badge badge-green"><i class="fas fa-check-circle"></i> Pago</span>',
        'parcial' => '<span class="badge badge-yellow"><i class="fas fa-minus-circle"></i> Parcial</span>',
    ];
    return $badges[$status] ?? $status;
}

require_once 'header.php';

// Mostrar mensagens
if (isset($_SESSION['success'])) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 md:p-4 mb-4 md:mb-6 rounded alert-auto-close">';
    echo '<p class="font-medium text-sm md:text-base"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</p>';
    echo '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 md:p-4 mb-4 md:mb-6 rounded alert-auto-close">';
    echo '<p class="font-medium text-sm md:text-base"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</p>';
    echo '</div>';
}
?>

<style>
/* Responsivo Mobile - Pedidos */
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
    
    /* Filtros em coluna no mobile */
    .filters-grid {
        grid-template-columns: 1fr !important;
    }
    
    /* Cards de pedidos mais compactos */
    .pedido-card {
        padding: 1rem !important;
    }
    
    .pedido-card .pedido-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }
    
    .pedido-card .pedido-info-grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }
    
    .pedido-card .pedido-actions {
        flex-direction: column !important;
        width: 100% !important;
        gap: 0.5rem !important;
    }
    
    .pedido-card .pedido-actions .btn-group {
        width: 100% !important;
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 0.25rem !important;
    }
    
    .pedido-card .pedido-valor {
        text-align: left !important;
        width: 100% !important;
        padding-top: 0.5rem !important;
        border-top: 1px solid #e5e7eb !important;
    }
}

@media (max-width: 480px) {
    .stat-card .stat-value {
        font-size: 1.25rem !important;
    }
}
</style>

<!-- Cabeçalho e botão novo pedido -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-4 mb-4 md:mb-6">

    <a href="pedido-form.php" class="btn btn-primary w-full md:w-auto text-center">
        <i class="fas fa-plus"></i> Novo Pedido
    </a>
</div>

<!-- Cards de Estatísticas Rápidas -->
<div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 md:mb-6">
    <?php
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE status = 'aguardando' AND empresa_id = ?");
        $stmt->execute([$empresaId]);
        $aguardando = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE status = 'em_producao' AND empresa_id = ?");
        $stmt->execute([$empresaId]);
        $emProducao = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE status = 'pronto' AND empresa_id = ?");
        $stmt->execute([$empresaId]);
        $pronto = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_entrega) = CURDATE() AND empresa_id = ?");
        $stmt->execute([$empresaId]);
        $entregueHoje = $stmt->fetch()['total'];
    } catch (PDOException $e) {
        $aguardando = $emProducao = $pronto = $entregueHoje = 0;
    }
    ?>
    
    <!-- Aguardando -->
    <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-clock text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-yellow-100 text-sm md:text-base font-bold mb-2">Aguardando</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $aguardando; ?></p>
            <p class="stat-subtitle text-yellow-100 text-xs md:text-base font-semibold">Pedidos</p>
        </div>
    </div>
    
    <!-- Em Produção -->
    <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-tools text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-blue-100 text-sm md:text-base font-bold mb-2">Em Produção</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $emProducao; ?></p>
            <p class="stat-subtitle text-blue-100 text-xs md:text-base font-semibold">Produzindo</p>
        </div>
    </div>
    
    <!-- Pronto -->
    <div class="stat-card bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-check text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-green-100 text-sm md:text-base font-bold mb-2">Pronto</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $pronto; ?></p>
            <p class="stat-subtitle text-green-100 text-xs md:text-base font-semibold">Para Entregar</p>
        </div>
    </div>
    
    <!-- Para Entregar Hoje -->
    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="flex flex-col items-center justify-center text-center h-full">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-calendar-day text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-purple-100 text-sm md:text-base font-bold mb-2">Entregar Hoje</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $entregueHoje; ?></p>
            <p class="stat-subtitle text-purple-100 text-xs md:text-base font-semibold">Urgente</p>
        </div>
    </div>
</div>

<!-- Filtros e Busca -->
<div class="white-card mb-4 md:mb-6 p-3 md:p-4">
    <form method="GET" action="" class="filters-grid grid grid-cols-1 md:grid-cols-5 gap-3 md:gap-4">
        <!-- Busca -->
        <div class="md:col-span-2">
            <input 
                type="text" 
                name="busca" 
                placeholder="Buscar por cliente, nº pedido..." 
                value="<?php echo htmlspecialchars($busca); ?>"
                class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
            >
        </div>
        
        <!-- Filtro Status -->
        <div>
            <select name="status" class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                <option value="todos" <?php echo $filtroStatus === 'todos' ? 'selected' : ''; ?>>Todos os Status</option>
                <option value="aguardando" <?php echo $filtroStatus === 'aguardando' ? 'selected' : ''; ?>>Aguardando</option>
                <option value="em_producao" <?php echo $filtroStatus === 'em_producao' ? 'selected' : ''; ?>>Em Produção</option>
                <option value="pronto" <?php echo $filtroStatus === 'pronto' ? 'selected' : ''; ?>>Pronto</option>
                <option value="entregue" <?php echo $filtroStatus === 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                <option value="cancelado" <?php echo $filtroStatus === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
            </select>
        </div>
        
        <!-- Filtro Pagamento -->
        <div>
            <select name="pagamento" class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                <option value="todos" <?php echo $filtroPagamento === 'todos' ? 'selected' : ''; ?>>Todos Pagamentos</option>
                <option value="pago" <?php echo $filtroPagamento === 'pago' ? 'selected' : ''; ?>>Pago</option>
                <option value="pendente" <?php echo $filtroPagamento === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                <option value="parcial" <?php echo $filtroPagamento === 'parcial' ? 'selected' : ''; ?>>Parcial</option>
            </select>
        </div>
        
        <!-- Botão Filtrar -->
        <div>
            <button type="submit" class="w-full bg-purple-600 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm md:text-base">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
    </form>
    
    <!-- Ordenação -->
    <div class="mt-3 md:mt-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-2 md:gap-3">
        <div class="flex items-center gap-2 w-full md:w-auto">
            <span class="text-xs md:text-sm text-gray-600">Ordenar:</span>
            <select name="ordem" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['ordem' => ''])); ?>&ordem=' + this.value" class="flex-1 md:flex-none px-2 md:px-3 py-1 border border-gray-300 rounded-lg text-xs md:text-sm">
                <option value="data_desc" <?php echo $ordenacao === 'data_desc' ? 'selected' : ''; ?>>Mais Recentes</option>
                <option value="data_asc" <?php echo $ordenacao === 'data_asc' ? 'selected' : ''; ?>>Mais Antigos</option>
                <option value="valor_desc" <?php echo $ordenacao === 'valor_desc' ? 'selected' : ''; ?>>Maior Valor</option>
                <option value="valor_asc" <?php echo $ordenacao === 'valor_asc' ? 'selected' : ''; ?>>Menor Valor</option>
                <option value="entrega" <?php echo $ordenacao === 'entrega' ? 'selected' : ''; ?>>Data de Entrega</option>
            </select>
        </div>
        
        <span class="text-xs md:text-sm text-gray-600">
            <?php echo $totalPedidos; ?> pedido(s) encontrado(s)
        </span>
    </div>
</div>

<!-- Lista de Pedidos -->
<div class="space-y-3 md:space-y-4">
    <?php if (empty($pedidos)): ?>
        <div class="white-card text-center py-8 md:py-12">
            <i class="fas fa-inbox text-5xl md:text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-600 text-base md:text-lg">Nenhum pedido encontrado</p>
            <p class="text-gray-500 text-xs md:text-sm mt-2">Crie seu primeiro pedido clicando no botão "Novo Pedido"</p>
        </div>
    <?php else: ?>
        <?php foreach ($pedidos as $pedido): ?>
            <div class="pedido-card white-card hover:shadow-lg transition p-4 md:p-6">
                <div class="pedido-header flex flex-col md:flex-row items-start md:items-center justify-between gap-3 md:gap-4 mb-3 md:mb-4">
                    <!-- Informações do Pedido -->
                    <div class="flex-1 w-full">
                        <div class="flex flex-wrap items-center gap-2 md:gap-3 mb-2 md:mb-3">
                            <h3 class="text-lg md:text-xl font-bold text-gray-900">#<?php echo htmlspecialchars($pedido['numero_pedido']); ?></h3>
                            <?php echo getStatusBadge($pedido['status']); ?>
                            <?php echo getStatusPagamentoBadge($pedido['status_pagamento']); ?>
                        </div>
                        
                        <div class="pedido-info-grid grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-4 text-xs md:text-sm">
                            <div>
                                <p class="text-gray-600">Cliente</p>
                                <p class="font-semibold text-gray-900">
                                    <i class="fas fa-user text-gray-400"></i>
                                    <?php echo htmlspecialchars($pedido['cliente_nome']); ?>
                                </p>
                                <p class="text-gray-600 text-xs">
                                    <i class="fas fa-phone text-gray-400"></i>
                                    <?php echo formatarTelefone($pedido['cliente_telefone']); ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-gray-600">Pedido</p>
                                <p class="font-semibold text-gray-900">
                                    <i class="fas fa-calendar text-gray-400"></i>
                                    <?php echo formatarData($pedido['data_pedido']); ?>
                                </p>
                                <p class="text-gray-600 text-xs">
                                    <i class="fas fa-box text-gray-400"></i>
                                    <?php echo $pedido['total_itens']; ?> item(ns)
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-gray-600">Entrega</p>
                                <p class="font-semibold text-gray-900">
                                    <i class="fas fa-truck text-gray-400"></i>
                                    <?php echo formatarData($pedido['data_entrega']); ?>
                                </p>
                                <?php
                                $diasRestantes = (strtotime($pedido['data_entrega']) - time()) / (60 * 60 * 24);
                                if ($diasRestantes < 0 && $pedido['status'] != 'entregue') {
                                    echo '<p class="text-red-600 text-xs font-semibold"><i class="fas fa-exclamation-triangle"></i> Atrasado!</p>';
                                } elseif ($diasRestantes <= 2 && $pedido['status'] != 'entregue') {
                                    echo '<p class="text-yellow-600 text-xs font-semibold"><i class="fas fa-clock"></i> Urgente!</p>';
                                }
                                ?>
                            </div>
                            
                            <div>
                                <p class="text-gray-600">Pagamento</p>
                                <p class="font-semibold text-gray-900">
                                    <?php echo ucfirst($pedido['forma_pagamento']); ?>
                                </p>
                                <p class="text-xs text-gray-600">
                                    Pago: <?php echo formatarMoeda($pedido['valor_pago']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Valor e Ações -->
                <div class="pedido-actions flex flex-col md:flex-row items-start md:items-end gap-3 md:gap-2 pt-3 md:pt-0 border-t md:border-t-0">
                    <div class="pedido-valor text-left md:text-right flex-1 w-full md:w-auto">
                        <p class="text-gray-600 text-xs md:text-sm">Valor Total</p>
                        <p class="text-2xl md:text-3xl font-bold text-purple-600"><?php echo formatarMoeda($pedido['valor_final']); ?></p>
                    </div>
                    
                    <div class="btn-group flex gap-2 w-full md:w-auto">
                        <!-- Ver Detalhes -->
                        <a href="pedido-detalhes.php?id=<?php echo $pedido['id']; ?>" class="flex-1 md:flex-none bg-blue-500 text-white px-2 md:px-3 py-2 rounded-lg hover:bg-blue-600 transition text-xs md:text-sm text-center" title="Ver Detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <!-- Editar -->
                        <a href="pedido-form.php?id=<?php echo $pedido['id']; ?>" class="flex-1 md:flex-none bg-green-500 text-white px-2 md:px-3 py-2 rounded-lg hover:bg-green-600 transition text-xs md:text-sm text-center" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <!-- Mudar Status -->
                        <div class="relative group flex-1 md:flex-none">
                            <button class="w-full bg-purple-500 text-white px-2 md:px-3 py-2 rounded-lg hover:bg-purple-600 transition text-xs md:text-sm" title="Mudar Status">
                                <i class="fas fa-exchange-alt"></i>
                            </button>
                            <div class="hidden group-hover:block absolute right-0 md:right-auto md:left-0 bottom-full md:top-full mb-2 md:mb-0 md:mt-2 w-48 bg-white rounded-lg shadow-xl z-10 py-2">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                    <button type="submit" name="status" value="aguardando" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm">Aguardando</button>
                                    <button type="submit" name="status" value="em_producao" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm">Em Produção</button>
                                    <button type="submit" name="status" value="pronto" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm">Pronto</button>
                                    <button type="submit" name="status" value="entregue" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm">Entregue</button>
                                    <button type="submit" name="status" value="cancelado" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm text-red-600">Cancelado</button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Excluir -->
                        <form method="POST" action="" onsubmit="return confirmarExclusao('Tem certeza que deseja excluir este pedido?')" class="flex-1 md:flex-none">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                            <button type="submit" class="w-full bg-red-500 text-white px-2 md:px-3 py-2 rounded-lg hover:bg-red-600 transition text-xs md:text-sm" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Paginação -->
<?php if ($totalPaginas > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-6 md:mt-8 flex-wrap">
        <?php if ($paginaAtual > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual - 1])); ?>" class="px-3 md:px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-xs md:text-base">
                <i class="fas fa-chevron-left"></i> <span class="hidden md:inline">Anterior</span>
            </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
               class="px-3 md:px-4 py-2 <?php echo $i === $paginaAtual ? 'bg-purple-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg text-xs md:text-base">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($paginaAtual < $totalPaginas): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual + 1])); ?>" class="px-3 md:px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-xs md:text-base">
                <span class="hidden md:inline">Próxima</span> <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>