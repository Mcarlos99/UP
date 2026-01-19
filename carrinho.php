<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

// Middleware: Corrigir carrinho automaticamente
require_once 'carrinho-middleware.php';

// Obter ID da empresa (da URL ou da sessão)
$empresaId = isset($_GET['empresa']) ? (int)$_GET['empresa'] : ($_SESSION['catalogo_empresa_id'] ?? 1);

// Se vier da URL, salvar na sessão
if (isset($_GET['empresa'])) {
    $_SESSION['catalogo_empresa_id'] = $empresaId;
}

// Inicializar carrinho
if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'atualizar':
                $index = (int)$_POST['index'];
                $quantidade = (int)$_POST['quantidade'];
                
                if (isset($_SESSION['carrinho'][$index])) {
                    if ($quantidade <= 0) {
                        unset($_SESSION['carrinho'][$index]);
                        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
                    } elseif ($quantidade <= $_SESSION['carrinho'][$index]['estoque_max']) {
                        $_SESSION['carrinho'][$index]['quantidade'] = $quantidade;
                    }
                }
                break;
                
            case 'remover':
                $index = (int)$_POST['index'];
                if (isset($_SESSION['carrinho'][$index])) {
                    unset($_SESSION['carrinho'][$index]);
                    $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
                }
                break;
                
            case 'limpar':
                $_SESSION['carrinho'] = [];
                break;
        }
    }
    
    header('Location: carrinho.php?empresa=' . $empresaId);
    exit;
}

// Calcular totais
$subtotal = 0;
foreach ($_SESSION['carrinho'] as $item) {
    $subtotal += $item['preco'] * $item['quantidade'];
}

// Buscar informações da empresa
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT nome, telefone, email FROM usuarios WHERE id = ? AND nivel_acesso = 'admin'");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch();
} catch (PDOException $e) {
    $empresa = ['nome' => 'Loja', 'telefone' => '', 'email' => ''];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - <?php echo htmlspecialchars($empresa['nome']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        @media (max-width: 768px) {
            .item-carrinho {
                flex-direction: column !important;
                gap: 0.75rem !important;
            }
            
            .item-info {
                width: 100% !important;
            }
            
            .item-actions {
                width: 100% !important;
                justify-content: space-between !important;
            }
            
            .resumo-pedido {
                position: relative !important;
                margin-top: 1rem !important;
            }
        }
    </style>
</head>
<body class="min-h-screen pb-8">
    
    <!-- Header -->
    <header class="bg-white shadow-lg mb-6">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        <i class="fas fa-shopping-cart"></i> Meu Carrinho
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">
                        <?php echo count($_SESSION['carrinho']); ?> item(ns) no carrinho
                    </p>
                </div>
                
                <a href="catalogo.php?empresa=<?php echo $empresaId; ?>" 
                   class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition font-semibold text-sm md:text-base">
                    <i class="fas fa-arrow-left"></i> <span class="hidden md:inline">Continuar Comprando</span>
                </a>
            </div>
        </div>
    </header>
    
    <div class="container mx-auto px-4">
        
        <?php if (empty($_SESSION['carrinho'])): ?>
            <!-- Carrinho Vazio -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Seu carrinho está vazio</h3>
                <p class="text-gray-600 mb-6">Adicione produtos para começar sua compra</p>
                <a href="catalogo.php?empresa=<?php echo $empresaId; ?>" 
                   class="inline-block bg-gradient-to-r from-purple-600 to-pink-600 text-white px-8 py-3 rounded-lg font-bold hover:from-purple-700 hover:to-pink-700 transition">
                    <i class="fas fa-store"></i> Ver Catálogo
                </a>
            </div>
        <?php else: ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Itens do Carrinho -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl shadow-lg p-4 md:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl md:text-2xl font-bold text-gray-900">
                                <i class="fas fa-list"></i> Produtos
                            </h2>
                            
                            <form method="POST" action="" onsubmit="return confirm('Limpar todo o carrinho?')">
                                <input type="hidden" name="action" value="limpar">
                                <button type="submit" class="text-red-600 hover:text-red-700 font-semibold text-sm">
                                    <i class="fas fa-trash"></i> Limpar Carrinho
                                </button>
                            </form>
                        </div>
                        
                        <div class="space-y-4">
                            <?php foreach ($_SESSION['carrinho'] as $index => $item): ?>
                                <div class="item-carrinho flex items-center justify-between p-4 bg-gray-50 rounded-xl border-2 border-gray-200 hover:border-purple-300 transition">
                                    
                                    <div class="item-info flex-1 min-w-0">
                                        <h3 class="font-bold text-gray-900 text-sm md:text-base mb-1">
                                            <?php echo htmlspecialchars($item['nome']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <?php echo formatarMoeda($item['preco']); ?> × <?php echo $item['quantidade']; ?> = 
                                            <span class="font-bold text-purple-600">
                                                <?php echo formatarMoeda($item['preco'] * $item['quantidade']); ?>
                                            </span>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-box"></i> Disponível: <?php echo $item['estoque_max']; ?> un.
                                        </p>
                                    </div>
                                    
                                    <div class="item-actions flex items-center gap-3">
                                        <!-- Controles de Quantidade -->
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="action" value="atualizar">
                                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                                <input type="hidden" name="quantidade" value="<?php echo $item['quantidade'] - 1; ?>">
                                                <button type="submit" 
                                                        class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition font-bold">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </form>
                                            
                                            <span class="w-12 text-center font-bold text-gray-900">
                                                <?php echo $item['quantidade']; ?>
                                            </span>
                                            
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="action" value="atualizar">
                                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                                <input type="hidden" name="quantidade" value="<?php echo $item['quantidade'] + 1; ?>">
                                                <button type="submit" 
                                                        <?php echo $item['quantidade'] >= $item['estoque_max'] ? 'disabled' : ''; ?>
                                                        class="w-8 h-8 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <!-- Remover -->
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="action" value="remover">
                                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                                            <button type="submit" 
                                                    class="w-8 h-8 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Resumo do Pedido -->
                <div class="lg:col-span-1">
                    <div class="resumo-pedido bg-white rounded-2xl shadow-lg p-6 sticky top-6">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-6">
                            <i class="fas fa-receipt"></i> Resumo
                        </h2>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between text-gray-700">
                                <span>Subtotal:</span>
                                <span class="font-semibold"><?php echo formatarMoeda($subtotal); ?></span>
                            </div>
                            
                            <div class="flex justify-between text-gray-700">
                                <span>Itens:</span>
                                <span class="font-semibold"><?php echo array_sum(array_column($_SESSION['carrinho'], 'quantidade')); ?> un.</span>
                            </div>
                            
                            <div class="border-t-2 border-gray-200 pt-3">
                                <div class="flex justify-between text-xl font-bold text-purple-600">
                                    <span>Total:</span>
                                    <span><?php echo formatarMoeda($subtotal); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <a href="pedido-online.php?empresa=<?php echo $empresaId; ?>" 
                           class="block w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white text-center font-bold py-4 rounded-lg hover:from-purple-700 hover:to-pink-700 transition shadow-lg text-lg">
                            <i class="fas fa-check-circle"></i> Finalizar Pedido
                        </a>
                        
                        <a href="catalogo.php?empresa=<?php echo $empresaId; ?>" 
                           class="block w-full mt-3 bg-gray-100 text-gray-700 text-center font-semibold py-3 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-arrow-left"></i> Continuar Comprando
                        </a>
                        
                        <div class="mt-6 p-4 bg-purple-50 rounded-lg border-2 border-purple-200">
                            <p class="text-sm text-purple-800">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Atenção:</strong> Este é um pedido online. 
                                Você receberá confirmação por telefone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
    
</body>
</html>