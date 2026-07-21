<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

set_exception_handler(function (Throwable $erro): void {
    error_log('[Hinnig] Falha ao gerar captcha: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível gerar a verificação.'], JSON_UNESCAPED_UNICODE);
    exit;
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'A sessão de verificação não foi iniciada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$primeiro = random_int(2, 9);
$segundo = random_int(1, 9);
$_SESSION['lead_captcha_answer'] = (string) ($primeiro + $segundo);

echo json_encode([
    'ok' => true,
    'pergunta' => "Quanto é {$primeiro} + {$segundo}?",
], JSON_UNESCAPED_UNICODE);
