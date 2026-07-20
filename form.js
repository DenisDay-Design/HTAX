/**
 * Hinnig Tax & Assets — Formulário de Contato
 * Conecta o formulário HTML ao contato.php via fetch (AJAX)
 * Substitui o trecho de WhatsApp direto que estava no HTML original
 */

document.addEventListener('DOMContentLoaded', function () {

  const form    = document.getElementById('form-contato');
  const msgOk   = document.getElementById('form-msg-ok');
  const msgErro = document.getElementById('form-msg-erro');
  const btnEnviar = document.getElementById('btn-enviar');

  if (!form) return; // segurança: sai se o form não existir na página

  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    // Limpa mensagens anteriores
    if (msgOk)   msgOk.style.display   = 'none';
    if (msgErro) msgErro.style.display = 'none';

    // Estado de carregamento no botão
    const textoOriginal = btnEnviar.textContent;
    btnEnviar.disabled = true;
    btnEnviar.textContent = 'ENVIANDO...';

    try {
      const resp = await fetch('contato.php', {
        method: 'POST',
        body: new FormData(form),
      });

      const json = await resp.json();

      if (json.ok) {
        // Sucesso — esconde o form e mostra mensagem
        form.style.display = 'none';
        if (msgOk) msgOk.style.display = 'block';
      } else {
        // Erros de validação ou erro interno
        const texto = json.erros
          ? json.erros.join('<br>')
          : (json.erro || 'Erro inesperado. Tente novamente.');

        if (msgErro) {
          msgErro.innerHTML = texto;
          msgErro.style.display = 'block';
        } else {
          alert(texto);
        }
        btnEnviar.disabled = false;
        btnEnviar.textContent = textoOriginal;
      }

    } catch (err) {
      console.error('[Hinnig Form]', err);
      const msg = 'Não foi possível enviar. Verifique sua conexão ou nos contate pelo WhatsApp.';
      if (msgErro) {
        msgErro.innerHTML = msg;
        msgErro.style.display = 'block';
      } else {
        alert(msg);
      }
      btnEnviar.disabled = false;
      btnEnviar.textContent = textoOriginal;
    }
  });

});
