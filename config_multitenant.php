<?php
/**
 * SISTEMA MULTI-TENANT
 * Cada usuário admin representa uma empresa diferente
 * Nenhuma empresa pode ver dados de outra empresa
 */

/**
 * Obtém o ID da empresa do usuário logado
 * Se for admin, o próprio ID do usuário é a empresa
 * Se for usuário comum, busca o admin responsável
 */
function getEmpresaId() {
    if (!isLoggedIn()) return null;
    
    // Se já está em sessão, retorna
    if (isset($_SESSION['empresa_id'])) {
        return $_SESSION['empresa_id'];
    }
    
    try {
        $db = getDB();
        $user = getUser();
        
        if (!$user) return null;
        
        // Se é admin, ele É a empresa
        if ($user['nivel_acesso'] === 'admin') {
            $_SESSION['empresa_id'] = $user['id'];
            return $user['id'];
        }
        
        // Se é usuário comum, busca qual admin ele pertence
        // (isso seria para quando você criar usuários subordinados a uma empresa)
        // Por enquanto, cada usuário admin é uma empresa independente
        return null;
        
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Verifica se o usuário é o super admin (primeiro admin criado)
 */
function isSuperAdmin() {
    if (!isLoggedIn()) return false;
    
    $user = getUser();
    if (!$user) return false;
    
    // ID 1 é o super admin
    return $user['id'] == 1 && $user['nivel_acesso'] === 'admin';
}

/**
 * Adiciona filtro de empresa em queries SQL
 * Uso: WHERE {adicionar_filtro_empresa('usuarios', 'u')}
 */
function getFiltroEmpresaSQL($tabela, $alias = null) {
    // Super admin vê tudo
    if (isSuperAdmin()) {
        return "1=1";
    }
    
    $empresaId = getEmpresaId();
    if (!$empresaId) {
        return "1=0"; // Não mostra nada se não tiver empresa
    }
    
    $prefixo = $alias ? $alias . '.' : '';
    
    // Para cada tabela, define qual coluna usar
    switch ($tabela) {
        case 'usuarios':
            // Usuários: mostrar apenas da própria empresa
            return "{$prefixo}id = $empresaId OR {$prefixo}empresa_id = $empresaId";
            
        case 'produtos':
        case 'clientes':
        case 'pedidos':
        case 'categorias':
        case 'financeiro':
            // Outras tabelas: filtrar por empresa_id
            return "{$prefixo}empresa_id = $empresaId";
            
        default:
            return "1=1";
    }
}

/**
 * Adiciona empresa_id automaticamente em INSERTs
 */
function adicionarEmpresaId() {
    $empresaId = getEmpresaId();
    return $empresaId ? $empresaId : null;
}

/**
 * Verifica se um registro pertence à empresa do usuário
 */
function pertenceAEmpresa($tabela, $registroId) {
    // Super admin tem acesso a tudo
    if (isSuperAdmin()) {
        return true;
    }
    
    $empresaId = getEmpresaId();
    if (!$empresaId) {
        return false;
    }
    
    try {
        $db = getDB();
        
        // Para usuários, verificar se é da mesma empresa
        if ($tabela === 'usuarios') {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND (id = ? OR empresa_id = ?)");
            $stmt->execute([$registroId, $empresaId, $empresaId]);
        } else {
            // Para outras tabelas
            $stmt = $db->prepare("SELECT id FROM {$tabela} WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$registroId, $empresaId]);
        }
        
        return $stmt->fetch() !== false;
        
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Validar acesso antes de operações críticas
 */
function validarAcessoEmpresa($tabela, $registroId) {
    if (!pertenceAEmpresa($tabela, $registroId)) {
        $_SESSION['error'] = 'Acesso negado! Este registro não pertence à sua empresa.';
        header('Location: dashboard.php');
        exit;
    }
}