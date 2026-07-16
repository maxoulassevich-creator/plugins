<?php
/**
 * Thankyou page (Foneona / Amarèssence layout)
 *
 * @package WooCommerce\Templates
 * @version 1.8.0
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

$shop_url       = wc_get_page_permalink( 'shop' );
$my_account_url = wc_get_page_permalink( 'myaccount' );

if ( ! $shop_url ) {
	$shop_url = home_url( '/' );
}
if ( ! $my_account_url ) {
	$my_account_url = home_url( '/' );
}
?>

<div class="woocommerce-order foneona-thankyou-page">
	<?php if ( $order ) : ?>
		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>
			<section class="foneona-thankyou-hero foneona-thankyou-hero--failed" aria-label="<?php echo esc_attr__( 'Заказ не оплачен', 'woocommerce' ); ?>">
				<div class="foneona-thankyou-hero__mark" aria-hidden="true">!</div>
				<div class="foneona-thankyou-hero__content">
					<div class="foneona-thankyou-hero__eyebrow"><?php echo esc_html__( 'статус заказа', 'woocommerce' ); ?></div>
					<h1 class="foneona-thankyou-hero__title"><?php echo esc_html__( 'Оплату не удалось завершить', 'woocommerce' ); ?></h1>
					<p class="foneona-thankyou-hero__text">
						<?php echo esc_html__( 'Банк или платёжный сервис отклонил операцию. Попробуйте оплатить заказ ещё раз или вернитесь в каталог.', 'woocommerce' ); ?>
					</p>
				</div>
				<div class="foneona-thankyou-hero__actions">
					<a class="foneona-thankyou-btn foneona-thankyou-btn--primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">
						<?php echo esc_html__( 'Повторить оплату', 'woocommerce' ); ?>
					</a>
					<a class="foneona-thankyou-btn foneona-thankyou-btn--ghost" href="<?php echo esc_url( $shop_url ); ?>">
						<?php echo esc_html__( 'Вернуться в каталог', 'woocommerce' ); ?>
					</a>
				</div>
			</section>
		<?php else : ?>
			<section class="foneona-thankyou-hero" aria-label="<?php echo esc_attr__( 'Заказ оформлен', 'woocommerce' ); ?>">
				<div class="foneona-thankyou-hero__mark" aria-hidden="true">✓</div>
				<div class="foneona-thankyou-hero__content">
					<div class="foneona-thankyou-hero__eyebrow"><?php echo esc_html__( 'заказ оформлен', 'woocommerce' ); ?></div>
					<h1 class="foneona-thankyou-hero__title"><?php echo esc_html__( 'Спасибо за заказ!', 'woocommerce' ); ?></h1>
					<p class="foneona-thankyou-hero__text">
						<?php
						echo wp_kses_post(
							apply_filters(
								'woocommerce_thankyou_order_received_text',
								__( 'Мы получили ваш заказ и уже готовим его к обработке. Детали заказа отправлены на email, указанный при оформлении.', 'woocommerce' ),
								$order
							)
						);
						?>
					</p>
				</div>
				<div class="foneona-thankyou-hero__actions">
					<a class="foneona-thankyou-btn foneona-thankyou-btn--primary" href="<?php echo esc_url( $shop_url ); ?>">
						<?php echo esc_html__( 'Продолжить покупки', 'woocommerce' ); ?>
					</a>
					<?php if ( is_user_logged_in() ) : ?>
						<a class="foneona-thankyou-btn foneona-thankyou-btn--ghost" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
							<?php echo esc_html__( 'Открыть заказ', 'woocommerce' ); ?>
						</a>
					<?php else : ?>
						<a class="foneona-thankyou-btn foneona-thankyou-btn--ghost" href="<?php echo esc_url( $my_account_url ); ?>">
							<?php echo esc_html__( 'Личный кабинет', 'woocommerce' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="foneona-thankyou-overview" aria-label="<?php echo esc_attr__( 'Краткая информация о заказе', 'woocommerce' ); ?>">
			<div class="foneona-thankyou-overview__item">
				<span><?php echo esc_html__( 'номер заказа', 'woocommerce' ); ?></span>
				<strong><?php echo esc_html( $order->get_order_number() ); ?></strong>
			</div>
			<div class="foneona-thankyou-overview__item">
				<span><?php echo esc_html__( 'дата', 'woocommerce' ); ?></span>
				<strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong>
			</div>
			<?php if ( $order->get_billing_email() ) : ?>
				<div class="foneona-thankyou-overview__item">
					<span><?php echo esc_html__( 'email', 'woocommerce' ); ?></span>
					<strong><?php echo esc_html( $order->get_billing_email() ); ?></strong>
				</div>
			<?php endif; ?>
			<div class="foneona-thankyou-overview__item">
				<span><?php echo esc_html__( 'сумма', 'woocommerce' ); ?></span>
				<strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
			</div>
			<?php if ( $order->get_payment_method_title() ) : ?>
				<div class="foneona-thankyou-overview__item">
					<span><?php echo esc_html__( 'оплата', 'woocommerce' ); ?></span>
					<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
				</div>
			<?php endif; ?>
		</section>

		<div class="foneona-thankyou-grid">
			<section class="foneona-thankyou-card foneona-thankyou-card--items" aria-label="<?php echo esc_attr__( 'Состав заказа', 'woocommerce' ); ?>">
				<div class="foneona-thankyou-card__head">
					<h2><?php echo esc_html__( 'Состав заказа', 'woocommerce' ); ?></h2>
					<span><?php echo esc_html( sprintf( _n( '%s товар', '%s товара', $order->get_item_count(), 'woocommerce' ), $order->get_item_count() ) ); ?></span>
				</div>

				<div class="foneona-thankyou-items">
					<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
						<?php
						$product = $item->get_product();
						$image   = $product ? $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'foneona-thankyou-item__image' ) ) : wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'foneona-thankyou-item__image' ) );
						?>
						<article class="foneona-thankyou-item">
							<div class="foneona-thankyou-item__thumb">
								<?php echo wp_kses_post( $image ); ?>
								<span class="foneona-thankyou-item__qty"><?php echo esc_html( $item->get_quantity() ); ?></span>
							</div>
							<div class="foneona-thankyou-item__body">
								<h3><?php echo esc_html( $item->get_name() ); ?></h3>
								<?php if ( $item->get_meta_data() ) : ?>
									<div class="foneona-thankyou-item__meta">
										<?php wc_display_item_meta( $item ); ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="foneona-thankyou-item__total">
								<?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>

			<aside class="foneona-thankyou-side" aria-label="<?php echo esc_attr__( 'Доставка и оплата', 'woocommerce' ); ?>">
				<section class="foneona-thankyou-card">
					<h2><?php echo esc_html__( 'Доставка', 'woocommerce' ); ?></h2>
					<div class="foneona-thankyou-info-list">
						<?php if ( $order->get_shipping_method() ) : ?>
							<div>
								<span><?php echo esc_html__( 'способ', 'woocommerce' ); ?></span>
								<strong><?php echo esc_html( $order->get_shipping_method() ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( $order->get_formatted_shipping_address() ) : ?>
							<div>
								<span><?php echo esc_html__( 'адрес', 'woocommerce' ); ?></span>
								<strong><?php echo wp_kses_post( $order->get_formatted_shipping_address() ); ?></strong>
							</div>
						<?php elseif ( $order->get_formatted_billing_address() ) : ?>
							<div>
								<span><?php echo esc_html__( 'адрес', 'woocommerce' ); ?></span>
								<strong><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( $order->get_billing_phone() ) : ?>
							<div>
								<span><?php echo esc_html__( 'телефон', 'woocommerce' ); ?></span>
								<strong><?php echo esc_html( $order->get_billing_phone() ); ?></strong>
							</div>
						<?php endif; ?>
					</div>
				</section>

				<section class="foneona-thankyou-card">
					<h2><?php echo esc_html__( 'Что дальше', 'woocommerce' ); ?></h2>
					<ul class="foneona-thankyou-next">
						<li><?php echo esc_html__( 'Мы проверим заказ и подготовим его к передаче в доставку.', 'woocommerce' ); ?></li>
						<li><?php echo esc_html__( 'Сообщения по заказу будут приходить на email и телефон, указанные при оформлении.', 'woocommerce' ); ?></li>
						<li><?php echo esc_html__( 'Если потребуется уточнение, менеджер свяжется с вами.', 'woocommerce' ); ?></li>
					</ul>
				</section>
			</aside>
		</div>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

	<?php else : ?>
		<section class="foneona-thankyou-hero foneona-thankyou-hero--empty" aria-label="<?php echo esc_attr__( 'Заказ не найден', 'woocommerce' ); ?>">
			<div class="foneona-thankyou-hero__mark" aria-hidden="true">?</div>
			<div class="foneona-thankyou-hero__content">
				<div class="foneona-thankyou-hero__eyebrow"><?php echo esc_html__( 'order received', 'woocommerce' ); ?></div>
				<h1 class="foneona-thankyou-hero__title"><?php echo esc_html__( 'Данные заказа недоступны', 'woocommerce' ); ?></h1>
				<p class="foneona-thankyou-hero__text">
					<?php echo esc_html__( 'Не удалось получить информацию о заказе. Проверьте ссылку из письма или перейдите в личный кабинет.', 'woocommerce' ); ?>
				</p>
			</div>
			<div class="foneona-thankyou-hero__actions">
				<a class="foneona-thankyou-btn foneona-thankyou-btn--primary" href="<?php echo esc_url( $my_account_url ); ?>">
					<?php echo esc_html__( 'Личный кабинет', 'woocommerce' ); ?>
				</a>
				<a class="foneona-thankyou-btn foneona-thankyou-btn--ghost" href="<?php echo esc_url( $shop_url ); ?>">
					<?php echo esc_html__( 'В каталог', 'woocommerce' ); ?>
				</a>
			</div>
		</section>
	<?php endif; ?>
</div>
