<?php
/**
 * Review order (Foneona layout)
 *
 * Must output .woocommerce-checkout-review-order-table so Woo checkout AJAX can refresh fragments.
 *
 * @package WooCommerce\Templates
 */

defined( 'ABSPATH' ) || exit;

$discount_total = Foneona_Woo_Layout::get_checkout_discount_total();
$points_context = Foneona_Woo_Layout::get_rrp_points_checkout_context();
$shipping_total = WC()->cart->needs_shipping() ? WC()->cart->get_cart_shipping_total() : wc_price( 0 );
?>

<div class="woocommerce-checkout-review-order-table foneona-checkout-review-order">

	<div class="foneona-order-items">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				$product_name  = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
				$thumbnail     = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
				$line_subtotal = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
				?>
				<div class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'foneona-order-item cart_item', $cart_item, $cart_item_key ) ); ?>">
					<div class="foneona-order-item__thumb">
						<?php echo wp_kses_post( $thumbnail ); ?>
						<span class="foneona-order-item__qty"><?php echo esc_html( 'x' . (int) $cart_item['quantity'] ); ?></span>
					</div>

					<div class="foneona-order-item__name">
						<?php echo wp_kses_post( $product_name ); ?>
						<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<div class="foneona-order-item__price">
						<?php echo wp_kses_post( $line_subtotal ); ?>
					</div>
				</div>
				<?php
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</div>

	<?php if ( wc_coupons_enabled() ) : ?>
		<div class="foneona-checkout-coupon">
			<div class="foneona-checkout-coupon__title"><?php echo esc_html__( 'Есть купон?', 'woocommerce' ); ?></div>
			<div class="foneona-checkout-coupon__row">
				<input type="text" class="foneona-checkout-coupon__input" placeholder="<?php echo esc_attr__( 'Код купона', 'woocommerce' ); ?>" />
				<button type="button" class="foneona-checkout-coupon__btn"><?php echo esc_html__( 'Применить', 'woocommerce' ); ?></button>
			</div>
		</div>
	<?php endif; ?>

	<div class="foneona-order-totals">
		<div class="foneona-order-total-row">
			<span class="label"><?php echo esc_html__( 'Доставка', 'woocommerce' ); ?></span>
			<span class="value"><?php echo wp_kses_post( $shipping_total ); ?></span>
		</div>

		<div class="foneona-order-total-row">
			<span class="label"><?php echo esc_html__( 'Скидка', 'woocommerce' ); ?></span>
			<span class="value"><?php echo wp_kses_post( wc_price( $discount_total ) ); ?></span>
		</div>
		<?php if ( $points_context['show_panel'] ) : ?>
			<div class="foneona-points-panel<?php echo $points_context['applied'] > 0 ? ' is-active' : ''; ?><?php echo ! $points_context['can_use'] ? ' is-disabled' : ''; ?>">
				<div class="foneona-points-panel__header">
					<div>
						<div class="foneona-points-panel__eyebrow">Баллы</div>
						<div class="foneona-points-panel__title">Оплатите часть заказа накопленными баллами</div>
					</div>
					<div class="foneona-points-panel__balance">
						<span class="foneona-points-panel__balance-label">Баланс</span>
						<strong><?php echo esc_html( $points_context['balance_formatted'] ); ?></strong>
					</div>
				</div>

				<?php if ( ! $points_context['logged_in'] ) : ?>
					<div class="foneona-points-panel__note">Войдите в аккаунт, чтобы использовать накопленные баллы при оформлении заказа.</div>
				<?php elseif ( ! empty( $points_context['coupon_conflict'] ) ) : ?>
					<div class="foneona-points-panel__note"><?php echo esc_html( ! empty( $points_context['coupon_conflict_message'] ) ? $points_context['coupon_conflict_message'] : 'Баллы нельзя использовать одновременно с промокодом.' ); ?></div>
				<?php else : ?>
					<div class="foneona-points-panel__controls">
						<label class="foneona-points-panel__label" for="foneona_points_amount">Сколько баллов использовать</label>
						<div class="foneona-points-panel__input-wrap">
							<input
								type="number"
								name="foneona_points_amount"
								id="foneona_points_amount"
								class="foneona-points-panel__input"
								min="0"
								step="<?php echo esc_attr( 0 === (int) $points_context['price_decimals'] ? '1' : '0.' . str_repeat( '0', max( 0, (int) $points_context['price_decimals'] - 1 ) ) . '1' ); ?>"
								max="<?php echo esc_attr( $points_context['max_redeemable_raw'] ); ?>"
								inputmode="decimal"
								value="<?php echo esc_attr( $points_context['applied_raw'] ); ?>"
								data-applied-value="<?php echo esc_attr( $points_context['applied_raw'] ); ?>"
								<?php disabled( ! $points_context['can_use'] ); ?>
							/>
							<button type="button" class="foneona-points-panel__apply" <?php disabled( ! $points_context['can_use'] ); ?>>Применить</button>
						</div>

						<div class="foneona-points-panel__hint">
							<?php if ( $points_context['can_use'] ) : ?>
								<span>Доступно к списанию сейчас: <strong><?php echo esc_html( $points_context['max_redeemable_formatted'] ); ?></strong></span>
								<?php if ( $points_context['applied'] > 0 ) : ?>
									<button type="button" class="foneona-points-panel__clear">Сбросить</button>
								<?php endif; ?>
							<?php else : ?>
								<span>Баллы пока недоступны для списания по текущему заказу.</span>
							<?php endif; ?>
						</div>

						<?php if ( $points_context['applied'] > 0 ) : ?>
							<div class="foneona-points-panel__applied">Будет списано: <strong><?php echo esc_html( $points_context['applied_formatted'] ); ?></strong></div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<?php
			$fee_amount = isset( $fee->amount ) ? (float) $fee->amount : 0.0;
			if ( $fee_amount <= 0 ) {
				continue;
			}
			?>
			<div class="foneona-order-total-row">
				<span class="label"><?php echo esc_html( $fee->name ); ?></span>
				<span class="value"><?php wc_cart_totals_fee_html( $fee ); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<div class="foneona-order-total-row">
						<span class="label"><?php echo esc_html( $tax->label ); ?></span>
						<span class="value"><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="foneona-order-total-row">
					<span class="label"><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
					<span class="value"><?php wc_cart_totals_taxes_total_html(); ?></span>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<div class="foneona-order-total-row is-strong">
			<span class="label"><?php echo esc_html__( 'Итого', 'woocommerce' ); ?></span>
			<span class="value foneona-order-total-value"><?php wc_cart_totals_order_total_html(); ?></span>
		</div>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</div>

</div>
