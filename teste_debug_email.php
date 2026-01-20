<?php
// teste_debug_email.php - Rode diretamente no navegador: https://extremesti.com.br/up/teste_debug_email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    // Debug completo - veja TUDO no navegador e no error_log
    $mail->SMTPDebug = 3;  // 3 = debug verbose (mostra conversa completa SMTP)
    $mail->Debugoutput = 'html';  // Mostra no navegador (mais fácil de ler)

    $mail->isSMTP();
    $mail->Host       = 'mail.extremesti.com.br';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'paperart@extremesti.com.br';
    $mail->Password   = 'paperart@123';  // Troque pela senha real se mudou
    $mail->SMTPSecure = 'ssl';           // ou 'tls' se testar porta 587
    $mail->Port       = 465;             // Teste primeiro 465/ssl, depois mude para 587/tls

    // Remetente - Use EXATAMENTE o email autenticado
    $mail->setFrom('paperart@extremesti.com.br', 'Teste Debug Mauro');
    $mail->addReplyTo('paperart@extremesti.com.br', 'Teste Debug Mauro');

    // Destinatário - Use um email que você controla (ex: seu Gmail pessoal)
    $mail->addAddress('maurocarlos.ti@gmail.com', 'Mauro Teste');  // ← MUDE PARA SEU EMAIL REAL

    $mail->Subject = 'TESTE DEBUG PHPMailer - ' . date('d/m/Y H:i:s');
    $mail->Body    = "Este é um teste de debug.\n\nSe você recebeu, o envio funcionou.\nData: " . date('d/m/Y H:i:s');
    $mail->AltBody = strip_tags($mail->Body);

    $mail->send();
    echo '<h2 style="color:green">Mensagem enviada com sucesso (PHPMailer reportou OK)</h2>';
    echo '<p>Verifique sua caixa de entrada/spam. Se não chegou, veja abaixo o debug completo:</p>';

} catch (Exception $e) {
    echo '<h2 style="color:red">Erro ao enviar:</h2>';
    echo '<pre>' . $mail->ErrorInfo . '</pre>';
}

// Força mostrar o debug mesmo se der erro
echo '<hr><h3>Debug completo da conversa SMTP:</h3><pre>';
// Se quiser log extra no arquivo: error_log(print_r($mail->Debugoutput, true));
echo '</pre>';
?>