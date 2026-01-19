<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

require_once '../config.php';

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['empresa']) || !isset($data['carrinho'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$empresaId = (int)$data['empresa'];
$carrinho = $data['carrinho'];

try {
    $db = getDB();
    
    // Validar empresa
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND ativo = 1");
    $stmt->execute([$empresaId]);
    if (!$stmt->fetch()) {
        throw new Exception('Empresa não encontrada');
    }
    
    // Validar produtos e estoque
    foreach ($carrinho as &$item) {
        $stmt = $db->prepare("
            SELECT estoque_atual, preco_venda 
            FROM produtos 
            WHERE id = ? AND ativo = 1 AND empresa_id = ?
        ");
        $stmt->execute([$item['id'], $empresaId]);
        $produto = $stmt->fetch();
        
        if (!$produto) {
            throw new Exception('Produto não encontrado');
        }
        
        if ($item['quantidade'] > $produto['estoque_atual']) {
            throw new Exception('Quantidade excede o estoque disponível');
        }
        
        // Atualizar preço (garantir preço atual)
        $item['preco'] = $produto['preco_venda'];
    }
    
    // Salvar na sessão
    $_SESSION['carrinho'] = $carrinho;
    $_SESSION['carrinho_empresa'] = $empresaId;
    
    echo json_encode([
        'success' => true,
        'message' => 'Carrinho atualizado',
        'total_itens' => count($carrinho),
        'quantidade_total' => array_sum(array_column($carrinho, 'quantidade'))
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
