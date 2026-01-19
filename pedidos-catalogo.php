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
$pageTitle = 'Pedidos do Catálogo';
$pageSubtitle = 'Gerencie os pedidos recebidos pelo catálogo online';

$empresaId = getEmpresaId();
$db = getDB();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['pedido_id'])) {
        try {
            $pedidoId = (int)$_POST['pedido_id'];
            
            // Verificar se o pedido pertence à empresa
            $stmt = $db->prepare("SELECT id FROM pedidos_catalogo WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$pedidoId, $empresaId]);
            if (!$stmt->fetch()) {
                throw new Exception('Pedido não encontrado');
            }
            
            if ($_POST['action'] === 'mudar_status') {
                $novoStatus = sanitize($_POST['novo_status']);
                
                $stmt = $db->prepare("UPDATE pedidos_catalogo SET status = ? WHERE id = ?");
                $stmt->execute([$novoStatus, $pedidoId]);
                
                logActivity('Status do pedido catálogo alterado', 'pedidos_catalogo', $pedidoId);
                $_SESSION['success'] = 'Status atualizado com sucesso!';
            }
            
            header('Location: pedidos-catalogo.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro: ' . $e->getMessage();
        }
    }
}

// Filtros
$statusFiltro = isset($_GET['status']) ? sanitize($_GET['status']) : 'todos';
$busca = isset($_GET['busca']) ? sanitize($_GET['busca']) : '';

// Construir query
$where = ["pc.empresa_id = ?"];
$params = [$empresaId];

if ($statusFiltro !== 'todos') {
    $where[] = "pc.status = ?";
    $params[] = $statusFiltro;
}

if (!empty($busca)) {
    $where[] = "(pc.cliente_nome LIKE ? OR pc.cliente_telefone LIKE ? OR pc.codigo_pedido LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Buscar pedidos
try {
    $sql = "SELECT 
                pc.*,
                (SELECT COUNT(*) FROM pedidos_catalogo_itens WHERE pedido_id = pc.id) as total_itens
            FROM pedidos_catalogo pc
            WHERE " . implode(" AND ", $where) . "
            ORDER BY 
                CASE pc.status 
                    WHEN 'pendente' THEN 1
                    WHEN 'confirmado' THEN 2
                    WHEN 'em_producao' THEN 3
                    WHEN 'pronto' THEN 4
                    WHEN 'enviado' THEN 5
                    WHEN 'entregue' THEN 6
                    WHEN 'cancelado' THEN 7
                END,
                pc.data_pedido DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
    
    // Estatísticas
    $stmt = $db->prepare("SELECT COUNT(*) as total, status FROM pedidos_catalogo WHERE empresa_id = ? GROUP BY status");
    $stmt->execute([$empresaId]);
    $estatisticas = [];
    while ($row = $stmt->fetch()) {
        $estatisticas[$row['status']] = $row['total'];
    }
    
} catch (PDOException $e) {
    $pedidos = [];
    $estatisticas = [];
}

require_once 'header.php';

// Mensagens
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
    unset($_SESSION['error']);
}

$statusLabels = [
    'pendente' => ['label' => 'Pendente', 'icon' => 'clock', 'color' => 'yellow'],
    'confirmado' => ['label' => 'Confirmado', 'icon' => 'check', 'color' => 'blue'],
    'em_producao' => ['label' => 'Em Produção', 'icon' => 'cogs', 'color' => 'purple'],
    'pronto' => ['label' => 'Pronto', 'icon' => 'box', 'color' => 'green'],
    'enviado' => ['label' => 'Enviado', 'icon' => 'shipping-fast', 'color' => 'cyan'],
    'entregue' => ['label' => 'Entregue', 'icon' => 'check-double', 'color' => 'gray'],
    'cancelado' => ['label' => 'Cancelado', 'icon' => 'times', 'color' => 'red']
];
?>

<style>
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem !important;
    }
    
    .filters-grid {
        grid-template-columns: 1fr !important;
    }
    
    .pedido-card {
        padding: 1rem !important;
    }
}
</style>

<!-- Cabeçalho -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
    <div>
        <h2 class="text-2xl md:text-3xl font-bold text-gray-900"><?php echo $pageTitle; ?></h2>
        <p class="text-gray-600 mt-1"><?php echo $pageSubtitle; ?></p>
    </div>
    <a href="catalogo-online.php" class="btn btn-primary w-full md:w-auto text-center">
        <i class="fas fa-arrow-left"></i> Voltar ao Catálogo
    </a>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-clock text-3xl"></i>
            </div>
            <p class="text-yellow-100 text-sm font-bold mb-2">Pendentes</p>
            <p class="text-3xl font-bold"><?php echo $estatisticas['pendente'] ?? 0; ?></p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-cogs text-3xl"></i>
            </div>
            <p class="text-blue-100 text-sm font-bold mb-2">Em Produção</p>
            <p class="text-3xl font-bold"><?php echo $estatisticas['em_producao'] ?? 0; ?></p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-check text-3xl"></i>
            </div>
            <p class="text-green-100 text-sm font-bold mb-2">Prontos</p>
            <p class="text-3xl font-bold"><?php echo $estatisticas['pronto'] ?? 0; ?></p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-check-double text-3xl"></i>
            </div>
            <p class="text-purple-100 text-sm font-bold mb-2">Entregues</p>
            <p class="text-3xl font-bold"><?php echo $estatisticas['entregue'] ?? 0; ?></p>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="white-card mb-6 p-4">
    <form method="GET" class="filters-grid grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
            <input 
                type="text" 
                name="busca" 
                placeholder="Buscar por nome, telefone ou código..." 
                value="<?php echo htmlspecialchars($busca); ?>"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
        </div>
        
        <div>
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="todos">Todos os Status</option>
                <?php foreach ($statusLabels as $valor => $info): ?>
                    <option value="<?php echo $valor; ?>" <?php echo $statusFiltro === $valor ? 'selected' : ''; ?>>
                        <?php echo $info['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<!-- Lista de Pedidos -->
<?php if (empty($pedidos)): ?>
    <div class="white-card text-center py-12">
        <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">Nenhum pedido encontrado</p>
        <p class="text-sm text-gray-500 mt-2">Os pedidos que seus clientes fizerem pelo catálogo aparecerão aqui</p>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($pedidos as $pedido): 
            $statusInfo = $statusLabels[$pedido['status']];
            $statusColorClass = "bg-{$statusInfo['color']}-100 text-{$statusInfo['color']}-800";
        ?>
            <div class="pedido-card white-card hover:shadow-lg transition">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <h3 class="text-xl font-bold text-gray-900">#<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></h3>
                                <span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $statusColorClass; ?>">
                                    <i class="fas fa-<?php echo $statusInfo['icon']; ?>"></i> <?php echo $statusInfo['label']; ?>
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                <div>
                                    <p class="text-gray-600">Cliente</p>
                                    <p class="font-semibold text-gray-900">
                                        <i class="fas fa-user text-gray-400"></i> <?php echo htmlspecialchars($pedido['cliente_nome']); ?>
                                    </p>
                                    <p class="text-gray-600">
                                        <i class="fas fa-phone text-gray-400"></i> <?php echo formatarTelefone($pedido['cliente_telefone']); ?>
                                    </p>
                                    <?php if (!empty($pedido['cliente_email'])): ?>
                                        <p class="text-gray-600 text-xs truncate">
                                            <i class="fas fa-envelope text-gray-400"></i> <?php echo htmlspecialchars($pedido['cliente_email']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <p class="text-gray-600">Pedido</p>
                                    <p class="font-semibold text-gray-900">
                                        <i class="fas fa-calendar text-gray-400"></i> <?php echo formatarData($pedido['data_pedido'], 'd/m/Y H:i'); ?>
                                    </p>
                                    <p class="text-gray-600">
                                        <i class="fas fa-box text-gray-400"></i> <?php echo $pedido['total_itens']; ?> item(ns)
                                    </p>
                                </div>
                                
                                <div>
                                    <p class="text-gray-600">Endereço</p>
                                    <?php if (!empty($pedido['endereco_completo'])): ?>
                                        <p class="font-semibold text-gray-900 text-xs">
                                            <i class="fas fa-map-marker-alt text-gray-400"></i> <?php echo htmlspecialchars($pedido['endereco_completo']); ?>
                                        </p>
                                        <?php if (!empty($pedido['cep'])): ?>
                                            <p class="text-gray-600 text-xs">CEP: <?php echo htmlspecialchars($pedido['cep']); ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-gray-500 text-xs">Não informado</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <p class="text-gray-600 text-sm">Valor Total</p>
                            <p class="text-3xl font-bold text-purple-600"><?php echo formatarMoeda($pedido['valor_total']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Itens do Pedido -->
                    <div class="border-t pt-4 mb-4">
                        <button onclick="toggleItens(<?php echo $pedido['id']; ?>)" class="text-purple-600 hover:text-purple-700 font-semibold text-sm">
                            <i class="fas fa-list"></i> Ver Itens do Pedido
                        </button>
                        
                        <div id="itens-<?php echo $pedido['id']; ?>" class="hidden mt-4">
                            <?php
                            try {
                                $stmt = $db->prepare("SELECT * FROM pedidos_catalogo_itens WHERE pedido_id = ?");
                                $stmt->execute([$pedido['id']]);
                                $itens = $stmt->fetchAll();
                                
                                if (!empty($itens)): ?>
                                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                                        <?php foreach ($itens as $item): ?>
                                            <div class="flex justify-between items-center text-sm">
                                                <div class="flex-1">
                                                    <span class="font-semibold"><?php echo $item['quantidade']; ?>x</span>
                                                    <span class="text-gray-900"><?php echo htmlspecialchars($item['produto_nome']); ?></span>
                                                </div>
                                                <span class="font-bold text-purple-600"><?php echo formatarMoeda($item['preco_unitario'] * $item['quantidade']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif;
                            } catch (PDOException $e) {
                                echo '<p class="text-red-600 text-sm">Erro ao carregar itens</p>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Informações Adicionais -->
                    <?php if (!empty($pedido['forma_pagamento_preferencial']) || !empty($pedido['observacoes'])): ?>
                    <div class="border-t pt-4 mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if (!empty($pedido['forma_pagamento_preferencial'])): ?>
                                <div>
                                    <p class="text-gray-600 text-sm mb-1">
                                        <i class="fas fa-credit-card"></i> Forma de Pagamento Preferencial
                                    </p>
                                    <p class="font-semibold text-gray-900 bg-blue-50 px-3 py-2 rounded-lg">
                                        <?php echo htmlspecialchars($pedido['forma_pagamento_preferencial']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($pedido['observacoes'])): ?>
                                <div>
                                    <p class="text-gray-600 text-sm mb-1">
                                        <i class="fas fa-comment"></i> Observações do Cliente
                                    </p>
                                    <p class="text-gray-900 bg-yellow-50 px-3 py-2 rounded-lg">
                                        <?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Ações -->
                    <div class="flex flex-wrap gap-2">
                        <!-- Mudar Status -->
                        <div class="relative">
                            <button onclick="toggleDropdown(<?php echo $pedido['id']; ?>)" 
                                    id="btn-status-<?php echo $pedido['id']; ?>"
                                    class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm">
                                <i class="fas fa-exchange-alt"></i> Mudar Status
                            </button>
                            <div id="dropdown-<?php echo $pedido['id']; ?>" 
                                 class="hidden absolute bottom-full mb-2 left-0 bg-white rounded-lg shadow-xl py-2 z-50 w-56 border border-gray-200">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="mudar_status">
                                    <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                    <?php foreach ($statusLabels as $valor => $info): ?>
                                        <button type="submit" name="novo_status" value="<?php echo $valor; ?>" 
                                                class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm text-gray-900 flex items-center gap-2
                                                       <?php echo ($pedido['status'] === $valor) ? 'bg-purple-50 font-bold' : ''; ?>">
                                            <i class="fas fa-<?php echo $info['icon']; ?> <?php echo "text-{$info['color']}-600"; ?>"></i>
                                            <?php echo $info['label']; ?>
                                            <?php if ($pedido['status'] === $valor): ?>
                                                <i class="fas fa-check ml-auto text-purple-600"></i>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </form>
                            </div>
                        </div>
                        
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $pedido['cliente_telefone']); ?>?text=<?php echo urlencode("Olá {$pedido['cliente_nome']}, tudo bem? Sobre seu pedido #{$pedido['codigo_pedido']}..."); ?>" 
                           target="_blank"
                           class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm inline-flex items-center gap-2">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        
                        <?php if (!empty($pedido['cliente_email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($pedido['cliente_email']); ?>?subject=Pedido <?php echo htmlspecialchars($pedido['codigo_pedido']); ?>" 
                               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm inline-flex items-center gap-2">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function toggleItens(pedidoId) {
    const div = document.getElementById('itens-' + pedidoId);
    if (div.classList.contains('hidden')) {
        div.classList.remove('hidden');
    } else {
        div.classList.add('hidden');
    }
}

function toggleDropdown(pedidoId) {
    const dropdown = document.getElementById('dropdown-' + pedidoId);
    
    // Fechar todos os outros dropdowns
    document.querySelectorAll('[id^="dropdown-"]').forEach(d => {
        if (d.id !== 'dropdown-' + pedidoId) {
            d.classList.add('hidden');
        }
    });
    
    // Toggle do dropdown atual
    dropdown.classList.toggle('hidden');
}

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(event) {
    const isDropdownButton = event.target.closest('[id^="btn-status-"]');
    const isDropdownContent = event.target.closest('[id^="dropdown-"]');
    
    if (!isDropdownButton && !isDropdownContent) {
        document.querySelectorAll('[id^="dropdown-"]').forEach(d => {
            d.classList.add('hidden');
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>