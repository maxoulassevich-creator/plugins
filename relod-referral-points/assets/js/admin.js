(function ($) {
  'use strict';

  function replaceAll(text, search, replacement) {
    return String(text || '').split(search).join(replacement);
  }

  function applyPlaceholders(html, placeholders) {
    var output = String(html || '');
    $.each(placeholders || {}, function (key, value) {
      output = replaceAll(output, key, value);
    });
    return output;
  }

  function getEditorContent(editorId) {
    if (window.tinymce && window.tinymce.get(editorId) && !window.tinymce.get(editorId).isHidden()) {
      return window.tinymce.get(editorId).getContent();
    }

    return $('#' + editorId).val() || '';
  }

  function getTextareaValue(id) {
    if (!id) {
      return '';
    }

    return $('#' + id).val() || '';
  }

  function buildOrderBlock(card, placeholders) {
    var rowTemplate = getTextareaValue(card.data('order-row-id'));
    var tableTemplate = getTextareaValue(card.data('order-table-id'));
    var rows = '';
    var firstItem = $.extend({}, placeholders, {
      '{item_name}': 'Шортики корректирующие Secret',
      '{item_quantity}': '1',
      '{item_subtotal}': '10 ₽'
    });
    var secondItem = $.extend({}, placeholders, {
      '{item_name}': 'Подарочная упаковка',
      '{item_quantity}': '1',
      '{item_subtotal}': '0 ₽'
    });

    if (!rowTemplate || !tableTemplate) {
      return '';
    }

    rows += applyPlaceholders(rowTemplate, firstItem);
    rows += applyPlaceholders(rowTemplate, secondItem);

    return applyPlaceholders(replaceAll(tableTemplate, '{order_items_rows}', rows), placeholders);
  }

  function buildReferralBlock(card, placeholders) {
    var template = getTextareaValue(card.data('referral-id'));
    return applyPlaceholders(template, placeholders);
  }

  function stripScripts(html) {
    return String(html || '').replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
  }

  function sanitizeInlinePreviewHtml(html) {
    return stripScripts(html)
      .replace(/\son[a-z]+\s*=\s*(['"]).*?\1/gi, '')
      .replace(/\son[a-z]+\s*=\s*[^\s>]+/gi, '')
      .replace(/javascript\s*:/gi, '');
  }

  function sanitizeCss(css) {
    return stripScripts(css)
      .replace(/<\/?style[^>]*>/gi, '')
      .replace(/javascript\s*:/gi, '');
  }

  function looksLikeFullDocument(html) {
    return /<(?:!doctype\s+html|html|head|body)\b/i.test(String(html || ''));
  }

  function injectCssIntoDocument(html, css) {
    if (!css) {
      return html;
    }

    var style = '<style type="text/css">' + css + '</style>';

    if (/<\/head>/i.test(html)) {
      return html.replace(/<\/head>/i, style + '</head>');
    }

    if (/<body[^>]*>/i.test(html)) {
      return html.replace(/<body([^>]*)>/i, '<body$1>' + style);
    }

    return '<!doctype html><html><head><meta charset="utf-8">' + style + '</head><body>' + html + '</body></html>';
  }

  function renderPreviewDocument(body, css, isFullDocument) {
    var safeBody = sanitizeInlinePreviewHtml(body);
    var safeCss = sanitizeCss(css);

    if (isFullDocument) {
      return injectCssIntoDocument(safeBody, safeCss);
    }

    return '<!doctype html><html><head><meta charset="utf-8">'
      + '<style>body{margin:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1d2327;line-height:1.5}.rrp-email-preview-shell{box-sizing:border-box;max-width:760px;margin:0 auto;background:#fff;padding:32px;min-height:520px}img{max-width:100%;height:auto}table{max-width:100%}'
      + safeCss
      + '</style></head><body><div class="rrp-email-preview-shell">'
      + safeBody
      + '</div></body></html>';
  }

  function updatePreview(card) {
    var placeholders = (window.rrpEmailPreview && window.rrpEmailPreview.placeholders) ? window.rrpEmailPreview.placeholders : {};
    var editorId = card.data('editor-id');
    var css = getTextareaValue(card.data('css-id'));
    var rawBody = getEditorContent(editorId);
    var body = rawBody;
    var orderBlock = buildOrderBlock(card, placeholders);
    var referralBlock = buildReferralBlock(card, placeholders);
    var appendOrderId = card.data('append-order-id');
    var appendReferralId = card.data('append-referral-id');
    var appendOrder = appendOrderId ? $('#' + appendOrderId).is(':checked') : false;
    var appendReferral = appendReferralId ? $('#' + appendReferralId).is(':checked') : false;

    body = replaceAll(body, '{order_details_table}', orderBlock);
    body = replaceAll(body, '{referral_link_block}', referralBlock);
    body = applyPlaceholders(body, placeholders);

    if (appendOrder && rawBody.indexOf('{order_details_table}') === -1 && orderBlock) {
      body += orderBlock;
    }

    if (appendReferral && rawBody.indexOf('{referral_link_block}') === -1 && referralBlock) {
      body += referralBlock;
    }

    css = applyPlaceholders(css, placeholders);

    var doc = renderPreviewDocument(body, css, looksLikeFullDocument(body));
    var frame = card.find('.rrp-email-preview-frame').get(0);

    if (!frame) {
      return;
    }

    if ('srcdoc' in frame) {
      frame.srcdoc = doc;
      return;
    }

    var frameDoc = frame.contentDocument || frame.contentWindow.document;
    frameDoc.open();
    frameDoc.write(doc);
    frameDoc.close();
  }

  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(null, args);
      }, delay || 250);
    };
  }

  function bindTinyMce(editorId, update) {
    var attempts = 0;
    var timer = setInterval(function () {
      attempts += 1;

      if (!window.tinymce) {
        if (attempts > 40) {
          clearInterval(timer);
        }
        return;
      }

      var editor = window.tinymce.get(editorId);
      if (!editor) {
        if (attempts > 40) {
          clearInterval(timer);
        }
        return;
      }

      if (!editor.rrpPreviewBound) {
        editor.on('keyup change input paste SetContent Undo Redo', update);
        editor.rrpPreviewBound = true;
      }

      clearInterval(timer);
      update();
    }, 250);
  }

  $(function () {
    $('.rrp-email-template-card').each(function () {
      var card = $(this);
      var update = debounce(function () {
        updatePreview(card);
      }, 200);
      var editorId = card.data('editor-id');

      card.on('input change keyup', 'input, textarea, select', update);
      $(document).on('tinymce-editor-init', function (event, editor) {
        if (editor && editor.id === editorId && !editor.rrpPreviewBound) {
          editor.on('keyup change input paste SetContent Undo Redo', update);
          editor.rrpPreviewBound = true;
          update();
        }
      });

      bindTinyMce(editorId, update);
      updatePreview(card);
    });
  });
})(jQuery);
