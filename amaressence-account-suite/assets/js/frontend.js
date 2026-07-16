(function () {
  const cfg = window.AmaAccountSuite || {};

  function setMessage(container, message, type) {
    if (!container) return;
    container.textContent = message || '';
    container.classList.remove('is-visible', 'is-success', 'is-error');
    if (message) {
      container.classList.add('is-visible');
      container.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }
  }

  function toggleLoading(button, isLoading, loadingText) {
    if (!button) return;
    if (isLoading) {
      button.dataset.originalText = button.textContent;
      button.disabled = true;
      button.textContent = loadingText || cfg.messages?.loading || 'Loading...';
    } else {
      button.disabled = false;
      if (button.dataset.originalText) {
        button.textContent = button.dataset.originalText;
      }
    }
  }

  function postAjax(action, payload) {
    const body = new URLSearchParams();
    body.append('action', action);
    body.append('nonce', cfg.nonce || '');
    Object.keys(payload || {}).forEach((key) => {
      if (payload[key] !== undefined && payload[key] !== null) {
        body.append(key, payload[key]);
      }
    });

    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
    }).then(async (response) => {
      const json = await response.json().catch(() => null);
      if (!json) {
        throw new Error(cfg.messages?.error || 'Request failed');
      }
      if (!response.ok || !json.success) {
        throw new Error(json?.data?.message || cfg.messages?.error || 'Request failed');
      }
      return json.data;
    });
  }

  function initPasswordToggles(root) {
    root.querySelectorAll('[data-toggle-password]').forEach((button) => {
      button.addEventListener('click', () => {
        const field = button.parentElement?.querySelector('input');
        if (!field) return;
        field.type = field.type === 'password' ? 'text' : 'password';
        button.classList.toggle('is-active', field.type === 'text');
      });
    });
  }

  function initAuthTabs(root) {
    const buttons = root.querySelectorAll('.ama-tab-button');
    const panels = root.querySelectorAll('.ama-tab-panel');
    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        const target = button.dataset.tab;
        buttons.forEach((item) => {
          const active = item === button;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
          panel.classList.toggle('is-active', panel.dataset.panel === target);
        });
      });
    });
  }

  function serializeForm(form) {
    const payload = {};
    new FormData(form).forEach((value, key) => {
      payload[key] = value;
    });
    return payload;
  }

  function initAjaxAuthForms(root) {
    root.querySelectorAll('[data-ama-ajax-form]').forEach((form) => {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const type = form.dataset.amaAjaxForm;
        const button = form.querySelector('button[type="submit"]');
        const messageBox = form.querySelector('[data-form-message]');
        const payload = serializeForm(form);
        const action = type === 'register' ? 'ama_register' : 'ama_login';
        setMessage(messageBox, '', '');
        toggleLoading(button, true, cfg.messages?.loading || '...');
        try {
          const data = await postAjax(action, payload);
          setMessage(messageBox, data.message || 'OK', 'success');
          if (data.redirect) {
            window.location.href = data.redirect;
            return;
          }
        } catch (error) {
          setMessage(messageBox, error.message || cfg.messages?.error || 'Error', 'error');
        } finally {
          toggleLoading(button, false);
        }
      });
    });
  }

  function initDashboardTabs(root) {
    const tabs = root.querySelectorAll('[data-dashboard-tab]');
    const panels = root.querySelectorAll('[data-dashboard-panel]');
    const stage = root.querySelector('[data-dashboard-stage]');

    function openTab(name) {
      tabs.forEach((tab) => {
        const isActive = tab.dataset.dashboardTab === name;
        tab.classList.toggle('is-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      panels.forEach((panel) => {
        const isActive = panel.dataset.dashboardPanel === name;
        panel.classList.toggle('is-active', isActive);
        if (isActive) {
          panel.scrollTop = 0;
        }
      });

      if (stage) {
        stage.scrollTop = 0;
      }
    }

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => openTab(tab.dataset.dashboardTab));
    });

    root.querySelectorAll('[data-open-dashboard-tab]').forEach((button) => {
      button.addEventListener('click', () => openTab(button.dataset.openDashboardTab));
    });
  }



  function initCopyButtons(root) {
    root.querySelectorAll('[data-copy-to-clipboard]').forEach((button) => {
      button.addEventListener('click', async () => {
        const text = button.dataset.copyToClipboard || '';
        const feedbackClass = button.dataset.copyFeedback || '';
        const feedback = feedbackClass ? root.querySelector(`.${feedbackClass}`) : null;

        if (!text) return;

        try {
          if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
          } else {
            const field = button.parentElement?.querySelector('input');
            if (!field) throw new Error('Copy field not found');
            field.focus();
            field.select();
            const copied = document.execCommand('copy');
            if (!copied) throw new Error('Copy command failed');
          }

          if (feedback) {
            feedback.textContent = cfg.messages?.copied || 'Ссылка скопирована.';
            feedback.classList.remove('is-error');
            feedback.classList.add('is-success');
          }
        } catch (error) {
          if (feedback) {
            feedback.textContent = cfg.messages?.copyError || 'Не удалось скопировать ссылку автоматически. Выделите и скопируйте её вручную.';
            feedback.classList.remove('is-success');
            feedback.classList.add('is-error');
          }
        }
      });
    });
  }

  function initAddressClear(root) {
    root.querySelectorAll('[data-clear-address]').forEach((button) => {
      button.addEventListener('click', () => {
        const group = button.dataset.clearAddress;
        root.querySelectorAll(`[data-address-group="${group}"]`).forEach((input) => {
          input.value = '';
        });
      });
    });
  }

  function initDashboardForms(root) {
    const profileForm = root.querySelector('[data-ama-dashboard-form="profile"]');
    const addressesForm = root.querySelector('[data-ama-dashboard-form="addresses"]');

    if (profileForm) {
      profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const messageBox = profileForm.querySelector('[data-form-message]');
        const button = profileForm.querySelector('button[type="submit"]');
        const payload = serializeForm(profileForm);

        if ((payload.new_password || payload.confirm_password) && payload.new_password !== payload.confirm_password) {
          setMessage(messageBox, cfg.messages?.passwordMismatch || 'Пароли не совпадают.', 'error');
          return;
        }

        setMessage(messageBox, '', '');
        toggleLoading(button, true, cfg.messages?.saving || 'Saving...');
        try {
          const data = await postAjax('ama_save_profile', payload);
          setMessage(messageBox, data.message || 'OK', 'success');
        } catch (error) {
          setMessage(messageBox, error.message || cfg.messages?.error || 'Error', 'error');
        } finally {
          toggleLoading(button, false);
        }
      });
    }

    if (addressesForm) {
      addressesForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const messageBox = addressesForm.querySelector('[data-form-message]');
        const button = addressesForm.querySelector('button[type="submit"]');
        const payload = serializeForm(addressesForm);
        setMessage(messageBox, '', '');
        toggleLoading(button, true, cfg.messages?.saving || 'Saving...');
        try {
          const data = await postAjax('ama_save_addresses', payload);
          setMessage(messageBox, data.message || 'OK', 'success');
          const billingPreview = root.querySelector('[data-billing-preview]');
          const shippingPreview = root.querySelector('[data-shipping-preview]');
          if (billingPreview && data.billingPreview) billingPreview.innerHTML = data.billingPreview;
          if (shippingPreview && data.shippingPreview) shippingPreview.innerHTML = data.shippingPreview;
        } catch (error) {
          setMessage(messageBox, error.message || cfg.messages?.error || 'Error', 'error');
        } finally {
          toggleLoading(button, false);
        }
      });
    }
  }

  function initOrderModal(root) {
    const modal = document.querySelector('[data-order-modal]');
    const body = modal?.querySelector('[data-order-modal-body]');
    if (!modal || !body) return;

    function openModal(html) {
      body.innerHTML = html;
      modal.hidden = false;
      document.documentElement.style.overflow = 'hidden';
    }

    function closeModal() {
      modal.hidden = true;
      body.innerHTML = '';
      document.documentElement.style.overflow = '';
    }

    modal.querySelectorAll('[data-close-order-modal]').forEach((element) => {
      element.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) {
        closeModal();
      }
    });

    root.querySelectorAll('[data-order-details]').forEach((button) => {
      button.addEventListener('click', async () => {
        toggleLoading(button, true, cfg.messages?.loading || '...');
        try {
          const data = await postAjax('ama_get_order', { order_id: button.dataset.orderDetails });
          openModal(data.html || '');
        } catch (error) {
          alert(error.message || cfg.messages?.error || 'Error');
        } finally {
          toggleLoading(button, false);
        }
      });
    });

    root.querySelectorAll('[data-order-cancel]').forEach((button) => {
      button.addEventListener('click', async () => {
        if (!window.confirm('Отменить заказ? После подтверждения магазин получит уведомление.')) return;
        toggleLoading(button, true, cfg.messages?.saving || '...');
        try {
          const data = await postAjax('ama_cancel_order', { order_id: button.dataset.orderCancel });
          const row = root.querySelector(`[data-order-row="${button.dataset.orderCancel}"]`);
          const statusNode = row?.querySelector('[data-order-status]');
          if (statusNode) {
            statusNode.textContent = data.status || 'Отменён';
            statusNode.className = `ama-status ama-status--${data.badge || 'cancelled'}`;
          }
          button.remove();
        } catch (error) {
          alert(error.message || cfg.messages?.error || 'Error');
        } finally {
          toggleLoading(button, false);
        }
      });
    });
  }

  function initRoot(root) {
    initPasswordToggles(root);
    if (root.matches('[data-ama-auth]')) {
      initAuthTabs(root);
      initAjaxAuthForms(root);
    }
    if (root.matches('[data-ama-dashboard]')) {
      initDashboardTabs(root);
      initCopyButtons(root);
      initAddressClear(root);
      initDashboardForms(root);
      initOrderModal(root);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ama-auth], [data-ama-dashboard]').forEach(initRoot);
  });
})();
