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
    
    // Verificar se o catálogo está ativo
    if (!$loja['catalogo_ativo']) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Catálogo Indisponível</title></head>';
        echo '<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Arial;background:#f5f5f5;">';
        echo '<div style="text-align:center;padding:40px;background:white;border-radius:10px;box-shadow:0 4px 6px rgba(0,0,0,0.1);">';
        echo '<h1 style="color:#8B5CF6;margin-bottom:20px;">Catálogo Temporariamente Indisponível</h1>';
        echo '<p style="color:#666;font-size:18px;">' . htmlspecialchars($loja['mensagem_catalogo_inativo'] ?? 'Voltaremos em breve!') . '</p>';
        echo '</div></body></html>';
        exit;
    }
    
    if (!$loja['aceita_pedidos']) {
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
    
    $sql = "SELECT p.*, c.nome as categoria_nome, c.icone as categoria_icone
            FROM produtos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.ativo = 1 
            AND p.estoque_atual > 0
            AND p.empresa_id = ?";
    
    $params = [$empresaId];
    
    if ($categoriaFiltro) {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoriaFiltro;
    }
    
    $sql .= " ORDER BY p.nome";
    
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

// Usar títulos personalizados ou padrões
$tituloCatalogo = !empty($loja['titulo_catalogo']) ? $loja['titulo_catalogo'] : $loja['nome_loja'];
$subtituloCatalogo = !empty($loja['subtitulo_catalogo']) ? $loja['subtitulo_catalogo'] : $loja['descricao_loja'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tituloCatalogo); ?> - Catálogo Online</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        <?php
        // Cores personalizadas da loja
        $corPrimaria = $loja['cor_primaria'] ?? '#8B5CF6';
        $corSecundaria = $loja['cor_secundaria'] ?? '#EC4899';
        $corDestaque = $loja['cor_destaque'] ?? '#10B981';
        $corTexto = $loja['cor_texto'] ?? '#1F2937';
        $corFundo = $loja['cor_fundo'] ?? '#F5F7FA';
        
        // Função para escurecer cor (hover)
        function darkenColor($hex, $percent) {
            $hex = str_replace('#', '', $hex);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            $r = max(0, $r - ($r * $percent / 100));
            $g = max(0, $g - ($g * $percent / 100));
            $b = max(0, $b - ($b * $percent / 100));
            
            return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                        str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                        str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        }
        
        $corPrimariaEscura = darkenColor($corPrimaria, 15);
        $corSecundariaEscura = darkenColor($corSecundaria, 15);
        ?>
        
        :root {
            --cor-primaria: <?php echo $corPrimaria; ?>;
            --cor-primaria-escura: <?php echo $corPrimariaEscura; ?>;
            --cor-secundaria: <?php echo $corSecundaria; ?>;
            --cor-secundaria-escura: <?php echo $corSecundariaEscura; ?>;
            --cor-destaque: <?php echo $corDestaque; ?>;
            --cor-texto: <?php echo $corTexto; ?>;
            --cor-fundo: <?php echo $corFundo; ?>;
        }
        
        body {
            background: <?php echo $corFundo; ?> !important;
            color: <?php echo $corTexto; ?> !important;
        }
        
        /* Header personalizado */
        header.bg-white {
            background: linear-gradient(135deg, var(--cor-primaria) 0%, var(--cor-secundaria) 100%) !important;
            color: white !important;
        }
        
        header h1, header p, header .text-gray-900, header .text-gray-600 {
            color: white !important;
        }
        
        /* Botões principais */
        .btn-primary, .bg-gradient-to-r.from-purple-600, button.bg-gradient-to-r {
            background: linear-gradient(to right, var(--cor-primaria), var(--cor-secundaria)) !important;
        }
        
        .btn-primary:hover, .bg-gradient-to-r.from-purple-600:hover {
            background: linear-gradient(to right, var(--cor-primaria-escura), var(--cor-secundaria-escura)) !important;
        }
        
        /* Cards de produto */
        .produto-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .produto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border-color: var(--cor-primaria) !important;
        }
        
        /* Preços */
        .text-purple-600, .preco-produto, .font-bold.text-2xl.text-purple-600 {
            color: var(--cor-destaque) !important;
        }
        
        /* Categorias */
        .categoria-btn, button.border-2 {
            border: 2px solid var(--cor-primaria) !important;
            color: var(--cor-primaria) !important;
            transition: all 0.3s;
        }
        
        .categoria-btn:hover, .categoria-btn.active, button.border-2.bg-purple-600 {
            background: var(--cor-primaria) !important;
            color: white !important;
        }
        
        /* Botão do carrinho */
        .cart-button, a.bg-gradient-to-r.from-purple-600.to-pink-600 {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: linear-gradient(135deg, var(--cor-primaria), var(--cor-secundaria)) !important;
        }
        
        .cart-button:hover {
            background: linear-gradient(135deg, var(--cor-primaria-escura), var(--cor-secundaria-escura)) !important;
        }
        
        /* Badge do carrinho */
        .badge-count, .bg-red-500 {
            background: var(--cor-destaque) !important;
        }
        
        /* Ícones de destaque */
        .fa-heart, .fa-star, .fa-fire, .text-yellow-500 {
            color: var(--cor-destaque) !important;
        }
        
        /* Links */
        a.text-purple-600 {
            color: var(--cor-primaria) !important;
        }
        
        /* Gradientes */
        .bg-gradient-to-br.from-purple-600 {
            background: linear-gradient(to bottom right, var(--cor-primaria), var(--cor-secundaria)) !important;
        }
        
        /* Animação do carrinho */
        @keyframes pulse-cart {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .cart-pulse {
            animation: pulse-cart 0.5s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .cart-button { bottom: 10px; right: 10px; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <?php if (!empty($loja['logo_url']) && file_exists($loja['logo_url'])): ?>
                        <?php
                        $logoSize = 'h-16';
                        if (($loja['logo_tamanho'] ?? 'media') === 'pequena') {
                            $logoSize = 'h-12';
                        } elseif (($loja['logo_tamanho'] ?? 'media') === 'grande') {
                            $logoSize = 'h-20';
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($loja['logo_url']); ?>" 
                             alt="Logo <?php echo htmlspecialchars($tituloCatalogo); ?>" 
                             class="<?php echo $logoSize; ?> w-auto object-contain">
                    <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-600 to-pink-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                            <?php echo strtoupper(substr($tituloCatalogo, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($tituloCatalogo); ?></h1>
                        <?php if ($subtituloCatalogo): ?>
                            <p class="text-sm text-gray-600 hidden md:block"><?php echo htmlspecialchars($subtituloCatalogo); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <?php 
                    // Usar whatsapp_comercial ou whatsapp_pedidos
                    $whatsapp = !empty($loja['whatsapp_comercial']) ? $loja['whatsapp_comercial'] : $loja['whatsapp_pedidos'];
                    if ($whatsapp): 
                    ?>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" 
                           target="_blank"
                           class="bg-green-500 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-green-600 transition text-sm md:text-base">
                            <i class="fab fa-whatsapp"></i>
                            <span class="hidden md:inline ml-2">Contato</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($loja['instagram_url'])): ?>
                        <a href="<?php echo htmlspecialchars($loja['instagram_url']); ?>" 
                           target="_blank"
                           class="bg-pink-500 text-white p-2 md:p-3 rounded-lg hover:bg-pink-600 transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($loja['facebook_url'])): ?>
                        <a href="<?php echo htmlspecialchars($loja['facebook_url']); ?>" 
                           target="_blank"
                           class="bg-blue-600 text-white p-2 md:p-3 rounded-lg hover:bg-blue-700 transition">
                            <i class="fab fa-facebook"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($loja['horario_atendimento'])): ?>
                <div class="mt-3 text-sm text-center opacity-90">
                    <i class="fas fa-clock"></i> <?php echo nl2br(htmlspecialchars($loja['horario_atendimento'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Mensagem de Boas Vindas -->
    <?php if (!empty($loja['mensagem_boas_vindas'])): ?>
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white py-4">
            <div class="container mx-auto px-4 text-center">
                <p class="text-sm md:text-base"><?php echo nl2br(htmlspecialchars($loja['mensagem_boas_vindas'])); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Frete Grátis -->
    <?php if (!empty($loja['valor_minimo_frete_gratis'])): ?>
        <div class="bg-green-500 text-white py-2">
            <div class="container mx-auto px-4 text-center text-sm">
                <i class="fas fa-truck"></i> <strong>FRETE GRÁTIS</strong> em compras acima de R$ <?php echo number_format($loja['valor_minimo_frete_gratis'], 2, ',', '.'); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($loja['mensagem_frete'])): ?>
        <div class="bg-blue-50 py-2">
            <div class="container mx-auto px-4 text-center text-sm text-blue-900">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($loja['mensagem_frete']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="container mx-auto px-4 py-6">
        
            <!-- Busca e Filtros 
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
        </div> -->

        <!-- Categorias -->
        <?php if (!empty($categorias)): ?>
            <div class="mb-6 overflow-x-auto">
                <div class="flex gap-2 pb-2">
                 <!--   <a href="?empresa=<?php echo $empresaId; ?>" 
                       class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition <?php echo !$categoriaFiltro ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                        <i class="fas fa-th"></i> Todos
                    </a> -->
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
    <!-- Rodapé -->
    <footer class="bg-white shadow-lg mt-12 py-6">
        <div class="container mx-auto px-4 text-center">
            <?php if (!empty($loja['rodape_texto'])): ?>
                <p class="text-gray-600 text-sm mb-2">
                    <?php echo nl2br(htmlspecialchars($loja['rodape_texto'])); ?>
                </p>
            <?php else: ?>
                <p class="text-gray-600 text-sm mb-2">
                    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($tituloCatalogo); ?> - Todos os direitos reservados
                </p>
            <?php endif; ?>
            
            <?php if (!empty($loja['whatsapp_comercial']) || !empty($loja['instagram_url']) || !empty($loja['facebook_url'])): ?>
                <div class="flex justify-center gap-4 mt-4">
                    <?php if (!empty($loja['whatsapp_comercial'])): ?>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $loja['whatsapp_comercial']); ?>" 
                           target="_blank"
                           class="text-green-600 hover:text-green-700 transition">
                            <i class="fab fa-whatsapp text-2xl"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($loja['instagram_url'])): ?>
                        <a href="<?php echo htmlspecialchars($loja['instagram_url']); ?>" 
                           target="_blank"
                           class="text-pink-600 hover:text-pink-700 transition">
                            <i class="fab fa-instagram text-2xl"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($loja['facebook_url'])): ?>
                        <a href="<?php echo htmlspecialchars($loja['facebook_url']); ?>" 
                           target="_blank"
                           class="text-blue-600 hover:text-blue-700 transition">
                            <i class="fab fa-facebook text-2xl"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </footer>