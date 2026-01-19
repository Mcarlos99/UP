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
$pageTitle = 'Configurações do Catálogo';
$pageSubtitle = 'Personalize as cores, logo e informações do seu catálogo online';

$empresaId = getEmpresaId();
$db = getDB();

// Processar upload de logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    try {
        $uploadDir = 'uploads/logos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['logo'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Formato de arquivo não permitido. Use: JPG, PNG, GIF, SVG ou WEBP');
        }
        
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB
            throw new Exception('Arquivo muito grande. Tamanho máximo: 2MB');
        }
        
        $newFileName = 'logo_empresa_' . $empresaId . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Atualizar logo no banco
            $stmt = $db->prepare("UPDATE configuracoes_catalogo SET logo_url = ? WHERE empresa_id = ?");
            $stmt->execute([$uploadPath, $empresaId]);
            
            $_SESSION['success'] = 'Logo atualizada com sucesso!';
        } else {
            throw new Exception('Erro ao fazer upload do arquivo');
        }
        
        header('Location: configuracoes-catalogo.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Processar formulário de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    try {
        $corPrimaria = sanitize($_POST['cor_primaria']);
        $corSecundaria = sanitize($_POST['cor_secundaria']);
        $corDestaque = sanitize($_POST['cor_destaque']);
        $corTexto = sanitize($_POST['cor_texto']);
        $corFundo = sanitize($_POST['cor_fundo']);
        
        $tituloCatalogo = sanitize($_POST['titulo_catalogo']);
        $subtituloCatalogo = sanitize($_POST['subtitulo_catalogo']);
        $mensagemBoasVindas = sanitize($_POST['mensagem_boas_vindas']);
        $rodapeTexto = sanitize($_POST['rodape_texto']);
        
        $whatsappComercial = sanitize($_POST['whatsapp_comercial']);
        $instagramUrl = sanitize($_POST['instagram_url']);
        $facebookUrl = sanitize($_POST['facebook_url']);
        
        $mostrarEstoque = isset($_POST['mostrar_estoque']) ? 1 : 0;
        $mostrarCodigoProduto = isset($_POST['mostrar_codigo_produto']) ? 1 : 0;
        $permitirPedidoSemEstoque = isset($_POST['permitir_pedido_sem_estoque']) ? 1 : 0;
        
        $produtosPorLinha = sanitize($_POST['produtos_por_linha']);
        $logoTamanho = sanitize($_POST['logo_tamanho']);
        
        $valorMinimoFreteGratis = !empty($_POST['valor_minimo_frete_gratis']) ? (float)$_POST['valor_minimo_frete_gratis'] : null;
        $mensagemFrete = sanitize($_POST['mensagem_frete']);
        $horarioAtendimento = sanitize($_POST['horario_atendimento']);
        
        $catalogoAtivo = isset($_POST['catalogo_ativo']) ? 1 : 0;
        $mensagemCatalogoInativo = sanitize($_POST['mensagem_catalogo_inativo']);
        
        // Verificar se já existe configuração
        $stmt = $db->prepare("SELECT id FROM configuracoes_catalogo WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            // UPDATE
            $stmt = $db->prepare("
                UPDATE configuracoes_catalogo SET
                    cor_primaria = ?,
                    cor_secundaria = ?,
                    cor_destaque = ?,
                    cor_texto = ?,
                    cor_fundo = ?,
                    titulo_catalogo = ?,
                    subtitulo_catalogo = ?,
                    mensagem_boas_vindas = ?,
                    rodape_texto = ?,
                    whatsapp_comercial = ?,
                    instagram_url = ?,
                    facebook_url = ?,
                    mostrar_estoque = ?,
                    mostrar_codigo_produto = ?,
                    permitir_pedido_sem_estoque = ?,
                    produtos_por_linha = ?,
                    logo_tamanho = ?,
                    valor_minimo_frete_gratis = ?,
                    mensagem_frete = ?,
                    horario_atendimento = ?,
                    catalogo_ativo = ?,
                    mensagem_catalogo_inativo = ?
                WHERE empresa_id = ?
            ");
            
            $stmt->execute([
                $corPrimaria, $corSecundaria, $corDestaque, $corTexto, $corFundo,
                $tituloCatalogo, $subtituloCatalogo, $mensagemBoasVindas, $rodapeTexto,
                $whatsappComercial, $instagramUrl, $facebookUrl,
                $mostrarEstoque, $mostrarCodigoProduto, $permitirPedidoSemEstoque,
                $produtosPorLinha, $logoTamanho,
                $valorMinimoFreteGratis, $mensagemFrete, $horarioAtendimento,
                $catalogoAtivo, $mensagemCatalogoInativo,
                $empresaId
            ]);
        } else {
            // INSERT
            $stmt = $db->prepare("
                INSERT INTO configuracoes_catalogo (
                    empresa_id, cor_primaria, cor_secundaria, cor_destaque, cor_texto, cor_fundo,
                    titulo_catalogo, subtitulo_catalogo, mensagem_boas_vindas, rodape_texto,
                    whatsapp_comercial, instagram_url, facebook_url,
                    mostrar_estoque, mostrar_codigo_produto, permitir_pedido_sem_estoque,
                    produtos_por_linha, logo_tamanho,
                    valor_minimo_frete_gratis, mensagem_frete, horario_atendimento,
                    catalogo_ativo, mensagem_catalogo_inativo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresaId, $corPrimaria, $corSecundaria, $corDestaque, $corTexto, $corFundo,
                $tituloCatalogo, $subtituloCatalogo, $mensagemBoasVindas, $rodapeTexto,
                $whatsappComercial, $instagramUrl, $facebookUrl,
                $mostrarEstoque, $mostrarCodigoProduto, $permitirPedidoSemEstoque,
                $produtosPorLinha, $logoTamanho,
                $valorMinimoFreteGratis, $mensagemFrete, $horarioAtendimento,
                $catalogoAtivo, $mensagemCatalogoInativo
            ]);
        }
        
        logActivity('Configurações do catálogo atualizadas', 'configuracoes_catalogo', $empresaId);
        $_SESSION['success'] = 'Configurações salvas com sucesso!';
        
        header('Location: configuracoes-catalogo.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao salvar: ' . $e->getMessage();
    }
}

// Buscar configurações atuais
try {
    $stmt = $db->prepare("SELECT * FROM configuracoes_catalogo WHERE empresa_id = ?");
    $stmt->execute([$empresaId]);
    $config = $stmt->fetch();
    
    // Se não existir, criar configuração padrão
    if (!$config) {
        $user = getUser();
        $stmt = $db->prepare("
            INSERT INTO configuracoes_catalogo (empresa_id, titulo_catalogo, subtitulo_catalogo)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $empresaId, 
            $user['nome'], 
            'Confira nossos produtos e faça seu pedido online!'
        ]);
        
        $stmt = $db->prepare("SELECT * FROM configuracoes_catalogo WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        $config = $stmt->fetch();
    }
} catch (PDOException $e) {
    $config = [
        'cor_primaria' => '#8B5CF6',
        'cor_secundaria' => '#EC4899',
        'cor_destaque' => '#10B981',
        'cor_texto' => '#1F2937',
        'cor_fundo' => '#F9FAFB',
        'titulo_catalogo' => '',
        'subtitulo_catalogo' => '',
        'produtos_por_linha' => '3',
        'logo_tamanho' => 'media',
        'catalogo_ativo' => 1
    ];
}

// Link do catálogo
$linkCatalogo = SITE_URL . 'catalogo.php?empresa=' . $empresaId;

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6">
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <p class="font-bold">Sucesso!</p>
            <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <p class="font-bold">Erro!</p>
            <p><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Preview do Link -->
    <div class="white-card mb-6">
        <h2 class="text-xl font-bold mb-4">
            <i class="fas fa-link"></i> Link do Seu Catálogo
        </h2>
        <div class="flex items-center gap-4">
            <input type="text" 
                   value="<?php echo $linkCatalogo; ?>" 
                   id="link-catalogo"
                   readonly
                   class="flex-1 px-4 py-3 border rounded-lg bg-gray-50 font-mono text-sm">
            <button onclick="copiarLink()" class="btn-primary">
                <i class="fas fa-copy"></i> Copiar
            </button>
            <a href="<?php echo $linkCatalogo; ?>" target="_blank" class="btn-secondary">
                <i class="fas fa-external-link-alt"></i> Visualizar
            </a>
        </div>
    </div>
    
    <form method="POST" enctype="multipart/form-data" id="form-config">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Coluna Esquerda: Cores e Logo -->
            <div class="space-y-6">
                
                <!-- Logo -->
                <div class="white-card">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-image"></i> Logo da Empresa
                    </h2>
                    
                    <?php if (!empty($config['logo_url']) && file_exists($config['logo_url'])): ?>
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg text-center">
                            <img src="<?php echo $config['logo_url']; ?>" 
                                 alt="Logo atual" 
                                 class="max-h-32 mx-auto object-contain">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="block text-sm font-semibold mb-2">Tamanho da Logo</label>
                        <select name="logo_tamanho" class="input-field">
                            <option value="pequena" <?php echo ($config['logo_tamanho'] ?? '') === 'pequena' ? 'selected' : ''; ?>>Pequena</option>
                            <option value="media" <?php echo ($config['logo_tamanho'] ?? 'media') === 'media' ? 'selected' : ''; ?>>Média</option>
                            <option value="grande" <?php echo ($config['logo_tamanho'] ?? '') === 'grande' ? 'selected' : ''; ?>>Grande</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="block text-sm font-semibold mb-2">Alterar Logo</label>
                        <input type="file" 
                               name="logo" 
                               accept="image/*"
                               class="input-field"
                               onchange="this.form.submit()">
                        <p class="text-xs text-gray-500 mt-1">
                            Formatos: JPG, PNG, GIF, SVG, WEBP | Tamanho máximo: 2MB
                        </p>
                    </div>
                </div>
                
                <!-- Cores -->
                <div class="white-card">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-palette"></i> Cores do Catálogo
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="form-group">
                            <label class="flex items-center justify-between text-sm font-semibold mb-2">
                                Cor Primária
                                <span class="text-xs font-normal text-gray-500">Botões e destaques</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="color" 
                                       name="cor_primaria" 
                                       value="<?php echo $config['cor_primaria'] ?? '#8B5CF6'; ?>"
                                       class="h-12 w-20 border-0 rounded cursor-pointer">
                                <input type="text" 
                                       value="<?php echo $config['cor_primaria'] ?? '#8B5CF6'; ?>"
                                       class="input-field flex-1 font-mono"
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="flex items-center justify-between text-sm font-semibold mb-2">
                                Cor Secundária
                                <span class="text-xs font-normal text-gray-500">Gradientes</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="color" 
                                       name="cor_secundaria" 
                                       value="<?php echo $config['cor_secundaria'] ?? '#EC4899'; ?>"
                                       class="h-12 w-20 border-0 rounded cursor-pointer">
                                <input type="text" 
                                       value="<?php echo $config['cor_secundaria'] ?? '#EC4899'; ?>"
                                       class="input-field flex-1 font-mono"
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="flex items-center justify-between text-sm font-semibold mb-2">
                                Cor de Destaque
                                <span class="text-xs font-normal text-gray-500">Preços e ícones</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="color" 
                                       name="cor_destaque" 
                                       value="<?php echo $config['cor_destaque'] ?? '#10B981'; ?>"
                                       class="h-12 w-20 border-0 rounded cursor-pointer">
                                <input type="text" 
                                       value="<?php echo $config['cor_destaque'] ?? '#10B981'; ?>"
                                       class="input-field flex-1 font-mono"
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="flex items-center justify-between text-sm font-semibold mb-2">
                                Cor do Texto
                                <span class="text-xs font-normal text-gray-500">Texto principal</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="color" 
                                       name="cor_texto" 
                                       value="<?php echo $config['cor_texto'] ?? '#1F2937'; ?>"
                                       class="h-12 w-20 border-0 rounded cursor-pointer">
                                <input type="text" 
                                       value="<?php echo $config['cor_texto'] ?? '#1F2937'; ?>"
                                       class="input-field flex-1 font-mono"
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="flex items-center justify-between text-sm font-semibold mb-2">
                                Cor de Fundo
                                <span class="text-xs font-normal text-gray-500">Fundo da página</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="color" 
                                       name="cor_fundo" 
                                       value="<?php echo $config['cor_fundo'] ?? '#F9FAFB'; ?>"
                                       class="h-12 w-20 border-0 rounded cursor-pointer">
                                <input type="text" 
                                       value="<?php echo $config['cor_fundo'] ?? '#F9FAFB'; ?>"
                                       class="input-field flex-1 font-mono"
                                       readonly>
                            </div>
                        </div>
                        
                        <button type="button" 
                                onclick="restaurarCoresPadrao()"
                                class="w-full text-sm text-purple-600 hover:text-purple-700 font-semibold">
                            <i class="fas fa-undo"></i> Restaurar Cores Padrão
                        </button>
                    </div>
                </div>
                
            </div>
            
            <!-- Coluna Central: Textos e Informações -->
            <div class="space-y-6">
                
                <!-- Textos -->
                <div class="white-card">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-font"></i> Textos do Catálogo
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Título do Catálogo</label>
                            <input type="text" 
                                   name="titulo_catalogo" 
                                   value="<?php echo htmlspecialchars($config['titulo_catalogo'] ?? ''); ?>"
                                   class="input-field"
                                   placeholder="Ex: Papelaria PapelOn">
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Subtítulo</label>
                            <input type="text" 
                                   name="subtitulo_catalogo" 
                                   value="<?php echo htmlspecialchars($config['subtitulo_catalogo'] ?? ''); ?>"
                                   class="input-field"
                                   placeholder="Ex: Produtos personalizados para todas as ocasiões">
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Mensagem de Boas-Vindas</label>
                            <textarea name="mensagem_boas_vindas" 
                                      rows="3"
                                      class="input-field"
                                      placeholder="Ex: Bem-vindo ao nosso catálogo! Aqui você encontra os melhores produtos..."><?php echo htmlspecialchars($config['mensagem_boas_vindas'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Texto do Rodapé</label>
                            <input type="text" 
                                   name="rodape_texto" 
                                   value="<?php echo htmlspecialchars($config['rodape_texto'] ?? ''); ?>"
                                   class="input-field"
                                   placeholder="Ex: © 2026 Papelaria PapelOn - Todos os direitos reservados">
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Horário de Atendimento</label>
                            <textarea name="horario_atendimento" 
                                      rows="2"
                                      class="input-field"
                                      placeholder="Ex: Seg a Sex: 8h às 18h | Sáb: 8h às 12h"><?php echo htmlspecialchars($config['horario_atendimento'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Redes Sociais -->
                <div class="white-card">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-share-alt"></i> Redes Sociais
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">
                                <i class="fab fa-whatsapp text-green-600"></i> WhatsApp Comercial
                            </label>
                            <input type="text" 
                                   name="whatsapp_comercial" 
                                   value="<?php echo htmlspecialchars($config['whatsapp_comercial'] ?? ''); ?>"
                                   class="input-field"
                                   placeholder="Ex: (11) 98765-4321">
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">
                                <i class="fab fa-instagram text-pink-600"></i> Instagram
                            </label>
                            <input type="text" 
                                   name="instagram_url" 
                                   value="<?php echo htmlspecialchars($config['instagram_url'] ?? ''); ?>"
                                   class="input-field"
                                   placeholder="Ex: https://instagram.com/suaempresa">
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">
                                <i class="fab fa-facebook text-blue-600"></i> Facebook
                            </label>
                            <input type="text" 
                                   name="facebook_url" 
                                   value="<?php echo htmlspecialchars($config['facebook_url'] ?? ''); ?>"
                                   class="input-field"
                                   placeholder="Ex: https://facebook.com/suaempresa">
                        </div>
                    </div>
                </div>
                
                <!-- Frete -->
                <div class="white-card">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-truck"></i> Configurações de Frete
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Valor Mínimo para Frete Grátis</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-500">R$</span>
                                <input type="number" 
                                       name="valor_minimo_frete_gratis" 
                                       value="<?php echo $config['valor_minimo_frete_gratis'] ?? ''; ?>"
                                       step="0.01"
                                       class="input-field pl-10"
                                       placeholder="Ex: 100.00">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Deixe em branco se não oferece frete grátis</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Mensagem sobre Frete</label>
                            <textarea name="mensagem_frete" 
                                      rows="2"
                                      class="input-field"
                                      placeholder="Ex: Entregamos em toda a cidade. Consulte o valor do frete."><?php echo htmlspecialchars($config['mensagem_frete'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Coluna Direita: Configurações de Exibição -->
            <div class="space-y-6">
                
                <!-- Exibição -->
                <div class="white-card">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-cog"></i> Configurações de Exibição
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Produtos por Linha</label>
                            <select name="produtos_por_linha" class="input-field">
                                <option value="2" <?php echo ($config['produtos_por_linha'] ?? '3') === '2' ? 'selected' : ''; ?>>2 produtos</option>
                                <option value="3" <?php echo ($config['produtos_por_linha'] ?? '3') === '3' ? 'selected' : ''; ?>>3 produtos</option>
                                <option value="4" <?php echo ($config['produtos_por_linha'] ?? '3') === '4' ? 'selected' : ''; ?>>4 produtos</option>
                            </select>
                        </div>
                        
                        <div class="border-t pt-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" 
                                       name="mostrar_estoque" 
                                       value="1"
                                       <?php echo ($config['mostrar_estoque'] ?? true) ? 'checked' : ''; ?>
                                       class="w-5 h-5 text-purple-600 rounded">
                                <div class="flex-1">
                                    <p class="font-semibold">Mostrar Estoque</p>
                                    <p class="text-xs text-gray-500">Exibir quantidade disponível dos produtos</p>
                                </div>
                            </label>
                        </div>
                        
                        <div class="border-t pt-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" 
                                       name="mostrar_codigo_produto" 
                                       value="1"
                                       <?php echo ($config['mostrar_codigo_produto'] ?? false) ? 'checked' : ''; ?>
                                       class="w-5 h-5 text-purple-600 rounded">
                                <div class="flex-1">
                                    <p class="font-semibold">Mostrar Código do Produto</p>
                                    <p class="text-xs text-gray-500">Exibir código/SKU nos produtos</p>
                                </div>
                            </label>
                        </div>
                        
                        <div class="border-t pt-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" 
                                       name="permitir_pedido_sem_estoque" 
                                       value="1"
                                       <?php echo ($config['permitir_pedido_sem_estoque'] ?? false) ? 'checked' : ''; ?>
                                       class="w-5 h-5 text-purple-600 rounded">
                                <div class="flex-1">
                                    <p class="font-semibold">Permitir Pedido Sem Estoque</p>
                                    <p class="text-xs text-gray-500">Clientes podem pedir produtos esgotados</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Status do Catálogo -->
                <div class="white-card">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-power-off"></i> Status do Catálogo
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="border-l-4 border-green-500 bg-green-50 p-4 rounded">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" 
                                       name="catalogo_ativo" 
                                       value="1"
                                       <?php echo ($config['catalogo_ativo'] ?? true) ? 'checked' : ''; ?>
                                       class="w-6 h-6 text-green-600 rounded">
                                <div class="flex-1">
                                    <p class="font-bold text-green-900">Catálogo Ativo</p>
                                    <p class="text-sm text-green-700">Clientes podem acessar e fazer pedidos</p>
                                </div>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="block text-sm font-semibold mb-2">Mensagem Quando Inativo</label>
                            <textarea name="mensagem_catalogo_inativo" 
                                      rows="3"
                                      class="input-field"
                                      placeholder="Ex: Nosso catálogo está temporariamente indisponível. Volte em breve!"><?php echo htmlspecialchars($config['mensagem_catalogo_inativo'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Preview -->
                <div class="white-card bg-gradient-to-r from-purple-600 to-pink-600 text-white">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-eye"></i> Preview ao Vivo
                    </h2>
                    <p class="mb-4 text-sm opacity-90">
                        Veja como está ficando o seu catálogo em tempo real
                    </p>
                    <a href="<?php echo $linkCatalogo; ?>" 
                       target="_blank"
                       class="block w-full bg-white text-purple-600 px-6 py-3 rounded-lg font-bold hover:bg-gray-100 transition text-center">
                        <i class="fas fa-external-link-alt"></i> Visualizar Catálogo
                    </a>
                </div>
                
            </div>
            
        </div>
        
        <!-- Botão Salvar -->
        <div class="mt-6 sticky bottom-4 z-10">
            <button type="submit" name="salvar_config" class="w-full btn-primary text-lg py-4 shadow-2xl">
                <i class="fas fa-save"></i> Salvar Todas as Configurações
            </button>
        </div>
        
    </form>
    
</div>

<script>
function copiarLink() {
    const input = document.getElementById('link-catalogo');
    input.select();
    document.execCommand('copy');
    
    alert('Link copiado para a área de transferência!');
}

function restaurarCoresPadrao() {
    if (confirm('Deseja restaurar as cores padrão do sistema?')) {
        document.querySelector('input[name="cor_primaria"]').value = '#8B5CF6';
        document.querySelector('input[name="cor_secundaria"]').value = '#EC4899';
        document.querySelector('input[name="cor_destaque"]').value = '#10B981';
        document.querySelector('input[name="cor_texto"]').value = '#1F2937';
        document.querySelector('input[name="cor_fundo"]').value = '#F9FAFB';
        
        // Atualizar os inputs de texto também
        document.querySelectorAll('input[type="text"][readonly]').forEach((input, index) => {
            const cores = ['#8B5CF6', '#EC4899', '#10B981', '#1F2937', '#F9FAFB'];
            input.value = cores[index];
        });
    }
}

// Sincronizar color picker com input de texto
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    colorInput.addEventListener('input', function() {
        this.nextElementSibling.nextElementSibling.value = this.value.toUpperCase();
    });
});
</script>

<?php require_once 'footer.php'; ?>
