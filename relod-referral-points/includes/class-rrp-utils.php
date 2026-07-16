<?php
/**
 * Utility helpers.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Utils {

	/**
	 * Format points number.
	 *
	 * @param mixed $points Points value.
	 * @return string
	 */
	public static function format_points( $points ) {
		$points = wc_format_decimal( $points, 2 );
		$points = (float) $points;

		if ( floor( $points ) === $points ) {
			return number_format_i18n( $points, 0 );
		}

		return number_format_i18n( $points, 2 );
	}

	/**
	 * Resolve user by ID or email.
	 *
	 * @param string $identifier User ID or email.
	 * @return WP_User|null
	 */
	public static function resolve_user( $identifier ) {
		$identifier = trim( (string) $identifier );

		if ( '' === $identifier ) {
			return null;
		}

		if ( is_numeric( $identifier ) ) {
			$user = get_user_by( 'id', absint( $identifier ) );
			if ( $user ) {
				return $user;
			}
		}

		if ( is_email( $identifier ) ) {
			$user = get_user_by( 'email', sanitize_email( $identifier ) );
			if ( $user ) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * Escape textarea for admin.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function admin_textarea( $value ) {
		return esc_textarea( (string) $value );
	}

	/**
	 * Get order status options with stripped wc- prefix.
	 *
	 * @return array
	 */
	public static function get_order_status_options() {
		$statuses = array();

		foreach ( wc_get_order_statuses() as $key => $label ) {
			$statuses[ str_replace( 'wc-', '', $key ) ] = $label;
		}

		return $statuses;
	}
}
