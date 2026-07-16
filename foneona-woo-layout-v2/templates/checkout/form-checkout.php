<?php
/**
 * Checkout Form (Foneona layout)
 *
 * v1.5.0 — Shipping methods block is now rendered unconditionally so the
 *           #foneona-shipping-methods container always exists in the DOM
 *           and can be updated via WooCommerce AJAX fragments.
 *           This fixes the issue where a second shipping method (e.g.
 *           «до двери») would never appear.
 *
 * @package WooCommerce\Templates
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<div class="foneona-checkout-layout">

		<div class="foneona-checkout__summary-wrap" aria-label="<?php echo esc_attr__( 'Order summary', 'woocommerce' ); ?>">
			<button type="button" class="foneona-checkout__summary-toggle" aria-expanded="false">
				<span class="foneona-checkout__summary-toggle-left">
					<span class="foneona-checkout__summary-toggle-icon" aria-hidden="true"></span>
					<span class="foneona-checkout__summary-toggle-label"><?php echo esc_html__( 'Показать сводку заказа', 'woocommerce' ); ?></span>
					<span class="foneona-checkout__summary-toggle-chevron" aria-hidden="true"></span>
				</span>
				<span class="foneona-checkout__summary-toggle-total"><?php echo wp_kses_post( WC()->cart->get_total() ); ?></span>
			</button>

			<div class="foneona-checkout__summary">
				<h3 class="foneona-checkout__summary-title"><?php echo esc_html__( 'ВАШ ЗАКАЗ', 'woocommerce' ); ?></h3>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>
			</div>
		</div>

		<div class="foneona-checkout__main">

			<div class="foneona-checkout__topbar">
				<div class="foneona-checkout__section-title"><?php echo esc_html__( 'Информация для доставки', 'woocommerce' ); ?></div>
				<a class="foneona-checkout__back" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html__( 'Вернуться в корзину', 'woocommerce' ); ?></a>
			</div>

			<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

			<div id="customer_details">
				<div class="col-1">
					<?php do_action( 'woocommerce_checkout_billing' ); ?>
				</div>

				<div class="col-2">
					<?php do_action( 'woocommerce_checkout_shipping' ); ?>
				</div>
			</div>

			<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php
			/*
			 * v1.5.0 — ALWAYS render the shipping section title and the
			 * shipping methods container.  The container is AJAX-replaceable
			 * via #foneona-shipping-methods.  If no city has been entered yet,
			 * get_shipping_methods_block_html() shows a user-friendly prompt.
			 */
			?>
			<div class="foneona-checkout__section-title"><?php echo esc_html__( 'Способ доставки', 'woocommerce' ); ?></div>
			<?php echo Foneona_Woo_Layout::get_shipping_methods_block_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<div class="foneona-checkout__section-title"><?php echo esc_html__( 'Оплата', 'woocommerce' ); ?></div>

			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

			<?php woocommerce_checkout_payment(); ?>

			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

		</div>

	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
