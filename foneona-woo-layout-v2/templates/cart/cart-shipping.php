<?php
/**
 * Shipping Methods Display (custom markup for cart totals / checkout).
 *
 * This template is loaded by wc_cart_totals_shipping_html().
 * On checkout it renders delivery methods as visual cards while preserving
 * standard WooCommerce shipping_method[] inputs and plugin hooks.
 *
 * @package FoneonaCartLayout/Templates
 */

defined( 'ABSPATH' ) || exit;

$formatted_destination    = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
$has_calculated_shipping  = ! empty( $has_calculated_shipping );
$show_shipping_calculator = ! empty( $show_shipping_calculator );
$calculator_text          = $formatted_destination ? 'ИЗМЕНИТЬ АДРЕС' : 'РАССЧИТАТЬ СТОИМОСТЬ ДОСТАВКИ';
$use_checkout_cards       = class_exists( 'Foneona_Woo_Layout' ) && Foneona_Woo_Layout::is_checkout_shipping_render_context();

if ( $use_checkout_cards && ! empty( $available_methods ) && is_array( $available_methods ) ) {
	$available_methods = Foneona_Woo_Layout::sort_shipping_rates_for_cards( $available_methods );
}
?>
<div class="foneona-shipping-package" data-index="<?php echo esc_attr( $index ); ?>">
	<?php if ( ! empty( $available_methods ) && is_array( $available_methods ) ) : ?>

		<?php if ( $use_checkout_cards ) : ?>
			<div class="foneona-shipping-cards" role="radiogroup" aria-label="Способ доставки">
				<?php foreach ( $available_methods as $method ) : ?>
					<?php
					$method_id    = isset( $method->id ) ? (string) $method->id : ( method_exists( $method, 'get_id' ) ? (string) $method->get_id() : '' );
					$input_id     = sprintf( 'shipping_method_%1$d_%2$s', $index, sanitize_title( $method_id ) );
					$card_data    = Foneona_Woo_Layout::get_shipping_card_data( $method );
					$is_selected  = ( $method_id === $chosen_method ) || ( 1 === count( $available_methods ) && empty( $chosen_method ) );
					$card_classes = array(
						'foneona-shipping-card',
						'foneona-shipping-card--' . sanitize_html_class( $card_data['carrier'] ),
						'foneona-shipping-card--' . sanitize_html_class( $card_data['type'] ),
					);

					if ( $is_selected ) {
						$card_classes[] = 'is-selected';
					}

					ob_start();
					do_action( 'woocommerce_after_shipping_rate', $method, $index );
					$rate_actions = ob_get_clean();

					if ( false === strpos( $rate_actions, 'wc-russian-post-choose-delivery-point' ) ) {
						$rate_actions .= Foneona_Woo_Layout::get_russian_post_pickup_button_html( $method );
					}
					?>
					<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" data-foneona-shipping-card="1" data-carrier="<?php echo esc_attr( $card_data['carrier'] ); ?>" data-service="<?php echo esc_attr( $card_data['type'] ); ?>">
						<?php if ( 1 < count( $available_methods ) ) : ?>
							<input type="radio" name="shipping_method[<?php echo esc_attr( $index ); ?>]" data-index="<?php echo esc_attr( $index ); ?>" id="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $method_id ); ?>" class="shipping_method foneona-shipping-card__input" <?php checked( $method_id, $chosen_method ); ?> />
						<?php else : ?>
							<input type="hidden" name="shipping_method[<?php echo esc_attr( $index ); ?>]" data-index="<?php echo esc_attr( $index ); ?>" id="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $method_id ); ?>" class="shipping_method foneona-shipping-card__input" />
						<?php endif; ?>

						<div class="foneona-shipping-card__main" data-foneona-shipping-card-select="<?php echo esc_attr( $input_id ); ?>">
							<div class="foneona-shipping-card__head">
								<span class="foneona-shipping-card__radio" aria-hidden="true"></span>
								<span class="foneona-shipping-card__carrier"><?php echo esc_html( $card_data['carrier_title'] ); ?></span>
								<span class="foneona-shipping-card__price"><?php echo wp_kses_post( $card_data['price_html'] ); ?></span>
							</div>
							<label class="foneona-shipping-card__title" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $card_data['service_title'] ); ?></label>
							<?php if ( ! empty( $card_data['description'] ) ) : ?>
								<div class="foneona-shipping-card__desc"><?php echo esc_html( $card_data['description'] ); ?></div>
							<?php endif; ?>
						</div>

						<?php if ( '' !== trim( wp_strip_all_tags( $rate_actions ) ) ) : ?>
							<div class="foneona-shipping-card__actions">
								<?php echo $rate_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<ul class="woocommerce-shipping-methods foneona-shipping-methods" id="shipping_method">
				<?php foreach ( $available_methods as $method ) : ?>
					<li class="foneona-shipping-method">
						<?php
						$method_label = wc_cart_totals_shipping_method_label( $method );
						$method_id    = isset( $method->id ) ? (string) $method->id : ( method_exists( $method, 'get_id' ) ? (string) $method->get_id() : '' );
						if ( 1 < count( $available_methods ) ) {
							printf(
								'<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />',
								$index,
								esc_attr( sanitize_title( $method_id ) ),
								esc_attr( $method_id ),
								checked( $method_id, $chosen_method, false )
							);
						} else {
							printf(
								'<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" />',
								$index,
								esc_attr( sanitize_title( $method_id ) ),
								esc_attr( $method_id )
							);
						}
						?>

						<div class="foneona-shipping-method__content">
							<?php if ( '' !== trim( wp_strip_all_tags( $method_label ) ) ) : ?>
								<?php printf( '<label for="shipping_method_%1$s_%2$s">%3$s</label>', $index, esc_attr( sanitize_title( $method_id ) ), $method_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
							<?php do_action( 'woocommerce_after_shipping_rate', $method, $index ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( is_cart() ) : ?>
			<?php if ( $formatted_destination ) : ?>
				<p class="foneona-shipping-destination">
					<?php
					printf(
						'%s <strong>%s</strong>.',
						esc_html( 'Доставка до ближайшего пункта рядом с' ),
						esc_html( $formatted_destination )
					);
					?>
				</p>
			<?php else : ?>
				<p class="foneona-shipping-hint">
					<?php echo esc_html( 'Введите свой адрес для просмотра параметров доставки.' ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>

	<?php elseif ( ! $has_calculated_shipping || ! $formatted_destination ) : ?>
		<p class="foneona-shipping-hint">
			<?php echo esc_html( 'Введите свой адрес для просмотра параметров доставки.' ); ?>
		</p>
	<?php elseif ( ! is_cart() ) : ?>
		<p class="foneona-shipping-hint">
			<?php echo esc_html( 'Не удалось показать варианты доставки для указанного адреса.' ); ?>
		</p>
	<?php else : ?>
		<p class="foneona-shipping-hint">
			<?php
			printf(
				'%s <strong>%s</strong>.',
				esc_html( 'Не найдено вариантов доставки для' ),
				esc_html( $formatted_destination )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( $show_shipping_calculator ) : ?>
		<?php woocommerce_shipping_calculator( $calculator_text ); ?>
	<?php endif; ?>
</div>
