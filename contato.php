<?php
/**
 * Hinnig Tax & Assets — Processador do Formulário de Contato
 * Envia e-mail para o Renato + salva no banco de dados MySQL
 */

// ─── CONFIGURAÇÕES ───────────────────────────────────────────────
define('EMAIL_DESTINO',  'renato@hinnig.com.br');   // ← troque pelo e-mail real
define('EMAIL_REMETENTE','noreply@businessrh.com.br'); // ← domínio do site
define('EMAIL_NOME',     'Hinnig Tax & Assets');

// Banco de dados (preencha com os dados da Hostinger)
define('DB_HOST', 'localhost');
define('DB_NAME', 'hinnig_contatos');   // ← nome do banco criado na Hostinger
define('DB_USER', 'hinnig_user');       // ← usuário do banco
define('DB_PASS', 'SUA_SENHA_AQUI');    // ← senha do banco

// ─── HEADERS ─────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

// ─── LEITURA E SANITIZAÇÃO DOS DADOS ────────────────────────────
function limpa($valor) {
    return htmlspecialchars(strip_tags(trim($valor)), ENT_QUOTES, 'UTF-8');
}

$nome       = limpa($_POST['nome']       ?? '');
$empresa    = limpa($_POST['empresa']    ?? '');
$cargo      = limpa($_POST['cargo']      ?? '');
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$telefone   = limpa($_POST['telefone']   ?? '');
$faturamento= limpa($_POST['faturamento']?? '');
$regime     = limpa($_POST['regime']     ?? '');
$desafio    = limpa($_POST['desafio']    ?? '');

// ─── VALIDAÇÃO DOS CAMPOS OBRIGATÓRIOS ──────────────────────────
$erros = [];

if (empty($nome))        $erros[] = 'Nome é obrigatório.';
if (empty($empresa))     $erros[] = 'Empresa é obrigatória.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                         $erros[] = 'E-mail inválido.';
if (empty($telefone))    $erros[] = 'WhatsApp é obrigatório.';
if (empty($faturamento)) $erros[] = 'Faturamento é obrigatório.';
if (empty($desafio))     $erros[] = 'Desafio é obrigatório.';

if (!empty($erros)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erros' => $erros]);
    exit;
}

// ─── SALVAR NO BANCO DE DADOS ────────────────────────────────────
$salvo_db = false;
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Cria a tabela se não existir (na primeira vez)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS solicitacoes (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            nome         VARCHAR(200)  NOT NULL,
            empresa      VARCHAR(200)  NOT NULL,
            cargo        VARCHAR(200),
            email        VARCHAR(200)  NOT NULL,
            telefone     VARCHAR(50)   NOT NULL,
            faturamento  VARCHAR(100),
            regime       VARCHAR(100),
            desafio      TEXT,
            ip           VARCHAR(50),
            criado_em    DATETIME      DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmt = $pdo->prepare("
        INSERT INTO solicitacoes
            (nome, empresa, cargo, email, telefone, faturamento, regime, desafio, ip)
        VALUES
            (:nome, :empresa, :cargo, :email, :telefone, :faturamento, :regime, :desafio, :ip)
    ");

    $stmt->execute([
        ':nome'        => $nome,
        ':empresa'     => $empresa,
        ':cargo'       => $cargo,
        ':email'       => $email,
        ':telefone'    => $telefone,
        ':faturamento' => $faturamento,
        ':regime'      => $regime,
        ':desafio'     => $desafio,
        ':ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $salvo_db = true;

} catch (PDOException $e) {
    // Não interrompe o fluxo — ainda tenta enviar o e-mail
    error_log('[Hinnig] Erro DB: ' . $e->getMessage());
}

// ─── ENVIAR E-MAIL ───────────────────────────────────────────────
$data_hora = date('d/m/Y \à\s H:i');

$corpo_html = "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
  <meta charset='UTF-8'>
  <style>
    body { font-family: 'Montserrat', Arial, sans-serif; background:#F8F6F2; margin:0; padding:0; }
    .wrap { max-width:600px; margin:40px auto; background:#fff; border:1px solid #E8E2D6; }
    .topo { background:#111111; padding:32px 40px; }
    .topo h1 { font-family: Georgia, serif; font-size:22px; color:#B8922A; letter-spacing:3px; margin:0; }
    .topo p  { color:rgba(255,255,255,0.45); font-size:11px; letter-spacing:2px; margin:6px 0 0; text-transform:uppercase; }
    .corpo { padding:36px 40px; }
    .linha { border-bottom:1px solid #E8E2D6; padding:16px 0; display:flex; gap:16px; }
    .linha:last-child { border-bottom:none; }
    .label { font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#B8922A; font-weight:700; min-width:160px; padding-top:2px; }
    .valor { font-size:14px; color:#111; line-height:1.6; }
    .destaque { background:#FBF7EF; border-left:3px solid #B8922A; padding:20px 24px; margin-top:24px; }
    .destaque .label { margin-bottom:8px; }
    .destaque .valor { color:#555; }
    .rodape { background:#F8F6F2; border-top:1px solid #E8E2D6; padding:20px 40px; font-size:11px; color:#888; letter-spacing:1px; }
  </style>
</head>
<body>
<div class='wrap'>
  <div class='topo'>
    <h1>HINNIG TAX &amp; ASSETS</h1>
    <p>Nova solicitação de conversa estratégica — {$data_hora}</p>
  </div>
  <div class='corpo'>
    <div class='linha'>
      <span class='label'>Nome</span>
      <span class='valor'>{$nome}</span>
    </div>
    <div class='linha'>
      <span class='label'>Empresa</span>
      <span class='valor'>{$empresa}</span>
    </div>
    <div class='linha'>
      <span class='label'>Cargo</span>
      <span class='valor'>" . ($cargo ?: '—') . "</span>
    </div>
    <div class='linha'>
      <span class='label'>E-mail</span>
      <span class='valor'><a href='mailto:{$email}' style='color:#B8922A;'>{$email}</a></span>
    </div>
    <div class='linha'>
      <span class='label'>WhatsApp</span>
      <span class='valor'><a href='https://wa.me/55" . preg_replace('/\D/', '', $telefone) . "' style='color:#B8922A;'>{$telefone}</a></span>
    </div>
    <div class='linha'>
      <span class='label'>Faturamento anual</span>
      <span class='valor'>{$faturamento}</span>
    </div>
    <div class='linha'>
      <span class='label'>Regime tributário</span>
      <span class='valor'>" . ($regime ?: '—') . "</span>
    </div>
    <div class='destaque'>
      <div class='label'>Principal desafio</div>
      <div class='valor'>{$desafio}</div>
    </div>
  </div>
  <div class='rodape'>
    Enviado em {$data_hora} · IP: " . ($_SERVER['REMOTE_ADDR'] ?? '—') . "
  </div>
</div>
</body>
</html>
";

$corpo_texto = "
Nova solicitação de conversa estratégica — Hinnig Tax & Assets
================================================================
Data/hora : {$data_hora}
Nome      : {$nome}
Empresa   : {$empresa}
Cargo     : {$cargo}
E-mail    : {$email}
WhatsApp  : {$telefone}
Faturamento: {$faturamento}
Regime    : {$regime}

Desafio:
{$desafio}
";

// Cabeçalhos do e-mail (multipart para HTML + texto)
$boundary = md5(uniqid(rand(), true));
$headers  = implode("\r\n", [
    'From: ' . EMAIL_NOME . ' <' . EMAIL_REMETENTE . '>',
    'Reply-To: ' . $nome . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    'X-Mailer: PHP/' . phpversion(),
]);

$body = "--{$boundary}\r\n"
      . "Content-Type: text/plain; charset=utf-8\r\n\r\n"
      . $corpo_texto . "\r\n"
      . "--{$boundary}\r\n"
      . "Content-Type: text/html; charset=utf-8\r\n\r\n"
      . $corpo_html . "\r\n"
      . "--{$boundary}--";

$assunto = "Nova solicitação — {$nome} ({$empresa})";

$email_enviado = mail(EMAIL_DESTINO, $assunto, $body, $headers);

if (!$email_enviado) {
    error_log('[Hinnig] Falha ao enviar e-mail para ' . EMAIL_DESTINO);
}

// ─── RESPOSTA FINAL ──────────────────────────────────────────────
if ($salvo_db || $email_enviado) {
    echo json_encode([
        'ok'      => true,
        'mensagem'=> 'Solicitação recebida com sucesso! Em breve entraremos em contato.',
        'db'      => $salvo_db,
        'email'   => $email_enviado,
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'ok'   => false,
        'erro' => 'Erro ao processar a solicitação. Tente novamente ou entre em contato pelo WhatsApp.',
    ]);
}
