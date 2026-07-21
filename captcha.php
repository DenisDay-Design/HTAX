<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$primeiro = random_int(2, 9);
$segundo = random_int(1, 9);
$_SESSION['lead_captcha_answer'] = (string) ($primeiro + $segundo);

echo json_encode([
    'ok' => true,
    'pergunta' => "Quanto é {$primeiro} + {$segundo}?",
], JSON_UNESCAPED_UNICODE);
