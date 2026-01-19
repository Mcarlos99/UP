<?php
session_start();
require_once 'config.php';

$empresaId = isset($_GET['empresa']) ? (int)$_GET['empresa'] : ($_SESSION['catalogo_empresa_id'] ?? 1);
$numeroPedido = isset($_GET['pedido']) ? $_GET['pedido'] : '';

// Buscar empresa
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT nome, telefone FROM usuarios WHERE id = ?");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch();
} catch (PDOException $e) {
    $empresa = ['nome' => 'Loja', 'telefone' => ''];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Enviado! - <?php echo htmlspecialchars($empresa['nome']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); }
        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .checkmark {
            animation: checkmark 0.6s ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-2xl w-full">
        <div class="bg-white rounded-3xl shadow-2xl p-8 md:p-12 text-center">
            
            <div class="checkmark w-24 h-24 mx-auto mb-6 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center">
                <i class="fas fa-check text-white text-5xl"></i>
            </div>
            
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Pedido Enviado com Sucesso!
            </h1>
            
            <div class="bg-purple-50 rounded-xl p-6 mb-6">
                <p class="text-gray-700 mb-2">Número do seu pedido:</p>
                <p class="text-3xl font-bold text-purple-600">#<?php echo htmlspecialchars($numeroPedido); ?></p>
            </div>
            
            <div class="bg-green-50 border-2 border-green-200 rounded-xl p-6 mb-6">
                <p class="text-green-800 font-semibold mb-2">
                    <i class="fas fa-phone text-2xl mb-2 block"></i>
                    Entraremos em contato em breve!
                </p>
                <p class="text-sm text-green-700">
                    Você receberá uma ligação ou mensagem no telefone informado para confirmar seu pedido.
                </p>
            </div>
            
            <div class="space-y-3">
                <a href="catalogo.php?empresa=<?php echo $empresaId; ?>" 
                   class="block w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold py-4 rounded-lg hover:from-purple-700 hover:to-pink-700 transition">
                    <i class="fas fa-store"></i> Continuar Comprando
                </a>
                
                <p class="text-sm text-gray-600">
                    <i class="fas fa-whatsapp text-green-500"></i> 
                    Dúvidas? Entre em contato: <?php echo formatarTelefone($empresa['telefone']); ?>
                </p>
            </div>
        </div>
    </div>
    
</body>
</html>
