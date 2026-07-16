<?php
/**
 * Лёгкий логгер (паттерн RRP_Logger: опция, 500 записей).
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Logger {

	protected $option_key = 'apq_logs';

	/** @var APQ_Settings */
	protected $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function log( $level, $message, $context = array() ) {
		if ( 'yes' !== $this->settings->get( 'logs_enabled', 'yes' ) ) {
			return;
		}

		$logs = get_option( $this->option_key, array() );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$clean_context = array();

		foreach ( (array) $context as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( is_scalar( $value ) || null === $value ) {
				$clean_context[ $key ] = sanitize_text_field( (string) $value );
			} else {
				$clean_context[ $key ] = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
			}
		}

		array_unshift(
			$logs,
			array(
				'time'    => current_time( 'mysql' ),
				'level'   => sanitize_key( $level ),
				'message' => wp_strip_all_tags( (string) $message ),
				'context' => $clean_context,
			)
		);

		if ( count( $logs ) > 500 ) {
			$logs = array_slice( $logs, 0, 500 );
		}

		update_option( $this->option_key, $logs, false );
	}

	public function get_logs( $limit = 200 ) {
		$logs = get_option( $this->option_key, array() );

		return is_array( $logs ) ? array_slice( $logs, 0, absint( $limit ) ) : array();
	}

	public function clear_logs() {
		delete_option( $this->option_key );
	}
}
