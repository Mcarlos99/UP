<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

// Verificar se há empresa especificada
if (!isset($_GET['empresa'])) {
    die('Loja não encontrada. Verifique o link e tente novamente.');
}

$empresaId = (int)$_GET['empresa'];

try {
    $db = getDB();
    
    // Buscar configurações da loja
    $stmt = $db->prepare("
        SELECT cc.*, u.nome as empresa_nome, u.telefone as empresa_telefone
        FROM configuracoes_catalogo cc
        INNER JOIN usuarios u ON cc.empresa_id = u.id
        WHERE cc.empresa_id = ? AND u.ativo = 1
    ");
    $stmt->execute([$empresaId]);
    $loja = $stmt->fetch();
    
    if (!$loja) {
        die('Loja não encontrada ou inativa.');
    }
    
    if (!$loja['catalogo_ativo'] || !$loja['aceita_pedidos']) {
        die('Desculpe, esta loja não está aceitando pedidos no momento.');
    }
    
    // Buscar categorias com produtos
    $stmt = $db->prepare("
        SELECT DISTINCT c.id, c.nome, c.icone, c.cor,
               COUNT(p.id) as total_produtos
        FROM categorias c
        INNER JOIN produtos p ON c.id = p.categoria_id
        WHERE p.ativo = 1 
        AND p.estoque_atual > 0
        AND p.empresa_id = ?
        GROUP BY c.id
        ORDER BY c.ordem, c.nome
    ");
    $stmt->execute([$empresaId]);
    $categorias = $stmt->fetchAll();
    
    // Buscar produtos
    $categoriaFiltro = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    
    $sql = "
        SELECT p.*, c.nome as categoria_nome, c.icone as categoria_icone, c.cor as categoria_cor
        FROM produtos p
        INNER JOIN categorias c ON p.categoria_id = c.id
        WHERE p.ativo = 1 
        AND p.estoque_atual > 0
        AND p.empresa_id = ?
    ";
    
    $params = [$empresaId];
    
    if ($categoriaFiltro) {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoriaFiltro;
    }
    
    if ($busca) {
        $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }
    
    $sql .= " ORDER BY p.nome ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die('Erro ao carregar catálogo. Tente novamente mais tarde.');
}

// Inicializar carrinho na sessão
if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}
if (!isset($_SESSION['carrinho_empresa'])) {
    $_SESSION['carrinho_empresa'] = $empresaId;
} elseif ($_SESSION['carrinho_empresa'] != $empresaId) {
    // Se mudou de loja, limpar carrinho
    $_SESSION['carrinho'] = [];
    $_SESSION['carrinho_empresa'] = $empresaId;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($loja['nome_loja']); ?> - Catálogo Online</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .produto-card {
            transition: all 0.3s ease;
        }
        .produto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .cart-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        @keyframes pulse-cart {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .cart-pulse {
            animation: pulse-cart 0.5s ease-in-out;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <?php if ($loja['logo_loja']): ?>
                        <img src="<?php echo htmlspecialchars($loja['logo_loja']); ?>" alt="Logo" class="h-12 w-12 object-contain">
                    <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-600 to-pink-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                            <?php echo strtoupper(substr($loja['nome_loja'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($loja['nome_loja']); ?></h1>
                        <?php if ($loja['descricao_loja']): ?>
                            <p class="text-sm text-gray-600 hidden md:block"><?php echo htmlspecialchars($loja['descricao_loja']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <?php if ($loja['whatsapp_pedidos']): ?>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $loja['whatsapp_pedidos']); ?>" 
                           target="_blank"
                           class="bg-green-500 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-green-600 transition text-sm md:text-base">
                            <i class="fab fa-whatsapp"></i>
                            <span class="hidden md:inline ml-2">Contato</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Boas Vindas -->
    <?php if ($loja['texto_boas_vindas']): ?>
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white py-4">
            <div class="container mx-auto px-4 text-center">
                <p class="text-sm md:text-base"><?php echo nl2br(htmlspecialchars($loja['texto_boas_vindas'])); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="container mx-auto px-4 py-6">
        
        <!-- Busca e Filtros -->
        <div class="mb-6">
            <form method="GET" action="" class="flex gap-3">
                <input type="hidden" name="empresa" value="<?php echo $empresaId; ?>">
                <?php if ($categoriaFiltro): ?>
                    <input type="hidden" name="categoria" value="<?php echo $categoriaFiltro; ?>">
                <?php endif; ?>
                
                <input 
                    type="text" 
                    name="busca" 
                    placeholder="Buscar produtos..." 
                    value="<?php echo htmlspecialchars($busca); ?>"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
                <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- Categorias -->
        <?php if (!empty($categorias)): ?>
            <div class="mb-6 overflow-x-auto">
                <div class="flex gap-2 pb-2">
                    <a href="?empresa=<?php echo $empresaId; ?>" 
                       class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition <?php echo !$categoriaFiltro ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                        <i class="fas fa-th"></i> Todos
                    </a>
                    <?php foreach ($categorias as $cat): ?>
                        <a href="?empresa=<?php echo $empresaId; ?>&categoria=<?php echo $cat['id']; ?>" 
                           class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition <?php echo $categoriaFiltro == $cat['id'] ? 'text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>"
                           style="<?php echo $categoriaFiltro == $cat['id'] ? 'background: ' . $cat['cor'] : ''; ?>">
                            <?php echo $cat['icone']; ?> <?php echo htmlspecialchars($cat['nome']); ?>
                            <span class="ml-1 opacity-75">(<?php echo $cat['total_produtos']; ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grid de Produtos -->
        <?php if (empty($produtos)): ?>
            <div class="text-center py-12">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">Nenhum produto encontrado</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                <?php foreach ($produtos as $produto): ?>
                    <div class="produto-card bg-white rounded-xl overflow-hidden shadow-md">
                        <!-- Imagem -->
                        <div class="produto-imagem bg-gradient-to-br from-purple-100 to-pink-100 h-48 flex items-center justify-center p-4">
                                <?php if (!empty($produto['imagem']) && file_exists($produto['imagem'])): ?>
                                    <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" 
                                         alt="<?php echo htmlspecialchars($produto['nome']); ?>" 
                                         class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                <div class="text-4xl text-gray-400">
                                    <i class="fas fa-box"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badge Categoria -->
                            <div class="absolute top-2 left-2">
                                <span class="text-xs px-2 py-1 rounded-full font-semibold text-white"
                                      style="background: <?php echo $produto['categoria_cor']; ?>;">
                                    <?php echo $produto['categoria_icone']; ?> <?php echo htmlspecialchars($produto['categoria_nome']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Informações -->
                        <div class="p-4">
                            <h3 class="font-bold text-gray-900 mb-2 line-clamp-2 min-h-[2.5rem]">
                                <?php echo htmlspecialchars($produto['nome']); ?>
                            </h3>
                            
                            <?php if ($produto['descricao']): ?>
                                <p class="text-xs text-gray-600 mb-3 line-clamp-2">
                                    <?php echo htmlspecialchars($produto['descricao']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <p class="text-xs text-gray-500">Preço</p>
                                    <p class="text-xl font-bold text-purple-600">
                                        R$ <?php echo number_format($produto['preco_venda'], 2, ',', '.'); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Estoque</p>
                                    <p class="text-sm font-semibold text-green-600">
                                        <?php echo $produto['estoque_atual']; ?> un.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Botão Adicionar -->
                            <button onclick="adicionarAoCarrinho(<?php echo $produto['id']; ?>, '<?php echo addslashes($produto['nome']); ?>', <?php echo $produto['preco_venda']; ?>, <?php echo $produto['estoque_atual']; ?>)"
                                    class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-2 rounded-lg hover:from-purple-700 hover:to-pink-700 transition font-semibold">
                                <i class="fas fa-cart-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Botão Carrinho Flutuante -->
    <button onclick="window.location.href='carrinho.php?empresa=<?php echo $empresaId; ?>'" 
            id="cartButton"
            class="cart-button bg-gradient-to-r from-purple-600 to-pink-600 text-white w-16 h-16 rounded-full shadow-2xl hover:shadow-3xl transition flex items-center justify-center">
        <i class="fas fa-shopping-cart text-2xl"></i>
        <span id="cartCount" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center">
            0
        </span>
    </button>

    <script>
    let carrinho = <?php echo json_encode($_SESSION['carrinho']); ?>;
    
    function adicionarAoCarrinho(produtoId, nome, preco, estoqueMax) {
        // Verificar se já existe no carrinho
        const index = carrinho.findIndex(item => item.id === produtoId);
        
        if (index >= 0) {
            // Incrementar quantidade se não exceder estoque
            if (carrinho[index].quantidade < estoqueMax) {
                carrinho[index].quantidade++;
            } else {
                alert('Quantidade máxima disponível em estoque atingida!');
                return;
            }
        } else {
            // Adicionar novo item
            carrinho.push({
                id: produtoId,
                nome: nome,
                preco: preco,
                quantidade: 1,
                estoque_max: estoqueMax
            });
        }
        
        // Salvar no servidor via AJAX
        salvarCarrinho();
        
        // Atualizar contador
        atualizarContador();
        
        // Animação
        document.getElementById('cartButton').classList.add('cart-pulse');
        setTimeout(() => {
            document.getElementById('cartButton').classList.remove('cart-pulse');
        }, 500);
    }
    
    function salvarCarrinho() {
        fetch('api/carrinho-add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                empresa: <?php echo $empresaId; ?>,
                carrinho: carrinho
            })
        });
    }
    
    function atualizarContador() {
        const total = carrinho.reduce((sum, item) => sum + item.quantidade, 0);
        document.getElementById('cartCount').textContent = total;
        
        if (total > 0) {
            document.getElementById('cartCount').style.display = 'flex';
        } else {
            document.getElementById('cartCount').style.display = 'none';
        }
    }
    
    // Atualizar contador ao carregar
    atualizarContador();
    </script>

</body>
</html>