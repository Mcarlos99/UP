<?php
/**
 * Classe para gerenciar envio de emails de notificação
 * Adaptado para o sistema multi-tenant PapelOn
 */

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

class EmailNotificacao {
    private $db;
    private $empresa_id;
    private $config;
    
    public function __construct($db, $empresa_id) {
        $this->db = $db;
        $this->empresa_id = $empresa_id;
        $this->carregarConfiguracao();
    }
    
private function carregarConfiguracao() {
    $stmt = $this->db->prepare("SELECT * FROM configuracoes_empresa WHERE empresa_id = ?");
    $stmt->execute([$this->empresa_id]);
    $empresaConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    // Carrega o global (SMTP + remetente padrão)
    $global = require __DIR__ . '/config_email_global.php';

    // Mescla configs
    $this->config = array_merge($global, $empresaConfig ?? []);

    // FORÇA o remetente global (não deixa a loja mudar o From)
    $this->config['from_email']      = $global['from_email'];          // paperart@extremesti.com.br
    $this->config['from_name']       = $global['from_name'];

    // O destinatário das notificações vem da config da empresa (ou fallback se vazio)
    $this->config['destinatario_notificacao'] = 
        $empresaConfig['email_notificacao'] ?? 
        $global['from_email'];  // fallback para o próprio global se a loja não configurou

    // Log para debug (remova depois de testar)
    error_log("Notificação será enviada PARA: " . $this->config['destinatario_notificacao']);
    error_log("Remetente (From): " . $this->config['from_email']);
}


    
    /**
     * Envia notificação de novo pedido
     */
    public function notificarNovoPedido($pedido_id) {
        // Verificar se o envio de email está habilitado
        if (!$this->config['enviar_email_pedido']) {
            return false;
        }
        
        // Buscar detalhes do pedido
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                c.nome as cliente_nome,
                c.email as cliente_email,
                c.telefone as cliente_telefone,
                u.nome as vendedor_nome
            FROM pedidos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.id = ? AND p.empresa_id = ?
        ");
        $stmt->execute([$pedido_id, $this->empresa_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            throw new Exception('Pedido não encontrado');
        }
        
        // Buscar itens do pedido
        $stmt = $this->db->prepare("
            SELECT 
                pi.*,
                p.nome as produto_nome,
                p.codigo as produto_codigo
            FROM pedidos_itens pi
            JOIN produtos p ON pi.produto_id = p.id
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedido_id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Montar email
        $assunto = "Novo Pedido #" . $pedido['id'] . " - " . $this->config['nome_empresa'];
        $corpo = $this->montarCorpoEmail($pedido, $itens);
        
        // Enviar email
        return $this->enviarEmail($this->config['destinatario_notificacao'], $assunto, $corpo);
    }
    
    
    
    /**
 * Envia notificação de novo pedido vindo do catálogo online
 * @param int $pedidoId ID do pedido na tabela pedidos_catalogo
 * @return bool Sucesso do envio
 */
public function notificarNovoPedidoCatalogo($pedidoId) {
    // Verificar se envio está habilitado (opcional - pode remover se quiser sempre enviar)
    // if (!$this->config['enviar_email_pedido']) return false;

    // Buscar detalhes do pedido do CATÁLOGO
    $stmt = $this->db->prepare("
        SELECT 
            pc.*,
            pc.cliente_nome,
            pc.cliente_email,
            pc.cliente_telefone,
            pc.endereco_completo,
            pc.cep,
            pc.valor_total,
            pc.forma_pagamento_preferencial,
            pc.observacoes
        FROM pedidos_catalogo pc
        WHERE pc.id = ? AND pc.empresa_id = ?
    ");
    $stmt->execute([$pedidoId, $this->empresa_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('Pedido do catálogo não encontrado');
    }

    // Buscar itens do pedido
    $stmtItens = $this->db->prepare("
        SELECT 
            pci.*,
            '' as produto_codigo,  -- se não tiver código, deixe vazio
            pci.produto_nome
        FROM pedidos_catalogo_itens pci
        WHERE pci.pedido_id = ?
    ");
    $stmtItens->execute([$pedidoId]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    // Montar assunto e corpo (reutilizando lógica similar)
    $assunto = "Novo Pedido do Catálogo #" . $pedido['codigo_pedido'] . " - " . $this->config['nome_empresa'];

    $corpo = $this->config['mensagem_email_pedido'] ?? "Novo pedido recebido pelo catálogo online!\n\n";
    $corpo .= "═══════════════════════════════════════════\n";
    $corpo .= "PEDIDO #" . $pedido['codigo_pedido'] . "\n";
    $corpo .= "═══════════════════════════════════════════\n\n";

    $corpo .= "Data/Hora: " . date('d/m/Y H:i', strtotime($pedido['data_criacao'] ?? 'now')) . "\n";
    $corpo .= "Status: Pendente\n\n";

    $corpo .= "--- CLIENTE ---\n";
    $corpo .= "Nome: " . $pedido['cliente_nome'] . "\n";
    if ($pedido['cliente_email']) $corpo .= "Email: " . $pedido['cliente_email'] . "\n";
    $corpo .= "Telefone: " . $pedido['cliente_telefone'] . "\n\n";

    if ($pedido['endereco_completo']) {
        $corpo .= "--- ENDEREÇO DE ENTREGA ---\n";
        $corpo .= $pedido['endereco_completo'] . "\n";
        $corpo .= "CEP: " . $pedido['cep'] . "\n\n";
    }

    $corpo .= "--- ITENS DO PEDIDO ---\n";
    foreach ($itens as $item) {
        $corpo .= sprintf(
            "%s\n  Qtd: %d | Preço: R$ %.2f | Subtotal: R$ %.2f\n",
            $item['produto_nome'],
            $item['quantidade'],
            $item['preco_unitario'],
            $item['subtotal']
        );
    }

    $corpo .= "\n" . str_repeat("-", 43) . "\n";
    $corpo .= sprintf("TOTAL: R$ %.2f\n", $pedido['valor_total']);
    $corpo .= str_repeat("=", 43) . "\n\n";

    if ($pedido['observacoes']) {
        $corpo .= "Observações: " . $pedido['observacoes'] . "\n\n";
    }

    if ($pedido['forma_pagamento_preferencial']) {
        $corpo .= "Forma de pagamento preferencial: " . ucfirst($pedido['forma_pagamento_preferencial']) . "\n\n";
    }

    $corpo .= "Acesse o painel para visualizar e gerenciar o pedido.\n";
    $corpo .= "\n---\n" . $this->config['nome_empresa'];

    // Enviar
    return $this->enviarEmail($this->config['destinatario_notificacao'], $assunto, $corpo);
}
    
    
    
    
    /**
     * Monta o corpo do email de notificação
     */
    private function montarCorpoEmail($pedido, $itens) {
        $corpo = $this->config['mensagem_email_pedido'] . "\n\n";
        $corpo .= "═══════════════════════════════════════════\n";
        $corpo .= "DETALHES DO PEDIDO #" . $pedido['id'] . "\n";
        $corpo .= "═══════════════════════════════════════════\n\n";
        
        // Informações do pedido
        $corpo .= "Data: " . date('d/m/Y H:i', strtotime($pedido['data_criacao'])) . "\n";
        $corpo .= "Status: " . $this->formatarStatus($pedido['status']) . "\n";
        
        if ($pedido['cliente_nome']) {
            $corpo .= "\n--- CLIENTE ---\n";
            $corpo .= "Nome: " . $pedido['cliente_nome'] . "\n";
            if ($pedido['cliente_email']) {
                $corpo .= "Email: " . $pedido['cliente_email'] . "\n";
            }
            if ($pedido['cliente_telefone']) {
                $corpo .= "Telefone: " . $pedido['cliente_telefone'] . "\n";
            }
        }
        
        if ($pedido['vendedor_nome']) {
            $corpo .= "\nVendedor: " . $pedido['vendedor_nome'] . "\n";
        }
        
        // Itens do pedido
        $corpo .= "\n--- ITENS DO PEDIDO ---\n";
        foreach ($itens as $item) {
            $codigo = $item['produto_codigo'] ? $item['produto_codigo'] . ' - ' : '';
            $corpo .= sprintf(
                "%s%s\n  Qtd: %d | Preço: R$ %.2f | Subtotal: R$ %.2f\n",
                $codigo,
                $item['produto_nome'],
                $item['quantidade'],
                $item['preco_unitario'],
                $item['subtotal']
            );
        }
        
        // Total
        $corpo .= "\n" . str_repeat("-", 43) . "\n";
        $corpo .= sprintf("TOTAL: R$ %.2f\n", $pedido['valor_total']);
        $corpo .= str_repeat("=", 43) . "\n\n";
        
        if ($pedido['observacoes']) {
            $corpo .= "Observações: " . $pedido['observacoes'] . "\n\n";
        }
        
        $corpo .= "Acesse o sistema para mais detalhes e gerenciar o pedido.\n";
        $corpo .= "\n---\n";
        $corpo .= $this->config['nome_empresa'] . "\n";
        
        if ($this->config['telefone']) {
            $corpo .= "Tel: " . $this->config['telefone'] . "\n";
        }
        
        return $corpo;
    }
    
    /**
     * Formata o status do pedido
     */
    private function formatarStatus($status) {
        $status_map = [
            'pendente' => 'Pendente',
            'processando' => 'Em Processamento',
            'producao' => 'Em Produção',
            'pronto' => 'Pronto para Entrega',
            'enviado' => 'Enviado',
            'entregue' => 'Entregue',
            'cancelado' => 'Cancelado'
        ];
        
        return $status_map[$status] ?? ucfirst($status);
    }
    
    /**
     * Envia o email usando PHPMailer
     */
private function enviarEmail($para, $assunto, $corpo) {
    $mail = new PHPMailer(true);

    try {
        // ATIVA DEBUG MÁXIMO – veja TUDO
        $mail->SMTPDebug   = 0;                    // 3 = verbose completo
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };

        $mail->isSMTP();
        $mail->Host       = $this->config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->config['smtp_username'];
        $mail->Password   = $this->config['smtp_password'];
        $mail->SMTPSecure = $this->config['smtp_secure'];
        $mail->Port       = $this->config['smtp_port'];

        // Remetente
        $mail->setFrom($this->config['from_email'], $this->config['from_name']);
        $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);

        // Destinatário
        $mail->addAddress($para);   // deve ser denisemifer44@gmail.com

        $mail->isHTML(false);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo;
        $mail->CharSet = 'UTF-8';

        error_log("Tentando enviar email PARA: $para | ASSUNTO: $assunto");

        $mail->send();

        error_log("Envio reportado como SUCESSO pelo PHPMailer");

        $resultado = true;

    } catch (Exception $e) {
        error_log("PHPMailer FALHOU: " . $mail->ErrorInfo);
        error_log("Exceção completa: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        $resultado = false;
    }

    $this->registrarLog($para, $assunto, $resultado);

    return $resultado;
}
    
    
    
/**
 * Envia email de teste
 */
public function enviarEmailTeste($emailDestino) {

    $assunto = "Teste de Email - " . $this->config['nome_empresa'];

    $corpo = "Este é um email de TESTE do sistema.\n\n";
    $corpo .= "Empresa: " . $this->config['nome_empresa'] . "\n";
    $corpo .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";
    $corpo .= "Se você recebeu este email, as configurações estão corretas.\n\n";
    $corpo .= "---\nSistema PaperArt";

    return $this->enviarEmail($emailDestino, $assunto, $corpo);
}




    
    /**
     * Registra log de envio de email
     */
    private function registrarLog($para, $assunto, $sucesso) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO logs_email (empresa_id, destinatario, assunto, sucesso, data_envio)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $this->empresa_id,
                $para,
                $assunto,
                $sucesso ? 1 : 0
            ]);
        } catch (Exception $e) {
            // Se a tabela de logs não existir, ignora silenciosamente
            error_log("Erro ao registrar log de email: " . $e->getMessage());
        }
    }
}
?>