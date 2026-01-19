<?php
session_start();

$corrigido = false;
$itensCorrigidos = 0;

// Verificar se há carrinho
if (isset($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) {
    
    // Corrigir cada item
    foreach ($_SESSION['carrinho'] as $index => &$item) {
        
        // Se tem 'id' mas não tem 'produto_id', corrigir
        if (isset($item['id']) && !isset($item['produto_id'])) {
            $item['produto_id'] = $item['id'];
            unset($item['id']); // Remover o campo 'id' antigo
            $itensCorrigidos++;
            $corrigido = true;
        }
    }
    unset($item); // Importante: quebrar a referência
}

$empresaId = $_SESSION['catalogo_empresa_id'] ?? 5;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção do Carrinho</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-8">
        
        <?php if ($corrigido): ?>
            <!-- Sucesso -->
            <div class="text-center">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce">
                    <i class="fas fa-check text-4xl text-green-600"></i>
                </div>
                
                <h1 class="text-3xl font-bold text-gray-900 mb-4">
                    ✅ Carrinho Corrigido!
                </h1>
                
                <div class="bg-green-50 border-l-4 border-green-500 p-6 mb-6 text-left">
                    <p class="text-lg text-green-800 mb-2">
                        <strong>Correção aplicada com sucesso!</strong>
                    </p>
                    <p class="text-sm text-green-700">
                        Foram corrigidos <strong class="text-2xl"><?php echo $itensCorrigidos; ?></strong> itens no carrinho.
                        Todos os campos <code class="bg-green-200 px-2 py-1 rounded">id</code> foram convertidos para 
                        <code class="bg-green-200 px-2 py-1 rounded">produto_id</code>.
                    </p>
                </div>
                
                <div class="bg-blue-50 rounded-lg p-6 mb-6">
                    <h3 class="font-bold text-blue-900 mb-3">🎉 O que aconteceu:</h3>
                    <ul class="text-left text-sm text-blue-800 space-y-2">
                        <li>✅ Todos os itens foram atualizados</li>
                        <li>✅ Estrutura do carrinho está correta</li>
                        <li>✅ Você pode finalizar o pedido agora</li>
                    </ul>
                </div>
                
                <div class="space-y-3">
                    <a href="carrinho.php?empresa=<?php echo $empresaId; ?>" 
                       class="block w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-4 rounded-lg font-bold hover:from-green-700 hover:to-green-800 transition shadow-lg">
                        <i class="fas fa-shopping-cart"></i> Ir para o Carrinho
                    </a>
                    
                    <a href="teste-carrinho.php" 
                       class="block w-full bg-blue-500 text-white py-4 rounded-lg font-bold hover:bg-blue-600 transition">
                        <i class="fas fa-microscope"></i> Ver Diagnóstico
                    </a>
                    
                    <a href="catalogo.php?empresa=<?php echo $empresaId; ?>" 
                       class="block w-full bg-purple-500 text-white py-4 rounded-lg font-bold hover:bg-purple-600 transition">
                        <i class="fas fa-store"></i> Continuar Comprando
                    </a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Nada para corrigir -->
            <div class="text-center">
                <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-info-circle text-4xl text-blue-600"></i>
                </div>
                
                <h1 class="text-3xl font-bold text-gray-900 mb-4">
                    ℹ️ Nada para Corrigir
                </h1>
                
                <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-6 text-left">
                    <p class="text-sm text-blue-700">
                        O carrinho já está com a estrutura correta ou está vazio. 
                        Não há itens para corrigir.
                    </p>
                </div>
                
                <div class="space-y-3">
                    <a href="teste-carrinho.php" 
                       class="block w-full bg-blue-500 text-white py-4 rounded-lg font-bold hover:bg-blue-600 transition">
                        <i class="fas fa-microscope"></i> Ver Diagnóstico Completo
                    </a>
                    
                    <a href="catalogo.php?empresa=<?php echo $empresaId; ?>" 
                       class="block w-full bg-purple-500 text-white py-4 rounded-lg font-bold hover:bg-purple-600 transition">
                        <i class="fas fa-store"></i> Ir ao Catálogo
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Detalhes Técnicos -->
        <div class="mt-8 pt-8 border-t">
            <details class="cursor-pointer">
                <summary class="text-sm font-semibold text-gray-700 hover:text-gray-900">
                    🔧 Ver Detalhes Técnicos
                </summary>
                <div class="mt-4 bg-gray-50 p-4 rounded-lg">
                    <p class="text-xs text-gray-600 mb-2"><strong>Sessão Atual:</strong></p>
                    <pre class="text-xs bg-white p-3 rounded border overflow-x-auto"><?php print_r($_SESSION); ?></pre>
                </div>
            </details>
        </div>
        
    </div>
    
</body>
</html>
