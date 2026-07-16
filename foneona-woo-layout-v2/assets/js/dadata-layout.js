(function($){
  'use strict';

  function debounce(fn, wait){
    var t = null;
    return function(){
      var args = arguments;
      var ctx = this;
      clearTimeout(t);
      t = setTimeout(function(){ fn.apply(ctx, args); }, wait);
    };
  }

  function escapeHtml(str){
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function normalize(str){
    return String(str || '').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function compact(parts){
    return parts.filter(function(part){
      return String(part || '').trim() !== '';
    });
  }

  function getCity(data){
    return data.city || data.settlement || data.area || data.region || '';
  }

  function getCityLabel(data, fallback){
    return data.city || data.settlement || data.city_with_type || data.settlement_with_type || fallback || '';
  }

  function getRegion(data){
    return data.region_with_type || data.region || data.area_with_type || data.area || '';
  }

  function getAddressLine(data, fallback){
    var parts = [];

    if (data.street_with_type) {
      parts.push(data.street_with_type);
    } else if (data.street) {
      parts.push(data.street);
    }
    if (data.house) {
      parts.push('д ' + data.house);
    }
    if (data.block) {
      parts.push((data.block_type || 'корп') + ' ' + data.block);
    }
    if (data.flat) {
      parts.push((data.flat_type || 'кв') + ' ' + data.flat);
    }

    var value = compact(parts).join(', ');
    return value || fallback || '';
  }

  function getModeFromInput($input){
    var explicitMode = $input.attr('data-foneona-dadata-mode');
    if (explicitMode) {
      return explicitMode;
    }

    var id = $input.attr('id') || '';
    if (id.indexOf('city') !== -1) {
      return 'city';
    }
    return 'address';
  }

  function getSuggestionMain(item, mode){
    var data = item && item.data ? item.data : {};

    if (mode === 'city') {
      return getCityLabel(data, item && (item.value || item.unrestricted_value));
    }

    return getAddressLine(data, item && (item.value || item.unrestricted_value));
  }

  function getSuggestionSub(item, mode){
    var data = item && item.data ? item.data : {};

    if (mode === 'city') {
      return compact([getRegion(data), data.postal_code || '']).join(', ');
    }

    return compact([getCityLabel(data), getRegion(data), data.postal_code || '']).join(', ');
  }

  function closeDropdown($context){
    var $dropdown = $context.find('.foneona-dadata__dropdown').first();
    if (!$dropdown.length) {
      return;
    }
    $dropdown.empty().attr('hidden', true).removeData('suggestions');
    $context.removeClass('is-open');
  }

  function renderDropdown($context, suggestions, mode){
    var $dropdown = $context.find('.foneona-dadata__dropdown').first();

    if (!$dropdown.length) {
      return;
    }

    if (!suggestions || !suggestions.length) {
      $dropdown.html('<div class="foneona-dadata__empty">' + escapeHtml(foneonaDadata.noResults || 'Ничего не найдено') + '</div>').attr('hidden', false);
      $context.addClass('is-open');
      return;
    }

    var html = '<div class="foneona-dadata__list">';
    suggestions.forEach(function(item, index){
      var main = getSuggestionMain(item, mode);
      var sub = getSuggestionSub(item, mode);
      html += '<button type="button" class="foneona-dadata__item" data-index="' + index + '">'
        + '<span class="foneona-dadata__item-main">' + escapeHtml(main) + '</span>'
        + (sub ? '<span class="foneona-dadata__item-sub">' + escapeHtml(sub) + '</span>' : '')
        + '</button>';
    });
    html += '</div>';

    $dropdown.html(html).attr('hidden', false).data('suggestions', suggestions);
    $context.addClass('is-open');
  }

  function setFieldValue($field, value){
    if (!$field.length || typeof value === 'undefined' || value === null) {
      return;
    }

    value = String(value);

    if ($field.is('select')) {
      var normalizedValue = normalize(value);
      var matched = false;

      $field.find('option').each(function(){
        var $option = $(this);
        var optionValue = normalize($option.val());
        var optionText = normalize($option.text());
        if (normalizedValue && (normalizedValue === optionValue || normalizedValue === optionText || optionText.indexOf(normalizedValue) !== -1 || normalizedValue.indexOf(optionText) !== -1)) {
          $field.val($option.val());
          matched = true;
          return false;
        }
      });

      if (!matched) {
        $field.val(value);
      }
    } else {
      $field.val(value);
    }

    $field.trigger('change');
  }

  function applyCartSuggestion(item){
    var data = item && item.data ? item.data : {};
    setFieldValue($('#foneona_dadata_cart'), getAddressLine(data, item && (item.value || item.unrestricted_value) ? (item.value || item.unrestricted_value) : ''));
    setFieldValue($('#calc_shipping_city'), getCity(data));
    setFieldValue($('#calc_shipping_state'), getRegion(data));
    setFieldValue($('#calc_shipping_postcode'), data.postal_code || '');
  }

  function applyCheckoutSuggestion($input, item, group, mode){
    var data = item && item.data ? item.data : {};
    var prefix = group === 'shipping' ? 'shipping_' : 'billing_';

    if (mode === 'city') {
      setFieldValue($input, getCityLabel(data, item && (item.value || item.unrestricted_value) ? (item.value || item.unrestricted_value) : ''));
      setFieldValue($('#' + prefix + 'city'), getCity(data));
      setFieldValue($('#' + prefix + 'state'), getRegion(data));
      setFieldValue($('#' + prefix + 'postcode'), data.postal_code || '');
      setFieldValue($('#' + prefix + 'country'), foneonaDadata.countryCode || 'RU');
      $(document.body).trigger('update_checkout');
      return;
    }

    setFieldValue($input, getAddressLine(data, item && (item.value || item.unrestricted_value) ? (item.value || item.unrestricted_value) : ''));
    setFieldValue($('#' + prefix + 'city'), getCity(data));
    setFieldValue($('#' + prefix + 'state'), getRegion(data));
    setFieldValue($('#' + prefix + 'postcode'), data.postal_code || '');
    setFieldValue($('#' + prefix + 'country'), foneonaDadata.countryCode || 'RU');

    $(document.body).trigger('update_checkout');
  }

  function fetchSuggestions($context, requestData){
    if (!window.foneonaDadata || !foneonaDadata.ajaxUrl) {
      return;
    }

    var xhr = $context.data('foneonaDadataXhr');
    if (xhr && xhr.readyState !== 4) {
      xhr.abort();
    }

    xhr = $.ajax({
      url: foneonaDadata.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: $.extend({
        action: 'foneona_dadata_suggest_address',
        nonce: foneonaDadata.nonce
      }, requestData)
    }).done(function(response){
      if (response && response.success && response.data) {
        renderDropdown($context, response.data.suggestions || [], requestData.mode || 'address');
      } else {
        renderDropdown($context, [], requestData.mode || 'address');
      }
    }).fail(function(xhrObject, status){
      if (status === 'abort') {
        return;
      }
      var $dropdown = $context.find('.foneona-dadata__dropdown').first();
      $dropdown.html('<div class="foneona-dadata__empty">' + escapeHtml(foneonaDadata.errorText || 'Не удалось получить подсказки') + '</div>').attr('hidden', false);
      $context.addClass('is-open');
    });

    $context.data('foneonaDadataXhr', xhr);
  }

  function initCartRoot(root){
    var $root = $(root);
    var $input = $root.find('.foneona-dadata__input').first();

    if (!$input.length || $root.data('foneonaDadataInit')) {
      return;
    }

    $root.data('foneonaDadataInit', true);
    $input.attr('autocomplete', 'off');

    var debouncedFetch = debounce(function(){
      var value = $.trim($input.val());
      if (value.length < (parseInt(foneonaDadata.minLength, 10) || 3)) {
        closeDropdown($root);
        return;
      }

      fetchSuggestions($root, {
        query: value,
        mode: 'address',
        location_city: $.trim($('#calc_shipping_city').val() || ''),
        location_region: $.trim($('#calc_shipping_state').val() || '')
      });
    }, 250);

    $input.on('input', debouncedFetch);
    $input.on('focus', function(){
      if ($root.hasClass('is-open')) {
        $root.find('.foneona-dadata__dropdown').first().attr('hidden', false);
      }
    });

    $root.on('mousedown', '.foneona-dadata__item', function(e){
      e.preventDefault();
      e.stopPropagation();
      var suggestions = $root.find('.foneona-dadata__dropdown').first().data('suggestions') || [];
      var index = parseInt($(this).attr('data-index'), 10);
      var item = suggestions[index];
      if (!item) {
        return;
      }

      applyCartSuggestion(item);
      closeDropdown($root);
    });
  }

  function getCheckoutGroup($input){
    var id = $input.attr('id') || $input.attr('name') || '';
    return id.indexOf('shipping_') === 0 ? 'shipping' : 'billing';
  }

  function initCheckoutInput(input){
    var $input = $(input);

    if (!$input.length || $input.data('foneonaDadataInit') || $input.is('[type="hidden"]') || $input.is('select')) {
      return;
    }

    var mode = getModeFromInput($input);
    var $wrapper = $input.closest('.woocommerce-input-wrapper');
    if (!$wrapper.length) {
      $wrapper = $input.parent();
    }
    if (!$wrapper.length) {
      return;
    }

    var $row = $input.closest('.form-row');
    $row.addClass('foneona-dadata-inline');
    $wrapper.addClass('foneona-dadata__control');
    $input.addClass('foneona-dadata__input').attr('autocomplete', 'off');

    var $dropdown = $wrapper.children('.foneona-dadata__dropdown');
    if (!$dropdown.length) {
      $dropdown = $('<div class="foneona-dadata__dropdown" hidden></div>');
      $wrapper.append($dropdown);
    }

    $input.data('foneonaDadataInit', true);

    var debouncedFetch = debounce(function(){
      var value = $.trim($input.val());
      var group = getCheckoutGroup($input);
      var prefix = group === 'shipping' ? 'shipping_' : 'billing_';

      if (value.length < (parseInt(foneonaDadata.minLength, 10) || 3)) {
        closeDropdown($wrapper);
        return;
      }

      fetchSuggestions($wrapper, {
        query: value,
        mode: mode,
        location_city: mode === 'address' ? $.trim($('#' + prefix + 'city').val() || '') : '',
        location_region: mode === 'address' ? $.trim($('#' + prefix + 'state').val() || '') : ''
      });
    }, 250);

    $input.on('input', debouncedFetch);
    $input.on('focus', function(){
      if ($wrapper.hasClass('is-open')) {
        $wrapper.find('.foneona-dadata__dropdown').first().attr('hidden', false);
      }
    });

    $wrapper.on('mousedown', '.foneona-dadata__item', function(e){
      e.preventDefault();
      e.stopPropagation();
      var suggestions = $wrapper.find('.foneona-dadata__dropdown').first().data('suggestions') || [];
      var index = parseInt($(this).attr('data-index'), 10);
      var item = suggestions[index];
      if (!item) {
        return;
      }

      applyCheckoutSuggestion($input, item, getCheckoutGroup($input), mode);
      closeDropdown($wrapper);
    });
  }

  function initAll(){
    $('[data-foneona-dadata="cart"]').each(function(){
      initCartRoot(this);
    });

    $('#billing_city, #billing_address_1, #shipping_city, #shipping_address_1').each(function(){
      initCheckoutInput(this);
    });
  }

  $(function(){
    initAll();

    $(document).on('mousedown touchstart', function(e){
      $('.foneona-dadata, .foneona-dadata__control').each(function(){
        if (!$.contains(this, e.target) && this !== e.target) {
          closeDropdown($(this));
        }
      });
    });

    $(document.body).on('updated_checkout updated_cart_totals', function(){
      initAll();
    });
  });

})(jQuery);
