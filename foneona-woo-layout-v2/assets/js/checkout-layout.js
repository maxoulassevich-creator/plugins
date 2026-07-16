(function($){
  'use strict';

  var pointsUpdateTimer = null;
  var pendingSubmitAfterPointsRefresh = false;

  function updateStickyTop(){
    var top = 20;
    var header = document.querySelector('.site-header, header, .header');
    if (header) {
      var cs = window.getComputedStyle(header);
      if (cs && (cs.position === 'fixed' || cs.position === 'sticky')) {
        top = header.offsetHeight + 20;
      }
    }
    var root = document.querySelector('.foneona-checkout-layout');
    if (root) {
      root.style.setProperty('--foneona-sticky-top', top + 'px');
    }
  }

  function updateMobileTotal(){
    var $total = $('.foneona-order-total-value').first();
    if ($total.length) {
      $('.foneona-checkout__summary-toggle-total').html($total.html());
    }
  }

  function refreshPaymentBoxes(){
    var $methods = $('#payment .wc_payment_methods .wc_payment_method');

    if (!$methods.length) {
      return;
    }

    $methods.each(function(){
      var $method = $(this);
      var $input = $method.children('input[name="payment_method"]');
      var $box = $method.children('.payment_box');

      if (!$box.length) {
        return;
      }

      if ($input.is(':checked')) {
        $box.stop(true, true).slideDown(0);
      } else {
        $box.stop(true, true).slideUp(0);
      }
    });
  }

  function bindSummaryToggle(){
    $(document)
      .off('click.foneonaSummary', '.foneona-checkout__summary-toggle')
      .on('click.foneonaSummary', '.foneona-checkout__summary-toggle', function(){
        var $btn = $(this);
        var $wrap = $btn.closest('.foneona-checkout__summary-wrap');

        $wrap.toggleClass('is-open');
        $btn.attr('aria-expanded', $wrap.hasClass('is-open') ? 'true' : 'false');
      });
  }

  function bindCouponApply(){
    $(document)
      .off('click.foneonaCoupon', '.foneona-checkout-coupon__btn')
      .on('click.foneonaCoupon', '.foneona-checkout-coupon__btn', function(e){
        e.preventDefault();

        if (typeof wc_checkout_params === 'undefined' || !wc_checkout_params.wc_ajax_url) {
          return;
        }

        var $btn = $(this);
        var $input = $btn.closest('.foneona-checkout-coupon').find('.foneona-checkout-coupon__input');
        var code = ($input.val() || '').trim();

        if (!code) {
          return;
        }

        $btn.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon'),
          data: {
            security: wc_checkout_params.apply_coupon_nonce,
            coupon_code: code
          },
          success: function(msg){
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .woocommerce-info').remove();

            if (!$('.woocommerce-notices-wrapper').length) {
              $('form.checkout').before('<div class="woocommerce-notices-wrapper"></div>');
            }

            $('.woocommerce-notices-wrapper').first().html(msg);

            $(document.body).trigger('applied_coupon_in_checkout', [code]);
            $(document.body).trigger('update_checkout');
          },
          complete: function(){
            $btn.prop('disabled', false);
          }
        });
      });
  }

  function normalizePointsValue(value){
    var normalized = parseFloat(String(value || '').replace(',', '.'));
    if (isNaN(normalized) || normalized < 0) {
      return 0;
    }
    return normalized;
  }

  function requestPointsRefresh(delay){
    if (pointsUpdateTimer) {
      window.clearTimeout(pointsUpdateTimer);
    }

    pointsUpdateTimer = window.setTimeout(function(){
      $(document.body).trigger('update_checkout');
    }, typeof delay === 'number' ? delay : 0);
  }


  var PHONE_MASK_TEMPLATE = '+7(___)___-__-__';
  var PHONE_MASK_POSITIONS = [3, 4, 5, 7, 8, 9, 11, 12, 14, 15];

  function extractRuPhoneDigits(value){
    var raw = String(value || '');

    if (!raw) {
      return '';
    }

    if (raw.indexOf('+7') === 0) {
      var maskedDigits = '';

      PHONE_MASK_POSITIONS.forEach(function(pos){
        var ch = raw.charAt(pos);
        if (/\d/.test(ch)) {
          maskedDigits += ch;
        }
      });

      if (maskedDigits) {
        return maskedDigits.slice(0, 10);
      }
    }

    var digits = raw.replace(/\D+/g, '');

    if (!digits) {
      return '';
    }

    if (digits.length > 10 && (digits.charAt(0) === '7' || digits.charAt(0) === '8')) {
      digits = digits.slice(1);
    }

    return digits.slice(0, 10);
  }

  function buildRuPhoneMask(digits){
    var chars = PHONE_MASK_TEMPLATE.split('');
    var normalized = String(digits || '').replace(/\D+/g, '').slice(0, 10);

    PHONE_MASK_POSITIONS.forEach(function(pos, index){
      chars[pos] = index < normalized.length ? normalized.charAt(index) : '_';
    });

    return chars.join('');
  }

  function setStoredRuPhoneDigits(input, digits){
    if (!input) {
      return '';
    }

    var normalized = String(digits || '').replace(/\D+/g, '').slice(0, 10);
    input.setAttribute('data-foneona-phone-digits', normalized);
    return normalized;
  }

  function getStoredRuPhoneDigits(input){
    if (!input) {
      return '';
    }

    var cached = input.getAttribute('data-foneona-phone-digits');
    if (cached !== null) {
      return String(cached || '').replace(/\D+/g, '').slice(0, 10);
    }

    return setStoredRuPhoneDigits(input, extractRuPhoneDigits(input.value));
  }

  function renderRuPhoneInput(input, forceMask){
    if (!input) {
      return;
    }

    var digits = getStoredRuPhoneDigits(input);

    if (!digits.length && !forceMask) {
      input.value = '';
      return;
    }

    input.value = buildRuPhoneMask(digits);
  }

  function getPhoneSelection(input){
    if (!input) {
      return { start: 0, end: 0 };
    }

    return {
      start: typeof input.selectionStart === 'number' ? input.selectionStart : 0,
      end: typeof input.selectionEnd === 'number' ? input.selectionEnd : 0
    };
  }

  function getPhoneDigitIndexFromCaret(position){
    var index = 0;

    PHONE_MASK_POSITIONS.forEach(function(pos, posIndex){
      if (position > pos) {
        index = posIndex + 1;
      }
    });

    return index;
  }

  function getPhoneCaretPositionForIndex(index){
    if (index <= 0) {
      return PHONE_MASK_POSITIONS[0];
    }

    if (index >= PHONE_MASK_POSITIONS.length) {
      return PHONE_MASK_TEMPLATE.length;
    }

    return PHONE_MASK_POSITIONS[index];
  }

  function getNearestPhoneCaretPosition(position){
    if (position <= PHONE_MASK_POSITIONS[0]) {
      return PHONE_MASK_POSITIONS[0];
    }

    for (var i = 0; i < PHONE_MASK_POSITIONS.length; i += 1) {
      if (position <= PHONE_MASK_POSITIONS[i]) {
        return PHONE_MASK_POSITIONS[i];
      }
    }

    return PHONE_MASK_TEMPLATE.length;
  }

  function setPhoneCaret(input, position){
    if (!input || typeof input.setSelectionRange !== 'function') {
      return;
    }

    window.requestAnimationFrame(function(){
      input.setSelectionRange(position, position);
    });
  }

  function replacePhoneDigitsInSelection(input, replacementDigits){
    var digits = getStoredRuPhoneDigits(input).split('');
    var selection = getPhoneSelection(input);
    var startIndex = getPhoneDigitIndexFromCaret(selection.start);
    var endIndex = getPhoneDigitIndexFromCaret(selection.end);
    var inserted = String(replacementDigits || '').replace(/\D+/g, '').slice(0, 10).split('');
    var removeCount = Math.max(0, endIndex - startIndex);

    digits.splice.apply(digits, [startIndex, removeCount].concat(inserted));
    digits = digits.slice(0, 10);

    setStoredRuPhoneDigits(input, digits.join(''));
    renderRuPhoneInput(input, true);
    setPhoneCaret(input, getPhoneCaretPositionForIndex(Math.min(startIndex + inserted.length, 10)));
  }

  function removePhoneDigit(input, isDelete){
    var digits = getStoredRuPhoneDigits(input).split('');
    var selection = getPhoneSelection(input);
    var startIndex = getPhoneDigitIndexFromCaret(selection.start);
    var endIndex = getPhoneDigitIndexFromCaret(selection.end);

    if (endIndex > startIndex) {
      digits.splice(startIndex, endIndex - startIndex);
      setStoredRuPhoneDigits(input, digits.join(''));
      renderRuPhoneInput(input, digits.length > 0);
      setPhoneCaret(input, getPhoneCaretPositionForIndex(startIndex));
      return;
    }

    var removeIndex = isDelete ? startIndex : startIndex - 1;

    if (removeIndex < 0 || removeIndex >= digits.length) {
      if (!digits.length) {
        renderRuPhoneInput(input, true);
        setPhoneCaret(input, PHONE_MASK_POSITIONS[0]);
      }
      return;
    }

    digits.splice(removeIndex, 1);
    setStoredRuPhoneDigits(input, digits.join(''));

    if (!digits.length) {
      renderRuPhoneInput(input, true);
      setPhoneCaret(input, PHONE_MASK_POSITIONS[0]);
      return;
    }

    renderRuPhoneInput(input, true);
    setPhoneCaret(input, getPhoneCaretPositionForIndex(removeIndex));
  }

  function syncPhoneValueForSubmit(context){
    var $context = context ? $(context) : $(document);
    var selector = 'input[type="tel"], input[name="billing_phone"], input[name="shipping_phone"], #billing_phone, #shipping_phone';

    $context.find(selector).each(function(){
      var digits = getStoredRuPhoneDigits(this);
      this.value = digits ? ('+7' + digits) : '';
    });
  }

  function initJqueryMaskedInput(selector){
    if (typeof $.fn.mask !== 'function') {
      return false;
    }

    $(selector).each(function(){
      var $input = $(this);
      var digits = extractRuPhoneDigits($input.val());

      $input.attr('placeholder', PHONE_MASK_TEMPLATE);
      setStoredRuPhoneDigits(this, digits);

      if (digits) {
        $input.val('+7' + digits);
      } else if (!$input.is(':focus')) {
        $input.val('');
      }

      $input.unmask();
      $input.mask('+7(999)999-99-99', { placeholder: '_' });
    });

    $('form.checkout')
      .off('submit.foneonaPhoneMaskSync place_order.foneonaPhoneMaskSync checkout_place_order.foneonaPhoneMaskSync')
      .on('submit.foneonaPhoneMaskSync place_order.foneonaPhoneMaskSync checkout_place_order.foneonaPhoneMaskSync', function(){
        syncPhoneValueForSubmit(this);
        return true;
      });

    return true;
  }

  function bindPhoneMask(){
    var selector = 'input[type="tel"], input[name="billing_phone"], input[name="shipping_phone"], #billing_phone, #shipping_phone';

    if (initJqueryMaskedInput(selector)) {
      return;
    }

    $(selector).each(function(){
      this.setAttribute('placeholder', PHONE_MASK_TEMPLATE);
      setStoredRuPhoneDigits(this, extractRuPhoneDigits(this.value));
      renderRuPhoneInput(this, false);
    });

    $(document)
      .off('focus.foneonaPhoneMask', selector)
      .on('focus.foneonaPhoneMask', selector, function(){
        this.setAttribute('placeholder', PHONE_MASK_TEMPLATE);
        setStoredRuPhoneDigits(this, extractRuPhoneDigits(this.value));
        renderRuPhoneInput(this, true);
        setPhoneCaret(this, getPhoneCaretPositionForIndex(getStoredRuPhoneDigits(this).length));
      })
      .off('click.foneonaPhoneMask', selector)
      .on('click.foneonaPhoneMask', selector, function(){
        var input = this;
        window.requestAnimationFrame(function(){
          var selection = getPhoneSelection(input);
          if (selection.start === selection.end) {
            setPhoneCaret(input, getNearestPhoneCaretPosition(selection.start));
          }
        });
      })
      .off('keydown.foneonaPhoneMask', selector)
      .on('keydown.foneonaPhoneMask', selector, function(e){
        var key = String(e.key || '');

        if (e.ctrlKey || e.metaKey || e.altKey) {
          return;
        }

        if (/^\d$/.test(key)) {
          e.preventDefault();
          replacePhoneDigitsInSelection(this, key);
          return;
        }

        if (key === 'Backspace') {
          e.preventDefault();
          removePhoneDigit(this, false);
          return;
        }

        if (key === 'Delete') {
          e.preventDefault();
          removePhoneDigit(this, true);
          return;
        }

        if (key === 'Home') {
          e.preventDefault();
          renderRuPhoneInput(this, true);
          setPhoneCaret(this, PHONE_MASK_POSITIONS[0]);
          return;
        }

        if (key === 'End') {
          e.preventDefault();
          renderRuPhoneInput(this, true);
          setPhoneCaret(this, getPhoneCaretPositionForIndex(getStoredRuPhoneDigits(this).length));
          return;
        }

        if (
          key === 'Tab' ||
          key === 'Enter' ||
          key === 'Escape' ||
          key === 'ArrowLeft' ||
          key === 'ArrowRight' ||
          key === 'ArrowUp' ||
          key === 'ArrowDown' ||
          key === 'Shift'
        ) {
          return;
        }

        e.preventDefault();
      })
      .off('paste.foneonaPhoneMask', selector)
      .on('paste.foneonaPhoneMask', selector, function(e){
        var original = e.originalEvent || e;
        var clipboard = original && original.clipboardData ? original.clipboardData.getData('text') : '';
        var digits = extractRuPhoneDigits(clipboard);

        if (!digits) {
          return;
        }

        e.preventDefault();
        replacePhoneDigitsInSelection(this, digits);
      })
      .off('input.foneonaPhoneMask', selector)
      .on('input.foneonaPhoneMask', selector, function(){
        if (document.activeElement !== this) {
          setStoredRuPhoneDigits(this, extractRuPhoneDigits(this.value));
          renderRuPhoneInput(this, false);
          return;
        }

        setStoredRuPhoneDigits(this, extractRuPhoneDigits(this.value));
        renderRuPhoneInput(this, true);
        setPhoneCaret(this, getPhoneCaretPositionForIndex(getStoredRuPhoneDigits(this).length));
      })
      .off('blur.foneonaPhoneMask', selector)
      .on('blur.foneonaPhoneMask', selector, function(){
        setStoredRuPhoneDigits(this, extractRuPhoneDigits(this.value));
        renderRuPhoneInput(this, false);
      });

    $('form.checkout')
      .off('submit.foneonaPhoneMaskSync place_order.foneonaPhoneMaskSync checkout_place_order.foneonaPhoneMaskSync')
      .on('submit.foneonaPhoneMaskSync place_order.foneonaPhoneMaskSync checkout_place_order.foneonaPhoneMaskSync', function(){
        syncPhoneValueForSubmit(this);
        return true;
      });
  }


  function moveGlobalShippingActionsIntoPickupCard(){
    $('.foneona-shipping-methods__global-actions').each(function(){
      var $global = $(this);

      if (!$global.length || !$.trim($global.text()) && !$global.find('button, a, select, input').length) {
        $global.remove();
        return;
      }

      var $selectedPickupCard = $('.foneona-shipping-card.is-selected[data-service="pickup"]').first();

      if (!$selectedPickupCard.length) {
        $global.show();
        return;
      }

      var carrier = String($selectedPickupCard.attr('data-carrier') || '');
      var globalText = String($global.text() || '').toLowerCase();
      var hasYandexControl = $global.find('[class*="yandex"], [id*="yandex"]').length > 0;
      var hasRussianPostControl = $global.find('.wc-russian-post-choose-delivery-point, .wc-russian-post-method-additional-info').length > 0;
      var looksLikePickupControl = globalText.indexOf('пункт') !== -1 || globalText.indexOf('выдач') !== -1 || globalText.indexOf('pvz') !== -1 || globalText.indexOf('pickup') !== -1;

      if (carrier === 'yandex' && (hasYandexControl || looksLikePickupControl)) {
        var $actions = $selectedPickupCard.children('.foneona-shipping-card__actions').first();
        if (!$actions.length) {
          $actions = $('<div class="foneona-shipping-card__actions"></div>').appendTo($selectedPickupCard);
        }
        $actions.append($global.contents());
        $global.remove();
        return;
      }

      if (carrier === 'russian_post' && hasRussianPostControl) {
        var $rpActions = $selectedPickupCard.children('.foneona-shipping-card__actions').first();
        if (!$rpActions.length) {
          $rpActions = $('<div class="foneona-shipping-card__actions"></div>').appendTo($selectedPickupCard);
        }
        if (!$rpActions.find('.wc-russian-post-choose-delivery-point').length) {
          $rpActions.append($global.contents());
        }
        $global.remove();
      }
    });
  }


  function refreshShippingCards(){
    $('.foneona-shipping-card').each(function(){
      var $card = $(this);
      var $input = $card.find('input.shipping_method').first();
      var selected = false;

      if ($input.length) {
        selected = $input.attr('type') === 'hidden' || $input.is(':checked');
      }

      $card.toggleClass('is-selected', selected);
      $card.attr('aria-checked', selected ? 'true' : 'false');
    });
  }

  function bindShippingCards(){
    $(document)
      .off('click.foneonaShippingCard', '.foneona-shipping-card')
      .on('click.foneonaShippingCard', '.foneona-shipping-card', function(e){
        if ($(e.target).closest('a, button, select, textarea, input, label, .select2-container, .wc-yandex-choose-pickup-point, .wc-russian-post-choose-delivery-point').length) {
          return;
        }

        var $input = $(this).find('input.shipping_method').first();
        if ($input.length && $input.attr('type') !== 'hidden' && !$input.is(':checked')) {
          $input.prop('checked', true).trigger('change');
        }
      })
      .off('click.foneonaShippingCardSelect', '[data-foneona-shipping-card-select]')
      .on('click.foneonaShippingCardSelect', '[data-foneona-shipping-card-select]', function(e){
        if ($(e.target).closest('a, button, select, textarea, input, label, .select2-container, .wc-yandex-choose-pickup-point, .wc-russian-post-choose-delivery-point').length) {
          return;
        }

        var inputId = $(this).attr('data-foneona-shipping-card-select');
        var $input = $('#' + inputId);
        if ($input.length && $input.attr('type') !== 'hidden' && !$input.is(':checked')) {
          $input.prop('checked', true).trigger('change');
        }
      })
      .off('change.foneonaShippingCard', 'input.shipping_method')
      .on('change.foneonaShippingCard', 'input.shipping_method', function(){
        refreshShippingCards();
      });

    refreshShippingCards();
    moveGlobalShippingActionsIntoPickupCard();
  }


  function bindPointsControls(){
    $(document)
      .off('click.foneonaPointsApply', '.foneona-points-panel__apply')
      .on('click.foneonaPointsApply', '.foneona-points-panel__apply', function(e){
        e.preventDefault();
        requestPointsRefresh(0);
      })
      .off('click.foneonaPointsClear', '.foneona-points-panel__clear')
      .on('click.foneonaPointsClear', '.foneona-points-panel__clear', function(e){
        e.preventDefault();
        var $panel = $(this).closest('.foneona-points-panel');
        $panel.find('.foneona-points-panel__input').val('0');
        requestPointsRefresh(0);
      })
      .off('input.foneonaPoints change.foneonaPoints blur.foneonaPoints', '.foneona-points-panel__input')
      .on('input.foneonaPoints', '.foneona-points-panel__input', function(){
        requestPointsRefresh(450);
      })
      .on('change.foneonaPoints blur.foneonaPoints', '.foneona-points-panel__input', function(){
        requestPointsRefresh(0);
      })
      .off('keydown.foneonaPoints', '.foneona-points-panel__input')
      .on('keydown.foneonaPoints', '.foneona-points-panel__input', function(e){
        if (e.key === 'Enter') {
          e.preventDefault();
          requestPointsRefresh(0);
        }
      });

    $('form.checkout')
      .off('checkout_place_order.foneonaPoints')
      .on('checkout_place_order.foneonaPoints', function(){
        var $input = $('.foneona-points-panel__input').first();

        if (!$input.length) {
          return true;
        }

        var currentValue = normalizePointsValue($input.val());
        var appliedValue = normalizePointsValue($input.attr('data-applied-value'));

        if (Math.abs(currentValue - appliedValue) > 0.0001) {
          pendingSubmitAfterPointsRefresh = true;
          requestPointsRefresh(0);
          return false;
        }

        return true;
      });
  }

  $(function(){
    updateStickyTop();
    bindSummaryToggle();
    bindCouponApply();
    bindPointsControls();
    bindPhoneMask();
    bindShippingCards();
    updateMobileTotal();
    refreshPaymentBoxes();

    $(window).on('resize', updateStickyTop);

    $(document.body).on('updated_checkout payment_method_selected', function(){
      updateStickyTop();
      updateMobileTotal();
      refreshPaymentBoxes();
      bindPointsControls();
      bindPhoneMask();
      bindShippingCards();

      if (pendingSubmitAfterPointsRefresh) {
        pendingSubmitAfterPointsRefresh = false;
        $('form.checkout').trigger('submit');
      }
    });

    $('form.checkout').on('change', 'input[name="payment_method"]', refreshPaymentBoxes);
  });

})(jQuery);
