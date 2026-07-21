document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('nav').forEach(function (nav, indice) {
    const menu = nav.querySelector(':scope > .nav-links, :scope > .header-links');
    if (!menu || nav.closest('footer')) return;

    const botao = document.createElement('button');
    botao.type = 'button';
    botao.className = 'mobile-menu-toggle';
    botao.setAttribute('aria-label', 'Abrir menu de navegação');
    botao.setAttribute('aria-expanded', 'false');
    menu.id = menu.id || 'menu-principal-' + indice;
    botao.setAttribute('aria-controls', menu.id);
    botao.innerHTML = '<span></span><span></span><span></span>';

    const cta = nav.querySelector(':scope > .nav-cta, :scope > .header-cta');
    if (cta && !menu.querySelector('.mobile-only-cta')) {
      const item = document.createElement('li');
      const link = cta.cloneNode(true);
      link.classList.add('mobile-only-cta');
      item.appendChild(link);
      menu.appendChild(item);
    }

    botao.addEventListener('click', function () {
      const aberto = menu.classList.toggle('mobile-open');
      botao.setAttribute('aria-expanded', String(aberto));
      botao.setAttribute('aria-label', aberto ? 'Fechar menu de navegação' : 'Abrir menu de navegação');
    });
    nav.appendChild(botao);
  });
});
