<?php
/**
 * Copie este arquivo para config.php e preencha com os dados da hospedagem.
 * Não publique config.php em repositórios ou pastas acessíveis pela web.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'hinnig_leads',
        'user' => 'SEU_USUARIO_MYSQL',
        'pass' => 'SUA_SENHA_MYSQL',
    ],
    'email' => [
        'destination' => 'contato@seudominio.com.br',
        'sender' => 'noreply@seudominio.com.br',
        'name' => 'Hinnig Tax & Assets',
    ],
    'recaptcha' => [
        // Chave privada do Google reCAPTCHA v2. Nunca publique este valor no HTML.
        'secret' => 'SUA_SECRET_KEY_DO_RECAPTCHA',
    ],
];
