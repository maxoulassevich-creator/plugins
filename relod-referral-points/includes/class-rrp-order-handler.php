<?php
/**
 * Order logic.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Order_Handler {

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
	 * Tracker.
	 *
	 * @var RRP_Referral_Tracker
	 */
	protected $tracker;

	/**
	 * Points manager.
	 *
	 * @var RRP_Points_Manager
	 */
	protected $points_manager;

	/**
	 * Email manager.
	 *
	 * @var RRP_Email_Manager
	 */
	protected $email_manager;

	/**
	 * Constructor.
	 *
	 * @param RRP_Settings         $settings        Settings.
	 * @param RRP_Logger           $logger          Logger.
	 * @param RRP_Profile_Manager  $profile_manager Profile manager.
	 * @param RRP_Referral_Tracker $tracker         Tracker.
	 * @param RRP_Points_Manager   $points_manager  Points manager.
	 * @param RRP_Email_Manager    $email_manager   Email manager.
	 */
	public function __construct( $settings, $logger, $profile_manager, $tracker, $points_manager, $email_manager ) {
		$this->settings        = $settings;
		$this->logger          = $logger;
		$this->profile_manager = $profile_manager;
		$this->tracker         = $tracker;
		$this->points_manager  = $points_manager;
		$this->email_manager   = $email_manager;

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'prepare_first_order_profile' ), 20, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_send_first_order_email_after_payment' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 20, 4 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_order_referral_info' ), 20, 1 );
	}

	/**
	 * Create/update the buyer profile at checkout, but do not email before payment.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order object.
	 * @return void
	 */
	public function prepare_first_order_profile( $order_id, $posted_data, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$profile = $this->profile_manager->get_or_create_profile_for_order( $order );
		if ( $profile ) {
			$this->profile_manager->touch_last_order( $profile['id'] );
		}
	}

	/**
	 * Send the first-order referral email after WooCommerce confirms payment.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function maybe_send_first_order_email_after_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->maybe_send_first_order_email_if_ready( $order );
	}

	/**
	 * Send the first-order referral email only after the configured status is reached.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	protected function maybe_send_first_order_email_if_ready( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$template = $this->email_manager->get_template( 'first_order_referral' );
		$scope    = isset( $template['send_scope'] ) ? sanitize_key( $template['send_scope'] ) : 'every_order';

		// По умолчанию письмо с реферальной ссылкой уходит на КАЖДЫЙ заказ
		// (стандартное письмо WooCommerce «в обработке» отключено и заменено этим).
		// Режим «first_order» ограничивает отправку только первым заказом покупателя.
		if ( 'first_order' === $scope && 'yes' !== $order->get_meta( '_rrp_customer_is_first_order', true ) ) {
			return;
		}

		if ( ! $this->is_email_send_status_reached( $order ) ) {
			return;
		}

		$this->email_manager->send_first_order_email( $order );
	}

	/**
	 * Check whether the order reached the configured email trigger state.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	protected function is_email_send_status_reached( $order ) {
		$template = $this->email_manager->get_template( 'first_order_referral' );
		$required = sanitize_key( isset( $template['send_order_status'] ) ? $template['send_order_status'] : $this->settings->get( 'email_send_order_status', 'paid' ) );

		if ( 'paid' === $required ) {
			$paid_statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
			return $order->is_paid() || in_array( $order->get_status(), $paid_statuses, true );
		}

		return sanitize_key( $order->get_status() ) === $required;
	}

	/**
	 * Handle reward and reversal logic on order status changes.
	 *
	 * @param int      $order_id    Order ID.
	 * @param string   $from_status Previous status slug.
	 * @param string   $to_status   New status slug.
	 * @param WC_Order $order       Order object.
	 * @return void
	 */
	public function handle_order_status_change( $order_id, $from_status, $to_status, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->maybe_send_first_order_email_if_ready( $order );
		$this->handle_referral_status_change( $order, $to_status );
		$this->handle_self_order_status_change( $order, $to_status );
	}

	/**
	 * Handle referral-related status transitions.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $to_status New status.
	 * @return void
	 */
	protected function handle_referral_status_change( $order, $to_status ) {
		$reward_status  = sanitize_key( $this->settings->get( 'reward_order_status', 'completed' ) );
		$processed      = 'yes' === $order->get_meta( '_rrp_reward_processed', true );
		$reversed       = 'yes' === $order->get_meta( '_rrp_reward_reversed', true );
		$referral_row   = $this->tracker->get_referral_by_order_id( $order->get_id() );
		$referral_state = $order->get_meta( '_rrp_referral_status', true );

		if ( ! $referral_row || 'pending' !== $referral_state ) {
			if ( $processed && ! $reversed ) {
				$this->maybe_reverse_points( $order, $to_status, $referral_row );
			}
			return;
		}

		if ( ! $processed && ! $reversed && $reward_status === sanitize_key( $to_status ) ) {
			$this->award_points( $order, $referral_row );
			return;
		}

		if ( $processed && ! $reversed ) {
			$this->maybe_reverse_points( $order, $to_status, $referral_row );
		}
	}

	/**
	 * Handle self-order reward status transitions.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $to_status New status.
	 * @return void
	 */
	protected function handle_self_order_status_change( $order, $to_status ) {
		$reward_status = sanitize_key( $this->settings->get( 'self_points_order_status', 'completed' ) );
		$processed     = 'yes' === $order->get_meta( '_rrp_self_reward_processed', true );
		$reversed      = 'yes' === $order->get_meta( '_rrp_self_reward_reversed', true );

		if ( ! $processed && ! $reversed && $reward_status === sanitize_key( $to_status ) ) {
			$this->award_self_order_points( $order );
			return;
		}

		if ( $processed && ! $reversed ) {
			$this->maybe_reverse_self_order_points( $order, $to_status );
		}
	}

	/**
	 * Award referral points.
	 *
	 * @param WC_Order $order        Order object.
	 * @param array    $referral_row Referral row.
	 * @return void
	 */
	protected function award_points( $order, $referral_row ) {
		if ( ! $this->points_manager->is_enabled() ) {
			$this->logger->log( 'info', 'Система баллов отключена. Начисление пропущено.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$referrer_profile = $this->profile_manager->get_profile( $referral_row['referrer_profile_id'] );

		if ( ! $referrer_profile ) {
			$this->logger->log( 'error', 'Не найден профиль реферера для начисления.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$points = $this->points_manager->calculate_reward_points( $order );

		if ( (float) $points <= 0 ) {
			$this->logger->log( 'warning', 'Рассчитано 0 баллов. Начисление пропущено.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$description = sprintf(
			/* translators: %s: order number. */
			__( 'Реферальное начисление за заказ #%s', 'relod-referral-points' ),
			$order->get_order_number()
		);

		$added = $this->points_manager->add_points( $referrer_profile, $order, (int) $referral_row['id'], $points, $description, 'referral_reward' );

		if ( ! $added ) {
			return;
		}

		$order->update_meta_data( '_rrp_reward_processed', 'yes' );
		$order->update_meta_data( '_rrp_reward_points', $points );
		$order->update_meta_data( '_rrp_referral_status', 'awarded' );
		$order->save();

		$this->tracker->update_referral_row(
			(int) $referral_row['id'],
			array(
				'points_awarded' => $points,
				'status'         => 'awarded',
				'reason'         => 'awarded_on_status',
				'updated_at'     => current_time( 'mysql' ),
				'awarded_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Award points to the buyer for their own order.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	protected function award_self_order_points( $order ) {
		if ( ! $this->points_manager->is_enabled() || 'yes' !== $this->settings->get( 'self_points_enabled', 'yes' ) ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_rrp_self_reward_processed', true ) ) {
			return;
		}

		if ( 'yes' === $this->settings->get( 'self_points_require_user', 'no' ) && ! $order->get_user_id() ) {
			$this->logger->log( 'info', 'Баллы за свой заказ пропущены: требуется авторизованный пользователь.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$referral_status = $order->get_meta( '_rrp_referral_status', true );

		if ( 'yes' === $this->settings->get( 'self_points_skip_if_referral', 'yes' ) && in_array( $referral_status, array( 'pending', 'awarded', 'reversed' ), true ) ) {
			$this->logger->log( 'info', 'Баллы за свой заказ пропущены: заказ уже участвует в реферальной программе.', array( 'order_id' => $order->get_id(), 'referral_status' => $referral_status ) );
			return;
		}

		$profile = $this->profile_manager->get_or_create_profile_for_order( $order );

		if ( ! $profile ) {
			$this->logger->log( 'error', 'Не удалось определить профиль покупателя для начисления за собственный заказ.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$points = $this->points_manager->calculate_self_order_points( $order );

		if ( (float) $points <= 0 ) {
			$this->logger->log( 'info', 'Баллы за свой заказ не начислены: рассчитано 0 баллов.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$description = sprintf(
			/* translators: %s: order number. */
			__( 'Начисление баллов за собственный заказ #%s', 'relod-referral-points' ),
			$order->get_order_number()
		);

		$added = $this->points_manager->add_points( $profile, $order, 0, $points, $description, 'self_order_reward' );

		if ( ! $added ) {
			return;
		}

		$order->update_meta_data( '_rrp_self_reward_processed', 'yes' );
		$order->update_meta_data( '_rrp_self_reward_reversed', 'no' );
		$order->update_meta_data( '_rrp_self_reward_points', $points );
		$order->update_meta_data( '_rrp_self_reward_profile_id', (int) $profile['id'] );
		$order->save();
	}

	/**
	 * Reverse points if order gets cancelled or refunded after award.
	 *
	 * @param WC_Order   $order        Order object.
	 * @param string     $to_status    New status.
	 * @param array|null $referral_row Referral row.
	 * @return void
	 */
	protected function maybe_reverse_points( $order, $to_status, $referral_row ) {
		if ( ! $referral_row ) {
			return;
		}

		$should_reverse = false;
		$reason         = '';

		if ( 'cancelled' === sanitize_key( $to_status ) && 'yes' === $this->settings->get( 'reverse_on_cancel', 'yes' ) ) {
			$should_reverse = true;
			$reason         = 'cancelled';
		}

		if ( 'refunded' === sanitize_key( $to_status ) && 'yes' === $this->settings->get( 'reverse_on_refund', 'yes' ) ) {
			$should_reverse = true;
			$reason         = 'refunded';
		}

		if ( ! $should_reverse ) {
			return;
		}

		$points = wc_format_decimal( $order->get_meta( '_rrp_reward_points', true ), 4 );
		if ( (float) $points <= 0 ) {
			$points = wc_format_decimal( $referral_row['points_awarded'], 4 );
		}

		if ( (float) $points <= 0 ) {
			return;
		}

		$referrer_profile = $this->profile_manager->get_profile( $referral_row['referrer_profile_id'] );
		if ( ! $referrer_profile ) {
			return;
		}

		$description = sprintf(
			/* translators: 1: order number, 2: reason. */
			__( 'Списание баллов за заказ #%1$s (%2$s)', 'relod-referral-points' ),
			$order->get_order_number(),
			$reason
		);

		$ok = $this->points_manager->subtract_points( $referrer_profile, $order, (int) $referral_row['id'], $points, $description );

		if ( ! $ok ) {
			return;
		}

		$order->update_meta_data( '_rrp_reward_reversed', 'yes' );
		$order->update_meta_data( '_rrp_referral_status', 'reversed' );
		$order->save();

		$this->tracker->update_referral_row(
			(int) $referral_row['id'],
			array(
				'status'      => 'reversed',
				'reason'      => $reason,
				'updated_at'  => current_time( 'mysql' ),
				'reversed_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Reverse points for the customer's own order when needed.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $to_status New status.
	 * @return void
	 */
	protected function maybe_reverse_self_order_points( $order, $to_status ) {
		$should_reverse = false;
		$reason         = '';

		if ( 'cancelled' === sanitize_key( $to_status ) && 'yes' === $this->settings->get( 'self_points_reverse_on_cancel', 'yes' ) ) {
			$should_reverse = true;
			$reason         = 'cancelled';
		}

		if ( 'refunded' === sanitize_key( $to_status ) && 'yes' === $this->settings->get( 'self_points_reverse_on_refund', 'yes' ) ) {
			$should_reverse = true;
			$reason         = 'refunded';
		}

		if ( ! $should_reverse ) {
			return;
		}

		$points = wc_format_decimal( $order->get_meta( '_rrp_self_reward_points', true ), 4 );

		if ( (float) $points <= 0 ) {
			return;
		}

		$profile_id = absint( $order->get_meta( '_rrp_self_reward_profile_id', true ) );
		$profile    = $profile_id ? $this->profile_manager->get_profile( $profile_id ) : $this->profile_manager->get_or_create_profile_for_order( $order );

		if ( ! $profile ) {
			return;
		}

		$description = sprintf(
			/* translators: 1: order number, 2: reason. */
			__( 'Списание баллов за собственный заказ #%1$s (%2$s)', 'relod-referral-points' ),
			$order->get_order_number(),
			$reason
		);

		$ok = $this->points_manager->subtract_points( $profile, $order, 0, $points, $description, 'self_order_reversal' );

		if ( ! $ok ) {
			return;
		}

		$order->update_meta_data( '_rrp_self_reward_reversed', 'yes' );
		$order->save();
	}

	/**
	 * Render referral info on admin order screen.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function render_admin_order_referral_info( $order ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $order instanceof WC_Order ) {
			return;
		}

		$referrer_profile_id  = absint( $order->get_meta( '_rrp_referrer_profile_id', true ) );
		$referral_code        = $order->get_meta( '_rrp_referral_code', true );
		$referral_status      = $order->get_meta( '_rrp_referral_status', true );
		$referral_click_id    = absint( $order->get_meta( '_rrp_referral_click_id', true ) );
		$referral_reason      = $order->get_meta( '_rrp_referral_reason', true );
		$reward_points        = $order->get_meta( '_rrp_reward_points', true );
		$referrer_profile     = $referrer_profile_id ? $this->profile_manager->get_profile( $referrer_profile_id ) : null;
		$self_reward_profile_id = absint( $order->get_meta( '_rrp_self_reward_profile_id', true ) );
		$self_reward_profile  = $self_reward_profile_id ? $this->profile_manager->get_profile( $self_reward_profile_id ) : null;
		$self_reward_points   = $order->get_meta( '_rrp_self_reward_points', true );
		$self_reward_processed = 'yes' === $order->get_meta( '_rrp_self_reward_processed', true );
		$self_reward_reversed = 'yes' === $order->get_meta( '_rrp_self_reward_reversed', true );

		echo '<div class="rrp-admin-order-box">';
		echo '<h4>' . esc_html__( 'Referral & Points', 'relod-referral-points' ) . '</h4>';
		echo '<p><strong>' . esc_html__( 'Первый заказ:', 'relod-referral-points' ) . '</strong> ' . esc_html( 'yes' === $order->get_meta( '_rrp_customer_is_first_order', true ) ? __( 'Да', 'relod-referral-points' ) : __( 'Нет', 'relod-referral-points' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Статус реферала:', 'relod-referral-points' ) . '</strong> ' . esc_html( $referral_status ? $referral_status : '—' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Причина:', 'relod-referral-points' ) . '</strong> ' . esc_html( $referral_reason ? $referral_reason : '—' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Реферальный код:', 'relod-referral-points' ) . '</strong> ' . esc_html( $referral_code ? $referral_code : '—' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'ID перехода:', 'relod-referral-points' ) . '</strong> ' . esc_html( $referral_click_id ? $referral_click_id : '—' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Реферер:', 'relod-referral-points' ) . '</strong> ' . esc_html( $referrer_profile ? $this->profile_manager->get_profile_label( $referrer_profile ) : '—' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Реферально начислено баллов:', 'relod-referral-points' ) . '</strong> ' . esc_html( $reward_points ? RRP_Utils::format_points( $reward_points ) : '0' ) . '</p>';
		echo '<hr>';
		echo '<p><strong>' . esc_html__( 'Баллы за свой заказ:', 'relod-referral-points' ) . '</strong> ' . esc_html( $self_reward_points ? RRP_Utils::format_points( $self_reward_points ) : '0' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Получатель баллов за свой заказ:', 'relod-referral-points' ) . '</strong> ' . esc_html( $self_reward_profile ? $this->profile_manager->get_profile_label( $self_reward_profile ) : '—' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Статус начисления за свой заказ:', 'relod-referral-points' ) . '</strong> ' . esc_html( $self_reward_reversed ? __( 'Списано', 'relod-referral-points' ) : ( $self_reward_processed ? __( 'Начислено', 'relod-referral-points' ) : __( 'Не начислено', 'relod-referral-points' ) ) ) . '</p>';
		echo '</div>';
	}
}
