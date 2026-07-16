<?php
/**
 * Points manager.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Points_Manager {

	/**
	 * Settings.
	 *
	 * @var RRP_Settings
	 */
	protected $settings;

	/**
	 * Logger.
	 *
	 * @var RRP_Logger
	 */
	protected $logger;

	/**
	 * Profile manager.
	 *
	 * @var RRP_Profile_Manager
	 */
	protected $profile_manager;

	/**
	 * Ledger table.
	 *
	 * @var string
	 */
	protected $ledger_table;

	/**
	 * Referrals table.
	 *
	 * @var string
	 */
	protected $referrals_table;

	/**
	 * Constructor.
	 *
	 * @param RRP_Settings        $settings        Settings.
	 * @param RRP_Logger          $logger          Logger.
	 * @param RRP_Profile_Manager $profile_manager Profile manager.
	 */
	public function __construct( $settings, $logger, $profile_manager ) {
		global $wpdb;

		$this->settings        = $settings;
		$this->logger          = $logger;
		$this->profile_manager = $profile_manager;
		$this->ledger_table    = $wpdb->prefix . 'rrp_points_ledger';
		$this->referrals_table = $wpdb->prefix . 'rrp_referrals';
	}

	/**
	 * Whether points system is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->settings->get( 'plugin_enabled', 'yes' ) && 'yes' === $this->settings->get( 'points_enabled', 'yes' );
	}

	/**
	 * Get current balance by profile ID.
	 *
	 * @param int $profile_id Profile ID.
	 * @return string
	 */
	public function get_balance( $profile_id ) {
		$profile = $this->profile_manager->get_profile( $profile_id );

		if ( ! $profile ) {
			return '0';
		}

		return wc_format_decimal( $profile['points_balance'], 4 );
	}

	/**
	 * Get expiration datetime for a credit entry according to current settings.
	 *
	 * @param string|null $created_at Credit creation datetime in site timezone.
	 * @return string|null MySQL datetime or null when expiration is disabled.
	 */
	public function get_expiration_datetime_for_created_at( $created_at = null ) {
		if ( 'yes' !== $this->settings->get( 'points_expiration_enabled', 'no' ) ) {
			return null;
		}

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

		try {
			$created = $created_at ? new DateTimeImmutable( $created_at, $timezone ) : new DateTimeImmutable( 'now', $timezone );
		} catch ( Exception $e ) {
			$created = new DateTimeImmutable( 'now', $timezone );
		}

		$mode = $this->settings->get( 'points_expiration_mode', 'rolling_days' );

		if ( 'fixed_date' === $mode ) {
			$month = max( 1, min( 12, absint( $this->settings->get( 'points_expiration_fixed_month', 12 ) ) ) );
			$day   = max( 1, min( 31, absint( $this->settings->get( 'points_expiration_fixed_day', 31 ) ) ) );
			$year  = (int) $created->format( 'Y' );

			$expiry = $this->build_fixed_expiry_date( $year, $month, $day, $timezone );

			if ( $expiry <= $created ) {
				$expiry = $this->build_fixed_expiry_date( $year + 1, $month, $day, $timezone );
			}
		} else {
			$days   = max( 1, absint( $this->settings->get( 'points_expiration_days', 365 ) ) );
			$expiry = $created->modify( '+' . $days . ' days' );
		}

		return $expiry->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Build fixed expiration date safely for short months.
	 *
	 * @param int          $year     Year.
	 * @param int          $month    Month.
	 * @param int          $day      Desired day.
	 * @param DateTimeZone $timezone Site timezone.
	 * @return DateTimeImmutable
	 */
	protected function build_fixed_expiry_date( $year, $month, $day, $timezone ) {
		$first_day = new DateTimeImmutable( sprintf( '%04d-%02d-01 23:59:59', $year, $month ), $timezone );
		$safe_day  = min( $day, (int) $first_day->format( 't' ) );

		return new DateTimeImmutable( sprintf( '%04d-%02d-%02d 23:59:59', $year, $month, $safe_day ), $timezone );
	}

	/**
	 * Calculate reward points from order total.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public function calculate_reward_points( $order ) {
		$rate  = (float) $this->settings->get( 'reward_rate', '0' );
		$total = (float) $order->get_total();
		$sum   = ( $total * $rate ) / 100;

		return wc_format_decimal( $sum, 4 );
	}

	/**
	 * Calculate points for the customer's own order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public function calculate_self_order_points( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '0';
		}

		$total     = (float) $order->get_total();
		$min_total = (float) $this->settings->get( 'self_points_min_order_total', '0' );

		if ( $total < $min_total ) {
			return '0';
		}

		$type  = $this->settings->get( 'self_points_reward_type', 'percent' );
		$value = (float) $this->settings->get( 'self_points_reward_value', '0' );

		if ( 'fixed' === $type ) {
			$sum = $value;
		} else {
			$sum = ( $total * $value ) / 100;
		}

		$max_points = (float) $this->settings->get( 'self_points_max_per_order', '0' );

		if ( $max_points > 0 && $sum > $max_points ) {
			$sum = $max_points;
		}

		if ( $sum < 0 ) {
			$sum = 0;
		}

		return wc_format_decimal( $sum, 4 );
	}

	/**
	 * Create reward entry.
	 *
	 * @param array    $profile     Profile data.
	 * @param WC_Order $order       Order.
	 * @param int      $referral_id Referral ID.
	 * @param string   $points      Points amount.
	 * @param string   $description Description.
	 * @param string   $entry_type  Entry type.
	 * @return bool
	 */
	public function add_points( $profile, $order, $referral_id, $points, $description, $entry_type = 'referral_reward' ) {
		global $wpdb;

		if ( ! $profile || ! $order instanceof WC_Order ) {
			return false;
		}

		$points = wc_format_decimal( $points, 4 );

		if ( (float) $points <= 0 ) {
			return false;
		}

		$current_balance = (float) $this->get_balance( $profile['id'] );
		$new_balance     = wc_format_decimal( $current_balance + (float) $points, 4 );
		$created_at      = current_time( 'mysql' );
		$expires_at      = $this->get_expiration_datetime_for_created_at( $created_at );

		$inserted = $wpdb->insert(
			$this->ledger_table,
			array(
				'profile_id'     => $profile['id'],
				'user_id'        => ! empty( $profile['user_id'] ) ? $profile['user_id'] : null,
				'email'          => $profile['email'],
				'order_id'       => $order->get_id(),
				'referral_id'    => absint( $referral_id ),
				'entry_type'     => sanitize_key( $entry_type ),
				'points'         => $points,
				'balance_after'  => $new_balance,
				'description'    => wp_kses_post( $description ),
				'expires_at'     => $expires_at,
				'expired_at'     => null,
				'expiration_email_sent_at' => null,
				'created_at'     => $created_at,
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->log(
				'error',
				'Не удалось записать начисление в ledger.',
				array(
					'profile_id'  => $profile['id'],
					'order_id'    => $order->get_id(),
					'referral_id' => $referral_id,
				)
			);

			return false;
		}

		$this->profile_manager->set_balance( $profile['id'], $new_balance );

		$this->logger->log(
			'info',
			'Начислены баллы.',
			array(
				'profile_id' => $profile['id'],
				'order_id'   => $order->get_id(),
				'points'     => $points,
			)
		);

		return true;
	}

	/**
	 * Reverse points entry.
	 *
	 * @param array    $profile     Profile.
	 * @param WC_Order $order       Order.
	 * @param int      $referral_id Referral ID.
	 * @param string   $points      Points.
	 * @param string   $description Description.
	 * @return bool
	 */
	public function subtract_points( $profile, $order, $referral_id, $points, $description, $entry_type = 'reversal' ) {
		global $wpdb;

		if ( ! $profile ) {
			return false;
		}

		$points = wc_format_decimal( $points, 4 );

		if ( (float) $points <= 0 ) {
			return false;
		}

		$current_balance = (float) $this->get_balance( $profile['id'] );
		$new_balance     = wc_format_decimal( $current_balance - (float) $points, 4 );

		$inserted = $wpdb->insert(
			$this->ledger_table,
			array(
				'profile_id'     => $profile['id'],
				'user_id'        => ! empty( $profile['user_id'] ) ? $profile['user_id'] : null,
				'email'          => $profile['email'],
				'order_id'       => $order instanceof WC_Order ? $order->get_id() : null,
				'referral_id'    => absint( $referral_id ),
				'entry_type'     => sanitize_key( $entry_type ),
				'points'         => wc_format_decimal( 0 - (float) $points, 4 ),
				'balance_after'  => $new_balance,
				'description'    => wp_kses_post( $description ),
				'expires_at'     => null,
				'expired_at'     => null,
				'expiration_email_sent_at' => null,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->log(
				'error',
				'Не удалось записать списание в ledger.',
				array(
					'profile_id'  => $profile['id'],
					'order_id'    => $order instanceof WC_Order ? $order->get_id() : 0,
					'referral_id' => $referral_id,
				)
			);

			return false;
		}

		$this->profile_manager->set_balance( $profile['id'], $new_balance );

		$this->logger->log(
			'info',
			'Баллы списаны.',
			array(
				'profile_id' => $profile['id'],
				'order_id'   => $order instanceof WC_Order ? $order->get_id() : 0,
				'points'     => $points,
			)
		);

		return true;
	}

	/**
	 * Manual points adjustment by identifier.
	 *
	 * @param string $identifier  Email or user ID.
	 * @param string $points      Points delta.
	 * @param string $description Description.
	 * @param int    $actor_id    Admin user ID.
	 * @return array
	 */
	public function adjust_points_by_identifier( $identifier, $points, $description, $actor_id = 0 ) {
		$identifier  = trim( (string) $identifier );
		$points      = wc_format_decimal( $points, 4 );
		$description = sanitize_text_field( $description );
		$actor_id    = absint( $actor_id );

		if ( '' === $identifier || 0.0 === (float) $points ) {
			return array(
				'success' => false,
				'message' => 'Заполните получателя и количество баллов.',
			);
		}

		$user    = RRP_Utils::resolve_user( $identifier );
		$profile = null;

		if ( $user ) {
			$profile = $this->profile_manager->get_or_create_profile_for_user( $user->ID );
		} elseif ( is_email( $identifier ) ) {
			$profile = $this->profile_manager->get_or_create_profile_by_email( $identifier );
		}

		if ( ! $profile ) {
			return array(
				'success' => false,
				'message' => 'Не удалось найти или создать профиль для указанного получателя.',
			);
		}

		$description = $description ? $description : 'Ручная корректировка баллов';
		if ( $actor_id ) {
			$description .= ' (администратор ID ' . $actor_id . ')';
		}

		if ( (float) $points > 0 ) {
			$ok = $this->add_manual_ledger_entry( $profile, $points, $description, 'manual_credit' );
		} else {
			$ok = $this->add_manual_ledger_entry( $profile, $points, $description, 'manual_debit' );
		}

		return array(
			'success' => $ok,
			'message' => $ok ? 'Баланс успешно скорректирован.' : 'Не удалось скорректировать баланс.',
		);
	}

	/**
	 * Manual ledger entry.
	 *
	 * @param array  $profile     Profile.
	 * @param string $points      Delta.
	 * @param string $description Description.
	 * @param string $entry_type  Type.
	 * @return bool
	 */
	protected function add_manual_ledger_entry( $profile, $points, $description, $entry_type ) {
		global $wpdb;

		$current_balance = (float) $this->get_balance( $profile['id'] );
		$new_balance     = wc_format_decimal( $current_balance + (float) $points, 4 );
		$created_at      = current_time( 'mysql' );
		$expires_at      = (float) $points > 0 ? $this->get_expiration_datetime_for_created_at( $created_at ) : null;

		$inserted = $wpdb->insert(
			$this->ledger_table,
			array(
				'profile_id'    => $profile['id'],
				'user_id'       => ! empty( $profile['user_id'] ) ? $profile['user_id'] : null,
				'email'         => $profile['email'],
				'order_id'      => null,
				'referral_id'   => null,
				'entry_type'    => sanitize_key( $entry_type ),
				'points'        => wc_format_decimal( $points, 4 ),
				'balance_after' => $new_balance,
				'description'   => sanitize_text_field( $description ),
				'expires_at'    => $expires_at,
				'expired_at'    => null,
				'expiration_email_sent_at' => null,
				'created_at'    => $created_at,
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$this->profile_manager->set_balance( $profile['id'], $new_balance );

		$this->logger->log(
			'info',
			'Выполнена ручная корректировка баллов.',
			array(
				'profile_id' => $profile['id'],
				'points'     => $points,
				'type'       => $entry_type,
			)
		);

		return true;
	}

	/**
	 * Get ledger rows by profile ID.
	 *
	 * @param int $profile_id Profile ID.
	 * @param int $limit      Limit.
	 * @return array
	 */
	public function get_ledger_by_profile( $profile_id, $limit = 50 ) {
		global $wpdb;

		$profile_id = absint( $profile_id );
		$limit      = absint( $limit );

		if ( ! $profile_id ) {
			return array();
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->ledger_table} WHERE profile_id = %d ORDER BY id DESC LIMIT %d",
			$profile_id,
			$limit
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Recalculate all balances from ledger.
	 *
	 * @return int Number of updated profiles.
	 */
	public function recalculate_all_balances() {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'rrp_profiles';
		$rows           = $wpdb->get_results( "SELECT id FROM {$profiles_table}", ARRAY_A );
		$count          = 0;

		if ( empty( $rows ) ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$profile_id = absint( $row['id'] );
			$sum_query  = $wpdb->prepare( "SELECT COALESCE(SUM(points),0) FROM {$this->ledger_table} WHERE profile_id = %d", $profile_id );
			$sum        = $wpdb->get_var( $sum_query );
			$this->profile_manager->set_balance( $profile_id, wc_format_decimal( $sum, 4 ) );
			++$count;
		}

		$this->logger->log( 'info', 'Пересчитаны балансы профилей.', array( 'count' => $count ) );

		return $count;
	}
}
