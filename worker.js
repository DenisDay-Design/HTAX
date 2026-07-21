const ARQUIVOS_PROTEGIDOS = new Set([
  '/worker.js',
  '/wrangler.jsonc',
  '/database.sql',
  '/d1-schema.sql',
  '/config.example.php',
  '/config.php',
  '/contato.php',
  '/captcha.php',
  '/.gitignore'
]);

function respostaJson(corpo, status = 200) {
  return new Response(JSON.stringify(corpo), {
    status,
    headers: { 'Content-Type': 'application/json; charset=utf-8' }
  });
}

function texto(valor, limite = 0) {
  const limpo = String(valor || '').replace(/<[^>]*>/g, '').trim();
  return limite ? limpo.slice(0, limite) : limpo;
}

async function validarTurnstile(token, secret, ip) {
  if (!token || !secret) return false;
  const dados = new FormData();
  dados.append('secret', secret);
  dados.append('response', token);
  if (ip) dados.append('remoteip', ip);

  const resposta = await fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', {
    method: 'POST',
    body: dados
  });
  const resultado = await resposta.json();
  return resposta.ok && resultado.success === true;
}

async function enviarAvisoEmail(env, lead) {
  if (!env.RESEND_API_KEY || !env.LEADS_EMAIL || !env.MAIL_FROM) return;
  const assunto = `Novo lead — ${lead.nome} (${lead.empresa})`;
  const textoEmail = [
    `Nome: ${lead.nome}`,
    `Empresa: ${lead.empresa}`,
    `Cargo: ${lead.cargo || '-'}`,
    `E-mail: ${lead.email}`,
    `WhatsApp: ${lead.telefone}`,
    `Faturamento: ${lead.faturamento || '-'}`,
    `Regime: ${lead.regime || '-'}`,
    `Desafio: ${lead.desafio || '-'}`,
    `Origem: ${lead.origem}`,
    `Consentimento de marketing: ${lead.consentimento ? 'Sim' : 'Não'}`
  ].join('\n');

  await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${env.RESEND_API_KEY}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      from: env.MAIL_FROM,
      to: [env.LEADS_EMAIL],
      reply_to: lead.email,
      subject: assunto,
      text: textoEmail
    })
  });
}

async function receberLead(request, env) {
  if (!env.LEADS_DB || !env.TURNSTILE_SECRET) {
    return respostaJson({ ok: false, erro: 'O formulário ainda não foi configurado na hospedagem.' }, 503);
  }

  const dados = await request.formData();
  if (texto(dados.get('bot-field')) || texto(dados.get('website'))) return respostaJson({ ok: true });

  const lead = {
    nome: texto(dados.get('nome'), 200),
    empresa: texto(dados.get('empresa'), 200),
    cargo: texto(dados.get('cargo'), 200),
    email: texto(dados.get('email'), 254),
    telefone: texto(dados.get('telefone') || dados.get('whatsapp'), 50),
    faturamento: texto(dados.get('faturamento'), 100),
    regime: texto(dados.get('regime'), 100),
    desafio: texto(dados.get('desafio'), 5000),
    origem: texto(dados.get('origem'), 100) || 'site',
    paginaOrigem: texto(dados.get('pagina_origem'), 255),
    utmSource: texto(dados.get('utm_source'), 100),
    utmMedium: texto(dados.get('utm_medium'), 100),
    utmCampaign: texto(dados.get('utm_campaign'), 150),
    utmTerm: texto(dados.get('utm_term'), 150),
    utmContent: texto(dados.get('utm_content'), 150),
    consentimento: dados.get('consentimento_marketing') === '1'
  };

  const erros = [];
  if (!lead.nome) erros.push('Nome é obrigatório.');
  if (!lead.empresa) erros.push('Empresa é obrigatória.');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(lead.email)) erros.push('E-mail inválido.');
  if (!lead.telefone) erros.push('WhatsApp é obrigatório.');
  if (erros.length) return respostaJson({ ok: false, erros }, 422);

  const ip = request.headers.get('CF-Connecting-IP') || '';
  const captchaValido = await validarTurnstile(
    texto(dados.get('cf-turnstile-response'), 3000),
    env.TURNSTILE_SECRET,
    ip
  );
  if (!captchaValido) {
    return respostaJson({ ok: false, erros: ['Confirme a verificação de segurança antes de enviar.'] }, 422);
  }

  const ipHash = ip ? await crypto.subtle.digest('SHA-256', new TextEncoder().encode(ip)).then(buffer =>
    Array.from(new Uint8Array(buffer)).map(byte => byte.toString(16).padStart(2, '0')).join('')
  ) : null;

  await env.LEADS_DB.prepare(
    `INSERT INTO leads (nome, empresa, cargo, email, telefone, faturamento, regime, desafio, origem, pagina_origem, utm_source, utm_medium, utm_campaign, utm_term, utm_content, consentimento_marketing, consentimento_em, ip_hash)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
  ).bind(
    lead.nome, lead.empresa, lead.cargo || null, lead.email, lead.telefone,
    lead.faturamento || null, lead.regime || null, lead.desafio || null,
    lead.origem, lead.paginaOrigem || null, lead.utmSource || null,
    lead.utmMedium || null, lead.utmCampaign || null, lead.utmTerm || null,
    lead.utmContent || null, lead.consentimento ? 1 : 0,
    lead.consentimento ? new Date().toISOString() : null, ipHash
  ).run();

  try {
    await enviarAvisoEmail(env, lead);
  } catch (erro) {
    console.error('Lead salvo, mas o e-mail não foi enviado.', erro);
  }

  return respostaJson({ ok: true, mensagem: 'Solicitação recebida com sucesso!' }, 201);
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (request.method === 'POST' && url.pathname === '/api/leads') {
      try {
        return await receberLead(request, env);
      } catch (erro) {
        console.error(erro);
        return respostaJson({ ok: false, erro: 'Não foi possível registrar sua solicitação. Tente novamente.' }, 500);
      }
    }
    if (ARQUIVOS_PROTEGIDOS.has(url.pathname)) return new Response('Não encontrado.', { status: 404 });
    return env.ASSETS.fetch(request);
  }
};
