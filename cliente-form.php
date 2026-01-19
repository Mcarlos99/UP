<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'config_multitenant.php';

$empresaId = getEmpresaId(); // <- ADICIONAR

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

define('INCLUDED', true);

$clienteId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = !empty($clienteId);

$pageTitle = $isEdit ? 'Editar Cliente' : 'Novo Cliente';
$pageSubtitle = $isEdit ? 'Edite as informações do cliente' : 'Adicione um novo cliente';

$db = getDB();

// Buscar cliente se for edição
$cliente = null;
if ($isEdit) {
    try {
        validarAcessoEmpresa('clientes', $clienteId); // <- ADICIONAR validação
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$clienteId, $empresaId]); // <- ADICIONAR $empresaId
        $cliente = $stmt->fetch();
        
        if (!$cliente) {
            $_SESSION['error'] = 'Cliente não encontrado!';
            header('Location: clientes.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao carregar cliente: ' . $e->getMessage();
        header('Location: clientes.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = sanitize($_POST['nome']);
        $cpfCnpj = sanitize($_POST['cpf_cnpj'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $telefone = sanitize($_POST['telefone'] ?? '');
        $whatsapp = sanitize($_POST['whatsapp']);
        $endereco = sanitize($_POST['endereco'] ?? '');
        $cidade = sanitize($_POST['cidade'] ?? '');
        $estado = sanitize($_POST['estado'] ?? '');
        $cep = sanitize($_POST['cep'] ?? '');
        $dataNascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        // Validações
        if (empty($nome)) {
            throw new Exception('Nome é obrigatório');
        }
        
        if (empty($whatsapp)) {
            throw new Exception('WhatsApp é obrigatório');
        }
        
        if (!empty($email) && !validarEmail($email)) {
            throw new Exception('Email inválido');
        }
        
        if ($isEdit) {
            validarAcessoEmpresa('clientes', $clienteId); // <- ADICIONAR validação
            // Atualizar cliente
            $stmt = $db->prepare("
                UPDATE clientes SET 
                    nome = ?, cpf_cnpj = ?, email = ?, telefone = ?, whatsapp = ?,
                    endereco = ?, cidade = ?, estado = ?, cep = ?,
                    data_nascimento = ?, observacoes = ?
                WHERE id = ? AND empresa_id = ?
            ");
            
            $stmt->execute([
                $nome, $cpfCnpj, $email, $telefone, $whatsapp,
                $endereco, $cidade, $estado, $cep,
                $dataNascimento, $observacoes, $clienteId, $empresaId
            ]);
            
            logActivity('Cliente atualizado', 'clientes', $clienteId);
            $_SESSION['success'] = 'Cliente atualizado com sucesso!';
            
        } else {
            // Criar cliente
            $stmt = $db->prepare("
                INSERT INTO clientes (
                    empresa_id, nome, cpf_cnpj, email, telefone, whatsapp,
                    endereco, cidade, estado, cep,
                    data_nascimento, observacoes, ativo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $empresaId, $nome, $cpfCnpj, $email, $telefone, $whatsapp,
                $endereco, $cidade, $estado, $cep,
                $dataNascimento, $observacoes
            ]);
            
            $novoClienteId = $db->lastInsertId();
            
            logActivity('Cliente criado', 'clientes', $novoClienteId);
            $_SESSION['success'] = 'Cliente criado com sucesso!';
        }
        
        header('Location: clientes.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao salvar cliente: ' . $e->getMessage();
    }
}

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="white-card">
        <form method="POST" action="" id="clienteForm">
            
            <!-- Informações Pessoais -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-user"></i> Informações Pessoais
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nome Completo *</label>
                        <input 
                            type="text" 
                            name="nome" 
                            required
                            value="<?php echo htmlspecialchars($cliente['nome'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Ex: Maria Silva"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">CPF/CNPJ</label>
                        <input 
                            type="text" 
                            name="cpf_cnpj" 
                            value="<?php echo htmlspecialchars($cliente['cpf_cnpj'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="000.000.000-00"
                            data-mask="cpf-cnpj"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data de Nascimento</label>
                        <input 
                            type="date" 
                            name="data_nascimento" 
                            value="<?php echo $cliente['data_nascimento'] ?? ''; ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Contato -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-phone"></i> Contato
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone</label>
                        <input 
                            type="tel" 
                            name="telefone" 
                            value="<?php echo htmlspecialchars($cliente['telefone'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="(00) 00000-0000"
                            data-mask="telefone"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">WhatsApp *</label>
                        <input 
                            type="tel" 
                            name="whatsapp" 
                            required
                            value="<?php echo htmlspecialchars($cliente['whatsapp'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="(00) 00000-0000"
                            data-mask="telefone"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input 
                            type="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="email@exemplo.com"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Endereço -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-map-marker-alt"></i> Endereço
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Endereço Completo</label>
                        <input 
                            type="text" 
                            name="endereco" 
                            value="<?php echo htmlspecialchars($cliente['endereco'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Rua, número, complemento"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">CEP</label>
                        <input 
                            type="text" 
                            name="cep" 
                            value="<?php echo htmlspecialchars($cliente['cep'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="00000-000"
                            data-mask="cep"
                            id="cep"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cidade</label>
                        <input 
                            type="text" 
                            name="cidade" 
                            value="<?php echo htmlspecialchars($cliente['cidade'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="São Paulo"
                            id="cidade"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Estado (UF)</label>
                        <select name="estado" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" id="estado">
                            <option value="">Selecione...</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                $selected = ($cliente && $cliente['estado'] == $uf) ? 'selected' : '';
                                echo "<option value='$uf' $selected>$uf</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Observações -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-sticky-note"></i> Observações
                </h3>
                
                <textarea 
                    name="observacoes" 
                    rows="4"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Informações adicionais sobre o cliente..."
                ><?php echo htmlspecialchars($cliente['observacoes'] ?? ''); ?></textarea>
            </div>
            
            <!-- Histórico (apenas na edição) -->
            <?php if ($isEdit): ?>
                <div class="mb-6 bg-purple-50 p-4 rounded-lg">
                    <h3 class="text-lg font-bold text-gray-900 mb-3">
                        <i class="fas fa-history"></i> Histórico do Cliente
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600">Total em Compras</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo formatarMoeda($cliente['total_compras']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Quantidade de Pedidos</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $cliente['quantidade_pedidos']; ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Última Compra</p>
                            <p class="text-lg font-bold text-gray-900">
                                <?php echo !empty($cliente['ultima_compra']) ? formatarData($cliente['ultima_compra']) : 'Nunca'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="pedidos.php?cliente_id=<?php echo $clienteId; ?>" class="text-purple-600 hover:text-purple-700 font-semibold text-sm">
                            <i class="fas fa-arrow-right"></i> Ver todos os pedidos deste cliente
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Botões -->
            <div class="flex gap-3 pt-6 border-t">
                <a href="clientes.php" class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 transition text-center">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="flex-1 btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $isEdit ? 'Salvar Alterações' : 'Criar Cliente'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Buscar CEP (API ViaCEP)
document.getElementById('cep')?.addEventListener('blur', function() {
    const cep = this.value.replace(/\D/g, '');
    
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.querySelector('input[name="endereco"]').value = data.logradouro;
                    document.getElementById('cidade').value = data.localidade;
                    document.getElementById('estado').value = data.uf;
                }
            })
            .catch(error => console.log('Erro ao buscar CEP:', error));
    }
});
</script>

<?php require_once 'footer.php'; ?>
