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
$pageTitle = 'Clientes';
$pageSubtitle = 'Gerencie sua base de clientes';

// MULTI-TENANT: Obter empresa do usuário logado
$empresaId = getEmpresaId();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    // Excluir cliente
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['cliente_id'])) {
        try {
            // MULTI-TENANT: Validar acesso antes de deletar
            validarAcessoEmpresa('clientes', $_POST['cliente_id']);
            
            // MULTI-TENANT: Adicionar empresa_id no WHERE
            $stmt = $db->prepare("UPDATE clientes SET ativo = 0 WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$_POST['cliente_id'], $empresaId]);
            
            logActivity('Cliente desativado', 'clientes', $_POST['cliente_id']);
            $_SESSION['success'] = 'Cliente removido com sucesso!';
            header('Location: clientes.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao remover cliente: ' . $e->getMessage();
        }
    }
}

// Filtros e busca
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$ordenacao = isset($_GET['ordem']) ? $_GET['ordem'] : 'nome_asc';

// MULTI-TENANT: Construir query com filtro de empresa
$where = ["ativo = 1", "empresa_id = ?"];
$params = [$empresaId];

if (!empty($busca)) {
    $where[] = "(nome LIKE ? OR telefone LIKE ? OR email LIKE ? OR cpf_cnpj LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Ordenação
$orderBy = "nome ASC";
switch ($ordenacao) {
    case 'nome_desc':
        $orderBy = "nome DESC";
        break;
    case 'compras_desc':
        $orderBy = "total_compras DESC";
        break;
    case 'compras_asc':
        $orderBy = "total_compras ASC";
        break;
    case 'pedidos_desc':
        $orderBy = "quantidade_pedidos DESC";
        break;
    case 'recentes':
        $orderBy = "data_cadastro DESC";
        break;
}

// Paginação
$itensPorPagina = 20;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Buscar clientes
$clientes = [];
$totalClientes = 0;

try {
    $db = getDB();
    
    // Contar total
    $sql = "SELECT COUNT(*) as total FROM clientes WHERE " . implode(" AND ", $where);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $totalClientes = $stmt->fetch()['total'];
    
    // Buscar clientes
    $sql = "SELECT * FROM clientes 
            WHERE " . implode(" AND ", $where) . "
            ORDER BY $orderBy
            LIMIT $itensPorPagina OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();
    
    // MULTI-TENANT: Estatísticas filtradas por empresa
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalAtivos = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT SUM(total_compras) as total FROM clientes WHERE ativo = 1 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalFaturamento = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1 AND empresa_id = ? AND ultima_compra >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$empresaId]);
    $clientesAtivos30dias = $stmt->fetch()['total'];
    
    $ticketMedio = $totalAtivos > 0 ? $totalFaturamento / $totalAtivos : 0;
    
} catch (PDOException $e) {
    $erro = "Erro ao carregar clientes: " . $e->getMessage();
}

// Calcular paginação
$totalPaginas = ceil($totalClientes / $itensPorPagina);

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
    unset($_SESSION['error']);
}
?>

<!-- Cabeçalho -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-3xl font-bold text-gray-900">Clientes</h2>
        <p class="text-gray-600 mt-1">Gerencie sua base de clientes</p>
    </div>
    <a href="cliente-form.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Novo Cliente
    </a>
</div>

<!-- Cards de Estatísticas - FORMATO 4x1 QUADRADO -->
<div class="grid grid-cols-4 gap-4 mb-6" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <!-- Card 1: Total de Clientes (Amarelo/Laranja) -->
    <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-users text-4xl"></i>
            </div>
            <p class="text-yellow-100 text-base font-bold mb-3">Total de Clientes</p>
            <p class="text-4xl font-bold mb-2"><?php echo $totalAtivos; ?></p>
            <p class="text-yellow-100 text-base font-semibold">Cadastrados</p>
        </div>
    </div>
    
    <!-- Card 2: Faturamento Total (Azul/Cyan) -->
    <div class="bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-dollar-sign text-4xl"></i>
            </div>
            <p class="text-blue-100 text-base font-bold mb-3">Faturamento Total</p>
            <p class="text-4xl font-bold mb-2"><?php echo formatarMoeda($totalFaturamento); ?></p>
            <p class="text-blue-100 text-base font-semibold">Todas vendas</p>
        </div>
    </div>
    
    <!-- Card 3: Ticket Médio (Verde/Esmeralda) -->
    <div class="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-chart-line text-4xl"></i>
            </div>
            <p class="text-green-100 text-base font-bold mb-3">Ticket Médio</p>
            <p class="text-4xl font-bold mb-2"><?php echo formatarMoeda($ticketMedio); ?></p>
            <p class="text-green-100 text-base font-semibold">Por cliente</p>
        </div>
    </div>
    
    <!-- Card 4: Ativos (30 dias) (Roxo/Rosa) -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition" style="aspect-ratio: 1/1;">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-fire text-4xl"></i>
            </div>
            <p class="text-purple-100 text-base font-bold mb-3">Ativos (30 dias)</p>
            <p class="text-4xl font-bold mb-2"><?php echo $clientesAtivos30dias; ?></p>
            <p class="text-purple-100 text-base font-semibold">Com compras</p>
        </div>
    </div>
</div>

<!-- Filtros e Busca -->
<div class="white-card mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Busca -->
        <div class="md:col-span-2">
            <input 
                type="text" 
                name="busca" 
                placeholder="Buscar por nome, telefone, email ou CPF/CNPJ..." 
                value="<?php echo htmlspecialchars($busca); ?>"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
        </div>
        
        <!-- Botão -->
        <div>
            <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                <i class="fas fa-search"></i> Buscar
            </button>
        </div>
    </form>
    
    <!-- Ordenação -->
    <div class="mt-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Ordenar por:</span>
            <select name="ordem" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['ordem' => ''])); ?>&ordem=' + this.value" class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                <option value="nome_asc" <?php echo $ordenacao === 'nome_asc' ? 'selected' : ''; ?>>Nome A-Z</option>
                <option value="nome_desc" <?php echo $ordenacao === 'nome_desc' ? 'selected' : ''; ?>>Nome Z-A</option>
                <option value="compras_desc" <?php echo $ordenacao === 'compras_desc' ? 'selected' : ''; ?>>Maiores Compradores</option>
                <option value="compras_asc" <?php echo $ordenacao === 'compras_asc' ? 'selected' : ''; ?>>Menores Compradores</option>
                <option value="pedidos_desc" <?php echo $ordenacao === 'pedidos_desc' ? 'selected' : ''; ?>>Mais Pedidos</option>
                <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais Recentes</option>
            </select>
        </div>
        
        <span class="text-sm text-gray-600">
            <?php echo $totalClientes; ?> cliente(s) encontrado(s)
        </span>
    </div>
</div>

<!-- Lista de Clientes -->
<?php if (empty($clientes)): ?>
    <div class="white-card text-center py-12">
        <i class="fas fa-user-friends text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-600 text-lg">Nenhum cliente encontrado</p>
        <p class="text-gray-500 text-sm mt-2">Adicione seu primeiro cliente clicando no botão "Novo Cliente"</p>
    </div>
<?php else: ?>
    <div class="grid gap-4">
        <?php foreach ($clientes as $cliente): ?>
            <div class="white-card hover:shadow-lg transition">
                <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
                    
                    <!-- Avatar -->
                    <div class="flex-shrink-0">
                        <div class="w-16 h-16 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                            <?php echo strtoupper(substr($cliente['nome'], 0, 1)); ?>
                        </div>
                    </div>
                    
                    <!-- Informações -->
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-4 w-full">
                        <!-- Nome e Contato -->
                        <div class="md:col-span-2">
                            <h3 class="text-xl font-bold text-gray-900 mb-1">
                                <?php echo htmlspecialchars($cliente['nome']); ?>
                            </h3>
                            <div class="space-y-1 text-sm text-gray-600">
                                <?php if (!empty($cliente['telefone'])): ?>
                                    <p>
                                        <i class="fas fa-phone text-gray-400 w-4"></i>
                                        <a href="tel:<?php echo $cliente['telefone']; ?>" class="hover:text-purple-600">
                                            <?php echo formatarTelefone($cliente['telefone']); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($cliente['whatsapp'])): ?>
                                    <p>
                                        <i class="fab fa-whatsapp text-green-500 w-4"></i>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $cliente['whatsapp']); ?>" target="_blank" class="hover:text-green-600">
                                            <?php echo formatarTelefone($cliente['whatsapp']); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($cliente['email'])): ?>
                                    <p>
                                        <i class="fas fa-envelope text-gray-400 w-4"></i>
                                        <a href="mailto:<?php echo $cliente['email']; ?>" class="hover:text-purple-600">
                                            <?php echo htmlspecialchars($cliente['email']); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Localização -->
                        <div>
                            <p class="text-gray-600 text-sm mb-1">Localização</p>
                            <?php if (!empty($cliente['cidade']) && !empty($cliente['estado'])): ?>
                                <p class="font-semibold text-gray-900">
                                    <i class="fas fa-map-marker-alt text-red-500"></i>
                                    <?php echo htmlspecialchars($cliente['cidade']); ?> - <?php echo htmlspecialchars($cliente['estado']); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-400 text-sm">Não informado</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($cliente['cpf_cnpj'])): ?>
                                <p class="text-gray-600 text-xs mt-1">
                                    CPF/CNPJ: <?php echo htmlspecialchars($cliente['cpf_cnpj']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Histórico de Compras -->
                        <div>
                            <p class="text-gray-600 text-sm mb-1">Histórico</p>
                            <div class="space-y-1">
                                <p class="font-semibold text-purple-600">
                                    <?php echo formatarMoeda($cliente['total_compras']); ?>
                                </p>
                                <p class="text-gray-600 text-sm">
                                    <i class="fas fa-shopping-bag text-gray-400"></i>
                                    <?php echo $cliente['quantidade_pedidos']; ?> pedido(s)
                                </p>
                                <?php if (!empty($cliente['ultima_compra'])): ?>
                                    <p class="text-gray-600 text-xs">
                                        Última: <?php echo formatarData($cliente['ultima_compra']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ações -->
                    <div class="flex md:flex-col gap-2 w-full md:w-auto">
                        <a href="cliente-detalhes.php?id=<?php echo $cliente['id']; ?>" class="flex-1 md:flex-none bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition text-center text-sm" title="Ver Detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <a href="cliente-form.php?id=<?php echo $cliente['id']; ?>" class="flex-1 md:flex-none bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition text-center text-sm" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <a href="pedido-form.php?cliente_id=<?php echo $cliente['id']; ?>" class="flex-1 md:flex-none bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition text-center text-sm" title="Novo Pedido">
                            <i class="fas fa-plus"></i>
                        </a>
                        
                        <form method="POST" action="" onsubmit="return confirmarExclusao('Tem certeza que deseja remover este cliente?')" class="flex-1 md:flex-none">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cliente_id" value="<?php echo $cliente['id']; ?>">
                            <button type="submit" class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition text-sm" title="Remover">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Paginação -->
<?php if ($totalPaginas > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-8">
        <?php if ($paginaAtual > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual - 1])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <i class="fas fa-chevron-left"></i> Anterior
            </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
               class="px-4 py-2 <?php echo $i === $paginaAtual ? 'bg-purple-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($paginaAtual < $totalPaginas): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $paginaAtual + 1])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Próxima <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>