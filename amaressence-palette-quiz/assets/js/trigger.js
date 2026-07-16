/**
 * Кнопка-триггер квиза.
 *
 * Клик по ссылке-триггеру (по умолчанию #apq-quiz) или по элементу с классом
 * apq-quiz-trigger ведёт на страницу квиза. Прохождение свободное — попап
 * авторизации больше не используется.
 */
(function () {
  'use strict';

  var cfg = window.APQTrigger || {};
  if (!cfg.quizUrl) return;

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
    window.location.href = cfg.quizUrl;
  });
})();
