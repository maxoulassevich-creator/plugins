<?php
/**
 * My Account endpoint.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_My_Account {

	/**
	 * Settings.
	 *
	 * @var RRP_Settings
	 */
	protected $settings;

	/**
	 * Profile manager.
	 *
	 * @var RRP_Profile_Manager
	 */
	protected $profile_manager;

	/**
	 * Points manager.
	 *
	 * @var RRP_Points_Manager
	 */
	protected $points_manager;

	/**
	 * Logger.
	 *
	 * @var RRP_Logger
	 */
	protected $logger;

	/**
	 * Referral tracker.
	 *
	 * @var RRP_Referral_Tracker
	 */
	protected $tracker;

	/**
	 * Constructor.
	 *
	 * @param RRP_Settings        $settings        Settings.
	 * @param RRP_Profile_Manager $profile_manager Profile manager.
	 * @param RRP_Points_Manager  $points_manager  Points manager.
	 * @param RRP_Logger           $logger          Logger.
	 * @param RRP_Referral_Tracker $tracker         Referral tracker.
	 */
	public function __construct( $settings, $profile_manager, $points_manager, $logger, $tracker ) {
		$this->settings        = $settings;
		$this->profile_manager = $profile_manager;
		$this->points_manager  = $points_manager;
		$this->logger          = $logger;
		$this->tracker         = $tracker;

		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ), 0 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'register_endpoint_content_hook' ), 20 );
	}

	/**
	 * Get endpoint slug.
	 *
	 * @return string
	 */
	public function get_endpoint() {
		return sanitize_title( $this->settings->get( 'account_endpoint', 'referrals' ) );
	}

	/**
	 * Add rewrite endpoint.
	 *
	 * @return void
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( $this->get_endpoint(), EP_ROOT | EP_PAGES );
	}

	/**
	 * Add query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = $this->get_endpoint();
		return $vars;
	}

	/**
	 * Register dynamic endpoint content hook.
	 *
	 * @return void
	 */
	public function register_endpoint_content_hook() {
		add_action( 'woocommerce_account_' . $this->get_endpoint() . '_endpoint', array( $this, 'render_endpoint' ) );
	}

	/**
	 * Add menu item to My Account.
	 *
	 * @param array $items Existing items.
	 * @return array
	 */
	public function add_menu_item( $items ) {
		if ( 'yes' !== $this->settings->get( 'plugin_enabled', 'yes' ) ) {
			return $items;
		}

		$new_items = array();
		$inserted  = false;

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( 'dashboard' === $key ) {
				$new_items[ $this->get_endpoint() ] = __( 'Referral & Points', 'relod-referral-points' );
				$inserted = true;
			}
		}

		if ( ! $inserted ) {
			$new_items[ $this->get_endpoint() ] = __( 'Referral & Points', 'relod-referral-points' );
		}

		return $new_items;
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		wp_enqueue_style( 'rrp-frontend', RRP_PLUGIN_URL . 'assets/css/frontend.css', array(), RRP_VERSION );
		wp_enqueue_script( 'rrp-frontend', RRP_PLUGIN_URL . 'assets/js/frontend.js', array(), RRP_VERSION, true );
	}

	/**
	 * Render endpoint content.
	 *
	 * @return void
	 */
	public function render_endpoint() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Пожалуйста, войдите в аккаунт.', 'relod-referral-points' ) . '</p>';
			return;
		}

		$user_id = get_current_user_id();
		$profile = $this->profile_manager->get_or_create_profile_for_user( $user_id );

		if ( ! $profile ) {
			echo '<p>' . esc_html__( 'Не удалось загрузить реферальный профиль.', 'relod-referral-points' ) . '</p>';
			return;
		}

		$referral_link = $this->profile_manager->build_referral_link( $profile );
		$balance       = $this->points_manager->get_balance( $profile['id'] );
		$link_stats    = $this->tracker->get_stats_for_profile( $profile['id'] );
		$ledger        = $this->points_manager->get_ledger_by_profile( $profile['id'], 50 );
		$intro_text    = $this->settings->get( 'account_intro_text', '' );

		echo '<div class="rrp-account-wrap">';
		echo '<h2>' . esc_html__( 'Referral & Points', 'relod-referral-points' ) . '</h2>';

		if ( $intro_text ) {
			echo '<div class="rrp-account-intro">' . wp_kses_post( wpautop( $intro_text ) ) . '</div>';
		}

		echo '<div class="rrp-account-grid">';
		echo '<div class="rrp-card">';
		echo '<h3>' . esc_html__( 'Ваша реферальная ссылка', 'relod-referral-points' ) . '</h3>';
		echo '<div class="rrp-referral-link-box">';
		echo '<input type="text" class="rrp-referral-link-input" readonly value="' . esc_attr( $referral_link ) . '">';
		echo '<button type="button" class="button rrp-copy-button" data-copy-text="' . esc_attr( $referral_link ) . '">' . esc_html__( 'Скопировать ссылку', 'relod-referral-points' ) . '</button>';
		echo '</div>';
		echo '<p class="rrp-copy-feedback" aria-live="polite"></p>';
		echo '</div>';

		echo '<div class="rrp-card">';
		echo '<h3>' . esc_html__( 'Баланс баллов', 'relod-referral-points' ) . '</h3>';
		echo '<div class="rrp-balance-value">' . esc_html( RRP_Utils::format_points( $balance ) ) . '</div>';
		echo '<p>' . esc_html__( 'Баллы начисляются владельцу ссылки после первого заказа приглашённого клиента.', 'relod-referral-points' ) . '</p>';
		echo '</div>';

		echo '<div class="rrp-card">';
		echo '<h3>' . esc_html__( 'Статистика ссылки', 'relod-referral-points' ) . '</h3>';
		echo '<p><strong>' . esc_html__( 'Переходов:', 'relod-referral-points' ) . '</strong> ' . esc_html( absint( $link_stats['clicks_count'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Заказов после перехода:', 'relod-referral-points' ) . '</strong> ' . esc_html( absint( $link_stats['conversions_count'] ) ) . '</p>';
		echo '</div>';
		echo '</div>';

		echo '<div class="rrp-card">';
		echo '<h3>' . esc_html__( 'История операций', 'relod-referral-points' ) . '</h3>';

		if ( empty( $ledger ) ) {
			echo '<p>' . esc_html__( 'Операций пока нет.', 'relod-referral-points' ) . '</p>';
		} else {
			echo '<div class="rrp-table-wrap"><table class="shop_table shop_table_responsive my_account_orders rrp-history-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Дата', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Тип', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Баллы', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Описание', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Баланс после', 'relod-referral-points' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $ledger as $entry ) {
				echo '<tr>';
				echo '<td data-title="' . esc_attr__( 'Дата', 'relod-referral-points' ) . '">' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['created_at'] ) ) . '</td>';
				echo '<td data-title="' . esc_attr__( 'Тип', 'relod-referral-points' ) . '">' . esc_html( $this->get_entry_label( $entry['entry_type'] ) ) . '</td>';
				echo '<td data-title="' . esc_attr__( 'Баллы', 'relod-referral-points' ) . '">' . esc_html( RRP_Utils::format_points( $entry['points'] ) ) . '</td>';
				echo '<td data-title="' . esc_attr__( 'Описание', 'relod-referral-points' ) . '">' . esc_html( wp_strip_all_tags( $entry['description'] ) ) . '</td>';
				echo '<td data-title="' . esc_attr__( 'Баланс после', 'relod-referral-points' ) . '">' . esc_html( RRP_Utils::format_points( $entry['balance_after'] ) ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table></div>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Human labels for ledger entry types.
	 *
	 * @param string $entry_type Entry type.
	 * @return string
	 */
	protected function get_entry_label( $entry_type ) {
		$map = array(
			'referral_reward'   => __( 'Реферальное начисление', 'relod-referral-points' ),
			'self_order_reward' => __( 'Начисление за свой заказ', 'relod-referral-points' ),
			'reversal'          => __( 'Списание', 'relod-referral-points' ),
			'self_order_reversal' => __( 'Списание за свой заказ', 'relod-referral-points' ),
			'manual_credit'     => __( 'Ручное начисление', 'relod-referral-points' ),
			'manual_debit'      => __( 'Ручное списание', 'relod-referral-points' ),
		);

		return isset( $map[ $entry_type ] ) ? $map[ $entry_type ] : $entry_type;
	}
}
