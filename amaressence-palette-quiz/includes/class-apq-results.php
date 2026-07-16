<?php
/**
 * Результаты квиза: «один раз на email», хранение, отложенное начисление после первого заказа.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Results {

	/** @var APQ_Settings */
	protected $settings;

	/** @var APQ_Logger */
	protected $logger;

	/** @var APQ_Points */
	protected $points;

	/** @var string */
	protected $table;

	public function __construct( $settings, $logger, $points ) {
		global $wpdb;

		$this->settings = $settings;
		$this->logger   = $logger;
		$this->points   = $points;
		$this->table    = $wpdb->prefix . 'apq_quiz_results';

		// Отложенное начисление: «баллы появятся после первой покупки».
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_award_on_order_status' ), 20, 4 );
	}

	/**
	 * Последний АКТИВНЫЙ результат по email. Обнулённые (revoked) записи не считаются —
	 * после обнуления баллов пользователь может пройти квиз заново.
	 */
	public function get_by_email( $email ) {
		global $wpdb;

		$email = $this->points->normalize_email( $email );

		if ( ! $email ) {
			return null;
		}

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE email = %s AND points_status != 'revoked' ORDER BY id DESC LIMIT 1", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function get_by_id( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id ) {
			return null;
		}

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function has_completed( $email ) {
		return null !== $this->get_by_email( $email );
	}

	/**
	 * Сохранить результат квиза. Ограничение по email контролируется настройкой one_attempt_per_email.
	 *
	 * @return array { success: bool, code: string, row: array|null }
	 */
	public function save_result( $user_id, $email, $session_id, $answers, $profile_id ) {
		global $wpdb;

		$email = $this->points->normalize_email( $email );

		if ( ! $email ) {
			return array( 'success' => false, 'code' => 'bad_email', 'row' => null );
		}

		if ( 'yes' === $this->settings->get( 'one_attempt_per_email', 'yes' ) && $this->has_completed( $email ) ) {
			return array( 'success' => false, 'code' => 'already_completed', 'row' => $this->get_by_email( $email ) );
		}

		$mode    = $this->settings->get( 'points_award_mode', 'after_first_order' );
		$points  = (float) $this->settings->get( 'points_amount', '400' );
		$status  = 'none';

		if ( $points > 0 && 'none' !== $mode && $this->points->rrp_available() ) {
			$status = ( 'immediate' === $mode ) ? 'awarded' : 'pending';
		}

		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$inserted = $wpdb->insert(
			$this->table,
			array(
				'user_id'       => $user_id ? absint( $user_id ) : null,
				'email'         => $email,
				'session_id'    => sanitize_text_field( (string) $session_id ),
				'answers'       => wp_json_encode( $answers, JSON_UNESCAPED_UNICODE ),
				'profile_id'    => sanitize_key( (string) $profile_id ),
				'points'        => $points,
				'points_status' => 'awarded' === $status ? 'pending' : $status, // подтвердим 'awarded' только после успешного начисления.
				'ip_hash'       => $ip ? hash( 'sha256', $ip . wp_salt( 'auth' ) ) : null,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$existing = ( 'yes' === $this->settings->get( 'one_attempt_per_email', 'yes' ) ) ? $this->get_by_email( $email ) : null;

			if ( $existing ) {
				return array( 'success' => false, 'code' => 'already_completed', 'row' => $existing );
			}

			$this->logger->log( 'error', 'Не удалось сохранить результат квиза.', array( 'email' => $email ) );

			return array( 'success' => false, 'code' => 'db_error', 'row' => null );
		}

		$row_id = (int) $wpdb->insert_id;

		$this->logger->log(
			'info',
			'Сохранён результат квиза.',
			array(
				'result_id' => $row_id,
				'email'     => $email,
				'profile'   => $profile_id,
				'mode'      => $mode,
			)
		);

		// Немедленное начисление.
		if ( 'awarded' === $status ) {
			$ok = $this->points->award_points( $email, $user_id, $points, $this->settings->get( 'points_description' ) );
			$this->mark_points_status( $row_id, $ok ? 'awarded' : 'failed', 0 );
			$status = $ok ? 'awarded' : 'failed';
		}

		$row = $this->get_by_email( $email );

		return array( 'success' => true, 'code' => 'saved', 'row' => $row, 'points_status' => $status );
	}

	protected function mark_points_status( $row_id, $status, $order_id = 0 ) {
		global $wpdb;

		$data    = array( 'points_status' => sanitize_key( $status ) );
		$formats = array( '%s' );

		if ( 'awarded' === $status ) {
			$data['awarded_at'] = current_time( 'mysql' );
			$formats[]          = '%s';

			if ( $order_id ) {
				$data['awarded_order_id'] = absint( $order_id );
				$formats[]                = '%d';
			}
		}

		$wpdb->update( $this->table, $data, array( 'id' => absint( $row_id ) ), $formats, array( '%d' ) );
	}

	/**
	 * Отложенное начисление: при достижении заказом нужного статуса,
	 * если у email заказа есть pending-результат квиза.
	 */
	public function maybe_award_on_order_status( $order_id, $old_status, $new_status, $order ) {
		if ( 'yes' !== $this->settings->get( 'plugin_enabled', 'yes' ) ) {
			return;
		}

		if ( 'after_first_order' !== $this->settings->get( 'points_award_mode', 'after_first_order' ) ) {
			return;
		}

		$target = $this->settings->get( 'points_order_status', 'completed' );

		if ( $new_status !== $target ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$row = $this->get_pending_for_order( $order );

		if ( ! $row ) {
			return;
		}

		// Идемпотентность на уровне заказа.
		if ( 'yes' === $order->get_meta( '_apq_quiz_points_awarded' ) ) {
			return;
		}

		$points = (float) $row['points'];

		if ( $points <= 0 ) {
			$this->mark_points_status( (int) $row['id'], 'none' );
			return;
		}

		$email   = $this->points->normalize_email( $row['email'] );
		$user_id = $order->get_user_id() ? absint( $order->get_user_id() ) : absint( $row['user_id'] );
		$desc    = $this->settings->get( 'points_description' ) . sprintf( ' (заказ #%s)', $order->get_order_number() );
		$ok      = $this->points->award_points( $email, $user_id, $points, $desc, $order->get_id() );

		if ( $ok ) {
			$this->mark_points_status( (int) $row['id'], 'awarded', $order->get_id() );
			$order->update_meta_data( '_apq_quiz_points_awarded', 'yes' );
			$order->save();
		}
	}


	protected function get_pending_for_order( $order ) {
		global $wpdb;

		$email = $this->points->normalize_email( $order->get_billing_email() );

		if ( $email ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$this->table} WHERE email = %s AND points_status = %s ORDER BY id DESC LIMIT 1", $email, 'pending' ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);

			if ( $row ) {
				return $row;
			}
		}

		$user_id = $order->get_user_id() ? absint( $order->get_user_id() ) : 0;

		if ( ! $user_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE user_id = %d AND points_status = %s ORDER BY id DESC LIMIT 1", $user_id, 'pending' ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Список результатов для админки (с необязательным поиском по email).
	 */
	public function get_results( $limit = 200, $offset = 0, $search = '' ) {
		global $wpdb;

		$search = trim( (string) $search );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			return (array) $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$this->table} WHERE email LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, absint( $limit ), absint( $offset ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d OFFSET %d", absint( $limit ), absint( $offset ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	public function count_results( $search = '' ) {
		global $wpdb;

		$search = trim( (string) $search );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE email LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Сводная статистика для вкладки «Результаты».
	 */
	public function get_stats() {
		global $wpdb;

		$stats = array(
			'total'          => 0,
			'awarded'        => 0,
			'pending'        => 0,
			'revoked'        => 0,
			'points_awarded' => 0.0,
		);

		$rows = (array) $wpdb->get_results( "SELECT points_status, COUNT(*) AS cnt, SUM(points) AS pts FROM {$this->table} GROUP BY points_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $rows as $row ) {
			$status = (string) $row['points_status'];
			$count  = (int) $row['cnt'];

			$stats['total'] += $count;

			if ( isset( $stats[ $status ] ) ) {
				$stats[ $status ] += $count;
			}

			if ( 'awarded' === $status ) {
				$stats['points_awarded'] = (float) $row['pts'];
			}
		}

		return $stats;
	}

	/**
	 * Обнуление: списать начисленные баллы (если были) и открыть повторное прохождение.
	 *
	 * @return array { success: bool, code: string }
	 */
	public function revoke_result( $row_id ) {
		global $wpdb;

		$row = $this->get_by_id( $row_id );

		if ( ! $row ) {
			return array( 'success' => false, 'code' => 'not_found' );
		}

		if ( 'revoked' === $row['points_status'] ) {
			return array( 'success' => false, 'code' => 'already_revoked' );
		}

		// Если баллы были реально начислены — списываем их из RRP.
		if ( 'awarded' === $row['points_status'] && (float) $row['points'] > 0 ) {
			$desc = sprintf( 'Обнуление бонуса за квиз «Палитра amarèssence» (результат #%d)', (int) $row['id'] );
			$ok   = $this->points->revoke_points( $row['email'], (int) $row['user_id'], (float) $row['points'], $desc );

			if ( ! $ok ) {
				return array( 'success' => false, 'code' => 'revoke_failed' );
			}
		}

		$wpdb->update(
			$this->table,
			array(
				'points_status' => 'revoked',
				'revoked_at'    => current_time( 'mysql' ),
			),
			array( 'id' => absint( $row['id'] ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Снимаем идемпотентный флаг с заказа, чтобы повторное прохождение
		// могло быть начислено после следующего подходящего заказа.
		if ( ! empty( $row['awarded_order_id'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $row['awarded_order_id'] );

			if ( $order ) {
				$order->delete_meta_data( '_apq_quiz_points_awarded' );
				$order->save();
			}
		}

		$this->logger->log(
			'info',
			'Обнулён результат квиза — пользователь может пройти заново.',
			array(
				'result_id' => (int) $row['id'],
				'email'     => $row['email'],
				'status'    => $row['points_status'],
			)
		);

		return array( 'success' => true, 'code' => 'revoked' );
	}

	/**
	 * Сброс попытки (инструмент админа): даёт пройти квиз заново.
	 */
	public function delete_by_email( $email ) {
		global $wpdb;

		$email = $this->points->normalize_email( $email );

		if ( ! $email ) {
			return false;
		}

		$deleted = $wpdb->delete( $this->table, array( 'email' => $email ), array( '%s' ) );

		if ( $deleted ) {
			$this->logger->log( 'info', 'Сброшена попытка квиза.', array( 'email' => $email ) );
		}

		return (bool) $deleted;
	}
}
