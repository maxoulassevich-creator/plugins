<?php
/**
 * Квиз: шорткод [amaressence_quiz], свободное прохождение для гостей,
 * приём результата по AJAX с email на финальном экране.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Quiz {

	const SHORTCODE = 'amaressence_quiz';
	const NONCE     = 'apq_quiz_nonce';

	/** @var APQ_Settings */
	protected $settings;

	/** @var APQ_Logger */
	protected $logger;

	/** @var APQ_Results */
	protected $results;

	/** @var APQ_Points */
	protected $points;

	/** @var APQ_Account */
	protected $account;

	public function __construct( $settings, $logger, $results, $points, $account = null ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->results  = $results;
		$this->points   = $points;
		$this->account  = $account;

		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Квиз открыт для всех: гости проходят свободно, email спрашиваем на финальном экране.
		add_action( 'wp_ajax_apq_submit_quiz', array( $this, 'ajax_submit_quiz' ) );
		add_action( 'wp_ajax_nopriv_apq_submit_quiz', array( $this, 'ajax_submit_quiz' ) );

		// Кэш-безопасное состояние: свежий nonce + признак «уже проходил» для текущего посетителя.
		add_action( 'wp_ajax_apq_quiz_state', array( $this, 'ajax_quiz_state' ) );
		add_action( 'wp_ajax_nopriv_apq_quiz_state', array( $this, 'ajax_quiz_state' ) );
	}

	public function register_assets() {
		wp_register_style( 'apq-fonts', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@400;500&display=swap', array(), null );
		wp_register_style( 'apq-quiz', APQ_PLUGIN_URL . 'assets/css/quiz.css', array( 'apq-fonts' ), APQ_VERSION );
		wp_register_script( 'apq-quiz', APQ_PLUGIN_URL . 'assets/js/quiz.js', array(), APQ_VERSION, true );
	}

	protected function replace_points( $text, $points ) {
		$points_label = ( floor( $points ) == $points ) ? number_format_i18n( $points, 0 ) : number_format_i18n( $points, 2 );

		return str_replace( '{points}', $points_label, (string) $text );
	}

	/**
	 * Шорткод квиза.
	 */
	public function render_shortcode( $atts = array() ) {
		if ( 'yes' !== $this->settings->get( 'plugin_enabled', 'yes' ) ) {
			return '';
		}

		wp_enqueue_style( 'apq-fonts' );
		wp_enqueue_style( 'apq-quiz' );
		wp_enqueue_script( 'apq-quiz' );

		$is_logged_in = is_user_logged_in();
		$email        = $is_logged_in ? $this->points->normalize_email( wp_get_current_user()->user_email ) : '';
		$points       = (float) $this->settings->get( 'points_amount', '400' );
		$mode         = $this->settings->get( 'points_award_mode', 'after_first_order' );
		$completed    = ( $is_logged_in && 'yes' === $this->settings->get( 'one_attempt_per_email', 'yes' ) ) ? $this->results->get_by_email( $email ) : null;
		$quiz_data    = $this->settings->get_quiz_data();
		$show_gift    = $points > 0 && 'none' !== $mode && $this->points->rrp_available();

		$success_desc = $show_gift
			? ( ( 'immediate' === $mode ) ? $this->settings->get( 'success_desc_awarded' ) : $this->settings->get( 'success_desc' ) )
			: __( 'Твой профиль сохранён.', 'amaressence-palette-quiz' );

		$completed_answers = array();

		if ( $completed && ! empty( $completed['answers'] ) ) {
			$decoded_completed_answers = json_decode( (string) $completed['answers'], true );

			if ( is_array( $decoded_completed_answers ) ) {
				$completed_answers = $decoded_completed_answers;
			}
		}

		wp_localize_script(
			'apq-quiz',
			'APQ',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE ),
				'isLoggedIn'   => $is_logged_in,
				'accountCreatedNote' => $this->settings->get( 'account_created_note' ),
				'values'       => $quiz_data['values'],
				'colors'       => $quiz_data['colors'],
				'profiles'     => $quiz_data['profiles'],
				'resultRules'  => $quiz_data['result_rules'],
				'familyLabels' => APQ_Settings::color_family_labels(),
				'completed'    => $completed ? array( 'profile' => $completed['profile_id'], 'answers' => $completed_answers ) : null,
				'i18n'         => array(
					'next'         => __( 'Далее →', 'amaressence-palette-quiz' ),
					'seeResult'    => __( 'Узнать результат', 'amaressence-palette-quiz' ),
					'sending'      => __( 'Сохраняем…', 'amaressence-palette-quiz' ),
					'error'        => __( 'Не удалось сохранить результат. Попробуйте ещё раз.', 'amaressence-palette-quiz' ),
					'emailEmpty'   => __( 'Укажи email, чтобы получить подарок.', 'amaressence-palette-quiz' ),
					'emailInvalid' => __( 'Похоже, в email опечатка. Проверь адрес.', 'amaressence-palette-quiz' ),
				),
			)
		);

		$logo_url        = $this->settings->get( 'logo_url', '' );
		$questions_count = count( $quiz_data['values'] );

		ob_start();
		?>
		<div class="apq-app" id="apq-app"
			data-completed="<?php echo $completed ? '1' : '0'; ?>">

			<!-- INTRO -->
			<div id="apq-intro" class="apq-screen<?php echo $completed ? '' : ' active'; ?>">
				<div class="apq-intro-logo-wrap">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="amarèssence" class="apq-intro-logo-img">
					<?php else : ?>
						<div class="apq-intro-logo-fallback">
							<svg width="36" height="40" viewBox="0 0 36 40" xmlns="http://www.w3.org/2000/svg" fill="#6B1A38"><path d="M18 37 C17.5 31 8 24 5 16 C2 8 7 2 13 3 C11 8 12 14 16 19 C16.5 16 16.5 12 18 9 C19.5 12 19.5 16 20 19 C24 14 25 8 23 3 C29 2 34 8 31 16 C28 24 18.5 31 18 37Z"/><rect x="16.5" y="37" width="3" height="3" rx="1.5"/></svg>
							<span>amarèssence</span>
						</div>
					<?php endif; ?>
				</div>
				<h1 class="apq-intro-headline"><?php echo wp_kses_post( $this->settings->get( 'intro_headline' ) ); ?></h1>
				<p class="apq-intro-sub"><?php echo esc_html( $this->settings->get( 'intro_sub' ) ); ?></p>
				<div class="apq-intro-pills">
					<span><?php echo esc_html( sprintf( _n( '%d вопрос', '%d вопросов', $questions_count, 'amaressence-palette-quiz' ), $questions_count ) ); ?></span>
					<span><?php echo esc_html( $this->settings->get( 'intro_time_text' ) ); ?></span>
				</div>
				<?php if ( $show_gift ) : ?>
					<div class="apq-reward-card">
						<div class="apq-reward-card-label"><?php echo esc_html( $this->settings->get( 'reward_card_label' ) ); ?></div>
						<div class="apq-reward-card-text"><?php echo esc_html( $this->replace_points( $this->settings->get( 'reward_card_text' ), $points ) ); ?></div>
					</div>
				<?php endif; ?>
				<button type="button" class="apq-btn apq-btn-brand" data-apq-start><?php esc_html_e( 'Начать', 'amaressence-palette-quiz' ); ?></button>
			</div>

			<!-- QUIZ -->
			<div id="apq-quiz-screen" class="apq-screen">
				<div class="apq-quiz-header">
					<div class="apq-progress-track"><div class="apq-progress-fill" id="apq-progress-fill"></div></div>
					<div class="apq-quiz-counter" id="apq-quiz-counter"></div>
				</div>
				<div class="apq-quiz-body" id="apq-quiz-body">
					<h2 class="apq-value-title" id="apq-value-title"></h2>
					<p class="apq-value-desc" id="apq-value-desc"></p>
					<div class="apq-swatches-grid" id="apq-swatches-grid"></div>
				</div>
				<div class="apq-quiz-footer">
					<button type="button" class="apq-btn apq-btn-brand apq-btn-full" id="apq-btn-next" disabled><?php esc_html_e( 'Далее →', 'amaressence-palette-quiz' ); ?></button>
				</div>
			</div>

			<!-- RESULT -->
			<div id="apq-result" class="apq-screen">
				<div class="apq-result-hero">
					<div class="apq-result-eyebrow"><?php esc_html_e( 'Твой профиль', 'amaressence-palette-quiz' ); ?></div>
					<h2 class="apq-result-title" id="apq-result-title"></h2>
					<div class="apq-result-tagline" id="apq-result-tagline"></div>
					<div class="apq-result-dots" id="apq-result-dots"></div>
					<p class="apq-result-desc" id="apq-result-desc"></p>
				</div>
				<div class="apq-result-divider"></div>
				<div class="apq-gift-section" id="apq-gift-section">
					<?php if ( $show_gift ) : ?>
						<div class="apq-gift-badge" data-apq-gift><?php echo esc_html( $this->settings->get( 'gift_badge' ) ); ?></div>
						<h3 class="apq-gift-title" data-apq-gift><?php echo esc_html( $this->replace_points( $this->settings->get( 'gift_title' ), $points ) ); ?></h3>
						<p class="apq-gift-desc" data-apq-gift><?php echo esc_html( $this->replace_points( $this->settings->get( 'gift_desc' ), $points ) ); ?></p>
					<?php endif; ?>

					<?php if ( ! $is_logged_in ) : ?>
						<div class="apq-claim-email" id="apq-claim-email" data-apq-gift>
							<label class="apq-claim-email-label" for="apq-claim-email-input"><?php echo esc_html( $this->settings->get( 'claim_email_label' ) ); ?></label>
							<input type="email" id="apq-claim-email-input" class="apq-claim-email-input" placeholder="<?php echo esc_attr( $this->settings->get( 'claim_email_placeholder' ) ); ?>" autocomplete="email" inputmode="email">
							<p class="apq-claim-email-hint"><?php echo esc_html( $this->settings->get( 'claim_email_hint' ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $show_gift ) : ?>
						<button type="button" class="apq-btn apq-btn-brand apq-btn-full apq-btn-claim" id="apq-btn-claim" data-apq-gift><?php echo esc_html( $this->replace_points( $this->settings->get( 'gift_button' ), $points ) ); ?></button>
					<?php else : ?>
						<button type="button" class="apq-btn apq-btn-brand apq-btn-full apq-btn-claim" id="apq-btn-claim" data-apq-gift><?php esc_html_e( 'Сохранить результат', 'amaressence-palette-quiz' ); ?></button>
					<?php endif; ?>

					<a class="apq-btn apq-btn-brand apq-replay-shop" id="apq-replay-shop" href="<?php echo esc_url( $this->settings->get( 'shop_button_url' ) ); ?>" hidden><?php echo esc_html( $this->settings->get( 'shop_button_text' ) ); ?></a>

					<p class="apq-error" id="apq-error" hidden></p>
				</div>
			</div>

			<!-- SUCCESS -->
			<div id="apq-success" class="apq-screen">
				<div class="apq-success-icon">✓</div>
				<h2 class="apq-success-title"><?php echo esc_html( $this->settings->get( 'success_title' ) ); ?></h2>
				<p class="apq-success-desc"><?php echo esc_html( $this->replace_points( $success_desc, $points ) ); ?></p>
				<p class="apq-success-note" id="apq-success-note" hidden></p>
				<?php if ( $show_gift ) : ?>
					<div class="apq-success-points">
						<div class="apq-success-points-num"><?php echo esc_html( $this->replace_points( '{points}', $points ) ); ?></div>
						<div class="apq-success-points-label"><?php esc_html_e( 'баллов ждут тебя', 'amaressence-palette-quiz' ); ?></div>
					</div>
				<?php endif; ?>
				<a class="apq-btn apq-btn-brand" href="<?php echo esc_url( $this->settings->get( 'shop_button_url' ) ); ?>"><?php echo esc_html( $this->settings->get( 'shop_button_text' ) ); ?></a>
			</div>

			<!-- ALREADY COMPLETED -->
			<div id="apq-already" class="apq-screen<?php echo $completed ? ' active' : ''; ?>">
				<div class="apq-success-icon">✓</div>
				<h2 class="apq-success-title"><?php echo esc_html( $this->settings->get( 'already_title' ) ); ?></h2>
				<div class="apq-result-tagline" id="apq-already-profile"></div>
				<div class="apq-result-dots" id="apq-already-dots"></div>
				<p class="apq-success-desc"><?php echo esc_html( $this->settings->get( 'already_desc' ) ); ?></p>
				<a class="apq-btn apq-btn-brand" href="<?php echo esc_url( $this->settings->get( 'shop_button_url' ) ); ?>"><?php echo esc_html( $this->settings->get( 'shop_button_text' ) ); ?></a>
				<button type="button" class="apq-replay-link" id="apq-btn-replay"><?php echo esc_html( $this->settings->get( 'already_replay_text' ) ); ?></button>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: сохранение результата. Доступно и гостям — гость указывает email
	 * существующего аккаунта на финальном экране, баллы зачисляются на него.
	 */
	public function ajax_submit_quiz() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( 'yes' !== $this->settings->get( 'plugin_enabled', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Квиз временно недоступен.', 'amaressence-palette-quiz' ) ), 400 );
		}

		$is_logged_in = is_user_logged_in();
		$user_id      = 0;
		$email        = '';

		if ( $is_logged_in ) {
			$user    = wp_get_current_user();
			$email   = $this->points->normalize_email( $user->user_email );
			$user_id = (int) $user->ID;

			if ( ! $email || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Некорректный email пользователя.', 'amaressence-palette-quiz' ) ), 400 );
			}
		} else {
			$email = isset( $_POST['claim_email'] ) ? $this->points->normalize_email( wp_unslash( (string) $_POST['claim_email'] ) ) : '';

			if ( ! $email || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Укажи корректный email, чтобы получить подарок.', 'amaressence-palette-quiz' ) ), 400 );
			}

			if ( $this->too_many_guest_claims() ) {
				wp_send_json_error( array( 'message' => __( 'Слишком много попыток. Попробуй немного позже.', 'amaressence-palette-quiz' ) ), 429 );
			}
		}

		$raw_answers = isset( $_POST['answers'] ) ? json_decode( wp_unslash( (string) $_POST['answers'] ), true ) : null;
		$session_id  = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['session_id'] ) ) : '';

		$quiz_data = $this->settings->get_quiz_data();
		$value_ids = wp_list_pluck( $quiz_data['values'], 'id' );
		$color_ids = wp_list_pluck( $quiz_data['colors'], 'id' );

		// Серверная валидация ответов: разрешаем только известные id.
		$answers = array();

		if ( is_array( $raw_answers ) ) {
			foreach ( $raw_answers as $value_id => $color_id ) {
				$value_id = sanitize_key( (string) $value_id );
				$color_id = sanitize_key( (string) $color_id );

				if ( in_array( $value_id, $value_ids, true ) && in_array( $color_id, $color_ids, true ) ) {
					$answers[ $value_id ] = $color_id;
				}
			}
		}

		if ( count( $answers ) !== count( $value_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Ответьте на все вопросы.', 'amaressence-palette-quiz' ) ), 400 );
		}

		$calculated = APQ_Settings::calculate_result( $answers, $quiz_data );
		$profile_id = $calculated['id'];

		$account_created = false;

		// Гость: если аккаунта с таким email нет — создаём его автоматически и
		// присылаем письмо с временным паролем (шаблон как в Foneona Woo Layout).
		if ( ! $is_logged_in ) {
			$existing = get_user_by( 'email', $email );

			if ( $existing instanceof WP_User ) {
				$user_id = (int) $existing->ID;
			} elseif ( $this->account instanceof APQ_Account ) {
				$created = $this->account->create_customer_from_quiz( $email, array( 'profile_id' => $profile_id ) );

				if ( empty( $created['success'] ) || empty( $created['user_id'] ) ) {
					wp_send_json_error( array( 'message' => __( 'Не удалось создать аккаунт. Попробуй ещё раз чуть позже.', 'amaressence-palette-quiz' ) ), 500 );
				}

				$user_id         = (int) $created['user_id'];
				$account_created = ! empty( $created['created'] );
			} else {
				wp_send_json_error( array( 'message' => __( 'Не удалось создать аккаунт.', 'amaressence-palette-quiz' ) ), 500 );
			}
		}

		$result = $this->results->save_result( $user_id, $email, $session_id, $answers, $profile_id );

		if ( ! $result['success'] ) {
			if ( 'already_completed' === $result['code'] ) {
				wp_send_json_error(
					array(
						'code'    => 'already_completed',
						'message' => $this->settings->get( 'already_desc' ),
						'profile' => $result['row'] ? $result['row']['profile_id'] : '',
						'answers' => ( $result['row'] && ! empty( $result['row']['answers'] ) ) ? json_decode( (string) $result['row']['answers'], true ) : array(),
					),
					409
				);
			}

			wp_send_json_error( array( 'message' => __( 'Не удалось сохранить результат.', 'amaressence-palette-quiz' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message'         => __( 'Результат сохранён.', 'amaressence-palette-quiz' ),
				'points_status'   => isset( $result['points_status'] ) ? $result['points_status'] : 'none',
				'profile'         => $profile_id,
				'result'          => $calculated,
				'account_created' => $account_created,
			)
		);
	}

	/**
	 * AJAX: свежее состояние для страницы квиза (безопасно при кэшировании страниц):
	 * актуальный nonce, статус авторизации и признак «уже проходил» для текущего посетителя.
	 */
	public function ajax_quiz_state() {
		$logged_in = is_user_logged_in();
		$completed = null;

		if ( $logged_in && 'yes' === $this->settings->get( 'one_attempt_per_email', 'yes' ) ) {
			$email = $this->points->normalize_email( wp_get_current_user()->user_email );
			$row   = $email ? $this->results->get_by_email( $email ) : null;

			if ( $row ) {
				$answers   = ! empty( $row['answers'] ) ? json_decode( (string) $row['answers'], true ) : array();
				$completed = array(
					'profile' => $row['profile_id'],
					'answers' => is_array( $answers ) ? $answers : array(),
				);
			}
		}

		wp_send_json_success(
			array(
				'nonce'     => wp_create_nonce( self::NONCE ),
				'loggedIn'  => $logged_in,
				'completed' => $completed,
			)
		);
	}

	/**
	 * Простая защита от перебора email гостями: не больше 10 попыток зачисления в час с одного IP.
	 */
	protected function too_many_guest_claims() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( ! $ip ) {
			return false;
		}

		$key   = 'apq_claims_' . md5( $ip . wp_salt( 'auth' ) );
		$count = (int) get_transient( $key );

		if ( $count >= 10 ) {
			$this->logger->log( 'warning', 'Гостевые зачисления ограничены по IP (лимит 10/час).', array() );

			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return false;
	}
}
