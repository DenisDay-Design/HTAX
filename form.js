document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('form-contato');
  if (!form) return;

  const msgOk = document.getElementById('form-msg-ok');
  const msgErro = document.getElementById('form-msg-erro');
  const btnEnviar = document.getElementById('btn-enviar');
  const parametros = new URLSearchParams(window.location.search);

  if (window.inicializarCaptcha) window.inicializarCaptcha(form);

  function campoOculto(nome, valor) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = nome;
    input.value = valor;
    form.appendChild(input);
  }

  campoOculto('origem', 'pagina-contato');
  campoOculto('pagina_origem', window.location.href);
  ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (nome) {
    campoOculto(nome, parametros.get(nome) || '');
  });

  form.addEventListener('submit', async function (evento) {
    evento.preventDefault();
    if (!form.reportValidity()) return;

    if (msgOk) msgOk.style.display = 'none';
    if (msgErro) msgErro.style.display = 'none';
    const textoOriginal = btnEnviar.textContent;
    btnEnviar.disabled = true;
    btnEnviar.textContent = 'ENVIANDO...';

    try {
      const resposta = await fetch('/api/leads', { method: 'POST', body: new FormData(form) });
      const corpo = await resposta.text();
      let json;
      try {
        json = JSON.parse(corpo);
      } catch (_) {
        throw new Error('O servidor retornou uma resposta inválida. Tente novamente em alguns instantes.');
      }
      if (!resposta.ok || !json.ok) throw new Error((json.erros || [json.erro || 'Erro inesperado.']).join(' '));

      form.reset();
      if (window.turnstile) window.turnstile.reset();
      if (msgOk) msgOk.style.display = 'block';
    } catch (erro) {
      if (window.turnstile) window.turnstile.reset();
      if (msgErro) {
        msgErro.textContent = erro.message || 'Não foi possível enviar. Tente novamente.';
        msgErro.style.display = 'block';
      }
    } finally {
      btnEnviar.disabled = false;
      btnEnviar.textContent = textoOriginal;
    }
  });
});
