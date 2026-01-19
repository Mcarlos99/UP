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
$pageTitle = 'Catálogo Online';
$pageSubtitle = 'Compartilhe seu catálogo com seus clientes';

$empresaId = getEmpresaId();
$db = getDB();
$user = getUser();

// Gerar link do catálogo (usa o ID da empresa)
$linkCatalogo = SITE_URL . 'catalogo.php?empresa=' . $empresaId;

// Buscar estatísticas do catálogo
try {
    // Total de produtos ativos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos WHERE ativo = 1 AND estoque_atual > 0 AND empresa_id = ?");
    $stmt->execute([$empresaId]);
    $totalProdutos = $stmt->fetch()['total'];
    
    // Total de pedidos do catálogo (últimos 30 dias)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos_catalogo WHERE empresa_id = ? AND data_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$empresaId]);
    $pedidosMes = $stmt->fetch()['total'] ?? 0;
    
    // Valor total dos pedidos do catálogo
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor_total), 0) as total FROM pedidos_catalogo WHERE empresa_id = ? AND data_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$empresaId]);
    $valorMes = $stmt->fetch()['total'] ?? 0;
    
    // Pedidos pendentes
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos_catalogo WHERE empresa_id = ? AND status = 'pendente'");
    $stmt->execute([$empresaId]);
    $pedidosPendentes = $stmt->fetch()['total'] ?? 0;
    
} catch (PDOException $e) {
    $totalProdutos = $pedidosMes = $valorMes = $pedidosPendentes = 0;
}

require_once 'header.php';

// Mensagens
if (isset($_SESSION['success'])) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 md:p-4 mb-4 md:mb-6 rounded alert-auto-close">';
    echo '<p class="font-medium text-sm md:text-base"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</p>';
    echo '</div>';
    unset($_SESSION['success']);
}
?>

<style>
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem !important;
    }
    
    .stat-card {
        padding: 1rem !important;
    }
    
    .link-box {
        flex-direction: column !important;
        gap: 1rem !important;
    }
    
    .qr-code-section {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- Cards de Estatísticas -->
<div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-box text-3xl"></i>
            </div>
            <p class="text-yellow-100 text-sm font-bold mb-2">Produtos Disponíveis</p>
            <p class="text-3xl font-bold mb-1"><?php echo $totalProdutos; ?></p>
            <p class="text-yellow-100 text-xs font-semibold">No catálogo</p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-shopping-cart text-3xl"></i>
            </div>
            <p class="text-blue-100 text-sm font-bold mb-2">Pedidos (30 dias)</p>
            <p class="text-3xl font-bold mb-1"><?php echo $pedidosMes; ?></p>
            <p class="text-blue-100 text-xs font-semibold">Recebidos</p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-dollar-sign text-3xl"></i>
            </div>
            <p class="text-green-100 text-sm font-bold mb-2">Valor (30 dias)</p>
            <p class="text-2xl font-bold mb-1"><?php echo formatarMoeda($valorMes); ?></p>
            <p class="text-green-100 text-xs font-semibold">Em pedidos</p>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl p-5 text-white shadow-lg">
        <div class="h-full flex flex-col items-center justify-center text-center">
            <div class="bg-white/20 p-3 rounded-full mb-3">
                <i class="fas fa-clock text-3xl"></i>
            </div>
            <p class="text-purple-100 text-sm font-bold mb-2">Pedidos Pendentes</p>
            <p class="text-3xl font-bold mb-1"><?php echo $pedidosPendentes; ?></p>
            <p class="text-purple-100 text-xs font-semibold">Aguardando</p>
        </div>
    </div>
</div>

<!-- Link do Catálogo -->
<div class="white-card mb-6">
    <div class="p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">
            <i class="fas fa-link text-purple-600"></i> Link do seu Catálogo
        </h3>
        
        <div class="link-box flex items-center gap-4 bg-gray-50 rounded-lg p-4">
            <div class="flex-1">
                <p class="text-sm text-gray-600 mb-2">Compartilhe este link com seus clientes:</p>
                <input 
                    type="text" 
                    id="linkCatalogo" 
                    value="<?php echo htmlspecialchars($linkCatalogo); ?>" 
                    readonly
                    class="w-full px-4 py-3 bg-white border-2 border-purple-200 rounded-lg font-mono text-sm md:text-base"
                >
            </div>
            <button onclick="copiarLink()" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition font-semibold whitespace-nowrap">
                <i class="fas fa-copy"></i> Copiar
            </button>
        </div>
        
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="<?php echo htmlspecialchars($linkCatalogo); ?>" target="_blank" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                <i class="fas fa-external-link-alt"></i> Visualizar Catálogo
            </a>
            
            <button onclick="compartilharWhatsApp()" class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm">
                <i class="fab fa-whatsapp"></i> Compartilhar no WhatsApp
            </button>
            
            <button onclick="compartilharFacebook()" class="inline-flex items-center gap-2 bg-blue-800 text-white px-4 py-2 rounded-lg hover:bg-blue-900 transition text-sm">
                <i class="fab fa-facebook"></i> Compartilhar no Facebook
            </button>
        </div>
    </div>
</div>

<!-- QR Code e Instruções -->
<div class="qr-code-section grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    
    <!-- QR Code -->
    <div class="white-card">
        <div class="p-6 text-center">
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-qrcode text-purple-600"></i> QR Code
            </h3>
            
            <div class="bg-white p-4 rounded-lg inline-block shadow-md mb-4">
                <div id="qrcode"></div>
            </div>
            
            <p class="text-sm text-gray-600 mb-4">
                Seus clientes podem escanear este QR Code para acessar seu catálogo diretamente
            </p>
            
            <button onclick="baixarQRCode()" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition font-semibold">
                <i class="fas fa-download"></i> Baixar QR Code
            </button>
        </div>
    </div>
    
    <!-- Como funciona -->
    <div class="white-card">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-blue-600"></i> Como Funciona
            </h3>
            
            <div class="space-y-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 font-bold">
                        1
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">Compartilhe o link</p>
                        <p class="text-sm text-gray-600">Envie o link ou QR Code para seus clientes pelo WhatsApp, redes sociais ou imprima em cartões</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 font-bold">
                        2
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">Cliente navega pelo catálogo</p>
                        <p class="text-sm text-gray-600">Seus clientes veem todos os produtos disponíveis, preços e podem adicionar ao carrinho</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 font-bold">
                        3
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">Cliente finaliza o pedido</p>
                        <p class="text-sm text-gray-600">O cliente informa seus dados e confirma o pedido</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 font-bold">
                        4
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">Você recebe a notificação</p>
                        <p class="text-sm text-gray-600">O pedido aparece aqui no painel e você pode gerenciá-lo</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                <p class="text-sm text-blue-800 font-semibold">
                    <i class="fas fa-lightbulb"></i> Dica
                </p>
                <p class="text-sm text-blue-700 mt-1">
                    Mantenha seus produtos sempre atualizados e com fotos de qualidade para aumentar suas vendas!
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Pedidos Recentes do Catálogo -->
<div class="white-card">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900">
                <i class="fas fa-list text-green-600"></i> Pedidos Recentes do Catálogo
            </h3>
            <a href="pedidos-catalogo.php" class="text-purple-600 hover:text-purple-700 font-semibold">
                Ver Todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <?php
        try {
            $stmt = $db->prepare("
                SELECT 
                    pc.*,
                    (SELECT COUNT(*) FROM pedidos_catalogo_itens WHERE pedido_id = pc.id) as total_itens
                FROM pedidos_catalogo pc
                WHERE pc.empresa_id = ?
                ORDER BY pc.data_pedido DESC
                LIMIT 5
            ");
            $stmt->execute([$empresaId]);
            $pedidosRecentes = $stmt->fetchAll();
            
            if (empty($pedidosRecentes)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-inbox text-5xl text-gray-300 mb-3"></i>
                    <p class="text-gray-600">Nenhum pedido recebido ainda</p>
                    <p class="text-sm text-gray-500 mt-2">Quando seus clientes fizerem pedidos pelo catálogo, eles aparecerão aqui</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($pedidosRecentes as $pedido): 
                        $statusClasses = [
                            'pendente' => 'bg-yellow-100 text-yellow-800',
                            'confirmado' => 'bg-blue-100 text-blue-800',
                            'em_producao' => 'bg-purple-100 text-purple-800',
                            'pronto' => 'bg-green-100 text-green-800',
                            'enviado' => 'bg-cyan-100 text-cyan-800',
                            'entregue' => 'bg-gray-100 text-gray-800',
                            'cancelado' => 'bg-red-100 text-red-800'
                        ];
                        $statusClass = $statusClasses[$pedido['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-bold text-gray-900">#<?php echo htmlspecialchars($pedido['codigo_pedido']); ?></span>
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $pedido['status'])); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($pedido['cliente_nome']); ?>
                                </p>
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-clock"></i> <?php echo formatarData($pedido['data_pedido'], 'd/m/Y H:i'); ?> •
                                    <i class="fas fa-box"></i> <?php echo $pedido['total_itens']; ?> item(ns)
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-purple-600"><?php echo formatarMoeda($pedido['valor_total']); ?></p>
                                <a href="pedidos-catalogo.php?id=<?php echo $pedido['id']; ?>" class="text-sm text-blue-600 hover:text-blue-700">
                                    Ver detalhes <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif;
        } catch (PDOException $e) {
            echo '<p class="text-red-600">Erro ao carregar pedidos</p>';
        }
        ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
// Gerar QR Code
const qrcode = new QRCode(document.getElementById("qrcode"), {
    text: "<?php echo addslashes($linkCatalogo); ?>",
    width: 200,
    height: 200,
    colorDark: "#8B5CF6",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

function copiarLink() {
    const input = document.getElementById('linkCatalogo');
    input.select();
    document.execCommand('copy');
    
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
    btn.classList.add('bg-green-600');
    btn.classList.remove('bg-purple-600');
    
    setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('bg-green-600');
        btn.classList.add('bg-purple-600');
    }, 2000);
}

function compartilharWhatsApp() {
    const texto = encodeURIComponent('Confira nosso catálogo de produtos! ' + '<?php echo addslashes($linkCatalogo); ?>');
    window.open('https://wa.me/?text=' + texto, '_blank');
}

function compartilharFacebook() {
    const url = encodeURIComponent('<?php echo addslashes($linkCatalogo); ?>');
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, '_blank');
}

function baixarQRCode() {
    const canvas = document.querySelector('#qrcode canvas');
    if (canvas) {
        const url = canvas.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = url;
        a.download = 'qrcode-catalogo.png';
        a.click();
    }
}
</script>

<?php require_once 'footer.php'; ?>