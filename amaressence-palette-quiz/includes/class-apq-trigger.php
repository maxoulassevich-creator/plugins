<?php
/**
 * Кнопка-триггер квиза.
 *
 * Прохождение квиза свободное: клик по ссылке-триггеру (по умолчанию #apq-quiz)
 * или по элементу с классом apq-quiz-trigger ведёт прямо на страницу квиза.
 * Никаких попапов авторизации/регистрации — если у гостя нет аккаунта, он
 * создаётся автоматически по email на финальном экране квиза.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Trigger {

	/** @var APQ_Settings */
	protected $settings;

	/** @var APQ_Logger */
	protected $logger;

	public function __construct( $settings, $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
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

		wp_enqueue_script( 'apq-trigger', APQ_PLUGIN_URL . 'assets/js/trigger.js', array(), APQ_VERSION, true );

		wp_localize_script(
			'apq-trigger',
			'APQTrigger',
			array(
				'quizUrl' => $this->get_quiz_url(),
				'trigger' => $this->settings->get( 'popup_trigger', '#apq-quiz' ),
			)
		);
	}
}
