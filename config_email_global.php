<?php
// config_email_global.php - Configurações SMTP padrão do sistema (usado quando a loja não configura ou para fallback)

return [
    'smtp_host'     => 'mail.extremesti.com.br',
    'smtp_port'     => 465,
    'smtp_secure'   => 'ssl',               // ou 'tls' se preferir porta 587
    'smtp_username' => 'paperart@extremesti.com.br',
    'smtp_password' => 'paperart@123',      // Coloque a senha real aqui (mantenha seguro!)
    'from_email'    => 'paperart@extremesti.com.br',
    'from_name'     => 'Notificações PaperArt',  // Pode mudar para o nome da sua marca
];