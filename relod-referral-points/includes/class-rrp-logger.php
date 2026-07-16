<?php
/**
 * Lightweight logger.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Logger {

	/**
	 * Log option key.
	 *
	 * @var string
	 */
	protected $option_key = 'rrp_logs';

	/**
	 * Settings.
	 *
	 * @var RRP_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param RRP_Settings $settings Settings instance.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Add log entry.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function log( $level, $message, $context = array() ) {
		if ( 'yes' !== $this->settings->get( 'logs_enabled', 'yes' ) ) {
			return;
		}

		$logs = get_option( $this->option_key, array() );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift(
			$logs,
			array(
				'time'    => current_time( 'mysql' ),
				'level'   => sanitize_key( $level ),
				'message' => wp_strip_all_tags( (string) $message ),
				'context' => $this->sanitize_context( $context ),
			)
		);

		if ( count( $logs ) > 500 ) {
			$logs = array_slice( $logs, 0, 500 );
		}

		update_option( $this->option_key, $logs, false );
	}

	/**
	 * Get logs.
	 *
	 * @param int $limit Number of items.
	 * @return array
	 */
	public function get_logs( $limit = 200 ) {
		$logs = get_option( $this->option_key, array() );

		if ( ! is_array( $logs ) ) {
			return array();
		}

		return array_slice( $logs, 0, absint( $limit ) );
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public function clear_logs() {
		delete_option( $this->option_key );
	}

	/**
	 * Sanitize log context.
	 *
	 * @param array $context Raw context.
	 * @return array
	 */
	protected function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return array();
		}

		$clean = array();

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			} else {
				$clean[ $key ] = wp_json_encode( $value );
			}
		}

		return $clean;
	}
}
