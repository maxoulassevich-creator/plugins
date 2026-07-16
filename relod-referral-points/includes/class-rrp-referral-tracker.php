<?php
/**
 * Referral tracking.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Referral_Tracker {

	/**
	 * Cookie name.
	 *
	 * @var string
	 */
	const COOKIE_NAME = 'rrp_referral';

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
	 * Referrals table.
	 *
	 * @var string
	 */
	protected $referrals_table;

	/**
	 * Referral clicks table.
	 *
	 * @var string
	 */
	protected $clicks_table;

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
		$this->referrals_table = $wpdb->prefix . 'rrp_referrals';
		$this->clicks_table    = $wpdb->prefix . 'rrp_referral_clicks';

		add_action( 'init', array( $this, 'capture_referral_from_request' ), 5 );
		add_action( 'wp', array( $this, 'sync_referral_to_wc_session' ), 5 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_referral_to_order' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'clear_tracking_after_checkout' ), 30, 3 );
	}

	/**
	 * Whether tracking is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->settings->get( 'plugin_enabled', 'yes' ) && 'yes' === $this->settings->get( 'referrals_enabled', 'yes' );
	}

	/**
	 * Capture referral from URL parameter.
	 *
	 * @return void
	 */
	public function capture_referral_from_request() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$param = $this->settings->get( 'referral_url_param', 'ref' );

		if ( empty( $_GET[ $param ] ) ) {
			return;
		}

		$code    = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
		$profile = $this->profile_manager->get_profile_by_code( $code );

		if ( ! $profile ) {
			$this->logger->log( 'warning', 'Передан неизвестный реферальный код.', array( 'code' => $code ) );
			return;
		}

		if ( $this->is_self_referral_by_current_user( $profile ) ) {
			$this->logger->log(
				'warning',
				'Заблокирована попытка самореферала через URL-переход.',
				array(
					'profile_id' => $profile['id'],
					'user_id'    => get_current_user_id(),
				)
			);
			return;
		}

		$click_id = $this->record_referral_click( $profile );
		$this->store_referral_context( $profile, $click_id );

		$this->logger->log(
			'info',
			'Реферальный переход сохранён.',
			array(
				'profile_id' => $profile['id'],
				'code'       => $profile['referral_code'],
				'click_id'   => $click_id,
			)
		);
	}

	/**
	 * Sync referral context into WooCommerce session.
	 *
	 * @return void
	 */
	public function sync_referral_to_wc_session() {
		$context = $this->get_cookie_context();

		if ( ! $context || empty( $context['referrer_profile_id'] ) ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'rrp_referrer_profile_id', (int) $context['referrer_profile_id'] );
			WC()->session->set( 'rrp_referral_code', sanitize_text_field( $context['referral_code'] ) );
			WC()->session->set( 'rrp_referral_expires_at', absint( $context['expires_at'] ) );
			WC()->session->set( 'rrp_referral_click_id', absint( $context['click_id'] ) );
		}

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_rrp_pending_referrer_profile_id', (int) $context['referrer_profile_id'] );
			update_user_meta( get_current_user_id(), '_rrp_pending_referral_code', sanitize_text_field( $context['referral_code'] ) );
			update_user_meta( get_current_user_id(), '_rrp_pending_referral_click_id', absint( $context['click_id'] ) );
		}
	}

	/**
	 * Attach referral information to order.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $data  Checkout data.
	 * @return void
	 */
	public function attach_referral_to_order( $order, $data ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$billing_email = $this->profile_manager->normalize_email( $order->get_billing_email() );
		$is_first      = $billing_email ? $this->is_first_order_by_email( $billing_email, $order->get_id() ) : false;

		$order->update_meta_data( '_rrp_customer_is_first_order', $is_first ? 'yes' : 'no' );

		if ( ! $this->is_enabled() ) {
			$order->update_meta_data( '_rrp_referral_status', 'disabled' );
			$order->update_meta_data( '_rrp_referral_reason', 'referrals_disabled' );
			return;
		}

		$context = $this->get_active_referral_context();

		if ( empty( $context['referrer_profile_id'] ) || empty( $context['referral_code'] ) ) {
			$order->update_meta_data( '_rrp_referral_status', 'none' );
			$order->update_meta_data( '_rrp_referral_reason', 'no_referral_context' );
			return;
		}

		$click_id         = ! empty( $context['click_id'] ) ? absint( $context['click_id'] ) : 0;
		$referrer_profile = $this->profile_manager->get_profile( $context['referrer_profile_id'] );
		$referred_profile = $this->profile_manager->get_or_create_profile_for_order( $order );
		$result           = $this->validate_referral_for_order( $order, $referrer_profile, $referred_profile, $is_first );

		$order->update_meta_data( '_rrp_referrer_profile_id', ! empty( $referrer_profile['id'] ) ? absint( $referrer_profile['id'] ) : 0 );
		$order->update_meta_data( '_rrp_referral_code', sanitize_text_field( $context['referral_code'] ) );
		$order->update_meta_data( '_rrp_referral_click_id', $click_id );
		$order->update_meta_data( '_rrp_referral_status', $result['status'] );
		$order->update_meta_data( '_rrp_referral_reason', $result['reason'] );
		$order->update_meta_data( '_rrp_reward_processed', 'no' );
		$order->update_meta_data( '_rrp_reward_reversed', 'no' );
		$order->update_meta_data( '_rrp_reward_points', '0' );

		$referral_id = $this->upsert_referral_row( $order, $referrer_profile, $referred_profile, $result, $context['referral_code'], $click_id );

		if ( $referral_id ) {
			$order->update_meta_data( '_rrp_referral_row_id', $referral_id );
		}

		if ( $click_id ) {
			$this->mark_click_converted( $click_id, $order->get_id() );
		}

		if ( $order->get_user_id() ) {
			delete_user_meta( $order->get_user_id(), '_rrp_pending_referrer_profile_id' );
			delete_user_meta( $order->get_user_id(), '_rrp_pending_referral_code' );
			delete_user_meta( $order->get_user_id(), '_rrp_pending_referral_click_id' );
		}
	}

	/**
	 * Clear client-side tracking after checkout has created the order.
	 *
	 * @param int      $order_id     Order ID.
	 * @param array    $posted_data  Posted data.
	 * @param WC_Order $order        Order object.
	 * @return void
	 */
	public function clear_tracking_after_checkout( $order_id, $posted_data, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$status = $order->get_meta( '_rrp_referral_status', true );

		if ( in_array( $status, array( 'pending', 'rejected', 'awarded', 'reversed', 'none', 'disabled' ), true ) ) {
			$this->clear_tracking();
		}
	}

	/**
	 * Validate referral for order.
	 *
	 * @param WC_Order   $order            Order.
	 * @param array|null $referrer_profile Referrer profile.
	 * @param array|null $referred_profile Referred profile.
	 * @param bool       $is_first         First order flag.
	 * @return array
	 */
	public function validate_referral_for_order( $order, $referrer_profile, $referred_profile, $is_first ) {
		$billing_email = $this->profile_manager->normalize_email( $order->get_billing_email() );

		if ( ! $referrer_profile ) {
			return array(
				'status' => 'rejected',
				'reason' => 'referrer_not_found',
			);
		}

		if ( 'yes' !== $this->settings->get( 'allow_guest_participation', 'yes' ) && ! $order->get_user_id() ) {
			return array(
				'status' => 'rejected',
				'reason' => 'guest_not_allowed',
			);
		}

		if ( ! $is_first ) {
			return array(
				'status' => 'rejected',
				'reason' => 'not_first_order',
			);
		}

		if ( 'yes' === $this->settings->get( 'prevent_self_referral', 'yes' ) ) {
			if ( $billing_email && $billing_email === $referrer_profile['email'] ) {
				return array(
					'status' => 'rejected',
					'reason' => 'self_referral_email',
				);
			}

			if ( $order->get_user_id() && ! empty( $referrer_profile['user_id'] ) && (int) $order->get_user_id() === (int) $referrer_profile['user_id'] ) {
				return array(
					'status' => 'rejected',
					'reason' => 'self_referral_user',
				);
			}
		}

		if ( $referred_profile && (int) $referred_profile['id'] === (int) $referrer_profile['id'] ) {
			return array(
				'status' => 'rejected',
				'reason' => 'same_profile',
			);
		}

		return array(
			'status' => 'pending',
			'reason' => 'eligible',
		);
	}

	/**
	 * Check if billing email has previous orders.
	 *
	 * @param string $email            Billing email.
	 * @param int    $current_order_id Current order ID.
	 * @return bool
	 */
	public function is_first_order_by_email( $email, $current_order_id = 0 ) {
		$email            = $this->profile_manager->normalize_email( $email );
		$current_order_id = absint( $current_order_id );

		if ( ! $email ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'limit'         => 10,
				'return'        => 'ids',
				'status'        => array_keys( wc_get_order_statuses() ),
			)
		);

		if ( empty( $orders ) ) {
			return true;
		}

		foreach ( $orders as $order_id ) {
			if ( $current_order_id && (int) $order_id === $current_order_id ) {
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Get current referral context.
	 *
	 * @return array|null
	 */
	public function get_active_referral_context() {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			$profile_id = absint( get_user_meta( $user_id, '_rrp_pending_referrer_profile_id', true ) );
			$code       = sanitize_text_field( get_user_meta( $user_id, '_rrp_pending_referral_code', true ) );
			$click_id   = absint( get_user_meta( $user_id, '_rrp_pending_referral_click_id', true ) );

			if ( $profile_id && $code ) {
				return array(
					'referrer_profile_id' => $profile_id,
					'referral_code'       => $code,
					'click_id'            => $click_id,
					'source'              => 'user_meta',
				);
			}
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$profile_id = absint( WC()->session->get( 'rrp_referrer_profile_id', 0 ) );
			$code       = sanitize_text_field( WC()->session->get( 'rrp_referral_code', '' ) );
			$click_id   = absint( WC()->session->get( 'rrp_referral_click_id', 0 ) );

			if ( $profile_id && $code ) {
				return array(
					'referrer_profile_id' => $profile_id,
					'referral_code'       => $code,
					'click_id'            => $click_id,
					'source'              => 'wc_session',
				);
			}
		}

		$cookie = $this->get_cookie_context();

		if ( $cookie ) {
			$cookie['source'] = 'cookie';
			return $cookie;
		}

		return null;
	}

	/**
	 * Get referral row by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public function get_referral_by_order_id( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return null;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$this->referrals_table} WHERE order_id = %d", $order_id );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * Update referral row.
	 *
	 * @param int   $referral_id Referral row ID.
	 * @param array $data        Update data.
	 * @return void
	 */
	public function update_referral_row( $referral_id, $data ) {
		global $wpdb;

		$referral_id = absint( $referral_id );

		if ( ! $referral_id || empty( $data ) || ! is_array( $data ) ) {
			return;
		}

		$allowed = array(
			'click_id'        => '%d',
			'points_awarded' => '%f',
			'status'         => '%s',
			'reason'         => '%s',
			'updated_at'     => '%s',
			'awarded_at'     => '%s',
			'reversed_at'    => '%s',
		);

		$update = array();
		$format = array();

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed[ $key ] ) ) {
				$update[ $key ] = $value;
				$format[]       = $allowed[ $key ];
			}
		}

		if ( empty( $update ) ) {
			return;
		}

		$wpdb->update( $this->referrals_table, $update, array( 'id' => $referral_id ), $format, array( '%d' ) );
	}

	/**
	 * Save or update referral row.
	 *
	 * @param WC_Order   $order            Order.
	 * @param array|null $referrer_profile Referrer profile.
	 * @param array|null $referred_profile Referred profile.
	 * @param array      $result           Validation result.
	 * @param string     $referral_code    Referral code.
	 * @param int        $click_id         Referral click row ID.
	 * @return int
	 */
	protected function upsert_referral_row( $order, $referrer_profile, $referred_profile, $result, $referral_code, $click_id = 0 ) {
		global $wpdb;

		if ( ! $referrer_profile ) {
			return 0;
		}

		$existing = $this->get_referral_by_order_id( $order->get_id() );
		$payload  = array(
			'order_id'             => $order->get_id(),
			'click_id'             => $click_id ? absint( $click_id ) : null,
			'referrer_profile_id'  => $referrer_profile['id'],
			'referred_profile_id'  => $referred_profile ? $referred_profile['id'] : null,
			'referred_user_id'     => $order->get_user_id() ? $order->get_user_id() : null,
			'referred_email'       => $this->profile_manager->normalize_email( $order->get_billing_email() ),
			'referral_code'        => sanitize_text_field( $referral_code ),
			'order_total'          => wc_format_decimal( $order->get_total(), 4 ),
			'reward_rate'          => wc_format_decimal( $this->settings->get( 'reward_rate', '0' ), 4 ),
			'points_awarded'       => 0,
			'status'               => $result['status'],
			'reason'               => $result['reason'],
			'updated_at'           => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update(
				$this->referrals_table,
				$payload,
				array( 'id' => $existing['id'] ),
				array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing['id'];
		}

		$payload['created_at'] = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$this->referrals_table,
			$payload,
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->log(
				'error',
				'Не удалось записать строку реферала.',
				array(
					'order_id' => $order->get_id(),
				)
			);

			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Record a referral click.
	 *
	 * @param array $profile Referrer profile.
	 * @return int
	 */
	public function record_referral_click( $profile ) {
		global $wpdb;

		if ( empty( $profile['id'] ) || empty( $profile['referral_code'] ) ) {
			return 0;
		}

		$user_id = get_current_user_id();
		$ip      = $this->get_server_value( 'REMOTE_ADDR' );
		$agent   = $this->get_server_value( 'HTTP_USER_AGENT' );

		$inserted = $wpdb->insert(
			$this->clicks_table,
			array(
				'referrer_profile_id' => (int) $profile['id'],
				'referral_code'       => sanitize_text_field( $profile['referral_code'] ),
				'user_id'             => $user_id ? $user_id : null,
				'visitor_hash'        => $this->hash_context_value( ( $user_id ? 'user:' . $user_id : 'guest' ) . '|' . $ip . '|' . $agent ),
				'ip_hash'             => $ip ? $this->hash_context_value( $ip ) : null,
				'user_agent_hash'     => $agent ? $this->hash_context_value( $agent ) : null,
				'landing_url'         => $this->get_request_url(),
				'referrer_url'        => $this->get_request_referrer(),
				'status'              => 'clicked',
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->log( 'error', 'Не удалось записать переход по реферальной ссылке.', array( 'profile_id' => $profile['id'] ) );
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Mark click as converted by order.
	 *
	 * @param int $click_id Click ID.
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function mark_click_converted( $click_id, $order_id ) {
		global $wpdb;

		$click_id = absint( $click_id );
		$order_id = absint( $order_id );

		if ( ! $click_id || ! $order_id ) {
			return;
		}

		$wpdb->update(
			$this->clicks_table,
			array(
				'converted_order_id' => $order_id,
				'converted_at'       => current_time( 'mysql' ),
				'status'             => 'converted',
			),
			array( 'id' => $click_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get aggregate stats grouped by referral link.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_referral_link_stats( $limit = 100 ) {
		global $wpdb;

		$limit          = max( 1, absint( $limit ) );
		$profiles_table = $wpdb->prefix . 'rrp_profiles';
		$query          = $wpdb->prepare(
			"SELECT p.id AS referrer_profile_id, p.referral_code, p.email, p.user_id, p.created_at AS profile_created_at,
			COUNT(c.id) AS clicks_count,
			SUM(CASE WHEN c.converted_order_id IS NULL THEN 0 ELSE 1 END) AS conversions_count,
			MAX(c.created_at) AS last_click_at,
			MAX(c.converted_at) AS last_conversion_at
			FROM {$profiles_table} p
			LEFT JOIN {$this->clicks_table} c ON c.referrer_profile_id = p.id
			GROUP BY p.id, p.referral_code, p.email, p.user_id, p.created_at
			ORDER BY COALESCE(MAX(c.created_at), p.created_at) DESC
			LIMIT %d",
			$limit
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent referral click history.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_recent_clicks( $limit = 200 ) {
		global $wpdb;

		$limit          = max( 1, absint( $limit ) );
		$profiles_table = $wpdb->prefix . 'rrp_profiles';
		$query          = $wpdb->prepare(
			"SELECT c.*, p.email, p.user_id AS referrer_user_id
			FROM {$this->clicks_table} c
			LEFT JOIN {$profiles_table} p ON p.id = c.referrer_profile_id
			ORDER BY c.id DESC
			LIMIT %d",
			$limit
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get click stats for one profile.
	 *
	 * @param int $profile_id Profile ID.
	 * @return array
	 */
	public function get_stats_for_profile( $profile_id ) {
		global $wpdb;

		$profile_id = absint( $profile_id );

		if ( ! $profile_id ) {
			return array(
				'clicks_count'      => 0,
				'conversions_count' => 0,
			);
		}

		$query = $wpdb->prepare(
			"SELECT COUNT(id) AS clicks_count, SUM(CASE WHEN converted_order_id IS NULL THEN 0 ELSE 1 END) AS conversions_count FROM {$this->clicks_table} WHERE referrer_profile_id = %d",
			$profile_id
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return array(
			'clicks_count'      => isset( $row['clicks_count'] ) ? absint( $row['clicks_count'] ) : 0,
			'conversions_count' => isset( $row['conversions_count'] ) ? absint( $row['conversions_count'] ) : 0,
		);
	}

	/**
	 * Store referral context into cookie/session/user meta.
	 *
	 * @param array $profile  Referrer profile.
	 * @param int   $click_id Referral click row ID.
	 * @return void
	 */
	protected function store_referral_context( $profile, $click_id = 0 ) {
		$expires_at = time() + ( absint( $this->settings->get( 'referral_cookie_days', 30 ) ) * DAY_IN_SECONDS );
		$payload    = array(
			'referrer_profile_id' => (int) $profile['id'],
			'referral_code'       => sanitize_text_field( $profile['referral_code'] ),
			'click_id'            => absint( $click_id ),
			'expires_at'          => $expires_at,
		);

		$this->set_cookie_context( $payload );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'rrp_referrer_profile_id', (int) $profile['id'] );
			WC()->session->set( 'rrp_referral_code', sanitize_text_field( $profile['referral_code'] ) );
			WC()->session->set( 'rrp_referral_expires_at', $expires_at );
			WC()->session->set( 'rrp_referral_click_id', absint( $click_id ) );
		}

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_rrp_pending_referrer_profile_id', (int) $profile['id'] );
			update_user_meta( get_current_user_id(), '_rrp_pending_referral_code', sanitize_text_field( $profile['referral_code'] ) );
			update_user_meta( get_current_user_id(), '_rrp_pending_referral_click_id', absint( $click_id ) );
		}
	}

	/**
	 * Check self-referral for current logged user.
	 *
	 * @param array $profile Referrer profile.
	 * @return bool
	 */
	protected function is_self_referral_by_current_user( $profile ) {
		if ( 'yes' !== $this->settings->get( 'prevent_self_referral', 'yes' ) ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$current_profile = $this->profile_manager->get_profile_by_user_id( get_current_user_id() );

		if ( ! $current_profile ) {
			return false;
		}

		return (int) $current_profile['id'] === (int) $profile['id'];
	}

	/**
	 * Get cookie context.
	 *
	 * @return array|null
	 */
	protected function get_cookie_context() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		$raw = wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
		$raw = base64_decode( $raw, true );

		if ( false === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data['payload'] ) || empty( $data['signature'] ) ) {
			return null;
		}

		$payload   = $data['payload'];
		$signature = $data['signature'];
		$expected  = hash_hmac( 'sha256', wp_json_encode( $payload ), wp_salt( 'auth' ) );

		if ( ! hash_equals( $expected, $signature ) ) {
			$this->clear_tracking();
			return null;
		}

		if ( empty( $payload['expires_at'] ) || time() > absint( $payload['expires_at'] ) ) {
			$this->clear_tracking();
			return null;
		}

		return array(
			'referrer_profile_id' => absint( $payload['referrer_profile_id'] ),
			'referral_code'       => sanitize_text_field( $payload['referral_code'] ),
			'click_id'            => ! empty( $payload['click_id'] ) ? absint( $payload['click_id'] ) : 0,
			'expires_at'          => absint( $payload['expires_at'] ),
		);
	}

	/**
	 * Set cookie context.
	 *
	 * @param array $payload Cookie payload.
	 * @return void
	 */
	protected function set_cookie_context( $payload ) {
		$wrapper = array(
			'payload'   => $payload,
			'signature' => hash_hmac( 'sha256', wp_json_encode( $payload ), wp_salt( 'auth' ) ),
		);
		$value   = base64_encode( wp_json_encode( $wrapper ) );
		$expire  = absint( $payload['expires_at'] );

		$this->set_cookie( self::COOKIE_NAME, $value, $expire );
	}

	/**
	 * Clear tracking context.
	 *
	 * @return void
	 */
	public function clear_tracking() {
		$this->set_cookie( self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS );
		unset( $_COOKIE[ self::COOKIE_NAME ] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( 'rrp_referrer_profile_id' );
			WC()->session->__unset( 'rrp_referral_code' );
			WC()->session->__unset( 'rrp_referral_expires_at' );
			WC()->session->__unset( 'rrp_referral_click_id' );
		}

		if ( is_user_logged_in() ) {
			delete_user_meta( get_current_user_id(), '_rrp_pending_referrer_profile_id' );
			delete_user_meta( get_current_user_id(), '_rrp_pending_referral_code' );
			delete_user_meta( get_current_user_id(), '_rrp_pending_referral_click_id' );
		}
	}

	/**
	 * Get sanitized current request URL.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$host = $this->get_server_value( 'HTTP_HOST' );
		$uri  = $this->get_server_value( 'REQUEST_URI' );

		if ( ! $host || ! $uri ) {
			return '';
		}

		return esc_url_raw( ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri );
	}

	/**
	 * Get sanitized HTTP referrer URL.
	 *
	 * @return string
	 */
	protected function get_request_referrer() {
		$referrer = $this->get_server_value( 'HTTP_REFERER' );

		return $referrer ? esc_url_raw( $referrer ) : '';
	}

	/**
	 * Get sanitized server value.
	 *
	 * @param string $key Server key.
	 * @return string
	 */
	protected function get_server_value( $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
	}

	/**
	 * Hash potentially sensitive context values.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function hash_context_value( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'nonce' ) );
	}

	/**
	 * Write cookie.
	 *
	 * @param string $name   Name.
	 * @param string $value  Value.
	 * @param int    $expire Expire timestamp.
	 * @return void
	 */
	protected function set_cookie( $name, $value, $expire ) {
		$secure   = is_ssl();
		$httponly = true;
		$path     = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain   = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expire,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => $secure,
				'httponly' => $httponly,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ $name ] = $value;
	}
}
