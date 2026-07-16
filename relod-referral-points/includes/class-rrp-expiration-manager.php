<?php
/**
 * Points expiration manager.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Expiration_Manager {

	const CRON_HOOK = 'rrp_points_expiration_daily';
	const SCHEDULED_HOUR_OPTION = 'rrp_points_expiration_scheduled_hour';

	/** @var RRP_Settings */
	protected $settings;

	/** @var RRP_Logger */
	protected $logger;

	/** @var RRP_Profile_Manager */
	protected $profile_manager;

	/** @var RRP_Points_Manager */
	protected $points_manager;

	/** @var RRP_Email_Manager */
	protected $email_manager;

	/** @var string */
	protected $ledger_table;

	/**
	 * Constructor.
	 *
	 * @param RRP_Settings        $settings        Settings.
	 * @param RRP_Logger          $logger          Logger.
	 * @param RRP_Profile_Manager $profile_manager Profile manager.
	 * @param RRP_Points_Manager  $points_manager  Points manager.
	 * @param RRP_Email_Manager   $email_manager   Email manager.
	 */
	public function __construct( $settings, $logger, $profile_manager, $points_manager, $email_manager ) {
		global $wpdb;

		$this->settings        = $settings;
		$this->logger          = $logger;
		$this->profile_manager = $profile_manager;
		$this->points_manager  = $points_manager;
		$this->email_manager   = $email_manager;
		$this->ledger_table    = $wpdb->prefix . 'rrp_points_ledger';

		add_action( self::CRON_HOOK, array( $this, 'run_daily' ) );
		$this->ensure_schedule();
	}

	/**
	 * Ensure WP-Cron event is scheduled at the configured hour.
	 *
	 * @return void
	 */
	public function ensure_schedule() {
		$hour           = absint( $this->settings->get( 'points_expiration_run_hour', 9 ) );
		$scheduled_hour = get_option( self::SCHEDULED_HOUR_OPTION, null );

		if ( (string) $scheduled_hour !== (string) $hour ) {
			$this->clear_schedule();
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( $this->get_next_run_timestamp( $hour ), 'daily', self::CRON_HOOK );
			update_option( self::SCHEDULED_HOUR_OPTION, $hour, false );
		}
	}

	/**
	 * Clear scheduled expiration event.
	 *
	 * @return void
	 */
	public static function clear_schedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}

		delete_option( self::SCHEDULED_HOUR_OPTION );
	}

	/**
	 * Get next daily run timestamp in site timezone.
	 *
	 * @param int $hour Hour 0-23.
	 * @return int Unix timestamp.
	 */
	protected function get_next_run_timestamp( $hour ) {
		$hour = max( 0, min( 23, absint( $hour ) ) );
		$tz   = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$now  = new DateTime( 'now', $tz );
		$run  = new DateTime( 'now', $tz );
		$run->setTime( $hour, 5, 0 );

		if ( $run <= $now ) {
			$run->modify( '+1 day' );
		}

		return $run->getTimestamp();
	}

	/**
	 * Run notice sending and expiration.
	 *
	 * @return array Summary.
	 */
	public function run_daily() {
		$summary = array(
			'backfilled' => 0,
			'emails'     => 0,
			'expired'    => 0,
			'skipped'    => false,
		);

		if ( 'yes' !== $this->settings->get( 'plugin_enabled', 'yes' ) || 'yes' !== $this->settings->get( 'points_enabled', 'yes' ) || 'yes' !== $this->settings->get( 'points_expiration_enabled', 'no' ) ) {
			$summary['skipped'] = true;
			return $summary;
		}

		$limit = absint( $this->settings->get( 'points_expiration_batch_limit', 200 ) );
		$limit = max( 10, min( 1000, $limit ) );

		$summary['backfilled'] = $this->backfill_expiration_dates( $limit );
		$summary['emails']     = $this->send_expiration_notices( $limit );
		$summary['expired']    = $this->expire_due_points( $limit );

		$this->logger->log( 'info', 'Проверка сгорания баллов выполнена.', $summary );

		return $summary;
	}

	/**
	 * Fill expires_at for old positive ledger entries.
	 *
	 * @param int $limit Batch limit.
	 * @return int Updated rows.
	 */
	public function backfill_expiration_dates( $limit = 200 ) {
		global $wpdb;

		$limit = max( 10, min( 1000, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at FROM {$this->ledger_table} WHERE points > 0 AND (expires_at IS NULL OR expires_at = '0000-00-00 00:00:00') ORDER BY id ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $rows as $row ) {
			$expires_at = $this->points_manager->get_expiration_datetime_for_created_at( $row['created_at'] );

			if ( ! $expires_at ) {
				continue;
			}

			$updated = $wpdb->update(
				$this->ledger_table,
				array( 'expires_at' => $expires_at ),
				array( 'id' => absint( $row['id'] ) ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false !== $updated ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Send points-expiring emails for credits inside the notice window.
	 *
	 * @param int $limit Batch limit.
	 * @return int Emails sent.
	 */
	public function send_expiration_notices( $limit = 200 ) {
		global $wpdb;

		if ( ! $this->email_manager->is_enabled( 'points_expiring' ) ) {
			return 0;
		}

		$limit       = max( 10, min( 1000, absint( $limit ) ) );
		$notice_days = max( 0, absint( $this->settings->get( 'points_expiration_notice_days', 7 ) ) );
		$now         = current_time( 'mysql' );
		$window_end  = $notice_days > 0
			? date_i18n( 'Y-m-d H:i:s', strtotime( '+' . $notice_days . ' days', current_time( 'timestamp' ) ) )
			: date_i18n( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->ledger_table} WHERE points > 0 AND expires_at IS NOT NULL AND expires_at >= %s AND expires_at <= %s AND expired_at IS NULL AND expiration_email_sent_at IS NULL ORDER BY expires_at ASC, id ASC LIMIT %d",
				$now,
				$window_end,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$profiles = array();

		foreach ( $rows as $row ) {
			$profile_id = absint( $row['profile_id'] );

			if ( ! isset( $profiles[ $profile_id ] ) ) {
				$profiles[ $profile_id ] = array(
					'rows' => array(),
				);
			}

			$profiles[ $profile_id ]['rows'][] = $row;
		}

		$sent_count = 0;

		foreach ( $profiles as $profile_id => $data ) {
			$profile = $this->profile_manager->get_profile( $profile_id );

			if ( ! $profile ) {
				continue;
			}

			$remaining_by_credit = $this->get_remaining_points_by_credit( $profile_id );
			$eligible_rows        = array();
			$earliest_date        = null;
			$earliest_timestamp   = null;

			foreach ( $data['rows'] as $row ) {
				$remaining = isset( $remaining_by_credit[ $row['id'] ] ) ? (float) $remaining_by_credit[ $row['id'] ] : 0.0;

				if ( $remaining <= 0 ) {
					$this->mark_credit_notified( absint( $row['id'] ) );
					continue;
				}

				$expires_ts = strtotime( $row['expires_at'] );

				if ( ! $expires_ts ) {
					continue;
				}

				$expires_date = date_i18n( 'Y-m-d', $expires_ts );

				if ( null === $earliest_date || $expires_date < $earliest_date ) {
					$earliest_date      = $expires_date;
					$earliest_timestamp = $expires_ts;
					$eligible_rows      = array();
				}

				if ( $expires_date === $earliest_date ) {
					$earliest_timestamp       = null === $earliest_timestamp ? $expires_ts : min( $earliest_timestamp, $expires_ts );
					$row['remaining_points']  = $remaining;
					$eligible_rows[]          = $row;
				}
			}

			if ( empty( $eligible_rows ) || null === $earliest_timestamp ) {
				continue;
			}

			$total = 0.0;
			$ids   = array();

			foreach ( $eligible_rows as $row ) {
				$total += (float) $row['remaining_points'];
				$ids[]  = absint( $row['id'] );
			}

			$days_left = max( 0, (int) ceil( ( $earliest_timestamp - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) );
			$sent      = $this->email_manager->send_points_expiring_email(
				$profile,
				wc_format_decimal( $total, 4 ),
				date_i18n( 'Y-m-d H:i:s', $earliest_timestamp ),
				$days_left,
				array(
					'loyalty_balance' => isset( $profile['points_balance'] ) ? $profile['points_balance'] : $total,
				)
			);

			if ( $sent ) {
				$this->mark_credits_notified( $ids );
				++$sent_count;
			}
		}

		return $sent_count;
	}

	/**
	 * Expire due point credits.
	 *
	 * @param int $limit Batch limit.
	 * @return int Credits processed.
	 */
	public function expire_due_points( $limit = 200 ) {
		global $wpdb;

		$limit = max( 10, min( 1000, absint( $limit ) ) );
		$now   = current_time( 'mysql' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->ledger_table} WHERE points > 0 AND expires_at IS NOT NULL AND expires_at <= %s AND expired_at IS NULL ORDER BY expires_at ASC, id ASC LIMIT %d",
				$now,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$profiles = array();

		foreach ( $rows as $row ) {
			$profile_id = absint( $row['profile_id'] );

			if ( ! isset( $profiles[ $profile_id ] ) ) {
				$profiles[ $profile_id ] = array();
			}

			$profiles[ $profile_id ][] = $row;
		}

		$processed = 0;

		foreach ( $profiles as $profile_id => $profile_rows ) {
			$profile = $this->profile_manager->get_profile( $profile_id );

			if ( ! $profile ) {
				continue;
			}

			$remaining_by_credit = $this->get_remaining_points_by_credit( $profile_id );

			foreach ( $profile_rows as $row ) {
				$credit_id = absint( $row['id'] );
				$remaining = isset( $remaining_by_credit[ $credit_id ] ) ? (float) $remaining_by_credit[ $credit_id ] : 0.0;

				if ( $remaining > 0 ) {
					$description = sprintf(
						/* translators: %s: expire date. */
						__( 'Сгорание баллов по сроку действия: %s', 'relod-referral-points' ),
						mysql2date( get_option( 'date_format' ), $row['expires_at'] )
					);

					$this->points_manager->subtract_points( $profile, null, 0, wc_format_decimal( $remaining, 4 ), $description, 'expiration' );
				}

				$this->mark_credit_expired( $credit_id );
				++$processed;
			}
		}

		return $processed;
	}

	/**
	 * Compute remaining points for positive credits using FIFO debit allocation.
	 *
	 * @param int $profile_id Profile ID.
	 * @return array credit_id => remaining points.
	 */
	protected function get_remaining_points_by_credit( $profile_id ) {
		global $wpdb;

		$profile_id = absint( $profile_id );
		$rows       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, points FROM {$this->ledger_table} WHERE profile_id = %d ORDER BY created_at ASC, id ASC",
				$profile_id
			),
			ARRAY_A
		);

		$credits = array();
		$order   = array();

		foreach ( $rows as $row ) {
			$points = (float) $row['points'];
			$id     = absint( $row['id'] );

			if ( $points > 0 ) {
				$credits[ $id ] = $points;
				$order[]        = $id;
				continue;
			}

			if ( $points >= 0 ) {
				continue;
			}

			$debit = abs( $points );

			foreach ( $order as $credit_id ) {
				if ( $debit <= 0 ) {
					break;
				}

				if ( empty( $credits[ $credit_id ] ) ) {
					continue;
				}

				$take = min( $credits[ $credit_id ], $debit );
				$credits[ $credit_id ] -= $take;
				$debit -= $take;
			}
		}

		foreach ( $credits as $id => $value ) {
			$credits[ $id ] = wc_format_decimal( max( 0, $value ), 4 );
		}

		return $credits;
	}

	/**
	 * Mark one credit as notified.
	 *
	 * @param int $credit_id Credit ledger ID.
	 * @return void
	 */
	protected function mark_credit_notified( $credit_id ) {
		$this->mark_credits_notified( array( $credit_id ) );
	}

	/**
	 * Mark credits as notified.
	 *
	 * @param array $credit_ids Ledger IDs.
	 * @return void
	 */
	protected function mark_credits_notified( $credit_ids ) {
		global $wpdb;

		$credit_ids = array_filter( array_map( 'absint', (array) $credit_ids ) );

		if ( empty( $credit_ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $credit_ids ), '%d' ) );
		$sql          = "UPDATE {$this->ledger_table} SET expiration_email_sent_at = %s WHERE id IN ({$placeholders})";
		$params       = array_merge( array( current_time( 'mysql' ) ), $credit_ids );

		$wpdb->query( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Mark credit as expired.
	 *
	 * @param int $credit_id Credit ledger ID.
	 * @return void
	 */
	protected function mark_credit_expired( $credit_id ) {
		global $wpdb;

		$wpdb->update(
			$this->ledger_table,
			array( 'expired_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $credit_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
