<?php
/**
 * Попап авторизации/регистрации для кнопки Elementor.
 *
 * Вёрстка и логика повторяют Amaressence Account Suite (табы Авторизация/Регистрация,
 * поля, чекбоксы согласий, AJAX-вход и регистрация), но в самодостаточном виде:
 * попап работает даже если Account Suite отключён.
 *
 * Триггер: ссылка кнопки Elementor вида #apq-quiz (настраивается) или элемент
 * с CSS-классом apq-quiz-trigger.
 *
 * Прохождение квиза свободное: клик по триггеру сразу ведёт на страницу квиза.
 * Попап открывается программно с финального экрана квиза («Создать аккаунт»,
 * когда гость указал email без аккаунта); после входа/регистрации квиз досылает
 * результат сам, без перезагрузки страницы.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Popup {

	const NONCE = 'apq_popup_nonce';

	/** @var APQ_Settings */
	protected $settings;

	/** @var APQ_Logger */
	protected $logger;

	public function __construct( $settings, $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_popup' ) );

		add_action( 'wp_ajax_apq_state', array( $this, 'ajax_state' ) );
		add_action( 'wp_ajax_nopriv_apq_state', array( $this, 'ajax_state' ) );

		add_action( 'wp_ajax_nopriv_apq_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_apq_login', array( $this, 'ajax_login' ) );

		add_action( 'wp_ajax_nopriv_apq_register', array( $this, 'ajax_register' ) );
		add_action( 'wp_ajax_apq_register', array( $this, 'ajax_register' ) );
	}

	protected function is_enabled() {
		return 'yes' === $this->settings->get( 'plugin_enabled', 'yes' ) && 'yes' === $this->settings->get( 'popup_enabled', 'yes' );
	}

	protected function get_quiz_url() {
		$page_id = absint( $this->settings->get( 'quiz_page_id', 0 ) );

		if ( $page_id ) {
			$url = get_permalink( $page_id );

			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	public function register_assets() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_enqueue_style( 'apq-popup', APQ_PLUGIN_URL . 'assets/css/popup.css', array(), APQ_VERSION );
		wp_enqueue_script( 'apq-popup', APQ_PLUGIN_URL . 'assets/js/popup.js', array(), APQ_VERSION, true );

		wp_localize_script(
			'apq-popup',
			'APQPopup',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
				'quizUrl'  => $this->get_quiz_url(),
				'trigger'  => $this->settings->get( 'popup_trigger', '#apq-quiz' ),
				'messages' => array(
					'loading' => __( 'Загружаем…', 'amaressence-palette-quiz' ),
					'error'   => __( 'Не удалось выполнить действие. Попробуйте ещё раз.', 'amaressence-palette-quiz' ),
				),
			)
		);
	}

	/**
	 * Разметка попапа в футере (повторяет ama-auth-card из Account Suite).
	 */
	public function render_popup() {
		if ( ! $this->is_enabled() || is_admin() ) {
			return;
		}

		$user_agreement_url = $this->settings->get( 'user_agreement_url' );
		$loyalty_url        = $this->settings->get( 'loyalty_url' );
		?>
		<div class="apq-popup-overlay" id="apq-popup" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Вход и регистрация', 'amaressence-palette-quiz' ); ?>">
			<div class="apq-popup-card" data-active-tab="login">
				<button type="button" class="apq-popup-close" data-apq-close aria-label="<?php echo esc_attr__( 'Закрыть', 'amaressence-palette-quiz' ); ?>">×</button>

				<p class="apq-popup-subtitle"><?php echo esc_html( $this->settings->get( 'popup_subtitle' ) ); ?></p>

				<div class="apq-tab-switcher" role="tablist">
					<button class="apq-tab-button is-active" type="button" data-tab="login" role="tab" aria-selected="true"><?php echo esc_html( $this->settings->get( 'popup_login_title', 'Авторизация' ) ); ?></button>
					<button class="apq-tab-button" type="button" data-tab="register" role="tab" aria-selected="false"><?php echo esc_html( $this->settings->get( 'popup_register_title', 'Регистрация' ) ); ?></button>
				</div>

				<!-- ВКЛАДКА: АВТОРИЗАЦИЯ -->
				<div class="apq-tab-panel is-active" data-panel="login">
					<form class="apq-form" data-apq-form="login" novalidate>
						<label class="apq-field">
							<input type="text" name="apq_login" placeholder="<?php echo esc_attr__( 'Эл. почта*', 'amaressence-palette-quiz' ); ?>" autocomplete="username" required>
						</label>
						<label class="apq-field apq-field--password">
							<input type="password" name="apq_password" placeholder="<?php echo esc_attr__( 'Пароль*', 'amaressence-palette-quiz' ); ?>" autocomplete="current-password" required>
							<button class="apq-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-palette-quiz' ); ?>"></button>
						</label>
						<div class="apq-ajax-response" data-form-message></div>
						<button class="apq-btn apq-btn--primary apq-btn--full" type="submit"><?php esc_html_e( 'Войти', 'amaressence-palette-quiz' ); ?></button>
						<a class="apq-link apq-link--center" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Забыли пароль?', 'amaressence-palette-quiz' ); ?></a>
					</form>
				</div>

				<!-- ВКЛАДКА: РЕГИСТРАЦИЯ -->
				<div class="apq-tab-panel" data-panel="register">
					<form class="apq-form" data-apq-form="register" novalidate>
						<label class="apq-field">
							<input type="text" name="apq_first_name" placeholder="<?php echo esc_attr__( 'Имя*', 'amaressence-palette-quiz' ); ?>" autocomplete="given-name" required>
						</label>
						<label class="apq-field">
							<input type="text" name="apq_last_name" placeholder="<?php echo esc_attr__( 'Фамилия', 'amaressence-palette-quiz' ); ?>" autocomplete="family-name">
						</label>
						<label class="apq-field">
							<input type="tel" name="apq_phone" placeholder="<?php echo esc_attr__( 'Номер телефона', 'amaressence-palette-quiz' ); ?>" autocomplete="tel">
						</label>
						<label class="apq-field">
							<input type="email" name="apq_email" placeholder="<?php echo esc_attr__( 'Эл. почта*', 'amaressence-palette-quiz' ); ?>" autocomplete="email" required>
						</label>
						<label class="apq-field apq-field--password">
							<input type="password" name="apq_reg_password" placeholder="<?php echo esc_attr__( 'Пароль*', 'amaressence-palette-quiz' ); ?>" autocomplete="new-password" required>
							<button class="apq-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-palette-quiz' ); ?>"></button>
						</label>
						<div class="apq-field-hint"><?php esc_html_e( 'Минимум 8 символов', 'amaressence-palette-quiz' ); ?></div>
						<label class="apq-field apq-field--password">
							<input type="password" name="apq_reg_password_confirm" placeholder="<?php echo esc_attr__( 'Подтвердите пароль*', 'amaressence-palette-quiz' ); ?>" autocomplete="new-password" required>
							<button class="apq-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-palette-quiz' ); ?>"></button>
						</label>

						<div class="apq-consents">
							<label class="apq-check">
								<input type="checkbox" name="apq_marketing_consent" value="1" checked>
								<span><?php echo esc_html( $this->settings->get( 'marketing_consent_text' ) ); ?></span>
							</label>
							<label class="apq-check">
								<input type="checkbox" name="apq_privacy" value="1" required>
								<span><?php esc_html_e( 'Подтверждаю своё согласие на обработку и хранение моих персональных данных в соответствии с', 'amaressence-palette-quiz' ); ?> <a class="apq-link" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $user_agreement_url ); ?>"><?php esc_html_e( 'пользовательским соглашением', 'amaressence-palette-quiz' ); ?></a></span>
							</label>
							<label class="apq-check">
								<input type="checkbox" name="apq_loyalty_consent" value="1" checked>
								<span><?php esc_html_e( 'Соглашаюсь с условиями', 'amaressence-palette-quiz' ); ?> <a class="apq-link" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $loyalty_url ); ?>"><?php esc_html_e( 'программы лояльности', 'amaressence-palette-quiz' ); ?></a></span>
							</label>
						</div>

						<div class="apq-ajax-response" data-form-message></div>
						<button class="apq-btn apq-btn--primary apq-btn--full" type="submit"><?php esc_html_e( 'Зарегистрироваться', 'amaressence-palette-quiz' ); ?></button>
						<button class="apq-link apq-link--center" type="button" data-tab-jump="login"><?php echo esc_html( $this->settings->get( 'popup_login_title', 'Авторизация' ) ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: статус авторизации (кэш-безопасная проверка по клику).
	 */
	public function ajax_state() {
		wp_send_json_success(
			array(
				'loggedIn' => is_user_logged_in(),
				'quizUrl'  => $this->get_quiz_url(),
			)
		);
	}

	/**
	 * AJAX: вход (логика как в Amaressence_Account_Suite::process_login_request).
	 */
	public function ajax_login() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$login_raw = isset( $_POST['apq_login'] ) ? sanitize_text_field( wp_unslash( $_POST['apq_login'] ) ) : '';
		$password  = isset( $_POST['apq_password'] ) ? (string) wp_unslash( $_POST['apq_password'] ) : '';

		if ( '' === $login_raw || '' === $password ) {
			wp_send_json_error( array( 'message' => __( 'Введите email и пароль.', 'amaressence-palette-quiz' ) ), 400 );
		}

		$user_login = $login_raw;

		if ( is_email( $login_raw ) ) {
			$user = get_user_by( 'email', $login_raw );

			if ( $user instanceof WP_User ) {
				$user_login = $user->user_login;
			}
		}

		$signed_on = wp_signon(
			array(
				'user_login'    => $user_login,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $signed_on ) ) {
			$codes   = $signed_on->get_error_codes();
			$message = ( in_array( 'invalid_username', $codes, true ) || in_array( 'incorrect_password', $codes, true ) || in_array( 'invalid_email', $codes, true ) )
				? __( 'Неверный email или пароль.', 'amaressence-palette-quiz' )
				: wp_strip_all_tags( $signed_on->get_error_message() );

			$this->logger->log( 'warning', 'Неудачная попытка входа через попап квиза.', array( 'login' => $login_raw ) );

			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		wp_set_current_user( $signed_on->ID, $signed_on->user_login );

		$this->logger->log( 'info', 'Вход через попап квиза.', array( 'user_id' => $signed_on->ID ) );

		wp_send_json_success(
			array(
				'message'   => __( 'Вход выполнен успешно.', 'amaressence-palette-quiz' ),
				'redirect'  => $this->get_quiz_url(),
				// Свежий nonce квиза: старый гостевой становится недействительным после входа.
				'quizNonce' => wp_create_nonce( APQ_Quiz::NONCE ),
			)
		);
	}

	/**
	 * AJAX: регистрация (логика как в Amaressence_Account_Suite::process_register_request).
	 */
	public function ajax_register() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$first_name = isset( $_POST['apq_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['apq_first_name'] ) ) : '';
		$last_name  = isset( $_POST['apq_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['apq_last_name'] ) ) : '';
		$email      = isset( $_POST['apq_email'] ) ? sanitize_email( wp_unslash( $_POST['apq_email'] ) ) : '';
		$phone      = isset( $_POST['apq_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['apq_phone'] ) ) : '';
		$password   = isset( $_POST['apq_reg_password'] ) ? (string) wp_unslash( $_POST['apq_reg_password'] ) : '';
		$confirm    = isset( $_POST['apq_reg_password_confirm'] ) ? (string) wp_unslash( $_POST['apq_reg_password_confirm'] ) : '';
		$consent    = ! empty( $_POST['apq_privacy'] );
		$marketing  = ! empty( $_POST['apq_marketing_consent'] );
		$loyalty    = ! empty( $_POST['apq_loyalty_consent'] );

		if ( '' === $first_name ) {
			wp_send_json_error( array( 'message' => __( 'Укажите имя.', 'amaressence-palette-quiz' ) ), 400 );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Укажите корректный email.', 'amaressence-palette-quiz' ) ), 400 );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Аккаунт с таким email уже существует. Используйте вкладку «Авторизация».', 'amaressence-palette-quiz' ) ), 400 );
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Пароль должен содержать минимум 8 символов.', 'amaressence-palette-quiz' ) ), 400 );
		}

		if ( $password !== $confirm ) {
			wp_send_json_error( array( 'message' => __( 'Пароли не совпадают.', 'amaressence-palette-quiz' ) ), 400 );
		}

		if ( ! $consent ) {
			wp_send_json_error( array( 'message' => __( 'Подтвердите согласие на обработку персональных данных.', 'amaressence-palette-quiz' ) ), 400 );
		}

		$username = $this->generate_username_from_email( $email );
		$display  = trim( $first_name . ' ' . $last_name );
		$role     = get_role( 'customer' ) ? 'customer' : get_option( 'default_role', 'subscriber' );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => $password,
				'user_email'   => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => '' !== $display ? $display : $first_name,
				'role'         => $role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => wp_strip_all_tags( $user_id->get_error_message() ) ), 400 );
		}

		if ( '' !== $phone ) {
			update_user_meta( $user_id, 'billing_phone', $phone );
		}

		// Фиксируем согласия (важно для маркетинга и юридической чистоты).
		update_user_meta( $user_id, '_apq_marketing_consent', $marketing ? 'yes' : 'no' );
		update_user_meta( $user_id, '_apq_loyalty_consent', $loyalty ? 'yes' : 'no' );
		update_user_meta( $user_id, '_apq_privacy_consent_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, '_apq_registered_via', 'quiz_popup' );

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			wp_send_json_error( array( 'message' => __( 'Аккаунт создан, но не удалось выполнить автоматический вход. Войдите вручную.', 'amaressence-palette-quiz' ) ), 500 );
		}

		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		if ( function_exists( 'wc_set_customer_auth_cookie' ) ) {
			wc_set_customer_auth_cookie( $user->ID );
		}

		$this->logger->log( 'info', 'Регистрация через попап квиза.', array( 'user_id' => $user->ID, 'email' => $email ) );

		wp_send_json_success(
			array(
				'message'   => __( 'Аккаунт создан.', 'amaressence-palette-quiz' ),
				'redirect'  => $this->get_quiz_url(),
				// Свежий nonce квиза: старый гостевой становится недействительным после входа.
				'quizNonce' => wp_create_nonce( APQ_Quiz::NONCE ),
			)
		);
	}

	protected function generate_username_from_email( $email ) {
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
}
