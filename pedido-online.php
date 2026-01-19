<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'config_multitenant.php';

// Middleware: Corrigir carrinho automaticamente
require_once 'carrinho-middleware.php';

$empresaId = isset($_GET['empresa']) ? (int)$_GET['empresa'] : ($_SESSION['catalogo_empresa_id'] ?? 1);

// Se vier da URL, salvar na sessão
if (isset($_GET['empresa'])) {
    $_SESSION['catalogo_empresa_id'] = $empresaId;
}

if (empty($_SESSION['carrinho'])) {
    header('Location: catalogo.php?empresa=' . $empresaId);
    exit;
}

// Buscar informações da empresa
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT nome, telefone, email FROM usuarios WHERE id = ? AND nivel_acesso = 'admin'");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch();
} catch (PDOException $e) {
    die('Erro ao carregar informações da loja');
}

// Processar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = sanitize($_POST['nome']);
        $telefone = sanitize($_POST['telefone']);
        $email = sanitize($_POST['email'] ?? '');
        $cep = sanitize($_POST['cep'] ?? '');
        $endereco = sanitize($_POST['endereco'] ?? '');
        $cidade = sanitize($_POST['cidade'] ?? '');
        $estado = sanitize($_POST['estado'] ?? '');
        $complemento = sanitize($_POST['complemento'] ?? '');
        $forma_pagamento = sanitize($_POST['forma_pagamento'] ?? '');
        $observacoes = sanitize($_POST['observacoes'] ?? '');
        
        if (empty($nome) || empty($telefone)) {
            throw new Exception('Nome e telefone são obrigatórios');
        }
        
        // Calcular total
        $valorTotal = 0;
        $quantidadeItens = 0;
        foreach ($_SESSION['carrinho'] as $item) {
            $valorTotal += $item['preco'] * $item['quantidade'];
            $quantidadeItens += $item['quantidade'];
        }
        
        // Calcular valores
        $valorProdutos = 0;
        $quantidadeItens = 0;
        foreach ($_SESSION['carrinho'] as $item) {
            $valorProdutos += $item['preco'] * $item['quantidade'];
            $quantidadeItens += $item['quantidade'];
        }
        $valorFrete = 0; // Pode ser calculado depois
        $valorTotal = $valorProdutos + $valorFrete;
        
        // Iniciar transação
        $db->beginTransaction();
        
        try {
            // Gerar código do pedido manualmente (caso trigger não funcione)
            $dataHoje = date('Ymd');
            $stmt = $db->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING(codigo_pedido, -4) AS UNSIGNED)), 0) + 1 as proxima_sequencia
                FROM pedidos_catalogo
                WHERE codigo_pedido LIKE CONCAT('CAT-', ?, '-%')
                AND empresa_id = ?
            ");
            $stmt->execute([$dataHoje, $empresaId]);
            $sequencia = $stmt->fetch()['proxima_sequencia'];
            $codigoPedido = 'CAT-' . $dataHoje . '-' . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
            
            // Inserir pedido principal
            $stmt = $db->prepare("
                INSERT INTO pedidos_catalogo (
                    empresa_id,
                    codigo_pedido,
                    cliente_nome, 
                    cliente_telefone, 
                    cliente_email,
                    endereco_completo, 
                    cep, 
                    valor_produtos,
                    valor_frete,
                    valor_total,
                    forma_pagamento_preferencial, 
                    observacoes, 
                    ip_cliente,
                    user_agent,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
            ");
            
            $enderecoCompleto = trim("$endereco, $cidade - $estado" . ($complemento ? " - $complemento" : ""));
            
            $stmt->execute([
                $empresaId,
                $codigoPedido,
                $nome, 
                $telefone, 
                $email,
                $enderecoCompleto, 
                $cep,
                $valorProdutos,
                $valorFrete,
                $valorTotal,
                $forma_pagamento, 
                $observacoes, 
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido'
            ]);
            
            $pedidoId = $db->lastInsertId();
            
            // Inserir itens do pedido
            $stmtItem = $db->prepare("
                INSERT INTO pedidos_catalogo_itens (
                    pedido_id,
                    produto_id,
                    produto_nome,
                    quantidade,
                    preco_unitario,
                    subtotal
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_SESSION['carrinho'] as $item) {
                // Validar que o item tem produto_id
                if (!isset($item['produto_id']) || empty($item['produto_id'])) {
                    throw new Exception('Carrinho contém itens inválidos. Por favor, esvazie o carrinho e adicione os produtos novamente.');
                }
                
                $subtotalItem = $item['preco'] * $item['quantidade'];
                $stmtItem->execute([
                    $pedidoId,
                    $item['produto_id'],
                    $item['nome'],
                    $item['quantidade'],
                    $item['preco'],
                    $subtotalItem
                ]);
            }
            
            // Código do pedido já foi gerado anteriormente
            // Confirmar transação
            $db->commit();
            
            // Limpar carrinho
            $_SESSION['carrinho'] = [];
            
            // Redirecionar para página de sucesso
            header('Location: pedido-sucesso.php?empresa=' . $empresaId . '&pedido=' . $codigoPedido);
            exit;
            
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}

// Calcular total
$subtotal = 0;
foreach ($_SESSION['carrinho'] as $item) {
    $subtotal += $item['preco'] * $item['quantidade'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido - <?php echo htmlspecialchars($empresa['nome']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); }
    </style>
</head>
<body class="min-h-screen pb-8">
    
    <header class="bg-white shadow-lg mb-6">
        <div class="container mx-auto px-4 py-4">
            <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                <i class="fas fa-check-circle"></i> Finalizar Pedido
            </h1>
        </div>
    </header>
    
    <div class="container mx-auto px-4">
        <?php if (isset($erro)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <p class="font-semibold"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($erro); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2">
                <form method="POST" action="" class="space-y-6">
                    
                    <!-- Dados Pessoais -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-user text-purple-600"></i> Seus Dados
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nome Completo *</label>
                                <input type="text" name="nome" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone/WhatsApp *</label>
                                <input type="tel" name="telefone" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                       placeholder="(00) 00000-0000">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input type="email" name="email"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Endereço -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-map-marker-alt text-blue-600"></i> Endereço de Entrega
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">CEP</label>
                                <input type="text" name="cep" id="cep"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                       placeholder="00000-000">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Endereço</label>
                                <input type="text" name="endereco" id="endereco"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Cidade</label>
                                <input type="text" name="cidade" id="cidade"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Estado</label>
                                <input type="text" name="estado" id="estado" maxlength="2"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Complemento</label>
                                <input type="text" name="complemento"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                       placeholder="Apartamento, bloco, etc.">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pagamento -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-credit-card text-green-600"></i> Forma de Pagamento
                        </h2>
                        
                        <select name="forma_pagamento"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Selecione (opcional)</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="credito">Cartão de Crédito</option>
                            <option value="debito">Cartão de Débito</option>
                        </select>
                        
                        <p class="text-sm text-gray-600 mt-2">
                            <i class="fas fa-info-circle"></i> A forma de pagamento será confirmada por telefone
                        </p>
                    </div>
                    
                    <!-- Observações -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-comment text-orange-600"></i> Observações
                        </h2>
                        
                        <textarea name="observacoes" rows="4"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                  placeholder="Alguma observação sobre seu pedido?"></textarea>
                    </div>
                    
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold py-4 rounded-lg hover:from-purple-700 hover:to-pink-700 transition text-lg shadow-lg">
                        <i class="fas fa-check-circle"></i> Enviar Pedido
                    </button>
                </form>
            </div>
            
            <!-- Resumo -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg p-6 sticky top-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-shopping-bag"></i> Seu Pedido
                    </h2>
                    
                    <div class="space-y-3 mb-4">
                        <?php foreach ($_SESSION['carrinho'] as $item): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-700">
                                    <?php echo htmlspecialchars($item['nome']); ?> × <?php echo $item['quantidade']; ?>
                                </span>
                                <span class="font-semibold"><?php echo formatarMoeda($item['preco'] * $item['quantidade']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="border-t-2 border-gray-200 pt-4">
                        <div class="flex justify-between text-xl font-bold text-purple-600">
                            <span>Total:</span>
                            <span><?php echo formatarMoeda($subtotal); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-6 p-4 bg-green-50 rounded-lg border-2 border-green-200">
                        <p class="text-sm text-green-800">
                            <i class="fas fa-phone"></i> 
                            Entraremos em contato para confirmar seu pedido!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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
                        document.getElementById('cidade').value = data.localidade || '';
                        document.getElementById('estado').value = data.uf || '';
                    }
                });
        }
    });
    </script>
</body>
</html>