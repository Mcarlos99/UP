<?php
if (!defined('INCLUDED')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'config.php';
    
    if (!isLoggedIn()) {
        redirect('index.php');
    }
    
    $user = getUser();
    if (!$user) {
        session_destroy();
        redirect('index.php');
    }
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Buscar pedidos pendentes do catálogo para badge
$pedidosPendentesCatalogo = 0;
try {
    $db = getDB();
    $empresaId = getEmpresaId();
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos_catalogo WHERE empresa_id = ? AND status = 'pendente'");
    $stmt->execute([$empresaId]);
    $result = $stmt->fetch();
    $pedidosPendentesCatalogo = $result['total'] ?? 0;
} catch (Exception $e) {
    // Silenciar erro se tabela não existir ainda
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar-item {
            transition: all 0.3s ease;
        }
        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid #fff;
        }
        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .card.bg-gradient-to-br {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .card.bg-gradient-to-br:hover {
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.4);
        }
        
        /* Badge de notificação */
        .badge-notification {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.25rem;
            height: 1.25rem;
            padding: 0 0.375rem;
            background: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            border-radius: 9999px;
            margin-left: auto;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        
        /* RESPONSIVO MOBILE - CORREÇÃO DA SIDEBAR */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            /* Remove margem da sidebar no mobile */
            .main-content {
                margin-left: 0 !important;
            }
            
            /* Overlay escuro quando menu aberto */
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .sidebar {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 16rem;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    
    <!-- Overlay para fechar menu mobile -->
    <div id="sidebar-overlay" class="sidebar-overlay" onclick="closeMobileMenu()"></div>
    
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 text-white z-50">
        <div class="p-6">
            <!-- Logo -->
            <div class="flex items-center gap-3 mb-8 pb-6 border-b border-white/20">
                <div class="bg-white/20 p-3 rounded-xl">
                    <i class="fas fa-book text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold">PaperArt</h1>
                    <p class="text-xs text-purple-200">Sistema de Gestão</p>
                </div>
            </div>

            <!-- Menu -->
            <nav class="space-y-2">
                <a href="dashboard.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="pedidos.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'pedidos' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-bag w-5"></i>
                    <span>Pedidos</span>
                </a>
                
                <a href="produtos.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'produtos' ? 'active' : ''; ?>">
                    <i class="fas fa-box w-5"></i>
                    <span>Produtos</span>
                </a>
                
                <a href="clientes.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'clientes' ? 'active' : ''; ?>">
                    <i class="fas fa-users w-5"></i>
                    <span>Clientes</span>
                </a>
                
                <!-- NOVO: Catálogo Online -->
                <a href="catalogo-online.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'catalogo-online' || $currentPage === 'pedidos-catalogo' ? 'active' : ''; ?>">
                    <i class="fas fa-store w-5"></i>
                   <span>Catálogo Online</span>
                   
                    <?php if ($pedidosPendentesCatalogo > 0): ?>
                        <span class="badge-notification"><?php echo $pedidosPendentesCatalogo; ?></span>
                   
                    <?php endif; ?>
                </a>
                
                
                <a href="configuracoes-catalogo.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'configuracoes-catalogo' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i>
                    <span>Personalizar Catálogo</span>
                </a>
                

                <a href="financeiro.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'financeiro' ? 'active' : ''; ?>">
                    <i class="fas fa-dollar-sign w-5"></i>
                    <span>Financeiro</span>
                </a>
                
                <a href="relatorios.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'relatorios' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar w-5"></i>
                    <span>Relatórios</span>
                </a>
                
                <?php if (isAdmin()): ?>
                <a href="configuracoes.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'configuracoes' ? 'active' : ''; ?>">
                    <i class="fas fa-cog w-5"></i>
                    <span>Configurações</span>
                </a>
                
                <a href="logout.php" class="sidebar-item flex items-center gap-3 px-4 py-3 rounded-lg" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>

        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content min-h-screen">
        
        <!-- Top Bar -->
        <header class="bg-white shadow-sm sticky top-0 z-40">
            <div class="flex items-center justify-between px-4 md:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button id="mobile-menu-btn" class="md:hidden text-gray-600 hover:text-gray-900 p-2" onclick="toggleMobileMenu()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div>
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800"><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h2>
                        <p class="text-xs md:text-sm text-gray-600"><?php echo isset($pageSubtitle) ? $pageSubtitle : ''; ?></p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2 md:gap-4">
                    <!-- Notificações -->
                    <button class="relative hover:bg-gray-100 p-2 rounded-lg transition">
                        <i class="fas fa-bell text-gray-600 text-lg md:text-xl"></i>
                        <?php if ($pedidosPendentesCatalogo > 0): ?>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Data/Hora -->
                    <div class="hidden md:block text-right">
                        <p class="text-sm text-gray-600">Hoje</p>
                        <p class="text-sm font-semibold text-gray-800"><?php echo date('d/m/Y'); ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="p-4 md:p-8">

<script>
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

function closeMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
}

// Fechar menu ao clicar em um link (mobile)
document.querySelectorAll('.sidebar-item').forEach(item => {
    item.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            closeMobileMenu();
        }
    });
});
</script>