<?php
/**
 * Shipping Calculator (custom styling)
 *
 * @package FoneonaCartLayout/Templates
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_shipping_calculator' );
?>
<form class="woocommerce-shipping-calculator foneona-shipping-calculator" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php
	printf(
		'<a href="#" class="shipping-calculator-button foneona-shipping-toggle" aria-expanded="false" aria-controls="shipping-calculator-form" role="button">%s</a>',
		esc_html( ! empty( $button_text ) ? $button_text : __( 'Calculate shipping', 'woocommerce' ) )
	);
	?>
	<section class="shipping-calculator-form foneona-shipping-form" id="shipping-calculator-form" style="display:none;">
		<input type="hidden" name="calc_shipping_country" id="calc_shipping_country" value="<?php echo esc_attr( Foneona_Woo_Layout::get_fixed_country_code() ); ?>" />

		<p class="form-row form-row-wide foneona-fixed-country-row">
			<label><?php esc_html_e( 'Страна/регион', 'woocommerce' ); ?></label>
			<span class="foneona-fixed-country-value"><?php echo esc_html( Foneona_Woo_Layout::get_fixed_country_name() ); ?></span>
		</p>

		<?php if ( Foneona_Woo_Layout::is_dadata_enabled() ) : ?>
			<div class="foneona-dadata foneona-dadata--cart" data-foneona-dadata="cart">
				<label class="foneona-dadata__label" for="foneona_dadata_cart"><?php esc_html_e( 'Адрес', 'woocommerce' ); ?></label>
				<div class="foneona-dadata__control">
					<input type="text" id="foneona_dadata_cart" class="foneona-dadata__input" autocomplete="off" placeholder="<?php echo esc_attr( Foneona_Woo_Layout::get_setting( 'dadata_placeholder', 'Начните вводить адрес' ) ); ?>" />
					<div class="foneona-dadata__dropdown" hidden></div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_state', true ) ) : ?>
			<p class="form-row form-row-wide" id="calc_shipping_state_field">
				<?php
				$current_cc = Foneona_Woo_Layout::get_fixed_country_code();
				$current_r  = '';
				$states     = WC()->countries->get_states( $current_cc );

				if ( is_array( $states ) && empty( $states ) ) {
					?>
					<input type="hidden" name="calc_shipping_state" id="calc_shipping_state" value="" />
					<?php
				} elseif ( is_array( $states ) ) {
					?>
					<span>
						<label for="calc_shipping_state"><?php esc_html_e( 'State / County', 'woocommerce' ); ?></label>
						<select name="calc_shipping_state" class="state_select" id="calc_shipping_state">
							<option value=""><?php esc_html_e( 'Select an option&hellip;', 'woocommerce' ); ?></option>
							<?php
							foreach ( $states as $ckey => $cvalue ) {
								echo '<option value="' . esc_attr( $ckey ) . '" ' . selected( $current_r, $ckey, false ) . '>' . esc_html( $cvalue ) . '</option>';
							}
							?>
						</select>
					</span>
					<?php
				} else {
					?>
					<label for="calc_shipping_state"><?php esc_html_e( 'State / County', 'woocommerce' ); ?></label>
					<input type="text" class="input-text" value="" name="calc_shipping_state" id="calc_shipping_state" autocomplete="off" />
					<?php
				}
				?>
			</p>
		<?php endif; ?>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_city', true ) ) : ?>
			<p class="form-row form-row-wide" id="calc_shipping_city_field">
				<label for="calc_shipping_city"><?php esc_html_e( 'City:', 'woocommerce' ); ?></label>
				<input type="text" class="input-text" value="" name="calc_shipping_city" id="calc_shipping_city" autocomplete="off" />
			</p>
		<?php endif; ?>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_postcode', true ) ) : ?>
			<p class="form-row form-row-wide" id="calc_shipping_postcode_field">
				<label for="calc_shipping_postcode"><?php esc_html_e( 'Postcode / ZIP:', 'woocommerce' ); ?></label>
				<input type="text" class="input-text" value="" name="calc_shipping_postcode" id="calc_shipping_postcode" autocomplete="off" />
			</p>
		<?php endif; ?>

		<p class="foneona-shipping-update-wrap">
			<button type="submit" name="calc_shipping" value="1" class="button foneona-shipping-update"><?php esc_html_e( 'Update', 'woocommerce' ); ?></button>
		</p>
		<?php wp_nonce_field( 'woocommerce-shipping-calculator', 'woocommerce-shipping-calculator-nonce' ); ?>
	</section>
</form>
<?php do_action( 'woocommerce_after_shipping_calculator' ); ?>
