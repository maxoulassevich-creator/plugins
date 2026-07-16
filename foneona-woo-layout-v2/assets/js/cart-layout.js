jQuery(function ($) {
	'use strict';

	var updateTimeout = null;

	function triggerCartUpdate($context) {
		var $form = $context.closest('form.woocommerce-cart-form');
		if (!$form.length) return;

		var $btn = $form.find('button[name="update_cart"]');
		if ($btn.length) {
			$btn.prop('disabled', false);
			clearTimeout(updateTimeout);
			updateTimeout = setTimeout(function () {
				$btn.trigger('click');
			}, 250);
		}
	}

	$(document).on('click', '.foneona-qty-btn', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var $qty = $btn.closest('.quantity').find('input.qty');

		if (!$qty.length) return;

		var currentVal = parseFloat($qty.val());
		if (isNaN(currentVal)) currentVal = 1;

		var min = parseFloat($qty.attr('min'));
		if (isNaN(min)) min = 1;

		var max = parseFloat($qty.attr('max'));
		if (isNaN(max)) max = 999999;

		if ($btn.hasClass('foneona-qty-plus')) {
			if (currentVal < max) currentVal = currentVal + 1;
		} else {
			if (currentVal > min) currentVal = currentVal - 1;
		}

		$qty.val(currentVal);
		$qty.trigger('change');

		triggerCartUpdate($qty);
	});

	// When user edits qty manually.
	$(document).on('change', 'form.woocommerce-cart-form input.qty', function () {
		triggerCartUpdate($(this));
	});
});
