/**
 * Попап авторизации/регистрации квиза.
 *
 * Триггер для кнопки Elementor: укажите в виджете кнопки ссылку #apq-quiz
 * (значение настраивается в админке) ИЛИ добавьте кнопке CSS-класс apq-quiz-trigger.
 *
 * Прохождение квиза свободное, поэтому клик по триггеру сразу ведёт на страницу
 * квиза — и гостей, и авторизованных. Попап открывается программно со страницы
 * квиза (кнопка «Создать аккаунт» на финальном экране) через window.APQPopupControl.
 */
(function () {
  'use strict';

  var cfg = window.APQPopup || {};
  var overlay = document.getElementById('apq-popup');
  if (!overlay) return;

  var card = overlay.querySelector('.apq-popup-card');

  // ── Открытие/закрытие ──
  function openPopup() {
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden', 'false');
    document.documentElement.style.overflow = 'hidden';
    var first = overlay.querySelector('.apq-tab-panel.is-active input');
    if (first) setTimeout(function () { first.focus(); }, 320);
  }

  function closePopup() {
    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden', 'true');
    document.documentElement.style.overflow = '';
  }

  // Публичное API для квиза: открыть попап на нужной вкладке с предзаполненным email.
  window.APQPopupControl = {
    open: function (tab, email) {
      if (tab) activateTab(tab);
      if (email) {
        var field = overlay.querySelector(tab === 'register' ? 'input[name="apq_email"]' : 'input[name="apq_login"]');
        if (field) field.value = email;
      }
      openPopup();
    },
    close: closePopup
  };

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closePopup();
  });
  overlay.querySelectorAll('[data-apq-close]').forEach(function (btn) {
    btn.addEventListener('click', closePopup);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('active')) closePopup();
  });

  // ── Триггер ──
  function isTrigger(el) {
    var trigger = cfg.trigger || '#apq-quiz';
    if (el.closest('.apq-quiz-trigger')) return true;

    var link = el.closest('a');
    if (!link) return false;

    var href = link.getAttribute('href') || '';
    if (href === trigger) return true;
    // Elementor может отдать абсолютный URL с якорем.
    if (trigger.charAt(0) === '#' && href.indexOf(trigger) !== -1 && href.split('#')[1] === trigger.slice(1)) return true;

    return false;
  }

  document.addEventListener('click', function (e) {
    var target = e.target instanceof Element ? e.target : null;
    if (!target || !isTrigger(target)) return;

    e.preventDefault();

    // Прохождение свободное: сразу ведём на страницу квиза без запроса авторизации.
    window.location.href = cfg.quizUrl;
  });

  // ── Табы ──
  var tabButtons = overlay.querySelectorAll('.apq-tab-button');
  var tabPanels = overlay.querySelectorAll('.apq-tab-panel');

  function activateTab(name) {
    tabButtons.forEach(function (b) {
      var active = b.getAttribute('data-tab') === name;
      b.classList.toggle('is-active', active);
      b.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    tabPanels.forEach(function (p) {
      p.classList.toggle('is-active', p.getAttribute('data-panel') === name);
    });
  }

  tabButtons.forEach(function (b) {
    b.addEventListener('click', function () { activateTab(b.getAttribute('data-tab')); });
  });
  overlay.querySelectorAll('[data-tab-jump]').forEach(function (b) {
    b.addEventListener('click', function () { activateTab(b.getAttribute('data-tab-jump')); });
  });

  // ── Глазок пароля ──
  overlay.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = button.parentElement ? button.parentElement.querySelector('input') : null;
      if (!field) return;
      field.type = field.type === 'password' ? 'text' : 'password';
      button.classList.toggle('is-active', field.type === 'text');
    });
  });

  // ── AJAX-формы ──
  function setMessage(container, message, type) {
    if (!container) return;
    container.textContent = message || '';
    container.classList.remove('is-visible', 'is-success', 'is-error');
    if (message) {
      container.classList.add('is-visible', type === 'success' ? 'is-success' : 'is-error');
    }
  }

  overlay.querySelectorAll('[data-apq-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var type = form.getAttribute('data-apq-form');
      var button = form.querySelector('button[type="submit"]');
      var messageBox = form.querySelector('[data-form-message]');
      var action = type === 'register' ? 'apq_register' : 'apq_login';

      var body = new URLSearchParams();
      body.append('action', action);
      body.append('nonce', cfg.nonce || '');
      new FormData(form).forEach(function (value, key) { body.append(key, value); });

      setMessage(messageBox, '', '');
      var original = button.textContent;
      button.disabled = true;
      button.textContent = (cfg.messages && cfg.messages.loading) || '…';

      fetch(cfg.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      }).then(function (response) {
        return response.json().then(function (json) { return { ok: response.ok, json: json }; });
      }).then(function (res) {
        if (!res.json || !res.json.success) {
          var msg = res.json && res.json.data && res.json.data.message;
          throw new Error(msg || (cfg.messages && cfg.messages.error) || 'Error');
        }
        setMessage(messageBox, res.json.data.message || 'OK', 'success');

        // Сообщаем квизу об успешном входе/регистрации. Если квиз на этой же
        // странице «забирает» событие (preventDefault), редирект не нужен —
        // квиз дошлёт результат сам, не теряя ответы пользователя.
        var authEvent;
        try {
          authEvent = new CustomEvent('apq:auth', { cancelable: true, detail: res.json.data });
        } catch (err) {
          authEvent = document.createEvent('CustomEvent');
          authEvent.initCustomEvent('apq:auth', false, true, res.json.data);
        }
        var proceed = document.dispatchEvent(authEvent);

        if (proceed) {
          window.location.href = res.json.data.redirect || cfg.quizUrl;
        } else {
          button.disabled = false;
          button.textContent = original;
          closePopup();
        }
      }).catch(function (error) {
        setMessage(messageBox, error.message || (cfg.messages && cfg.messages.error) || 'Error', 'error');
        button.disabled = false;
        button.textContent = original;
      });
    });
  });
})();
