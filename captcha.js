(function () {
  function formatarTelefone(valor) {
    const numeros = valor.replace(/\D/g, '').slice(0, 11);
    if (numeros.length <= 2) return numeros ? '(' + numeros : '';
    if (numeros.length <= 6) return '(' + numeros.slice(0, 2) + ') ' + numeros.slice(2);
    if (numeros.length <= 10) return '(' + numeros.slice(0, 2) + ') ' + numeros.slice(2, 6) + '-' + numeros.slice(6);
    return '(' + numeros.slice(0, 2) + ') ' + numeros.slice(2, 7) + '-' + numeros.slice(7);
  }

  window.inicializarCaptcha = async function (form) {
    const pergunta = form.querySelector('[data-captcha-pergunta]');
    const resposta = form.querySelector('[name="captcha_resposta"]');
    if (!pergunta || !resposta) return;

    pergunta.textContent = 'Carregando verificação...';
    resposta.disabled = true;
    try {
      const requisicao = await fetch('captcha.php', { credentials: 'same-origin', cache: 'no-store' });
      const dados = await requisicao.json();
      if (!requisicao.ok || !dados.ok) throw new Error();
      pergunta.textContent = dados.pergunta;
      resposta.disabled = false;
      resposta.value = '';
      resposta.focus();
    } catch (_) {
      pergunta.textContent = 'Não foi possível carregar a verificação. Atualize a página e tente novamente.';
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[name="telefone"]').forEach(function (campo) {
      campo.inputMode = 'tel';
      campo.maxLength = 16;
      campo.addEventListener('input', function () { campo.value = formatarTelefone(campo.value); });
      campo.value = formatarTelefone(campo.value);
    });
  });
})();
