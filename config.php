<?php
/**
 * Configuração do Banco de Dados
 * Sistema de Gestão para Papelaria
 */

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');           // Host do banco de dados
define('DB_NAME', 'extremes_paperup');    // Nome do banco de dados
define('DB_USER', 'extremes_paperupuser');                // Usuário do banco de dados
define('DB_PASS', 'paperup@123');                    // Senha do banco de dados
define('DB_CHARSET', 'utf8mb4');          // Charset

// Configurações do Sistema
define('SITE_URL', 'http://www.extremesti.com.br/up/'); // URL base do sistema
define('SITE_NAME', 'PaperArt - Sistema de Gestão');     // Nome do sistema
define('SITE_EMAIL', 'contato@papelaria.com');          // Email do sistema

// Configurações de Segurança
define('SESSION_NAME', 'PAPELARIA_SESSION');
define('SESSION_LIFETIME', 7200); // 2 horas em segundos
define('PASSWORD_SALT', 'SEU_SALT_AQUI_MUDE_ISSO'); // Mude isso para um valor único

// Configurações de Upload
define('UPLOAD_DIR', __DIR__ . '/uploads/produtos/');
define('MAX_FILE_SIZE', 5242880); // 5MB em bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Exibição de erros (ajuste para produção)
// Em PRODUÇÃO, use: error_reporting(E_ERROR | E_PARSE);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

/**
 * Classe de Conexão com o Banco de Dados
 */
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            die("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    // Previne clonagem do objeto
    private function __clone() {}
    
    // Previne deserialização
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Funções auxiliares
 */

/**
 * Obtém conexão com o banco de dados
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Sanitiza string
 */
function sanitize($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata data brasileira
 */
function formatarData($data, $formato = 'd/m/Y') {
    if (empty($data)) return '-';
    return date($formato, strtotime($data));
}

/**
 * Formata valor monetário
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Gera senha hash
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifica senha
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Gera token aleatório
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Redireciona para uma URL
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

/**
 * Verifica se usuário está logado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtém dados do usuário logado
 */
function getUser() {
    if (!isLoggedIn()) return null;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nome, email, nivel_acesso FROM usuarios WHERE id = ? AND ativo = 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Verifica se usuário é admin
 */
function isAdmin() {
    $user = getUser();
    return $user && $user['nivel_acesso'] === 'admin';
}

/**
 * Registra atividade no log
 */
function logActivity($acao, $tabela = null, $registro_id = null, $detalhes = null) {
    if (!isLoggedIn()) return;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO log_atividades 
            (usuario_id, acao, tabela, registro_id, detalhes, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $acao,
            $tabela,
            $registro_id,
            $detalhes,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Silenciosamente falha
    }
}

/**
 * Envia resposta JSON
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Valida email
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Valida telefone brasileiro
 */
function validarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    return strlen($telefone) >= 10 && strlen($telefone) <= 11;
}

/**
 * Formata telefone brasileiro
 */
function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    if (strlen($telefone) == 11) {
        return preg_replace('/^(\d{2})(\d{5})(\d{4})$/', '($1) $2-$3', $telefone);
    } else if (strlen($telefone) == 10) {
        return preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2-$3', $telefone);
    }
    
    return $telefone;
}

/**
 * Upload de arquivo
 */
function uploadFile($file, $pasta = 'geral') {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Parâmetros inválidos.');
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Nenhum arquivo foi enviado.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Arquivo excede o tamanho limite.');
        default:
            throw new RuntimeException('Erro desconhecido.');
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('Arquivo excede o tamanho limite.');
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $ext = array_search(
        $finfo->file($file['tmp_name']),
        [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ],
        true
    );
    
    if ($ext === false) {
        throw new RuntimeException('Formato de arquivo inválido.');
    }
    
    $uploadDir = UPLOAD_DIR . $pasta . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = sprintf('%s_%s.%s',
        date('YmdHis'),
        md5(uniqid('', true)),
        $ext
    );
    
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        throw new RuntimeException('Falha ao mover arquivo enviado.');
    }
    
    return $pasta . '/' . $fileName;
}

/**
 * Remove arquivo
 */
function deleteFile($filePath) {
    $fullPath = UPLOAD_DIR . $filePath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Paginação
 */
function paginate($totalItems, $itemsPerPage = 20, $currentPage = 1) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}
