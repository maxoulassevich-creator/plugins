document.addEventListener('click', function (event) {
  var button = event.target.closest('.rrp-copy-button');
  if (!button) {
    return;
  }

  var text = button.getAttribute('data-copy-text') || '';
  var wrap = button.closest('.rrp-card');
  var feedback = wrap ? wrap.parentNode.querySelector('.rrp-copy-feedback') : null;

  var showMessage = function (message) {
    if (feedback) {
      feedback.textContent = message;
    }
  };

  if (!text) {
    showMessage('Ссылка не найдена.');
    return;
  }

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function () {
      showMessage('Ссылка скопирована.');
    }).catch(function () {
      showMessage('Не удалось скопировать ссылку.');
    });
    return;
  }

  var temp = document.createElement('input');
  temp.value = text;
  document.body.appendChild(temp);
  temp.select();

  try {
    document.execCommand('copy');
    showMessage('Ссылка скопирована.');
  } catch (e) {
    showMessage('Не удалось скопировать ссылку.');
  }

  document.body.removeChild(temp);
});
