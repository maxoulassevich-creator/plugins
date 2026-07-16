<?php
/**
 * Checkout login form (Foneona layout)
 *
 * @package WooCommerce\Templates
 * @version 1.8.0
 */

defined( 'ABSPATH' ) || exit;

$registration_at_checkout   = WC_Checkout::instance()->is_registration_enabled();
$login_reminder_at_checkout = 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' );

if ( is_user_logged_in() ) {
	return;
}

if ( $login_reminder_at_checkout ) :
	?>
	<section class="foneona-login-card foneona-login-card--toggle" aria-label="<?php echo esc_attr__( 'Вход в личный кабинет', 'woocommerce' ); ?>">
		<div class="foneona-login-card__content">
			<div class="foneona-login-card__eyebrow"><?php echo esc_html__( 'личный кабинет', 'woocommerce' ); ?></div>
			<h2 class="foneona-login-card__title"><?php echo esc_html__( 'Уже оформляли заказ?', 'woocommerce' ); ?></h2>
			<p class="foneona-login-card__text">
				<?php echo esc_html__( 'Войдите в аккаунт, чтобы подтянуть данные покупателя и быстрее завершить оформление.', 'woocommerce' ); ?>
			</p>
		</div>
		<a href="#" class="foneona-login-card__button showlogin">
			<?php echo esc_html__( 'Войти', 'woocommerce' ); ?>
		</a>
	</section>
	<?php
endif;

if ( $registration_at_checkout || $login_reminder_at_checkout ) :
	$show_form = isset( $_POST['login'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	?>
	<div class="foneona-login-form-wrap">
		<?php
		woocommerce_login_form(
			array(
				'message'  => esc_html__( 'Введите email или логин и пароль от личного кабинета.', 'woocommerce' ),
				'redirect' => wc_get_checkout_url(),
				'hidden'   => ! $show_form,
			)
		);
		?>
	</div>
	<?php
endif;
