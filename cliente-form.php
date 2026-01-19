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

$empresaId = getEmpresaId();
$db = getDB();

// Modo: criar ou editar
$modo = 'criar';
$cliente = null;

if (isset($_GET['id'])) {
    $modo = 'editar';
    $clienteId = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$clienteId, $empresaId]);
        $cliente = $stmt->fetch();
        
        if (!$cliente) {
            $_SESSION['error'] = 'Cliente não encontrado';
            header('Location: clientes.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar cliente';
        header('Location: clientes.php');
        exit;
    }
}

$pageTitle = $modo === 'criar' ? 'Novo Cliente' : 'Editar Cliente';
$pageSubtitle = $modo === 'criar' ? 'Cadastre um novo cliente' : 'Atualize as informações do cliente';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = sanitize($_POST['nome']);
        $email = sanitize($_POST['email'] ?? '');
        $telefone = sanitize($_POST['telefone']);
        $cpf = sanitize($_POST['cpf'] ?? '');
        $cep = sanitize($_POST['cep'] ?? '');
        $endereco = sanitize($_POST['endereco'] ?? '');
        $numero = sanitize($_POST['numero'] ?? '');
        $complemento = sanitize($_POST['complemento'] ?? '');
        $bairro = sanitize($_POST['bairro'] ?? '');
        $cidade = sanitize($_POST['cidade'] ?? '');
        $estado = sanitize($_POST['estado'] ?? '');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        // Validações
        if (empty($nome)) {
            throw new Exception('Nome é obrigatório');
        }
        
        if (empty($telefone)) {
            throw new Exception('Telefone é obrigatório');
        }
        
        if (!empty($email) && !validarEmail($email)) {
            throw new Exception('Email inválido');
        }
        
        if ($modo === 'criar') {
            // Verificar duplicidade de email
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ? AND empresa_id = ?");
                $stmt->execute([$email, $empresaId]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe um cliente com este email');
                }
            }
            
            $stmt = $db->prepare("
                INSERT INTO clientes (
                    nome, email, telefone, cpf, cep, endereco, numero, 
                    complemento, bairro, cidade, estado, observacoes, empresa_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nome, $email, $telefone, $cpf, $cep, $endereco, $numero,
                $complemento, $bairro, $cidade, $estado, $observacoes, $empresaId
            ]);
            
            $clienteId = $db->lastInsertId();
            logActivity('Cliente criado: ' . $nome, 'clientes', $clienteId);
            
            $_SESSION['success'] = "Cliente '$nome' cadastrado com sucesso!";
            
        } else {
            // Verificar duplicidade de email (exceto o próprio cliente)
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ? AND empresa_id = ? AND id != ?");
                $stmt->execute([$email, $empresaId, $clienteId]);
                if ($stmt->fetch()) {
                    throw new Exception('Já existe outro cliente com este email');
                }
            }
            
            $stmt = $db->prepare("
                UPDATE clientes SET
                    nome = ?, email = ?, telefone = ?, cpf = ?, cep = ?, 
                    endereco = ?, numero = ?, complemento = ?, bairro = ?, 
                    cidade = ?, estado = ?, observacoes = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([
                $nome, $email, $telefone, $cpf, $cep, $endereco, $numero,
                $complemento, $bairro, $cidade, $estado, $observacoes,
                $clienteId, $empresaId
            ]);
            
            logActivity('Cliente editado: ' . $nome, 'clientes', $clienteId);
            
            $_SESSION['success'] = "Cliente '$nome' atualizado com sucesso!";
        }
        
        header('Location: clientes.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao salvar cliente: ' . $e->getMessage();
    }
}

require_once 'header.php';

// Mensagens
if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 md:p-4 mb-4 md:mb-6 rounded">';
    echo '<p class="font-medium text-sm md:text-base"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</p>';
    echo '</div>';
    unset($_SESSION['error']);
}
?>

<style>
/* Responsivo Mobile - Formulários */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }
    
    .form-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem !important;
    }
    
    .form-actions {
        flex-direction: column-reverse !important;
        gap: 0.75rem !important;
    }
    
    .form-actions button,
    .form-actions a {
        width: 100% !important;
        text-align: center !important;
    }
    
    .section-title {
        font-size: 1rem !important;
        padding: 0.75rem !important;
    }
}
</style>

<!-- Cabeçalho do Formulário -->
<div class="mb-4 md:mb-6">
    <div class="form-header flex items-center justify-between">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
                <?php echo $modo === 'criar' ? 'Novo Cliente' : 'Editar Cliente'; ?>
            </h2>
            <p class="text-gray-600 mt-1 text-sm md:text-base">
                <?php echo $modo === 'criar' ? 'Preencha os dados para cadastrar um novo cliente' : 'Atualize as informações do cliente'; ?>
            </p>
        </div>
        <a href="clientes.php" class="hidden md:inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-arrow-left mr-2"></i> Voltar
        </a>
    </div>
</div>

<!-- Formulário -->
<form method="POST" action="" class="space-y-4 md:space-y-6">
    
    <!-- Seção: Dados Pessoais -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-purple-600 to-pink-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-user"></i> Dados Pessoais
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome Completo *</label>
                    <input type="text" name="nome" required
                           value="<?php echo htmlspecialchars($cliente['nome'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Nome completo do cliente">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">CPF</label>
                    <input type="text" name="cpf"
                           value="<?php echo htmlspecialchars($cliente['cpf'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="000.000.000-00"
                           data-mask="cpf">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone *</label>
                    <input type="text" name="telefone" required
                           value="<?php echo htmlspecialchars($cliente['telefone'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="(00) 00000-0000"
                           data-mask="telefone">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" name="email"
                           value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="email@exemplo.com">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Endereço -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-blue-600 to-cyan-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-map-marker-alt"></i> Endereço
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <div class="form-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">CEP</label>
                    <input type="text" name="cep" id="cep"
                           value="<?php echo htmlspecialchars($cliente['cep'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="00000-000"
                           data-mask="cep">
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Preenchimento automático
                    </p>
                </div>
                
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Endereço</label>
                    <input type="text" name="endereco" id="endereco"
                           value="<?php echo htmlspecialchars($cliente['endereco'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Rua, Avenida...">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Número</label>
                    <input type="text" name="numero"
                           value="<?php echo htmlspecialchars($cliente['numero'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="123">
                </div>
                
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Complemento</label>
                    <input type="text" name="complemento"
                           value="<?php echo htmlspecialchars($cliente['complemento'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Apartamento, Bloco...">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Bairro</label>
                    <input type="text" name="bairro" id="bairro"
                           value="<?php echo htmlspecialchars($cliente['bairro'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Bairro">
                </div>
                
                <div class="md:col-span-2 lg:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Cidade</label>
                    <input type="text" name="cidade" id="cidade"
                           value="<?php echo htmlspecialchars($cliente['cidade'] ?? ''); ?>"
                           class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                           placeholder="Cidade">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Estado</label>
                    <select name="estado" id="estado"
                            class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base">
                        <option value="">Selecione</option>
                        <?php
                        $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                        foreach ($estados as $uf) {
                            $selected = (isset($cliente['estado']) && $cliente['estado'] === $uf) ? 'selected' : '';
                            echo "<option value='$uf' $selected>$uf</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seção: Observações -->
    <div class="white-card">
        <div class="section-title bg-gradient-to-r from-green-600 to-emerald-600 text-white p-3 md:p-4 rounded-t-xl mb-4 md:mb-6">
            <h3 class="text-lg md:text-xl font-bold">
                <i class="fas fa-sticky-note"></i> Observações
            </h3>
        </div>
        
        <div class="px-4 md:px-6 pb-4 md:pb-6">
            <textarea name="observacoes" rows="4"
                      class="w-full px-3 md:px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm md:text-base"
                      placeholder="Anotações adicionais sobre o cliente..."><?php echo htmlspecialchars($cliente['observacoes'] ?? ''); ?></textarea>
        </div>
    </div>
    
    <!-- Botões de Ação -->
    <div class="form-actions flex gap-3 pt-4">
        <a href="clientes.php" class="flex-1 md:flex-none px-4 md:px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-semibold text-sm md:text-base">
            <i class="fas fa-times mr-2"></i> Cancelar
        </a>
        <button type="submit" class="flex-1 md:flex-none btn btn-primary text-sm md:text-base">
            <i class="fas fa-save mr-2"></i>
            <?php echo $modo === 'criar' ? 'Cadastrar Cliente' : 'Salvar Alterações'; ?>
        </button>
    </div>
</form>

<script>
// Buscar CEP
document.getElementById('cep')?.addEventListener('blur', function() {
    let cep = this.value.replace(/\D/g, '');
    
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('endereco').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('estado').value = data.uf || '';
                    
                    // Focar no campo número
                    document.querySelector('input[name="numero"]').focus();
                }
            })
            .catch(error => console.error('Erro ao buscar CEP:', error));
    }
});
</script>

<?php require_once 'footer.php'; ?>