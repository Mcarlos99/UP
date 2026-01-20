<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'config.php';
require_once 'config_multitenant.php';
require_once 'EmailNotificacao.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if (!isAdmin()) {
    $_SESSION['error'] = 'Acesso negado! Apenas administradores podem acessar esta área.';
    header('Location: dashboard.php');
    exit;
}

$empresaId = getEmpresaId();
$db = getDB();

// ================== TESTE DE ENVIO DE EMAIL ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'testar_email') {

    try {
        if (empty($_POST['email_teste'])) {
            throw new Exception('Email de teste não informado');
        }

        $emailTeste = filter_var($_POST['email_teste'], FILTER_VALIDATE_EMAIL);
        if (!$emailTeste) {
            throw new Exception('Email inválido');
        }

        $email = new EmailNotificacao($db, $empresaId);
        $sucesso = $email->enviarEmailTeste($emailTeste);

        $_SESSION['success'] = $sucesso
            ? 'Email de teste enviado com sucesso para ' . $emailTeste
            : 'Falha ao enviar email de teste';

    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro no teste de email: ' . $e->getMessage();
    }

    header('Location: configuracoes.php');
    exit;
}
// =============================================================
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

    
    // Salvar configurações de email
    if (isset($_POST['action']) && $_POST['action'] === 'salvar_config_email') {
        try {
            $email_notificacao = sanitize($_POST['email_notificacao']);
            $nome_empresa_email = sanitize($_POST['nome_empresa_email'] ?? '');
            $telefone_email = sanitize($_POST['telefone_email'] ?? '');
            $enviar_email = isset($_POST['enviar_email_pedido']) ? 1 : 0;
            $mensagem_email = sanitize($_POST['mensagem_email_pedido']);
            $cor_primaria = sanitize($_POST['cor_primaria'] ?? '#4F46E5');
            $cor_secundaria = sanitize($_POST['cor_secundaria'] ?? '#10B981');
            
            if (!validarEmail($email_notificacao)) {
                throw new Exception('Email de notificação inválido');
            }
            
            // Verificar se já existe configuração
            $stmt = $db->prepare("SELECT id FROM configuracoes_empresa WHERE empresa_id = ?");
            $stmt->execute([$empresaId]);
            $existe = $stmt->fetch();
            
            if ($existe) {
                // Atualizar
                $stmt = $db->prepare("
                    UPDATE configuracoes_empresa 
                    SET email_notificacao = ?,
                        nome_empresa = ?,
                        telefone = ?,
                        enviar_email_pedido = ?,
                        mensagem_email_pedido = ?,
                        cor_primaria = ?,
                        cor_secundaria = ?
                    WHERE empresa_id = ?
                ");
                $stmt->execute([
                    $email_notificacao,
                    $nome_empresa_email,
                    $telefone_email,
                    $enviar_email,
                    $mensagem_email,
                    $cor_primaria,
                    $cor_secundaria,
                    $empresaId
                ]);
            } else {
                // Inserir
                $stmt = $db->prepare("
                    INSERT INTO configuracoes_empresa 
                    (empresa_id, email_notificacao, nome_empresa, telefone, enviar_email_pedido, mensagem_email_pedido, cor_primaria, cor_secundaria) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $empresaId,
                    $email_notificacao,
                    $nome_empresa_email,
                    $telefone_email,
                    $enviar_email,
                    $mensagem_email,
                    $cor_primaria,
                    $cor_secundaria
                ]);
            }
            
            logActivity('Configurações de email atualizadas', 'configuracoes_empresa');
            $_SESSION['success'] = 'Configurações de email salvas com sucesso!';
            header('Location: configuracoes.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao salvar configurações de email: ' . $e->getMessage();
        }
    }
    
    // Testar envio de email
    if (isset($_POST['action']) && $_POST['action'] === 'testar_email') {
        try {
            $email_teste = sanitize($_POST['email_teste']);
            
            if (!validarEmail($email_teste)) {
                throw new Exception('Email de teste inválido');
            }
            
            // Buscar configurações
            $stmt = $db->prepare("SELECT * FROM configuracoes_empresa WHERE empresa_id = ?");
            $stmt->execute([$empresaId]);
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception('Configure o email de notificação primeiro');
            }
            
            // Montar email
            $assunto = 'Teste de Notificação - ' . ($config['nome_empresa'] ?? 'Sistema');
            $corpo = "Este é um email de teste do sistema PapelOn.\n\n";
            $corpo .= "Se você recebeu este email, significa que as notificações estão funcionando corretamente!\n\n";
            $corpo .= "Empresa: " . ($config['nome_empresa'] ?? 'N/A') . "\n";
            $corpo .= "Email: " . $config['email_notificacao'] . "\n";
            $corpo .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";
            $corpo .= "---\n";
            $corpo .= "Sistema PapelOn Multi-Tenant";
            
            $headers = "From: " . $config['email_notificacao'] . "\r\n";
            $headers .= "Reply-To: " . $config['email_notificacao'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            $sucesso = mail($email_teste, $assunto, $corpo, $headers);
            
            // Registrar log
            $stmt = $db->prepare("
                INSERT INTO logs_email (empresa_id, destinatario, assunto, sucesso, data_envio)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$empresaId, $email_teste, $assunto, $sucesso ? 1 : 0]);
            
            if ($sucesso) {
                $_SESSION['success'] = 'Email de teste enviado com sucesso para ' . $email_teste;
            } else {
                throw new Exception('Falha ao enviar email. Verifique a configuração do servidor.');
            }
            
            header('Location: configuracoes.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao testar email: ' . $e->getMessage();
            header('Location: configuracoes.php');
            exit;
        }
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
            
             <button onclick="mostrarTab('email')" id="tab-email" class="tab-button border-b-2 border-transparent py-3 md:py-4 px-1 text-gray-500 hover:text-gray-700 font-semibold text-sm md:text-base">
            
                <i class="fas fa-envelope"></i> <span class="hidden md:inline">Email</span>
            
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
<!-- Tab: Notificações por Email -->
<div id="content-email" class="tab-content hidden">
    <div class="white-card p-3 md:p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg md:text-2xl font-bold text-gray-900">
                <i class="fas fa-envelope text-purple-600"></i> Configurações de Email
            </h3>
        </div>

        <?php
        // Buscar ou criar configuração de email
        try {
            $stmt = $db->prepare("SELECT * FROM configuracoes_empresa WHERE empresa_id = ?");
            $stmt->execute([$empresaId]);
            $configEmail = $stmt->fetch();
            
            if (!$configEmail) {
                // Criar configuração padrão
                $stmt = $db->prepare("
                    INSERT INTO configuracoes_empresa 
                    (empresa_id, email_notificacao, nome_empresa, enviar_email_pedido, mensagem_email_pedido) 
                    VALUES (?, ?, ?, 1, ?)
                ");
                $stmt->execute([
                    $empresaId,
                    $_SESSION['user_email'] ?? 'contato@empresa.com',
                    $_SESSION['user_nome'] ?? 'Empresa',
                    'Você recebeu um novo pedido! Acesse o sistema para visualizar os detalhes.'
                ]);
                
                $stmt = $db->prepare("SELECT * FROM configuracoes_empresa WHERE empresa_id = ?");
                $stmt->execute([$empresaId]);
                $configEmail = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $configEmail = null;
            $erro = "Erro ao carregar configurações de email";
        }
        ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="salvar_config_email">
            
            <div class="space-y-6">
                <!-- Email de Notificação -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-at text-purple-600"></i> Email para Notificações *
                    </label>
                    <input type="email" name="email_notificacao" required
                           value="<?php echo htmlspecialchars($configEmail['email_notificacao'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                           placeholder="seu-email@empresa.com">
                    <p class="text-xs text-gray-500 mt-1">
                        Este email receberá notificações quando novos pedidos forem criados
                    </p>
                </div>

                <!-- Nome da Empresa -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-building text-purple-600"></i> Nome da Empresa
                    </label>
                    <input type="text" name="nome_empresa_email"
                           value="<?php echo htmlspecialchars($configEmail['nome_empresa'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                           placeholder="Nome que aparecerá nos emails">
                </div>

                <!-- Telefone -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-phone text-purple-600"></i> Telefone de Contato
                    </label>
                    <input type="text" name="telefone_email"
                           value="<?php echo htmlspecialchars($configEmail['telefone'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                           placeholder="(00) 00000-0000"
                           data-mask="telefone">
                </div>

                <!-- Checkbox: Enviar Email -->
                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="enviar_email_pedido" 
                               <?php echo ($configEmail['enviar_email_pedido'] ?? 1) ? 'checked' : ''; ?>
                               class="h-5 w-5 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                        <span class="ml-3 text-sm font-semibold text-gray-900">
                            Enviar email automaticamente quando um novo pedido for criado
                        </span>
                    </label>
                </div>

                <!-- Mensagem Personalizada -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-comment-dots text-purple-600"></i> Mensagem Personalizada do Email
                    </label>
                    <textarea name="mensagem_email_pedido" rows="5"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                              placeholder="Digite a mensagem que aparecerá no email de notificação"
                    ><?php echo htmlspecialchars($configEmail['mensagem_email_pedido'] ?? 'Você recebeu um novo pedido! Acesse o sistema para visualizar os detalhes.'); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        Esta mensagem aparecerá no início de cada email de notificação de pedido
                    </p>
                </div>

                <!-- Cores de Personalização -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-palette text-purple-600"></i> Cor Primária
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="cor_primaria"
                                   value="<?php echo htmlspecialchars($configEmail['cor_primaria'] ?? '#4F46E5'); ?>"
                                   class="h-12 w-24 border border-gray-300 rounded cursor-pointer">
                            <span class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($configEmail['cor_primaria'] ?? '#4F46E5'); ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-palette text-purple-600"></i> Cor Secundária
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="cor_secundaria"
                                   value="<?php echo htmlspecialchars($configEmail['cor_secundaria'] ?? '#10B981'); ?>"
                                   class="h-12 w-24 border border-gray-300 rounded cursor-pointer">
                            <span class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($configEmail['cor_secundaria'] ?? '#10B981'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Botão Salvar -->
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="btn btn-primary flex-1">
                        <i class="fas fa-save"></i> Salvar Configurações de Email
                    </button>
                </div>
            </div>
        </form>

        <!-- Seção de Teste -->
        <div class="mt-8 pt-8 border-t border-gray-200">
            <h4 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-flask text-blue-600"></i> Testar Envio de Email
            </h4>
            
            <form method="POST" action="" class="flex gap-3">
                <input type="hidden" name="action" value="testar_email">
                <div class="flex-1">
                    <input type="email" name="email_teste" required
                           placeholder="Digite um email para teste"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold whitespace-nowrap">
                    <i class="fas fa-paper-plane"></i> Enviar Teste
                </button>
            </form>
            
            <p class="text-xs text-gray-500 mt-2">
                <i class="fas fa-info-circle"></i> Um email de teste será enviado para verificar se está tudo funcionando
            </p>
        </div>

        <!-- Logs Recentes -->
        <div class="mt-8 pt-8 border-t border-gray-200">
            <h4 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-history text-gray-600"></i> Últimos Emails Enviados
            </h4>
            
            <?php
            try {
                $stmt = $db->prepare("
                    SELECT * FROM logs_email 
                    WHERE empresa_id = ? 
                    ORDER BY data_envio DESC 
                    LIMIT 10
                ");
                $stmt->execute([$empresaId]);
                $logsEmail = $stmt->fetchAll();
            } catch (PDOException $e) {
                $logsEmail = [];
            }
            ?>

            <div class="space-y-2">
                <?php if (empty($logsEmail)): ?>
                    <p class="text-gray-500 text-center py-4 text-sm">Nenhum email enviado ainda</p>
                <?php else: ?>
                    <?php foreach ($logsEmail as $log): ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="<?php echo $log['sucesso'] ? 'bg-green-100' : 'bg-red-100'; ?> p-2 rounded-lg">
                                <i class="fas <?php echo $log['sucesso'] ? 'fa-check text-green-600' : 'fa-times text-red-600'; ?>"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($log['assunto'] ?? 'Email'); ?>
                                </p>
                                <p class="text-xs text-gray-600">
                                    Para: <?php echo htmlspecialchars($log['destinatario']); ?> • 
                                    <?php echo formatarData($log['data_envio'], 'd/m/Y H:i'); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>