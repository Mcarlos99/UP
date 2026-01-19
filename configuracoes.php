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

// Verificar se é admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Acesso negado! Apenas administradores podem acessar esta área.';
    header('Location: dashboard.php');
    exit;
}

define('INCLUDED', true);
$pageTitle = 'Configurações';
$pageSubtitle = 'Gerencie as configurações do sistema';

$empresaId = getEmpresaId();
$db = getDB();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Criar nova empresa (usuário admin) - SOMENTE SUPER ADMIN
    if (isset($_POST['action']) && $_POST['action'] === 'criar_empresa') {
        if (!isSuperAdmin()) {
            $_SESSION['error'] = 'Acesso negado! Apenas o super administrador pode criar novas empresas.';
            header('Location: configuracoes.php');
            exit;
        }
        
        try {
            $nomeEmpresa = sanitize($_POST['nome_empresa']);
            $emailEmpresa = sanitize($_POST['email_empresa']);
            $telefoneEmpresa = sanitize($_POST['telefone_empresa']);
            $senhaEmpresa = $_POST['senha_empresa'];
            
            // Validações
            if (empty($nomeEmpresa) || empty($emailEmpresa) || empty($senhaEmpresa)) {
                throw new Exception('Preencha todos os campos obrigatórios');
            }
            
            if (!validarEmail($emailEmpresa)) {
                throw new Exception('Email inválido');
            }
            
            // Verificar se email já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$emailEmpresa]);
            if ($stmt->fetch()) {
                throw new Exception('Email já cadastrado no sistema');
            }
            
            // Criar usuário admin (empresa)
            $senhaHash = hashPassword($senhaEmpresa);
            
            $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, telefone, nivel_acesso, ativo) 
                                  VALUES (?, ?, ?, ?, 'admin', 1)");
            $stmt->execute([$nomeEmpresa, $emailEmpresa, $senhaHash, $telefoneEmpresa]);
            
            $novaEmpresaId = $db->lastInsertId();
            
            logActivity('Nova empresa criada', 'usuarios', $novaEmpresaId);
            $_SESSION['success'] = "Empresa '$nomeEmpresa' criada com sucesso! Login: $emailEmpresa";
            header('Location: configuracoes.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao criar empresa: ' . $e->getMessage();
        }
    }
    
    // Salvar configurações gerais
    if (isset($_POST['action']) && $_POST['action'] === 'salvar_config') {
        try {
            $configs = [
                'empresa_nome' => sanitize($_POST['empresa_nome']),
                'empresa_telefone' => sanitize($_POST['empresa_telefone']),
                'empresa_email' => sanitize($_POST['empresa_email']),
                'empresa_cnpj' => sanitize($_POST['empresa_cnpj'] ?? ''),
                'empresa_endereco' => sanitize($_POST['empresa_endereco'] ?? ''),
                'prazo_entrega_padrao' => (int)$_POST['prazo_entrega_padrao'],
                'estoque_alerta' => (int)$_POST['estoque_alerta'],
            ];
            
            $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor, tipo) VALUES (?, ?, 'texto') 
                                  ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            
            foreach ($configs as $chave => $valor) {
                $stmt->execute([$chave, $valor]);
            }
            
            logActivity('Configurações atualizadas', 'configuracoes');
            $_SESSION['success'] = 'Configurações salvas com sucesso!';
            header('Location: configuracoes.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao salvar configurações: ' . $e->getMessage();
        }
    }
    
    // Desativar empresa - SOMENTE SUPER ADMIN
    if (isset($_POST['action']) && $_POST['action'] === 'desativar_empresa') {
        if (!isSuperAdmin()) {
            $_SESSION['error'] = 'Acesso negado! Apenas o super administrador pode desativar empresas.';
            header('Location: configuracoes.php');
            exit;
        }
        
        try {
            $empresaIdDesativar = (int)$_POST['empresa_id'];
            
            if ($empresaIdDesativar == $_SESSION['user_id']) {
                throw new Exception('Você não pode desativar sua própria empresa');
            }
            
            $stmt = $db->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ? AND nivel_acesso = 'admin'");
            $stmt->execute([$empresaIdDesativar]);
            
            logActivity('Empresa desativada', 'usuarios', $empresaIdDesativar);
            $_SESSION['success'] = 'Empresa desativada com sucesso!';
            header('Location: configuracoes.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao desativar empresa: ' . $e->getMessage();
        }
    }
}

// Buscar configurações
$configs = [];
try {
    $stmt = $db->query("SELECT chave, valor FROM configuracoes");
    while ($row = $stmt->fetch()) {
        $configs[$row['chave']] = $row['valor'];
    }
} catch (PDOException $e) {
    $erro = "Erro ao carregar configurações";
}

// Buscar empresas (apenas para SUPER ADMIN)
$empresas = [];
if (isSuperAdmin()) {
    try {
        $stmt = $db->query("SELECT * FROM usuarios WHERE nivel_acesso = 'admin' ORDER BY ativo DESC, nome ASC");
        $empresas = $stmt->fetchAll();
    } catch (PDOException $e) {
        $erro = "Erro ao carregar empresas";
    }
}

// Estatísticas
try {
    if (isSuperAdmin()) {
        $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1 AND nivel_acesso = 'admin'");
        $totalEmpresas = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1");
        $totalUsuarios = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM log_atividades WHERE DATE(data_hora) = CURDATE()");
        $atividadesHoje = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM configuracoes");
        $totalConfigs = $stmt->fetch()['total'];
    } else {
        // Para admins normais, mostrar apenas estatísticas da própria empresa
        $totalEmpresas = 1;
        $totalUsuarios = 1;
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM log_atividades WHERE usuario_id = ? AND DATE(data_hora) = CURDATE()");
        $stmt->execute([$empresaId]);
        $atividadesHoje = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM configuracoes");
        $totalConfigs = $stmt->fetch()['total'];
    }
    
} catch (PDOException $e) {
    $totalEmpresas = $totalUsuarios = $atividadesHoje = $totalConfigs = 0;
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
?>

<style>
/* Responsivo Mobile - Configurações */
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
    
    /* Tabs em scroll horizontal */
    .tabs-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    
    .tabs-container::-webkit-scrollbar {
        display: none;
    }
    
    .tabs-nav {
        min-width: max-content;
    }
    
    .tab-button {
        white-space: nowrap;
        font-size: 0.875rem !important;
        padding: 0.75rem 1rem !important;
    }
    
    /* Cards de empresa */
    .empresa-card {
        padding: 1rem !important;
    }
    
    .empresa-card .empresa-avatar {
        width: 48px !important;
        height: 48px !important;
        font-size: 1.25rem !important;
    }
    
    .empresa-card .empresa-info-grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }
    
    .empresa-card .empresa-actions {
        flex-direction: column !important;
        width: 100% !important;
        gap: 0.5rem !important;
    }
    
    /* Formulários em coluna */
    .form-grid {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 480px) {
    .stat-card .stat-value {
        font-size: 1.25rem !important;
    }
}
</style>

<!-- Cabeçalho -->


<!-- Cards -->
<div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 md:mb-6">
    <?php if (isSuperAdmin()): ?>
        <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
            <div class="h-full flex flex-col items-center justify-center text-center">
                <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                    <i class="fas fa-store text-3xl md:text-4xl"></i>
                </div>
                <p class="stat-title text-yellow-100 text-sm md:text-base font-bold mb-3">Total de Empresas</p>
                <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalEmpresas; ?></p>
                <p class="stat-subtitle text-yellow-100 text-xs md:text-base font-semibold">Cadastradas</p>
            </div>
        </div>
        
        <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
            <div class="h-full flex flex-col items-center justify-center text-center">
                <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                    <i class="fas fa-users text-3xl md:text-4xl"></i>
                </div>
                <p class="stat-title text-blue-100 text-sm md:text-base font-bold mb-3">Total de Usuários</p>
                <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalUsuarios; ?></p>
                <p class="stat-subtitle text-blue-100 text-xs md:text-base font-semibold">Ativos</p>
            </div>
        </div>
    <?php else: ?>
        <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
            <div class="h-full flex flex-col items-center justify-center text-center">
                <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                    <i class="fas fa-building text-3xl md:text-4xl"></i>
                </div>
                <p class="stat-title text-yellow-100 text-sm md:text-base font-bold mb-3">Minha Empresa</p>
                <p class="stat-value text-2xl md:text-4xl font-bold mb-2">1</p>
                <p class="stat-subtitle text-yellow-100 text-xs md:text-base font-semibold">Ativa</p>
            </div>
        </div>
        
        <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
            <div class="h-full flex flex-col items-center justify-center text-center">
                <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                    <i class="fas fa-user text-3xl md:text-4xl"></i>
                </div>
                <p class="stat-title text-blue-100 text-sm md:text-base font-bold mb-3">Usuários</p>
                <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalUsuarios; ?></p>
                <p class="stat-subtitle text-blue-100 text-xs md:text-base font-semibold">Ativos</p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="stat-card bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-chart-line text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-green-100 text-sm md:text-base font-bold mb-3">Atividades Hoje</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $atividadesHoje; ?></p>
            <p class="stat-subtitle text-green-100 text-xs md:text-base font-semibold">Registros</p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="icon-box bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-cog text-3xl md:text-4xl"></i>
            </div>
            <p class="stat-title text-purple-100 text-sm md:text-base font-bold mb-3">Configurações</p>
            <p class="stat-value text-2xl md:text-4xl font-bold mb-2"><?php echo $totalConfigs; ?></p>
            <p class="stat-subtitle text-purple-100 text-xs md:text-base font-semibold">Definidas</p>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="mb-4 md:mb-6">
    <div class="tabs-container border-b border-gray-200 overflow-x-auto">
        <nav class="tabs-nav flex space-x-4 md:space-x-8">
            <?php if (isSuperAdmin()): ?>
                <button onclick="mostrarTab('empresas')" id="tab-empresas" class="tab-button border-b-2 border-purple-600 py-3 md:py-4 px-1 text-purple-600 font-semibold text-sm md:text-base">
                    <i class="fas fa-store"></i> Empresas
                </button>
            <?php endif; ?>
            
            <button onclick="mostrarTab('geral')" id="tab-geral" class="tab-button border-b-2 <?php echo isSuperAdmin() ? 'border-transparent' : 'border-purple-600'; ?> py-3 md:py-4 px-1 <?php echo isSuperAdmin() ? 'text-gray-500 hover:text-gray-700' : 'text-purple-600'; ?> font-semibold text-sm md:text-base">
                <i class="fas fa-building"></i> Geral
            </button>
            
            <?php if (isSuperAdmin()): ?>
                <button onclick="mostrarTab('sistema')" id="tab-sistema" class="tab-button border-b-2 border-transparent py-3 md:py-4 px-1 text-gray-500 hover:text-gray-700 font-semibold text-sm md:text-base">
                    <i class="fas fa-cogs"></i> Sistema
                </button>
            <?php endif; ?>
        </nav>
    </div>
</div>

<!-- Tab: Empresas (SOMENTE SUPER ADMIN) -->
<?php if (isSuperAdmin()): ?>
<div id="content-empresas" class="tab-content">
    <div class="white-card mb-4 md:mb-6 p-3 md:p-4">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 md:gap-4 mb-4 md:mb-6">
            <div>
                <h3 class="text-lg md:text-xl font-bold text-gray-900">
                    <i class="fas fa-store text-purple-600"></i> Empresas Cadastradas
                </h3>
                <p class="text-xs md:text-sm text-gray-600 mt-1">Cada empresa tem acesso isolado aos seus dados</p>
            </div>
            <button onclick="abrirModalEmpresa()" class="w-full md:w-auto btn btn-primary text-center">
                <i class="fas fa-plus"></i> Nova Empresa
            </button>
        </div>
        
        <div class="grid gap-3 md:gap-4">
            <?php if (empty($empresas)): ?>
                <div class="text-center py-8 md:py-12">
                    <i class="fas fa-store text-5xl md:text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600 text-base md:text-lg">Nenhuma empresa cadastrada</p>
                </div>
            <?php else: ?>
                <?php foreach ($empresas as $empresa): ?>
                    <div class="empresa-card bg-gray-50 rounded-xl p-4 md:p-6 hover:shadow-lg transition <?php echo !$empresa['ativo'] ? 'opacity-50' : ''; ?>">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3 md:gap-4">
                            <div class="flex items-center gap-3 md:gap-4 flex-1 w-full">
                                <div class="empresa-avatar w-12 h-12 md:w-16 md:h-16 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white text-lg md:text-2xl font-bold flex-shrink-0">
                                    <?php echo strtoupper(substr($empresa['nome'], 0, 2)); ?>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-lg md:text-xl font-bold text-gray-900 mb-1 truncate"><?php echo htmlspecialchars($empresa['nome']); ?></h4>
                                    <div class="empresa-info-grid grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-4 text-xs md:text-sm mt-2">
                                        <div>
                                            <p class="text-gray-600">Email</p>
                                            <p class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($empresa['email']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Telefone</p>
                                            <p class="font-semibold text-gray-900"><?php echo $empresa['telefone'] ? formatarTelefone($empresa['telefone']) : '-'; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Cadastro</p>
                                            <p class="font-semibold text-gray-900"><?php echo formatarData($empresa['data_criacao']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="empresa-actions flex flex-col gap-2 w-full md:w-auto">
                                <?php if ($empresa['ativo']): ?>
                                    <span class="px-3 md:px-4 py-2 bg-green-100 text-green-800 rounded-lg text-xs md:text-sm font-semibold text-center">
                                        <i class="fas fa-check-circle"></i> Ativa
                                    </span>
                                    <?php if ($empresa['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" action="" onsubmit="return confirm('Desativar esta empresa? Ela não poderá mais acessar o sistema.')">
                                            <input type="hidden" name="action" value="desativar_empresa">
                                            <input type="hidden" name="empresa_id" value="<?php echo $empresa['id']; ?>">
                                            <button type="submit" class="w-full bg-red-500 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-red-600 transition text-xs md:text-sm font-semibold">
                                                <i class="fas fa-ban"></i> Desativar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="px-3 md:px-4 py-2 bg-red-100 text-red-800 rounded-lg text-xs md:text-sm font-semibold text-center">
                                        <i class="fas fa-times-circle"></i> Inativa
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tab: Geral -->
<div id="content-geral" class="tab-content <?php echo isSuperAdmin() ? 'hidden' : ''; ?>">
    <div class="white-card p-3 md:p-4">
        <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-4 md:mb-6">
            <i class="fas fa-building text-purple-600"></i> Configurações Gerais <?php echo isSuperAdmin() ? 'do Sistema' : 'da Empresa'; ?>
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="salvar_config">
            
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome da Empresa <?php echo isSuperAdmin() ? 'Master' : ''; ?> *</label>
                    <input type="text" name="empresa_nome" required
                           value="<?php echo htmlspecialchars($configs['empresa_nome'] ?? 'PaperArt - Sistema Multi-Empresa'); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">CNPJ</label>
                    <input type="text" name="empresa_cnpj"
                           value="<?php echo htmlspecialchars($configs['empresa_cnpj'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="00.000.000/0000-00">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone *</label>
                    <input type="text" name="empresa_telefone" required
                           value="<?php echo htmlspecialchars($configs['empresa_telefone'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="(00) 00000-0000"
                           data-mask="telefone">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                    <input type="email" name="empresa_email" required
                           value="<?php echo htmlspecialchars($configs['empresa_email'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="contato@empresa.com">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Endereço</label>
                    <input type="text" name="empresa_endereco"
                           value="<?php echo htmlspecialchars($configs['empresa_endereco'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Prazo de Entrega Padrão (dias)</label>
                    <input type="number" name="prazo_entrega_padrao" min="1"
                           value="<?php echo $configs['prazo_entrega_padrao'] ?? 7; ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Alerta de Estoque Baixo</label>
                    <input type="number" name="estoque_alerta" min="1"
                           value="<?php echo $configs['estoque_alerta'] ?? 10; ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                </div>
            </div>
            
            <div class="mt-4 md:mt-6">
                <button type="submit" class="w-full md:w-auto btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tab: Sistema (SOMENTE SUPER ADMIN) -->
<?php if (isSuperAdmin()): ?>
<div id="content-sistema" class="tab-content hidden">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
        <div class="white-card p-3 md:p-4">
            <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-4 md:mb-6">
                <i class="fas fa-info-circle text-purple-600"></i> Informações do Sistema
            </h3>
            
            <div class="space-y-3 md:space-y-4">
                <div class="flex justify-between items-center p-2 md:p-3 bg-gray-50 rounded-lg">
                    <span class="text-gray-700 font-medium text-sm md:text-base">Versão</span>
                    <span class="text-purple-600 font-bold text-sm md:text-base">1.0.0 Multi-Tenant</span>
                </div>
                <div class="flex justify-between items-center p-2 md:p-3 bg-gray-50 rounded-lg">
                    <span class="text-gray-700 font-medium text-sm md:text-base">PHP</span>
                    <span class="text-purple-600 font-bold text-sm md:text-base"><?php echo phpversion(); ?></span>
                </div>
                <div class="flex justify-between items-center p-2 md:p-3 bg-gray-50 rounded-lg">
                    <span class="text-gray-700 font-medium text-sm md:text-base">Banco de Dados</span>
                    <span class="text-purple-600 font-bold text-sm md:text-base">MySQL</span>
                </div>
            </div>
        </div>
        
        <div class="white-card p-3 md:p-4">
            <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-4 md:mb-6">
                <i class="fas fa-history text-purple-600"></i> Atividades Recentes
            </h3>
            
            <?php
            try {
                $stmt = $db->query("SELECT l.*, u.nome FROM log_atividades l LEFT JOIN usuarios u ON l.usuario_id = u.id ORDER BY l.data_hora DESC LIMIT 10");
                $logs = $stmt->fetchAll();
            } catch (PDOException $e) {
                $logs = [];
            }
            ?>
            
            <div class="space-y-2 md:space-y-3">
                <?php if (empty($logs)): ?>
                    <p class="text-gray-500 text-center py-4 text-sm md:text-base">Nenhuma atividade</p>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="flex items-start gap-2 md:gap-3 p-2 md:p-3 bg-gray-50 rounded-lg">
                            <div class="bg-purple-100 p-1.5 md:p-2 rounded-lg flex-shrink-0">
                                <i class="fas fa-circle text-purple-600 text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs md:text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($log['acao']); ?></p>
                                <p class="text-xs text-gray-600 truncate">
                                    <?php echo htmlspecialchars($log['nome'] ?? 'Sistema'); ?> • 
                                    <?php echo formatarData($log['data_hora'], 'd/m/Y H:i'); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Nova Empresa (SOMENTE SUPER ADMIN) -->
<?php if (isSuperAdmin()): ?>
<div id="modalEmpresa" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-screen overflow-y-auto">
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-4 md:p-6 rounded-t-2xl flex items-center justify-between sticky top-0 z-10">
            <h3 class="text-xl md:text-2xl font-bold">
                <i class="fas fa-store"></i> Nova Empresa
            </h3>
            <button onclick="fecharModalEmpresa()" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-xl md:text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" action="" class="p-4 md:p-6">
            <input type="hidden" name="action" value="criar_empresa">
            
            <div class="space-y-4">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-3 md:p-4 rounded">
                    <p class="text-blue-800 text-xs md:text-sm font-semibold">
                        <i class="fas fa-info-circle"></i> Informação Importante
                    </p>
                    <p class="text-blue-700 text-xs md:text-sm mt-1">
                        Cada empresa terá acesso <strong>totalmente isolado</strong> aos seus dados. 
                        Nenhuma empresa poderá ver informações de outras empresas.
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome da Empresa *</label>
                    <input type="text" name="nome_empresa" required
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Ex: DEH Personalizados">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email de Login *</label>
                        <input type="email" name="email_empresa" required
                               class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                               placeholder="contato@empresa.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone</label>
                        <input type="text" name="telefone_empresa"
                               class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                               placeholder="(00) 00000-0000"
                               data-mask="telefone">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Senha *</label>
                    <input type="password" name="senha_empresa" required
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Mínimo 6 caracteres"
                           minlength="6">
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row gap-3 mt-6 pt-6 border-t">
                <button type="button" onclick="fecharModalEmpresa()" 
                        class="flex-1 px-4 md:px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition text-sm md:text-base">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 btn btn-primary">
                    <i class="fas fa-plus"></i> Criar Empresa
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function mostrarTab(tab) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(b => {
        b.classList.remove('border-purple-600', 'text-purple-600');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('content-' + tab).classList.remove('hidden');
    const btn = document.getElementById('tab-' + tab);
    btn.classList.remove('border-transparent', 'text-gray-500');
    btn.classList.add('border-purple-600', 'text-purple-600');
}

<?php if (isSuperAdmin()): ?>
function abrirModalEmpresa() {
    document.getElementById('modalEmpresa').style.display = 'flex';
}

function fecharModalEmpresa() {
    document.getElementById('modalEmpresa').style.display = 'none';
}

// Fechar com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalEmpresa();
    }
});

// Fechar clicando fora
document.getElementById('modalEmpresa')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalEmpresa();
    }
});
<?php endif; ?>

// Inicializar primeira tab
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isSuperAdmin()): ?>
        mostrarTab('empresas');
    <?php else: ?>
        mostrarTab('geral');
    <?php endif; ?>
});
</script>

<?php require_once 'footer.php'; ?>