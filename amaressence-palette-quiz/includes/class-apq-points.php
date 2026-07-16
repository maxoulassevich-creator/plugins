<?php
/**
 * Интеграция с RELOD Referral Points: начисление баллов в rrp_profiles + rrp_points_ledger.
 *
 * Работает напрямую с таблицами плагина RRP (та же логика, что в RRP_Points_Manager::add_points
 * и RRP_Profile_Manager::get_or_create_profile_by_email), поэтому не зависит от внутренних
 * private/protected сервисов RRP и не дублирует его хуки.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Points {

	const ENTRY_TYPE        = 'quiz_reward';
	const ENTRY_TYPE_REVOKE = 'quiz_reward_revoke';

	/** @var APQ_Settings */
	protected $settings;

	/** @var APQ_Logger */
	protected $logger;

	public function __construct( $settings, $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Активен ли RELOD Referral Points (есть ли его таблицы).
	 */
	public function rrp_available() {
		global $wpdb;

		$profiles = $wpdb->prefix . 'rrp_profiles';
		$ledger   = $wpdb->prefix . 'rrp_points_ledger';

		$has_profiles = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $profiles ) ) === $profiles;
		$has_ledger   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ledger ) ) === $ledger;

		return $has_profiles && $has_ledger;
	}

	public function normalize_email( $email ) {
		$email = sanitize_email( wp_unslash( (string) $email ) );
		$email = strtolower( trim( $email ) );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Профиль RRP по email: найти или создать (зеркало RRP_Profile_Manager).
	 *
	 * @return array|null
	 */
	public function get_or_create_rrp_profile( $email, $user_id = 0 ) {
		global $wpdb;

		if ( ! $this->rrp_available() ) {
			return null;
		}

		$table   = $wpdb->prefix . 'rrp_profiles';
		$email   = $this->normalize_email( $email );
		$user_id = absint( $user_id );

		if ( ! $email ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $row ) {
			if ( $user_id && (int) $row['user_id'] !== $user_id ) {
				$wpdb->update(
					$table,
					array(
						'user_id'    => $user_id,
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => (int) $row['id'] ),
					array( '%d', '%s' ),
					array( '%d' )
				);
				update_user_meta( $user_id, '_rrp_profile_id', (int) $row['id'] );
				$row['user_id'] = $user_id;
			}

			return $row;
		}

		$now      = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id ? $user_id : null,
				'email'          => $email,
				'referral_code'  => $this->generate_unique_referral_code(),
				'points_balance' => 0,
				'status'         => 'active',
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->log( 'error', 'Не удалось создать RRP-профиль из квиза.', array( 'email' => $email ) );

			return null;
		}

		$profile_id = (int) $wpdb->insert_id;

		if ( $user_id ) {
			update_user_meta( $user_id, '_rrp_profile_id', $profile_id );
		}

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $profile_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function generate_unique_referral_code() {
		global $wpdb;

		$table = $wpdb->prefix . 'rrp_profiles';

		for ( $i = 0; $i < 20; $i++ ) {
			$code = strtoupper( wp_generate_password( 12, false, false ) );
			$code = substr( preg_replace( '/[^A-Z0-9]/', '', $code ), 0, 12 );

			if ( strlen( $code ) < 6 ) {
				continue;
			}

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE referral_code = %s", $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( ! $exists ) {
				return $code;
			}
		}

		return strtoupper( substr( md5( uniqid( '', true ) ), 0, 12 ) );
	}

	/**
	 * Начислить баллы за квиз: запись в ledger + обновление баланса профиля.
	 *
	 * @param string $email       Email получателя.
	 * @param int    $user_id     ID пользователя.
	 * @param float  $points      Сколько баллов.
	 * @param string $description Описание.
	 * @param int    $order_id    Связанный заказ (для режима after_first_order).
	 * @return bool
	 */
	public function award_points( $email, $user_id, $points, $description, $order_id = 0 ) {
		global $wpdb;

		$points = (float) $points;

		if ( $points <= 0 ) {
			return false;
		}

		if ( ! $this->rrp_available() ) {
			$this->logger->log( 'error', 'RELOD Referral Points недоступен — баллы за квиз не начислены.', array( 'email' => $email ) );

			return false;
		}

		$profile = $this->get_or_create_rrp_profile( $email, $user_id );

		if ( ! $profile ) {
			return false;
		}

		$ledger_table   = $wpdb->prefix . 'rrp_points_ledger';
		$current        = (float) $profile['points_balance'];
		$new_balance    = round( $current + $points, 4 );

		$inserted = $wpdb->insert(
			$ledger_table,
			array(
				'profile_id'    => (int) $profile['id'],
				'user_id'       => ! empty( $profile['user_id'] ) ? (int) $profile['user_id'] : null,
				'email'         => $profile['email'],
				'order_id'      => $order_id ? absint( $order_id ) : null,
				'referral_id'   => null,
				'entry_type'    => self::ENTRY_TYPE,
				'points'        => $points,
				'balance_after' => $new_balance,
				'description'   => sanitize_text_field( $description ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->log( 'error', 'Не удалось записать начисление за квиз в ledger.', array( 'profile_id' => $profile['id'] ) );

			return false;
		}

		$wpdb->update(
			$wpdb->prefix . 'rrp_profiles',
			array(
				'points_balance' => $new_balance,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => (int) $profile['id'] ),
			array( '%f', '%s' ),
			array( '%d' )
		);

		$this->logger->log(
			'info',
			'Начислены баллы за прохождение квиза.',
			array(
				'profile_id' => $profile['id'],
				'email'      => $profile['email'],
				'points'     => $points,
				'order_id'   => $order_id,
			)
		);

		/**
		 * Хук для сторонних интеграций (email, аналитика и т.п.).
		 */
		do_action( 'apq_points_awarded', $profile, $points, $order_id );

		return true;
	}

	/**
	 * Обнулить (списать) баллы, начисленные за квиз: отрицательная запись в ledger + уменьшение баланса.
	 *
	 * Списывается не больше текущего баланса — если баллы уже потрачены, баланс не уходит в минус.
	 *
	 * @param string $email       Email профиля.
	 * @param int    $user_id     ID пользователя (для поиска профиля).
	 * @param float  $points      Сколько баллов списать (сумма исходного начисления).
	 * @param string $description Описание операции в выписке.
	 * @return bool
	 */
	public function revoke_points( $email, $user_id, $points, $description ) {
		global $wpdb;

		$points = (float) $points;

		if ( $points <= 0 ) {
			return true;
		}

		if ( ! $this->rrp_available() ) {
			$this->logger->log( 'error', 'RELOD Referral Points недоступен — баллы за квиз не списаны.', array( 'email' => $email ) );

			return false;
		}

		$email   = $this->normalize_email( $email );
		$profile = $email ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rrp_profiles WHERE email = %s", $email ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $profile ) {
			// Профиля нет — списывать нечего, обнуление считаем выполненным.
			$this->logger->log( 'warning', 'Обнуление баллов: RRP-профиль не найден, списание пропущено.', array( 'email' => $email ) );

			return true;
		}

		$current = (float) $profile['points_balance'];
		$deduct  = round( min( $points, max( 0, $current ) ), 4 );

		if ( $deduct > 0 ) {
			$new_balance = round( $current - $deduct, 4 );

			$inserted = $wpdb->insert(
				$wpdb->prefix . 'rrp_points_ledger',
				array(
					'profile_id'    => (int) $profile['id'],
					'user_id'       => ! empty( $profile['user_id'] ) ? (int) $profile['user_id'] : null,
					'email'         => $profile['email'],
					'order_id'      => null,
					'referral_id'   => null,
					'entry_type'    => self::ENTRY_TYPE_REVOKE,
					'points'        => -$deduct,
					'balance_after' => $new_balance,
					'description'   => sanitize_text_field( $description ),
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s' )
			);

			if ( false === $inserted ) {
				$this->logger->log( 'error', 'Не удалось записать списание за квиз в ledger.', array( 'profile_id' => $profile['id'] ) );

				return false;
			}

			$wpdb->update(
				$wpdb->prefix . 'rrp_profiles',
				array(
					'points_balance' => $new_balance,
					'updated_at'     => current_time( 'mysql' ),
				),
				array( 'id' => (int) $profile['id'] ),
				array( '%f', '%s' ),
				array( '%d' )
			);
		}

		$this->logger->log(
			'info',
			'Обнулены баллы за квиз.',
			array(
				'profile_id' => $profile['id'],
				'email'      => $profile['email'],
				'points'     => $points,
				'deducted'   => $deduct,
			)
		);

		do_action( 'apq_points_revoked', $profile, $deduct );

		return true;
	}
}
