<?php
/**
 * MIDDLEWARE: Correção Automática do Carrinho
 * 
 * Este arquivo deve ser incluído no início de:
 * - catalogo.php
 * - carrinho.php
 * - pedido-online.php
 * 
 * Ele garante que todos os itens do carrinho tenham 'produto_id' correto
 */

// Só executar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_ACTIVE) {
    
    // Verificar se há carrinho
    if (isset($_SESSION['carrinho']) && is_array($_SESSION['carrinho']) && !empty($_SESSION['carrinho'])) {
        
        $corrigiu = false;
        
        // Corrigir cada item
        foreach ($_SESSION['carrinho'] as $index => &$item) {
            
            // CORREÇÃO 1: Se tem 'id' mas não tem 'produto_id'
            if (isset($item['id']) && !isset($item['produto_id'])) {
                $item['produto_id'] = $item['id'];
                unset($item['id']);
                $corrigiu = true;
            }
            
            // CORREÇÃO 2: Se produto_id é 0 ou null, remover item
            if (!isset($item['produto_id']) || empty($item['produto_id']) || $item['produto_id'] <= 0) {
                unset($_SESSION['carrinho'][$index]);
                $corrigiu = true;
            }
        }
        unset($item); // Quebrar referência
        
        // Reindexar array se removeu algum item
        if ($corrigiu) {
            $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
        }
    }
}
?>
