<?php
session_start();

$empresaId = isset($_GET['empresa']) ? (int)$_GET['empresa'] : 5;

if (isset($_GET['confirmar'])) {
    $_SESSION['carrinho'] = [];
    $_SESSION['catalogo_empresa_id'] = null;
    
    header('Location: catalogo.php?empresa=' . $empresaId . '&limpo=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpar Carrinho</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-purple-50 to-pink-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8 text-center">
        
        <!-- Ícone -->
        <div class="mb-6">
            <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto">
                <i class="fas fa-exclamation-triangle text-4xl text-yellow-600"></i>
            </div>
        </div>
        
        <!-- Título -->
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">
            Limpar Carrinho
        </h1>
        
        <!-- Mensagem -->
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 text-left">
            <p class="text-sm text-yellow-800 mb-2">
                <strong>⚠️ Carrinho com itens inválidos</strong>
            </p>
            <p class="text-xs text-yellow-700">
                Seu carrinho contém itens antigos que foram adicionados antes das atualizações do sistema. 
                Para continuar, você precisa limpar o carrinho e adicionar os produtos novamente.
            </p>
        </div>
        
        <!-- Informações -->
        <div class="text-left mb-6 bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-700 mb-2">
                <i class="fas fa-info-circle text-blue-500"></i> <strong>O que acontecerá:</strong>
            </p>
            <ul class="text-sm text-gray-600 space-y-1 ml-6">
                <li>✓ Todos os itens serão removidos</li>
                <li>✓ Você será redirecionado ao catálogo</li>
                <li>✓ Poderá adicionar produtos novamente</li>
            </ul>
        </div>
        
        <!-- Estatísticas -->
        <?php if (!empty($_SESSION['carrinho'])): ?>
            <div class="bg-purple-50 rounded-lg p-4 mb-6">
                <p class="text-sm font-semibold text-purple-900 mb-2">
                    Itens no carrinho atual:
                </p>
                <p class="text-3xl font-bold text-purple-600">
                    <?php echo count($_SESSION['carrinho']); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Botões -->
        <div class="space-y-3">
            <a href="?empresa=<?php echo $empresaId; ?>&confirmar=1" 
               class="block w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-4 rounded-lg font-bold hover:from-red-700 hover:to-red-800 transition shadow-lg">
                <i class="fas fa-trash"></i> Sim, Limpar Carrinho
            </a>
            
            <a href="carrinho.php?empresa=<?php echo $empresaId; ?>" 
               class="block w-full bg-gray-200 text-gray-700 py-4 rounded-lg font-bold hover:bg-gray-300 transition">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
        
        <!-- Link alternativo -->
        <div class="mt-6 pt-6 border-t">
            <p class="text-sm text-gray-600">
                Precisa de ajuda?
            </p>
            <a href="debug-carrinho.php" class="text-sm text-purple-600 hover:text-purple-700 font-semibold">
                <i class="fas fa-tools"></i> Ver Diagnóstico Completo
            </a>
        </div>
        
    </div>
    
</body>
</html>
