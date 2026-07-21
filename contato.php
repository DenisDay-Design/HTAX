<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
session_start();

set_exception_handler(function (Throwable $erro): void {
    error_log('[Hinnig] Erro não tratado no formulário: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'O formulário não pôde ser processado no momento.'], JSON_UNESCAPED_UNICODE);
    exit;
});

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function texto(string $chave, int $limite = 0): string {
    $valor = trim((string) ($_POST[$chave] ?? ''));
    $valor = strip_tags($valor);
    if ($limite <= 0) return $valor;
    return function_exists('mb_substr') ? mb_substr($valor, 0, $limite) : substr($valor, 0, $limite);
}

function validarRecaptcha(string $token, string $secret, string $ip): bool {
    if ($token === '' || $secret === '') return false;

    $dados = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ]);
    $resposta = false;

    if (function_exists('curl_init')) {
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $dados,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resposta = curl_exec($curl);
        curl_close($curl);
    } else {
        $contexto = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $dados,
            'timeout' => 10,
        ]]);
        $resposta = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $contexto);
    }

    $resultado = is_string($resposta) ? json_decode($resposta, true) : null;
    return is_array($resultado) && !empty($resultado['success']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'erro' => 'Método não permitido.']);
}

// Honeypot: bots normalmente preenchem este campo invisível.
if (texto('bot-field') !== '' || texto('website') !== '') {
    responder(200, ['ok' => true]);
}

$arquivoConfig = __DIR__ . '/config.php';
if (!is_file($arquivoConfig)) {
    error_log('[Hinnig] config.php não encontrado.');
    responder(503, ['ok' => false, 'erro' => 'O formulário está temporariamente indisponível.']);
}

$config = require $arquivoConfig;
if (!is_array($config) || empty($config['db']) || empty($config['email']) || empty($config['recaptcha']['secret'])) {
    error_log('[Hinnig] config.php inválido.');
    responder(503, ['ok' => false, 'erro' => 'O formulário está temporariamente indisponível.']);
}

$nome = texto('nome', 200);
$empresa = texto('empresa', 200);
$cargo = texto('cargo', 200);
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$telefone = texto('telefone', 50) ?: texto('whatsapp', 50);
$faturamento = texto('faturamento', 100);
$regime = texto('regime', 100);
$desafio = texto('desafio', 5000);
$origem = texto('origem', 100) ?: 'site';
$paginaOrigem = texto('pagina_origem', 255);
$utmSource = texto('utm_source', 100);
$utmMedium = texto('utm_medium', 100);
$utmCampaign = texto('utm_campaign', 150);
$utmTerm = texto('utm_term', 150);
$utmContent = texto('utm_content', 150);
$consentimento = (string) ($_POST['consentimento_marketing'] ?? '') === '1';
$recaptchaToken = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

$erros = [];
if ($nome === '') $erros[] = 'Nome é obrigatório.';
if ($empresa === '') $erros[] = 'Empresa é obrigatória.';
if (!$email) $erros[] = 'E-mail inválido.';
if ($telefone === '') $erros[] = 'WhatsApp é obrigatório.';
if (!validarRecaptcha($recaptchaToken, (string) $config['recaptcha']['secret'], $ip)) {
    $erros[] = 'Confirme a verificação “Não sou um robô” antes de enviar.';
}
if ($erros) responder(422, ['ok' => false, 'erros' => $erros]);

$db = $config['db'];
$emailConfig = $config['email'];
$ipHash = $ip !== '' ? hash('sha256', $ip) : null;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['name']),
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $stmt = $pdo->prepare(
        'INSERT INTO leads (nome, empresa, cargo, email, telefone, faturamento, regime, desafio, origem, pagina_origem, utm_source, utm_medium, utm_campaign, utm_term, utm_content, consentimento_marketing, consentimento_em, ip_hash)
         VALUES (:nome, :empresa, :cargo, :email, :telefone, :faturamento, :regime, :desafio, :origem, :pagina_origem, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content, :consentimento_marketing, :consentimento_em, :ip_hash)'
    );
    $stmt->execute([
        ':nome' => $nome, ':empresa' => $empresa, ':cargo' => $cargo ?: null,
        ':email' => $email, ':telefone' => $telefone, ':faturamento' => $faturamento ?: null,
        ':regime' => $regime ?: null, ':desafio' => $desafio ?: null, ':origem' => $origem,
        ':pagina_origem' => $paginaOrigem ?: null, ':utm_source' => $utmSource ?: null,
        ':utm_medium' => $utmMedium ?: null, ':utm_campaign' => $utmCampaign ?: null,
        ':utm_term' => $utmTerm ?: null, ':utm_content' => $utmContent ?: null,
        ':consentimento_marketing' => $consentimento ? 1 : 0,
        ':consentimento_em' => $consentimento ? date('Y-m-d H:i:s') : null,
        ':ip_hash' => $ipHash,
    ]);
} catch (Throwable $e) {
    error_log('[Hinnig] Falha ao salvar lead: ' . $e->getMessage());
    responder(500, ['ok' => false, 'erro' => 'Não foi possível registrar sua solicitação. Tente novamente.']);
}

$assunto = 'Novo lead — ' . $nome . ' (' . $empresa . ')';
$mensagem = "Nome: {$nome}\nEmpresa: {$empresa}\nCargo: {$cargo}\nE-mail: {$email}\nWhatsApp: {$telefone}\nFaturamento: {$faturamento}\nRegime: {$regime}\nDesafio: {$desafio}\nOrigem: {$origem}\nConsentimento de marketing: " . ($consentimento ? 'Sim' : 'Não');
$cabecalhos = [
    'From: ' . $emailConfig['name'] . ' <' . $emailConfig['sender'] . '>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
];
if (!mail($emailConfig['destination'], $assunto, $mensagem, implode("\r\n", $cabecalhos))) {
    error_log('[Hinnig] Lead salvo, mas e-mail não enviado.');
}

responder(201, ['ok' => true, 'mensagem' => 'Solicitação recebida com sucesso!']);
