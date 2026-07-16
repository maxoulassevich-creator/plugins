/**
 * Amaressence Palette Quiz.
 * Client-side display mirrors the server-side APQ_Settings::calculate_result().
 *
 * Квиз открыт для всех. Гость проходит свободно, на финальном экране вводит
 * email аккаунта — на него зачисляются баллы. Если аккаунта нет, предлагаем
 * регистрацию через попап (после регистрации результат отправляется сам).
 */
(function () {
  'use strict';

  var cfg = window.APQ || {};
  cfg.i18n = cfg.i18n || {};

  var app = document.getElementById('apq-app');
  if (!app) return;

  if (document.body) {
    document.body.classList.add('apq-quiz-page-active');
  }

  var VALUES = Array.isArray(cfg.values) ? cfg.values : [];
  var COLORS = Array.isArray(cfg.colors) ? cfg.colors : [];
  var LEGACY_PROFILES = Array.isArray(cfg.profiles) ? cfg.profiles : [];
  var RESULT_RULES = Array.isArray(cfg.resultRules) ? cfg.resultRules : [];
  var FAMILY_LABELS = cfg.familyLabels || {};
  var DRAFT_KEY = 'apq_draft_v1';

  var state = {
    step: 0,
    answers: {},
    result: null,
    submitting: false,
    replay: false,
    loggedIn: !!cfg.isLoggedIn,
    sessionId: 'sess_' + Math.random().toString(36).slice(2, 9) + '_' + Date.now()
  };

  function $(id) { return document.getElementById(id); }

  function showScreen(id) {
    app.querySelectorAll('.apq-screen').forEach(function (s) { s.classList.remove('active'); });
    var el = $(id);
    if (!el) return;
    el.scrollTop = 0;
    requestAnimationFrame(function () { el.classList.add('active'); });
  }

  function findColor(cid) {
    for (var i = 0; i < COLORS.length; i++) { if (COLORS[i].id === cid) return COLORS[i]; }
    return null;
  }

  function findLegacyProfile(pid) {
    for (var i = 0; i < LEGACY_PROFILES.length; i++) { if (LEGACY_PROFILES[i].id === pid) return LEGACY_PROFILES[i]; }
    return null;
  }

  function findResultRule(id) {
    for (var i = 0; i < RESULT_RULES.length; i++) { if (RESULT_RULES[i].id === id) return RESULT_RULES[i]; }
    for (var j = 0; j < RESULT_RULES.length; j++) { if (RESULT_RULES[j].match === 'fallback') return RESULT_RULES[j]; }
    return RESULT_RULES[RESULT_RULES.length - 1] || { id: 'diverse_mosaic', title: '', tagline: '', description: '' };
  }

  function labelToText(label) {
    var tmp = document.createElement('div');
    tmp.innerHTML = String(label || '').replace(/<br\s*\/?>/gi, ' ');
    return (tmp.textContent || tmp.innerText || '').trim();
  }

  function rankCounts(counts, firstSeen) {
    return Object.keys(counts).map(function (id) {
      return { id: id, count: counts[id], first: firstSeen[id] || 0 };
    }).sort(function (a, b) {
      if (b.count === a.count) return a.first - b.first;
      return b.count - a.count;
    });
  }

  function allCountsEqual(ranked) {
    if (!ranked.length) return false;
    var count = ranked[0].count;
    return ranked.every(function (row) { return row.count === count; });
  }

  function colorLabelFromRank(ranked, index) {
    if (!ranked[index]) return '';
    var color = findColor(ranked[index].id);
    return color ? labelToText(color.label) : ranked[index].id;
  }

  function familyLabelFromRank(ranked, index) {
    if (!ranked[index]) return '';
    return FAMILY_LABELS[ranked[index].id] || ranked[index].id;
  }

  function replaceTokens(text, context) {
    return String(text || '').replace(/\{([a-z_]+)\}/g, function (match, key) {
      return Object.prototype.hasOwnProperty.call(context, key) ? context[key] : match;
    });
  }

  function matchFamilyClusterRule(familyCounts, total) {
    var best = null;
    var bestScore = 0;
    var threshold = Math.max(1, Math.floor(total / 2) + 1);

    RESULT_RULES.forEach(function (rule) {
      if (!rule || rule.match !== 'family_cluster') return;
      var score = 0;
      (Array.isArray(rule.families) ? rule.families : []).forEach(function (family) {
        score += familyCounts[family] || 0;
      });
      if (score >= threshold && score > bestScore) {
        best = rule;
        bestScore = score;
      }
    });

    return best;
  }

  function buildResultPayload(rule, selectedColors, rankedColors, rankedFamilies) {
    var context = {
      top_color: colorLabelFromRank(rankedColors, 0),
      second_color: colorLabelFromRank(rankedColors, 1),
      top_family: familyLabelFromRank(rankedFamilies, 0),
      second_family: familyLabelFromRank(rankedFamilies, 1),
      selected_count: String(selectedColors.length),
      unique_colors: String(rankedColors.length),
      unique_families: String(rankedFamilies.length)
    };

    return {
      id: rule.id || 'diverse_mosaic',
      title: replaceTokens(rule.title, context),
      tagline: replaceTokens(rule.tagline, context),
      description: replaceTokens(rule.description, context),
      selected_colors: selectedColors.slice(),
      context: context
    };
  }

  function computeResult(answers) {
    var selectedColors = [];
    var colorCounts = {};
    var familyCounts = {};
    var firstSeenColors = {};
    var firstSeenFamilies = {};

    VALUES.forEach(function (value, index) {
      var colorId = answers[value.id];
      var color = findColor(colorId);
      if (!color) return;

      var family = color.family || 'neutral';
      selectedColors.push(color.id);

      if (!colorCounts[color.id]) {
        colorCounts[color.id] = 0;
        firstSeenColors[color.id] = index;
      }
      if (!familyCounts[family]) {
        familyCounts[family] = 0;
        firstSeenFamilies[family] = index;
      }

      colorCounts[color.id] += 1;
      familyCounts[family] += 1;
    });

    var total = selectedColors.length;
    var rankedColors = rankCounts(colorCounts, firstSeenColors);
    var rankedFamilies = rankCounts(familyCounts, firstSeenFamilies);

    if (!total) {
      return buildResultPayload(findResultRule('diverse_mosaic'), [], [], []);
    }

    var topColorCount = rankedColors[0].count;
    var topFamilyCount = rankedFamilies[0].count;
    var uniqueColors = rankedColors.length;
    var uniqueFamilies = rankedFamilies.length;
    var ruleId = 'diverse_mosaic';

    if (topColorCount === total) {
      ruleId = 'one_color_all';
    } else if (topFamilyCount === total) {
      ruleId = 'one_family_all';
    } else if (uniqueColors === 2 && allCountsEqual(rankedColors)) {
      ruleId = 'two_color_tie';
    } else if (uniqueColors === 3 && allCountsEqual(rankedColors)) {
      ruleId = 'three_color_tie';
    } else if (topColorCount > (total / 2)) {
      ruleId = 'dominant_color';
    } else {
      var clusterRule = matchFamilyClusterRule(familyCounts, total);
      if (clusterRule) {
        return buildResultPayload(clusterRule, selectedColors, rankedColors, rankedFamilies);
      } else if (uniqueFamilies === 2 && allCountsEqual(rankedFamilies)) {
        ruleId = 'two_family_tie';
      } else if (uniqueFamilies === 3 && allCountsEqual(rankedFamilies)) {
        ruleId = 'three_family_tie';
      } else if (topColorCount > 1) {
        ruleId = 'paired_mosaic';
      } else if (uniqueColors === total) {
        ruleId = 'all_unique';
      }
    }

    return buildResultPayload(findResultRule(ruleId), selectedColors, rankedColors, rankedFamilies);
  }

  // ── DRAFT (черновик ответов переживает перезагрузку страницы) ──
  function saveDraft() {
    try {
      window.localStorage.setItem(DRAFT_KEY, JSON.stringify({ answers: state.answers, ts: Date.now() }));
    } catch (e) { /* приватный режим — молча пропускаем */ }
  }

  function loadDraft() {
    try {
      var raw = window.localStorage.getItem(DRAFT_KEY);
      if (!raw) return null;
      var draft = JSON.parse(raw);
      if (!draft || !draft.answers || (Date.now() - (draft.ts || 0)) > 24 * 60 * 60 * 1000) return null;
      // Черновик валиден, только если его ответы соответствуют текущим вопросам/цветам.
      var valid = {};
      VALUES.forEach(function (v) {
        if (draft.answers[v.id] && findColor(draft.answers[v.id])) valid[v.id] = draft.answers[v.id];
      });
      return Object.keys(valid).length ? valid : null;
    } catch (e) { return null; }
  }

  function clearDraft() {
    try { window.localStorage.removeItem(DRAFT_KEY); } catch (e) { /* noop */ }
  }

  // ── QUIZ ──
  function startQuiz() {
    if (!VALUES.length || !COLORS.length || !RESULT_RULES.length) return;
    state.answers = {};
    state.step = 0;

    if (!state.replay) {
      var draft = loadDraft();
      if (draft) {
        state.answers = draft;
        // Продолжаем с первого неотвеченного вопроса.
        for (var i = 0; i < VALUES.length; i++) {
          if (!state.answers[VALUES[i].id]) { state.step = i; break; }
          state.step = i;
        }
      }
    }

    renderQuestion();
    showScreen('apq-quiz-screen');
  }

  function renderQuestion() {
    var v = VALUES[state.step];
    if (!v) return;
    var pct = (state.step / VALUES.length) * 100;

    $('apq-progress-fill').style.width = pct + '%';
    $('apq-quiz-counter').textContent = (state.step + 1) + ' / ' + VALUES.length;
    $('apq-value-title').textContent = v.title;
    $('apq-value-desc').textContent = v.description;

    var selected = state.answers[v.id];
    var grid = $('apq-swatches-grid');
    grid.innerHTML = '';

    COLORS.forEach(function (c) {
      var item = document.createElement('div');
      item.className = 'apq-swatch-item' + (selected === c.id ? ' selected' : '');
      item.setAttribute('data-color', c.id);
      item.setAttribute('role', 'button');
      item.setAttribute('tabindex', '0');
      item.setAttribute('aria-label', labelToText(c.label));

      var circle = document.createElement('div');
      circle.className = 'apq-swatch-circle';
      circle.style.background = c.hex;

      var check = document.createElement('div');
      check.className = 'apq-swatch-check';
      check.textContent = '✓';
      circle.appendChild(check);

      var label = document.createElement('div');
      label.className = 'apq-swatch-label';
      label.innerHTML = c.label;

      item.appendChild(circle);
      item.appendChild(label);
      item.addEventListener('click', function () { selectColor(c.id); });
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectColor(c.id); }
      });
      grid.appendChild(item);
    });

    var btnNext = $('apq-btn-next');
    btnNext.disabled = !selected;
    btnNext.textContent = state.step === VALUES.length - 1 ? (cfg.i18n.seeResult || 'Узнать результат') : (cfg.i18n.next || 'Далее →');
  }

  function selectColor(colorId) {
    state.answers[VALUES[state.step].id] = colorId;
    if (!state.replay) saveDraft();
    app.querySelectorAll('.apq-swatch-item').forEach(function (el) {
      el.classList.toggle('selected', el.getAttribute('data-color') === colorId);
    });
    $('apq-btn-next').disabled = false;
  }

  function nextQuestion() {
    if (state.step < VALUES.length - 1) {
      var body = $('apq-quiz-body');
      body.classList.add('exit');
      setTimeout(function () {
        state.step++;
        renderQuestion();
        body.classList.remove('exit');
        body.classList.add('enter');
        requestAnimationFrame(function () {
          requestAnimationFrame(function () { body.classList.remove('enter'); });
        });
      }, 220);
    } else {
      showResults();
    }
  }

  function renderDots(containerId, colorIds) {
    var box = $(containerId);
    if (!box) return;
    box.innerHTML = '';
    (colorIds || []).forEach(function (cid) {
      var c = findColor(cid);
      if (!c) return;
      var dot = document.createElement('div');
      dot.className = 'apq-result-dot';
      dot.style.background = c.hex;
      dot.title = labelToText(c.label);
      box.appendChild(dot);
    });
  }

  function renderResult(result) {
    if (!result) return;
    $('apq-result-title').textContent = result.title || '';
    $('apq-result-tagline').textContent = result.tagline || '';
    $('apq-result-desc').textContent = result.description || '';
    renderDots('apq-result-dots', result.selected_colors || []);
  }

  function setGiftSectionMode(replay) {
    var section = $('apq-gift-section');
    if (!section) return;
    section.querySelectorAll('[data-apq-gift]').forEach(function (el) {
      // Поле email не показываем авторизованным, даже вне режима повтора.
      el.hidden = replay || (el.id === 'apq-claim-email' && state.loggedIn);
    });
    var shop = $('apq-replay-shop');
    if (shop) shop.hidden = !replay;
    hideNoAccount();
  }

  function showResults() {
    state.result = computeResult(state.answers);
    renderResult(state.result);
    setGiftSectionMode(state.replay);
    if (state.replay) clearDraft();
    showScreen('apq-result');
  }

  // ── EMAIL / NO ACCOUNT ──
  function getClaimEmail() {
    var input = $('apq-claim-email-input');
    return input ? String(input.value || '').trim() : '';
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  }

  function showError(message) {
    var err = $('apq-error');
    err.textContent = message || cfg.i18n.error || 'Error';
    err.hidden = false;
  }

  function hideError() {
    var err = $('apq-error');
    err.hidden = true;
  }

  function showNoAccount(message) {
    var box = $('apq-no-account');
    if (!box) return;
    if (message) $('apq-no-account-text').textContent = message;
    box.hidden = false;
  }

  function hideNoAccount() {
    var box = $('apq-no-account');
    if (box) box.hidden = true;
  }

  function markLoggedIn() {
    state.loggedIn = true;
    var emailField = $('apq-claim-email');
    if (emailField) emailField.hidden = true;
    hideNoAccount();
  }

  // ── SUBMIT ──
  function submitQuiz() {
    if (state.submitting || !state.result || state.replay) return;

    hideError();
    hideNoAccount();

    var claimEmail = '';

    if (!state.loggedIn) {
      claimEmail = getClaimEmail();
      if (!claimEmail) {
        showError(cfg.i18n.emailEmpty || 'Укажи email.');
        var input = $('apq-claim-email-input');
        if (input) input.focus();
        return;
      }
      if (!isValidEmail(claimEmail)) {
        showError(cfg.i18n.emailInvalid || 'Проверь email.');
        return;
      }
    }

    state.submitting = true;

    var btn = $('apq-btn-claim');
    var original = btn.textContent;
    btn.disabled = true;
    btn.textContent = cfg.i18n.sending || '…';

    var body = new URLSearchParams();
    body.append('action', 'apq_submit_quiz');
    body.append('nonce', cfg.nonce || '');
    body.append('answers', JSON.stringify(state.answers));
    body.append('session_id', state.sessionId);
    if (claimEmail) body.append('claim_email', claimEmail);

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (json) { return { ok: response.ok, json: json }; });
    }).then(function (res) {
      if (res.json && res.json.success) {
        clearDraft();
        showScreen('apq-success');
        return;
      }
      var data = res.json && res.json.data ? res.json.data : {};
      if (data.code === 'already_completed') {
        clearDraft();
        showAlready(data.profile || '', data.answers || {});
        return;
      }
      if (data.code === 'no_account') {
        showNoAccount(data.message || '');
        return;
      }
      throw new Error(data.message || cfg.i18n.error);
    }).catch(function (e) {
      showError(e.message || cfg.i18n.error);
    }).finally(function () {
      state.submitting = false;
      btn.disabled = false;
      btn.textContent = original;
    });
  }

  // ── ALREADY COMPLETED ──
  function showAlready(profileId, answers) {
    var result = answers && Object.keys(answers).length ? computeResult(answers) : null;

    if (result) {
      $('apq-already-profile').textContent = result.title;
      renderDots('apq-already-dots', result.selected_colors || []);
    } else {
      var p = findLegacyProfile(profileId);
      if (p) {
        $('apq-already-profile').textContent = p.title;
        renderDots('apq-already-dots', p.colors || []);
      }
    }

    showScreen('apq-already');
  }

  // ── ИНТЕГРАЦИЯ С ПОПАПОМ АВТОРИЗАЦИИ ──
  // Кнопка «Создать аккаунт» под сообщением «аккаунт не найден»:
  // если попап на странице — открываем регистрацию с предзаполненным email,
  // иначе уводим на страницу регистрации.
  var noAccountBtn = $('apq-no-account-register');
  if (noAccountBtn) {
    noAccountBtn.addEventListener('click', function () {
      var api = window.APQPopupControl;
      if (api && typeof api.open === 'function') {
        api.open('register', getClaimEmail());
      } else if (cfg.registerUrl) {
        window.location.href = cfg.registerUrl;
      }
    });
  }

  // После входа/регистрации через попап: обновляем nonce, скрываем поле email
  // и досылаем результат сами — без перезагрузки страницы (ответы не теряются).
  document.addEventListener('apq:auth', function (e) {
    var resultScreen = $('apq-result');
    var onResult = resultScreen && resultScreen.classList.contains('active');

    if (e.detail && e.detail.quizNonce) cfg.nonce = e.detail.quizNonce;
    markLoggedIn();

    if (onResult && state.result && !state.replay) {
      e.preventDefault(); // попап не делает редирект — мы досылаем результат здесь.
      submitQuiz();
    }
  });

  // ── КЭШ-БЕЗОПАСНОЕ СОСТОЯНИЕ ──
  // Страница может отдаваться из кэша: свежий nonce и признак «уже проходил»
  // запрашиваем AJAX-ом при загрузке.
  function refreshState() {
    var body = new URLSearchParams();
    body.append('action', 'apq_quiz_state');

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (r) { return r.json(); }).then(function (json) {
      if (!json || !json.success || !json.data) return;
      if (json.data.nonce) cfg.nonce = json.data.nonce;
      if (json.data.loggedIn) markLoggedIn();
      if (json.data.completed && !cfg.completed) {
        cfg.completed = json.data.completed;
        clearDraft();
        showAlready(cfg.completed.profile, cfg.completed.answers || {});
      }
    }).catch(function () { /* сеть недоступна — работаем с тем, что отрендерено */ });
  }

  // ── INIT ──
  var startBtn = app.querySelector('[data-apq-start]');
  if (startBtn) startBtn.addEventListener('click', startQuiz);
  $('apq-btn-next').addEventListener('click', nextQuestion);
  $('apq-btn-claim').addEventListener('click', submitQuiz);

  var replayBtn = $('apq-btn-replay');
  if (replayBtn) {
    replayBtn.addEventListener('click', function () {
      state.replay = true;
      startQuiz();
    });
  }

  var emailInput = $('apq-claim-email-input');
  if (emailInput) {
    emailInput.addEventListener('input', function () { hideError(); hideNoAccount(); });
    emailInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); submitQuiz(); }
    });
  }

  if (app.getAttribute('data-completed') === '1' && cfg.completed) {
    clearDraft();
    showAlready(cfg.completed.profile, cfg.completed.answers || {});
  } else {
    showScreen('apq-intro');
  }

  refreshState();
})();
