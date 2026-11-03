/**
 * King Decants — assets/js/app.js
 * ---------------------------------------------------------------------------
 * JavaScript mínimo (vanilla, sem build) para UX:
 *   1. Busca e menu mobile (toggle)
 *   2. Modais (login rápido) + ESC/backdrop para fechar
 *   3. Toasts (notificações visuais) — window.kdToast(type, msg)
 *   4. Loading state em botões de formulários
 *   5. Adicionar à sacola via AJAX (atualiza o contador sem recarregar)
 *   6. Login rápido via AJAX
 *   7. Checkout: máscaras (CPF/telefone/CEP), ViaCEP, validação de CPF e
 *      envio AJAX que abre o init_point do Mercado Pago (mesma aba ou nova aba)
 *   8. PIX: copiar "copia e cola" e polling do status do pedido
 *   9. Animação de revelação ao rolar
 * Tudo funciona sem JS também (os formulários têm action/method normais).
 */
(function () {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  // ---------------------------------------------------------------- 1. Toggles
  $$('[data-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.toggle === 'search' ? 'search-panel' : btn.dataset.toggle);
      if (!target) return;
      target.classList.toggle('hidden');
      const input = target.querySelector('input');
      if (input && !target.classList.contains('hidden')) input.focus();
    });
  });

  // ---------------------------------------------------------------- 2. Modais
  function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return false;
    m.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => m.querySelector('input:not([type=hidden])')?.focus(), 50);
    return true;
  }
  function closeModals() {
    $$('.modal').forEach((m) => m.classList.add('hidden'));
    document.body.style.overflow = '';
  }
  $$('[data-open-modal]').forEach((el) =>
    el.addEventListener('click', (ev) => {
      if (openModal(el.dataset.openModal)) ev.preventDefault();
    })
  );
  $$('[data-close-modal]').forEach((el) => el.addEventListener('click', closeModals));
  document.addEventListener('keydown', (ev) => ev.key === 'Escape' && closeModals());

  // ---------------------------------------------------------------- 3. Toasts
  const ICONS = {
    success: '<path d="m5 12 5 5 9-10"/>',
    error: '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17v.5"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.5"/>',
  };
  function dismiss(el) {
    el.classList.add('is-leaving');
    setTimeout(() => el.remove(), 350);
  }
  window.kdToast = function (type, message) {
    const stack = document.getElementById('toast-stack');
    if (!stack) return alert(message);
    const el = document.createElement('div');
    el.className = 'toast pointer-events-auto';
    el.dataset.type = type;
    el.innerHTML =
      '<span class="toast-icon"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round">' +
      (ICONS[type] || ICONS.info) +
      '</svg></span><p></p>';
    el.querySelector('p').textContent = message; // textContent: sem risco de XSS
    stack.appendChild(el);
    setTimeout(() => dismiss(el), 4500);
  };
  $$('#toast-stack .toast').forEach((el) => setTimeout(() => dismiss(el), 5000));

  // ---------------------------------------------------------------- 4. Loading state
  function setLoading(btn, on) {
    if (!btn) return;
    if (on) {
      btn.dataset.originalHtml = btn.innerHTML;
      btn.setAttribute('aria-busy', 'true');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span><span>' + (btn.dataset.loadingText || 'Aguarde…') + '</span>';
    } else if (btn.dataset.originalHtml) {
      btn.innerHTML = btn.dataset.originalHtml;
      btn.removeAttribute('aria-busy');
      btn.disabled = false;
    }
  }
  // Formulários comuns (não-AJAX): mostra spinner ao enviar.
  $$('form[data-loading]').forEach((form) =>
    form.addEventListener('submit', () => setLoading(form.querySelector('[type=submit]'), true))
  );

  async function postForm(form, extraHeaders = {}) {
    // getAttribute: form.action pode ser "sombreado" por um <input name="action">.
    const res = await fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: new FormData(form),
      headers: Object.assign({ Accept: 'application/json', 'X-CSRF-Token': csrf }, extraHeaders),
      credentials: 'same-origin',
    });
    let data = {};
    try {
      data = await res.json();
    } catch (e) {
      data = { ok: false, error: 'Resposta inesperada do servidor.' };
    }
    return data;
  }

  // ---------------------------------------------------------------- 5. Adicionar à sacola
  function updateCartCount(n) {
    // Cabeçalho e barra inferior do celular
    $$('[data-cart-count]').forEach((badge) => {
      badge.textContent = n;
      badge.classList.toggle('scale-0', !n);
      badge.animate([{ transform: 'scale(1.5)' }, { transform: 'scale(1)' }], { duration: 350, easing: 'ease-out' });
    });
  }
  $$('form[data-add-to-cart]').forEach((form) =>
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = form.querySelector('[type=submit]');
      setLoading(btn, true);
      try {
        const data = await postForm(form);
        if (data.ok) {
          updateCartCount(data.count);
          kdToast('success', data.message || 'Adicionado à sacola.');
        } else {
          kdToast('error', data.error || 'Não foi possível adicionar.');
        }
      } catch (e) {
        kdToast('error', 'Falha de conexão. Tente novamente.');
      } finally {
        setLoading(btn, false);
      }
    })
  );

  // ---------------------------------------------------------------- 6. Login rápido (modal)
  $$('form[data-ajax-login]').forEach((form) =>
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = form.querySelector('[type=submit]');
      const err = form.querySelector('[data-error]');
      err?.classList.add('hidden');
      setLoading(btn, true);
      try {
        const data = await postForm(form);
        if (data.ok) {
          window.location.href = data.redirect || window.location.href;
          return;
        }
        if (err) {
          err.textContent = data.error || 'Não foi possível entrar.';
          err.classList.remove('hidden');
        }
      } catch (e) {
        kdToast('error', 'Falha de conexão.');
      }
      setLoading(btn, false);
    })
  );

  // ---------------------------------------------------------------- 7. Checkout
  const masks = {
    cpf: (v) => v.replace(/\D/g, '').slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2'),
    phone: (v) => {
      const d = v.replace(/\D/g, '').slice(0, 11);
      if (d.length <= 2) return d.length ? '(' + d : '';
      if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
      return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
    },
    cep: (v) => v.replace(/\D/g, '').slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2'),
  };
  $$('[data-mask]').forEach((input) => {
    const fn = masks[input.dataset.mask];
    if (!fn) return;
    input.addEventListener('input', () => (input.value = fn(input.value)));
    input.value = fn(input.value);
  });

  // Validação de CPF no cliente (apenas UX — o servidor valida de novo).
  function isValidCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    for (let t = 9; t < 11; t++) {
      let sum = 0;
      for (let i = 0; i < t; i++) sum += Number(cpf[i]) * (t + 1 - i);
      if (Number(cpf[t]) !== ((10 * sum) % 11) % 10) return false;
    }
    return true;
  }
  $$('input[data-mask="cpf"]').forEach((input) =>
    input.addEventListener('blur', () => {
      const bad = input.value && !isValidCPF(input.value);
      input.classList.toggle('is-invalid', bad);
      if (bad) kdToast('error', 'CPF inválido. Confira os números.');
    })
  );

  // ViaCEP: autocompleta logradouro/bairro/cidade/UF ao sair do campo CEP.
  // Para desativar, remova o atributo data-viacep do input no checkout.php.
  $$('input[data-viacep]').forEach((input) =>
    input.addEventListener('blur', async () => {
      const cep = input.value.replace(/\D/g, '');
      if (cep.length !== 8) return;
      const form = input.form;
      const status = form.querySelector('[data-cep-status]');
      if (status) status.textContent = 'Buscando endereço…';
      try {
        const res = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
        const d = await res.json();
        if (d.erro) throw new Error('CEP não encontrado');
        const set = (name, val) => {
          const f = form.elements[name];
          if (f && val && !f.value) f.value = val;
        };
        set('street', d.logradouro);
        set('district', d.bairro);
        set('city', d.localidade);
        if (form.elements.state && d.uf) form.elements.state.value = d.uf;
        if (status) status.textContent = '';
        form.elements.number?.focus();
        form.dispatchEvent(new CustomEvent('kd:cep', { detail: { cep } }));
      } catch (e) {
        if (status) status.textContent = 'CEP não encontrado. Preencha o endereço manualmente.';
      }
    })
  );

  // Atualiza o resumo de frete quando o CEP muda (consulta o servidor).
  const checkoutForm = $('form[data-checkout]');
  if (checkoutForm) {
    checkoutForm.addEventListener('kd:cep', async (ev) => {
      try {
        const base = checkoutForm.dataset.shippingUrl;
        const url = base + (base.includes('?') ? '&' : '?') + 'cep=' + encodeURIComponent(ev.detail.cep);
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const d = await res.json();
        if (d.ok) {
          $('[data-shipping]').textContent = d.shipping_label;
          $('[data-total]').textContent = d.total_label;
          const days = $('[data-shipping-days]');
          if (days) days.textContent = d.days || '';
        }
      } catch (e) { /* silencioso: o servidor recalcula no envio */ }
    });

    // Envio AJAX: o servidor cria o pedido + preferência e devolve a URL.
    checkoutForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = checkoutForm.querySelector('[type=submit]');
      $$('.field-error', checkoutForm).forEach((el) => el.remove());
      $$('.is-invalid', checkoutForm).forEach((el) => el.classList.remove('is-invalid'));
      setLoading(btn, true);

      // Abrir em nova aba precisa ser síncrono ao clique (evita bloqueio de pop-up).
      const newTab = checkoutForm.elements.open_new_tab?.checked && checkoutForm.elements.payment_method.value === 'card';
      const popup = newTab ? window.open('', '_blank') : null;

      try {
        const data = await postForm(checkoutForm);
        if (data.ok) {
          if (popup && data.init_point) {
            // Exemplo: abre o Checkout Pro em nova aba e leva esta aba para a página do pedido.
            popup.location.href = data.init_point;
            window.location.href = data.order_url;
          } else {
            // Exemplo: redireciona na mesma aba (init_point do cartão ou página do PIX).
            popup?.close();
            window.location.href = data.redirect;
          }
          return;
        }
        popup?.close();
        if (data.errors) {
          Object.entries(data.errors).forEach(([name, msg]) => {
            const field = checkoutForm.elements[name];
            if (field && field.classList) {
              field.classList.add('is-invalid');
              const p = document.createElement('p');
              p.className = 'field-error';
              p.textContent = msg;
              field.insertAdjacentElement('afterend', p);
            }
          });
          checkoutForm.querySelector('.is-invalid')?.focus();
        }
        kdToast('error', data.error || 'Revise os campos destacados.');
      } catch (e) {
        popup?.close();
        kdToast('error', 'Falha de conexão. Tente novamente.');
      }
      setLoading(btn, false);
    });

    // Mostra a opção "nova aba" apenas para cartão.
    const toggleNewTab = () => {
      const box = $('[data-new-tab-option]');
      if (box) box.classList.toggle('hidden', checkoutForm.elements.payment_method.value !== 'card');
    };
    $$('input[name=payment_method]', checkoutForm).forEach((r) => r.addEventListener('change', toggleNewTab));
    toggleNewTab();
  }

  // Checkout: preenche o endereço a partir de um endereço salvo.
  $$('select[data-address-picker]').forEach((sel) =>
    sel.addEventListener('change', () => {
      const opt = sel.selectedOptions[0];
      if (!opt || !opt.dataset.address) return;
      const a = JSON.parse(opt.dataset.address);
      const form = sel.form;
      Object.entries(a).forEach(([k, v]) => {
        const f = form.elements[k];
        if (f) f.value = v || '';
      });
      if (form.elements.cep) form.elements.cep.value = masks.cep(form.elements.cep.value);
      form.dispatchEvent(new CustomEvent('kd:cep', { detail: { cep: (a.cep || '').replace(/\D/g, '') } }));
    })
  );

  // ---------------------------------------------------------------- 8. PIX
  $$('[data-copy]').forEach((btn) =>
    btn.addEventListener('click', async () => {
      const target = document.getElementById(btn.dataset.copy);
      try {
        await navigator.clipboard.writeText(target.value || target.textContent);
        kdToast('success', 'Código PIX copiado!');
      } catch (e) {
        target.select?.();
        kdToast('info', 'Selecione e copie o código manualmente.');
      }
    })
  );
  // Polling: consulta o status a cada 5s; quando pago, recarrega a página.
  const poll = $('[data-poll-status]');
  if (poll) {
    const tick = async () => {
      try {
        const res = await fetch(poll.dataset.pollStatus, { headers: { Accept: 'application/json' } });
        const d = await res.json();
        if (d.status && d.status !== 'pending') return window.location.reload();
      } catch (e) { /* tenta de novo */ }
      setTimeout(tick, 5000);
    };
    setTimeout(tick, 5000);
  }

  // ---------------------------------------------------------------- 8b. Galeria do produto
  const mainImg = document.getElementById('gallery-main');
  $$('[data-gallery-thumb]').forEach((thumb) =>
    thumb.addEventListener('click', () => {
      if (!mainImg) return;
      $$('[data-gallery-thumb]').forEach((t) => {
        t.classList.remove('is-active');
        t.setAttribute('aria-selected', 'false');
      });
      thumb.classList.add('is-active');
      thumb.setAttribute('aria-selected', 'true');
      mainImg.style.opacity = '0';
      setTimeout(() => {
        mainImg.src = thumb.dataset.galleryThumb;
        mainImg.onload = () => (mainImg.style.opacity = '1');
      }, 150);
    })
  );

  // ---------------------------------------------------------------- 8c. Favoritos
  $$('form[data-wishlist]').forEach((form) =>
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = form.querySelector('button');
      btn.disabled = true;
      try {
        const data = await postForm(form);
        if (data.ok) {
          btn.classList.toggle('is-active', data.in_wishlist);
          btn.setAttribute('aria-pressed', data.in_wishlist ? 'true' : 'false');
          const label = form.querySelector('[data-wishlist-label]');
          if (label) label.textContent = data.in_wishlist ? 'Nos favoritos' : 'Adicionar aos favoritos';
          kdToast('success', data.message);
        } else {
          kdToast('error', data.error || 'Não foi possível atualizar os favoritos.');
        }
      } catch (e) {
        kdToast('error', 'Falha de conexão.');
      }
      btn.disabled = false;
    })
  );

  // ---------------------------------------------------------------- 9. Revelação ao rolar
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(
      (entries) => entries.forEach((en) => {
        if (en.isIntersecting) {
          en.target.classList.add('is-visible');
          io.unobserve(en.target);
        }
      }),
      { threshold: 0.12 }
    );
    $$('.reveal').forEach((el) => io.observe(el));
  } else {
    $$('.reveal').forEach((el) => el.classList.add('is-visible'));
  }

  // Remove o skeleton quando a imagem carrega.
  $$('img[data-skeleton]').forEach((img) => {
    const done = () => img.closest('.skeleton')?.classList.remove('skeleton');
    if (img.complete) done();
    else { img.addEventListener('load', done); img.addEventListener('error', done); }
  });
})();
