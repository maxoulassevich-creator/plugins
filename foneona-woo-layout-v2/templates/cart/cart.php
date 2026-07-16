<?php
/**
 * Cart Page (custom layout)
 *
 * @package FoneonaCartLayout/Templates
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

?>
<div class="foneona-cart-page">
	<div class="foneona-cart-grid">
		<div class="foneona-cart-left">

			<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
				<?php do_action( 'woocommerce_before_cart_table' ); ?>

				<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents foneona-cart-table" cellspacing="0">
					<thead>
						<tr>
							<th class="product-name" colspan="2"><?php echo esc_html( 'ТОВАР' ); ?></th>
							<th class="product-price"><?php echo esc_html( 'ЦЕНА' ); ?></th>
							<th class="product-quantity"><?php echo esc_html( 'КОЛИЧЕСТВО' ); ?></th>
							<th class="product-subtotal"><?php echo esc_html( 'ПОДЫТОГ' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php do_action( 'woocommerce_before_cart_contents' ); ?>

						<?php
						foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
							$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
							$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

							if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
								continue;
							}

							if ( ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
								continue;
							}

							$product_permalink = apply_filters(
								'woocommerce_cart_item_permalink',
								$_product->is_visible() ? $_product->get_permalink( $cart_item ) : '',
								$cart_item,
								$cart_item_key
							);
							?>
							<tr class="woocommerce-cart-form__cart-item cart_item">

								<td class="product-thumbnail">
									<?php
									$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );

									if ( ! $product_permalink ) {
										echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									} else {
										printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</td>

								<td class="product-name" data-title="<?php echo esc_attr( 'Товар' ); ?>">
									<?php
									if ( ! $product_permalink ) {
										echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
									} else {
										echo wp_kses_post(
											apply_filters(
												'woocommerce_cart_item_name',
												sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ),
												$cart_item,
												$cart_item_key
											)
										);
									}

									do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

									// Meta data.
									echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

									// Backorder notification.
									if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
										echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
									}
									?>

									<div class="foneona-remove-wrap">
										<?php
										echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											'woocommerce_cart_item_remove_link',
											sprintf(
												'<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">%s</a>',
												esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
												esc_attr__( 'Remove this item', 'woocommerce' ),
												esc_attr( $product_id ),
												esc_attr( $_product->get_sku() ),
												esc_html( 'УДАЛИТЬ' )
											),
											$cart_item_key
										);
										?>
									</div>
								</td>

								<td class="product-price" data-title="<?php echo esc_attr( 'Цена' ); ?>">
									<span class="foneona-mobile-label"><?php echo esc_html( 'Цена:' ); ?></span>
									<span class="foneona-mobile-value">
										<?php
										echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</span>
								</td>

								<td class="product-quantity" data-title="<?php echo esc_attr( 'Количество' ); ?>">
									<span class="foneona-mobile-label"><?php echo esc_html( 'Количество:' ); ?></span>
									<span class="foneona-mobile-value">
										<?php
										if ( $_product->is_sold_individually() ) {
											$product_quantity = '1';
											echo '<span class="foneona-qty-static">1</span>';
											echo '<input type="hidden" name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="1" />';
										} else {
											$product_quantity = woocommerce_quantity_input(
												array(
													'input_name'   => "cart[{$cart_item_key}][qty]",
													'input_value'  => $cart_item['quantity'],
													'max_value'    => $_product->get_max_purchase_quantity(),
													'min_value'    => max( 1, (int) $_product->get_min_purchase_quantity() ),
													'product_name' => $_product->get_name(),
												),
												$_product,
												false
											);

											// Inject +/- buttons inside default quantity wrapper.
											$product_quantity = preg_replace(
												'/(<div[^>]*class="quantity[^"]*"[^>]*>)/',
												'$1<button type="button" class="foneona-qty-btn foneona-qty-minus" aria-label="Minus">−</button>',
												$product_quantity,
												1
											);
											$product_quantity = str_replace(
												'</div>',
												'<button type="button" class="foneona-qty-btn foneona-qty-plus" aria-label="Plus">+</button></div>',
												$product_quantity
											);

											echo $product_quantity; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
										?>
									</span>
								</td>

								<td class="product-subtotal" data-title="<?php echo esc_attr( 'Подытог' ); ?>">
									<span class="foneona-mobile-label"><?php echo esc_html( 'Подытог:' ); ?></span>
									<span class="foneona-mobile-value">
										<?php
										echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</span>
								</td>
							</tr>
							<?php
						}
						?>

						<?php do_action( 'woocommerce_cart_contents' ); ?>

						<tr class="foneona-actions-row">
							<td colspan="5" class="actions">
								<?php if ( wc_coupons_enabled() ) : ?>
									<div class="coupon" style="display:none;">
										<label for="coupon_code"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
										<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
										<button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
										<?php do_action( 'woocommerce_cart_coupon' ); ?>
									</div>
								<?php endif; ?>

								<button type="submit" class="button" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

								<?php do_action( 'woocommerce_cart_actions' ); ?>

								<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
							</td>
						</tr>

						<?php do_action( 'woocommerce_after_cart_contents' ); ?>
					</tbody>
				</table>

				<?php do_action( 'woocommerce_after_cart_table' ); ?>
			</form>
		</div>

		<aside class="foneona-cart-right">
			<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
			<?php woocommerce_cart_totals(); ?>
			<?php do_action( 'woocommerce_after_cart_collaterals' ); ?>
		</aside>
	</div>
</div>
<?php
do_action( 'woocommerce_after_cart' );
