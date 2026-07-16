<?php
/**
 * Referral profile manager.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Profile_Manager {

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
	 * Profiles table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Constructor.
	 *
	 * @param RRP_Settings $settings Settings.
	 * @param RRP_Logger   $logger   Logger.
	 */
	public function __construct( $settings, $logger ) {
		global $wpdb;

		$this->settings = $settings;
		$this->logger   = $logger;
		$this->table    = $wpdb->prefix . 'rrp_profiles';

		add_action( 'user_register', array( $this, 'bind_profile_to_registered_user' ), 20 );
		add_action( 'wp_login', array( $this, 'bind_profile_on_login' ), 20, 2 );
	}

	/**
	 * Normalize email.
	 *
	 * @param string $email Raw email.
	 * @return string
	 */
	public function normalize_email( $email ) {
		$email = sanitize_email( wp_unslash( (string) $email ) );
		$email = strtolower( trim( $email ) );

		return $email;
	}

	/**
	 * Get profile by ID.
	 *
	 * @param int $profile_id Profile ID.
	 * @return array|null
	 */
	public function get_profile( $profile_id ) {
		global $wpdb;

		$profile_id = absint( $profile_id );

		if ( ! $profile_id ) {
			return null;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $profile_id );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ? $this->cast_profile( $row ) : null;
	}

	/**
	 * Get profile by email.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public function get_profile_by_email( $email ) {
		global $wpdb;

		$email = $this->normalize_email( $email );

		if ( ! $email ) {
			return null;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE email = %s", $email );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ? $this->cast_profile( $row ) : null;
	}

	/**
	 * Get profile by referral code.
	 *
	 * @param string $code Referral code.
	 * @return array|null
	 */
	public function get_profile_by_code( $code ) {
		global $wpdb;

		$code = sanitize_text_field( wp_unslash( (string) $code ) );
		$code = strtoupper( trim( $code ) );

		if ( ! $code ) {
			return null;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE referral_code = %s", $code );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ? $this->cast_profile( $row ) : null;
	}

	/**
	 * Get profile by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array|null
	 */
	public function get_profile_by_user_id( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return null;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE user_id = %d", $user_id );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		if ( $row ) {
			return $this->cast_profile( $row );
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user || empty( $user->user_email ) ) {
			return null;
		}

		$profile = $this->get_or_create_profile_by_email( $user->user_email, $user_id );

		if ( $profile ) {
			$this->link_profile_to_user( $profile['id'], $user_id );
		}

		return $profile;
	}

	/**
	 * Get or create profile by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array|null
	 */
	public function get_or_create_profile_for_user( $user_id ) {
		return $this->get_profile_by_user_id( $user_id );
	}

	/**
	 * Get or create profile by email.
	 *
	 * @param string $email   Email.
	 * @param int    $user_id Optional linked user ID.
	 * @return array|null
	 */
	public function get_or_create_profile_by_email( $email, $user_id = 0 ) {
		global $wpdb;

		$email = $this->normalize_email( $email );
		$user_id = absint( $user_id );

		if ( ! $email ) {
			return null;
		}

		$existing = $this->get_profile_by_email( $email );

		if ( $existing ) {
			if ( $user_id && (int) $existing['user_id'] !== $user_id ) {
				$this->link_profile_to_user( $existing['id'], $user_id );
				$existing = $this->get_profile( $existing['id'] );
			}

			return $existing;
		}

		$code = $this->generate_unique_referral_code();
		$now  = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'user_id'        => $user_id ? $user_id : null,
				'email'          => $email,
				'referral_code'  => $code,
				'points_balance' => 0,
				'status'         => 'active',
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->log(
				'error',
				'Не удалось создать реферальный профиль.',
				array(
					'email'   => $email,
					'user_id' => $user_id,
				)
			);

			return $this->get_profile_by_email( $email );
		}

		$profile_id = (int) $wpdb->insert_id;

		if ( $user_id ) {
			update_user_meta( $user_id, '_rrp_profile_id', $profile_id );
		}

		$this->logger->log(
			'info',
			'Создан реферальный профиль.',
			array(
				'profile_id' => $profile_id,
				'email'      => $email,
				'user_id'    => $user_id,
			)
		);

		return $this->get_profile( $profile_id );
	}

	/**
	 * Link profile to user.
	 *
	 * @param int $profile_id Profile ID.
	 * @param int $user_id    User ID.
	 * @return void
	 */
	public function link_profile_to_user( $profile_id, $user_id ) {
		global $wpdb;

		$profile_id = absint( $profile_id );
		$user_id    = absint( $user_id );

		if ( ! $profile_id || ! $user_id ) {
			return;
		}

		$profile = $this->get_profile( $profile_id );
		$user    = get_user_by( 'id', $user_id );

		if ( ! $profile || ! $user ) {
			return;
		}

		$user_email             = $this->normalize_email( $user->user_email );
		$profile_with_user_email = $this->get_profile_by_email( $user_email );

		if ( $profile_with_user_email && (int) $profile_with_user_email['id'] !== $profile_id ) {
			update_user_meta( $user_id, '_rrp_profile_id', (int) $profile_with_user_email['id'] );
			return;
		}

		$wpdb->update(
			$this->table,
			array(
				'user_id'    => $user_id,
				'email'      => $user_email,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $profile_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		update_user_meta( $user_id, '_rrp_profile_id', $profile_id );
	}

	/**
	 * Update profile balance.
	 *
	 * @param int    $profile_id Profile ID.
	 * @param string $new_balance New balance.
	 * @return void
	 */
	public function set_balance( $profile_id, $new_balance ) {
		global $wpdb;

		$profile_id   = absint( $profile_id );
		$new_balance  = wc_format_decimal( $new_balance, 4 );

		$wpdb->update(
			$this->table,
			array(
				'points_balance' => $new_balance,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $profile_id ),
			array( '%f', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update last order timestamp.
	 *
	 * @param int $profile_id Profile ID.
	 * @return void
	 */
	public function touch_last_order( $profile_id ) {
		global $wpdb;

		$profile_id = absint( $profile_id );

		if ( ! $profile_id ) {
			return;
		}

		$wpdb->update(
			$this->table,
			array(
				'last_order_at' => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $profile_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Generate unique referral code.
	 *
	 * @return string
	 */
	public function generate_unique_referral_code() {
		for ( $i = 0; $i < 20; $i++ ) {
			$code = strtoupper( wp_generate_password( 10, false, false ) );
			$code = preg_replace( '/[^A-Z0-9]/', '', $code );

			if ( strlen( $code ) < 6 ) {
				$code .= strtoupper( wp_generate_password( 6, false, false ) );
				$code = preg_replace( '/[^A-Z0-9]/', '', $code );
			}

			$code = substr( $code, 0, 12 );

			if ( ! $this->get_profile_by_code( $code ) ) {
				return $code;
			}
		}

		return strtoupper( wp_generate_password( 12, false, false ) );
	}

	/**
	 * Build referral link by profile.
	 *
	 * @param array $profile Profile row.
	 * @return string
	 */
	public function build_referral_link( $profile ) {
		if ( empty( $profile['referral_code'] ) ) {
			return '';
		}

		$param = $this->settings->get( 'referral_url_param', 'ref' );

		return add_query_arg(
			array(
				$param => rawurlencode( $profile['referral_code'] ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Build profile for order email.
	 *
	 * @param WC_Order $order Order object.
	 * @return array|null
	 */
	public function get_or_create_profile_for_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		$email   = $this->normalize_email( $order->get_billing_email() );
		$user_id = $order->get_user_id() ? absint( $order->get_user_id() ) : 0;

		return $this->get_or_create_profile_by_email( $email, $user_id );
	}

	/**
	 * Get profile display label.
	 *
	 * @param array $profile Profile data.
	 * @return string
	 */
	public function get_profile_label( $profile ) {
		if ( empty( $profile ) ) {
			return '';
		}

		if ( ! empty( $profile['user_id'] ) ) {
			$user = get_user_by( 'id', $profile['user_id'] );

			if ( $user ) {
				return sprintf( '%s (%s)', $user->display_name, $profile['email'] );
			}
		}

		return $profile['email'];
	}

	/**
	 * Bind profile on user registration.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function bind_profile_to_registered_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$profile = $this->get_or_create_profile_by_email( $user->user_email, $user_id );

		if ( $profile ) {
			$this->link_profile_to_user( $profile['id'], $user_id );
		}
	}

	/**
	 * Bind profile on login.
	 *
	 * @param string  $user_login User login.
	 * @param WP_User $user       User object.
	 * @return void
	 */
	public function bind_profile_on_login( $user_login, $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$profile = $this->get_or_create_profile_by_email( $user->user_email, $user->ID );

		if ( $profile ) {
			$this->link_profile_to_user( $profile['id'], $user->ID );
		}
	}

	/**
	 * Cast profile values.
	 *
	 * @param array $profile Raw profile.
	 * @return array
	 */
	protected function cast_profile( $profile ) {
		$profile['id']             = (int) $profile['id'];
		$profile['user_id']        = isset( $profile['user_id'] ) ? (int) $profile['user_id'] : 0;
		$profile['points_balance'] = wc_format_decimal( $profile['points_balance'], 4 );

		return $profile;
	}
}
