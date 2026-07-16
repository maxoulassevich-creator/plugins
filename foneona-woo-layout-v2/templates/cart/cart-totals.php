<?php
/**
 * Cart Totals (custom layout)
 *
 * @package FoneonaCartLayout/Templates
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="cart_totals foneona-cart-totals">
	<?php do_action( 'woocommerce_before_cart_totals' ); ?>

	<h2 class="foneona-cart-totals__title"><?php echo esc_html( 'СУММА КОРЗИНЫ' ); ?></h2>

	<div class="foneona-totals-row foneona-totals-row--total foneona-totals-row--products-only">
		<div class="foneona-totals-label"><?php echo esc_html( 'Итого' ); ?></div>
		<div class="foneona-totals-value"><?php echo wp_kses_post( Foneona_Woo_Layout::get_cart_items_total_without_shipping_html() ); ?></div>
	</div>

	<?php if ( wc_coupons_enabled() ) : ?>
		<form class="foneona-coupon-form" method="post">
			<input type="text" name="coupon_code" class="input-text foneona-coupon-input" id="coupon_code" value="" placeholder="<?php echo esc_attr( 'Код купона' ); ?>" />
			<button type="submit" class="button foneona-coupon-button" name="apply_coupon" value="<?php echo esc_attr( '1' ); ?>"><?php echo esc_html( 'ПРИМЕНИТЬ' ); ?></button>
			<?php do_action( 'woocommerce_cart_coupon' ); ?>
			<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
		</form>
	<?php endif; ?>

	<div class="wc-proceed-to-checkout foneona-proceed">
		<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
	</div>

	<?php do_action( 'woocommerce_after_cart_totals' ); ?>
</div>
