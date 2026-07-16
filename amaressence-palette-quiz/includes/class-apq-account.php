<?php
/**
 * Автоматическое создание аккаунта покупателя по email с финального экрана квиза
 * и письмо с временным паролем.
 *
 * Логика письма и создания аккаунта повторяет плагин «Foneona Woo Layout»
 * (wc_create_new_customer + wp_generate_password + HTML-письмо с временным паролем),
 * чтобы покупатель получал одинаковое по оформлению письмо независимо от того,
 * где был создан аккаунт — при оформлении заказа или после прохождения квиза.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Account {

	/** @var APQ_Settings */
	protected $settings;

	/** @var APQ_Logger */
	protected $logger;

	/** @var APQ_Points */
	protected $points;

	public function __construct( $settings, $logger, $points ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->points   = $points;
	}

	/**
	 * Создать аккаунт покупателя по email, указанному на финальном экране квиза.
	 * Если аккаунт с таким email уже есть — возвращаем его ID (ничего не создаём).
	 *
	 * @param string $email   Email гостя.
	 * @param array  $context Доп. данные (profile_id, first_name, last_name).
	 * @return array { success: bool, code: string, user_id: int, created: bool }
	 */
	public function create_customer_from_quiz( $email, $context = array() ) {
		$email = $this->points->normalize_email( $email );

		if ( ! $email ) {
			return array( 'success' => false, 'code' => 'bad_email', 'user_id' => 0, 'created' => false );
		}

		$existing = get_user_by( 'email', $email );

		if ( $existing instanceof WP_User ) {
			return array( 'success' => true, 'code' => 'exists', 'user_id' => (int) $existing->ID, 'created' => false );
		}

		$first_name = isset( $context['first_name'] ) ? sanitize_text_field( (string) $context['first_name'] ) : '';
		$last_name  = isset( $context['last_name'] ) ? sanitize_text_field( (string) $context['last_name'] ) : '';
		$display    = trim( $first_name . ' ' . $last_name );
		$username   = $this->generate_unique_username_from_email( $email );
		$password   = wp_generate_password( 20, false, false );
		$role       = get_role( 'customer' ) ? 'customer' : get_option( 'default_role', 'subscriber' );

		$args = array(
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => '' !== $display ? $display : $username,
			'role'         => $role,
		);

		if ( function_exists( 'wc_create_new_customer' ) ) {
			$user_id = wc_create_new_customer( $email, $username, $password, $args );
		} else {
			$user_id = wp_insert_user( array_merge( $args, array( 'user_login' => $username, 'user_email' => $email, 'user_pass' => $password ) ) );
		}

		if ( is_wp_error( $user_id ) ) {
			$this->logger->log( 'error', 'Не удалось автоматически создать аккаунт по email квиза.', array( 'email' => $email, 'error' => $user_id->get_error_message() ) );

			return array( 'success' => false, 'code' => 'create_failed', 'user_id' => 0, 'created' => false );
		}

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array( 'success' => false, 'code' => 'empty_user_id', 'user_id' => 0, 'created' => false );
		}

		// Фиксируем источник и результат квиза (данные, которые у нас есть).
		update_user_meta( $user_id, '_apq_registered_via', 'quiz' );
		update_user_meta( $user_id, '_apq_registered_at', current_time( 'mysql' ) );

		if ( ! empty( $context['profile_id'] ) ) {
			update_user_meta( $user_id, '_apq_quiz_profile', sanitize_key( (string) $context['profile_id'] ) );
		}

		if ( '' !== $first_name ) {
			update_user_meta( $user_id, 'billing_first_name', $first_name );
		}

		if ( '' !== $last_name ) {
			update_user_meta( $user_id, 'billing_last_name', $last_name );
		}

		// Привязываем уже существующий RRP-профиль (если гость успел «засветиться» по email).
		$this->link_rrp_profile_to_user_by_email( $email, $user_id );

		$this->send_temporary_password_email( $user_id, $password );

		$this->logger->log( 'info', 'Автоматически создан аккаунт по email квиза.', array( 'user_id' => $user_id, 'email' => $email ) );

		$password = null;

		return array( 'success' => true, 'code' => 'created', 'user_id' => $user_id, 'created' => true );
	}

	/**
	 * Отправить письмо с временным паролем (шаблон как в Foneona Woo Layout).
	 *
	 * @param int    $user_id            ID пользователя.
	 * @param string $temporary_password Временный пароль.
	 * @return bool
	 */
	public function send_temporary_password_email( $user_id, $temporary_password ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || 'yes' !== $this->settings->get( 'account_email_enabled', 'yes' ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User || empty( $user->user_email ) || '' === (string) $temporary_password ) {
			return false;
		}

		$site_name      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$my_account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();

		if ( ! $my_account_url ) {
			$my_account_url = wp_login_url();
		}

		$first_name = trim( (string) get_user_meta( $user_id, 'first_name', true ) );

		$replacements = array(
			'{site_name}'            => $site_name,
			'{customer_first_name}'  => '' !== $first_name ? $first_name : $user->display_name,
			'{customer_email}'       => $user->user_email,
			'{username}'             => $user->user_login,
			'{temporary_password}'   => (string) $temporary_password,
			'{my_account_url}'       => esc_url_raw( $my_account_url ),
			'{change_password_note}' => __( 'После входа вы сможете изменить пароль в личном кабинете.', 'amaressence-palette-quiz' ),
		);

		$subject_template = (string) $this->settings->get( 'account_email_subject', 'Ваш личный кабинет на сайте {site_name}' );
		$body_template    = (string) $this->settings->get( 'account_email_message', '' );
		$css_template     = (string) $this->settings->get( 'account_email_css', '' );

		$subject = wp_strip_all_tags( strtr( $subject_template, $replacements ) );
		$body    = $this->build_email_html( $body_template, $css_template, $replacements );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $user->user_email, $subject, $body, $headers );

		if ( $sent ) {
			$this->logger->log( 'info', 'Отправлено письмо с временным паролем (квиз).', array( 'user_id' => $user_id, 'email' => $user->user_email ) );
		} else {
			$this->logger->log( 'error', 'Аккаунт создан, но письмо с временным паролем не отправлено. Проверьте wp_mail/SMTP.', array( 'user_id' => $user_id, 'email' => $user->user_email ) );
		}

		return (bool) $sent;
	}

	/**
	 * Собрать HTML письма: тело + CSS в самодостаточном документе (как в Foneona Woo Layout).
	 *
	 * @param string $body_template Тело письма (HTML с плейсхолдерами).
	 * @param string $css           CSS письма.
	 * @param array  $replacements  Замены плейсхолдеров.
	 * @return string
	 */
	protected function build_email_html( $body_template, $css, $replacements ) {
		if ( '' === trim( wp_strip_all_tags( (string) $body_template ) ) ) {
			$body_template = APQ_Settings::defaults()['account_email_message'];
		}

		$html_replacements = array();

		foreach ( (array) $replacements as $placeholder => $value ) {
			$html_replacements[ $placeholder ] = ( '{my_account_url}' === $placeholder ) ? esc_url( $value ) : esc_html( $value );
		}

		$body = strtr( (string) $body_template, $html_replacements );
		$body = wpautop( wp_kses_post( $body ) );
		$css  = APQ_Settings::sanitize_email_css( $css );

		return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style type="text/css">' . $css . '</style></head><body><div class="foneona-email-wrap">' . $body . '</div></body></html>';
	}

	/**
	 * Сгенерировать уникальный логин из email.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	protected function generate_unique_username_from_email( $email ) {
		$email = strtolower( (string) $email );
		$parts = explode( '@', $email );
		$base  = sanitize_user( isset( $parts[0] ) ? $parts[0] : '', true );

		if ( '' === $base ) {
			$base = 'customer';
		}

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			$suffix++;
		}

		return $username;
	}

	/**
	 * Привязать существующий RRP-профиль к новому пользователю по email (как в Foneona Woo Layout).
	 *
	 * @param string $email   Email.
	 * @param int    $user_id ID пользователя.
	 * @return bool
	 */
	protected function link_rrp_profile_to_user_by_email( $email, $user_id ) {
		global $wpdb;

		$email   = $this->points->normalize_email( $email );
		$user_id = absint( $user_id );

		if ( ! $email || ! $user_id ) {
			return false;
		}

		$table  = $wpdb->prefix . 'rrp_profiles';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return false;
		}

		$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $profile ) ) {
			return false;
		}

		$profile_id      = absint( isset( $profile['id'] ) ? $profile['id'] : 0 );
		$profile_user_id = absint( isset( $profile['user_id'] ) ? $profile['user_id'] : 0 );

		if ( ! $profile_id || ( $profile_user_id && $profile_user_id !== $user_id ) ) {
			return false;
		}

		if ( ! $profile_user_id ) {
			$wpdb->update(
				$table,
				array( 'user_id' => $user_id, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $profile_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		update_user_meta( $user_id, '_rrp_profile_id', $profile_id );

		return true;
	}
}
