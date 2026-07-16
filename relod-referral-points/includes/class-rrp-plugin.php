<?php
/**
 * Main plugin bootstrap.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var RRP_Plugin|null
	 */
	protected static $instance = null;

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
	 * Points manager.
	 *
	 * @var RRP_Points_Manager
	 */
	protected $points_manager;

	/**
	 * Referral tracker.
	 *
	 * @var RRP_Referral_Tracker
	 */
	protected $tracker;

	/**
	 * Email manager.
	 *
	 * @var RRP_Email_Manager
	 */
	protected $email_manager;

	/**
	 * Expiration manager.
	 *
	 * @var RRP_Expiration_Manager
	 */
	protected $expiration_manager;

	/**
	 * Order handler.
	 *
	 * @var RRP_Order_Handler
	 */
	protected $order_handler;

	/**
	 * My account endpoint.
	 *
	 * @var RRP_My_Account
	 */
	protected $my_account;

	/**
	 * Admin.
	 *
	 * @var RRP_Admin
	 */
	protected $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return RRP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->load_dependencies();
		$this->boot();
	}

	/**
	 * Load required files.
	 *
	 * @return void
	 */
	protected function load_dependencies() {
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-settings.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-utils.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-logger.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-profile-manager.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-points-manager.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-referral-tracker.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-email-manager.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-expiration-manager.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-order-handler.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-my-account.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-admin.php';
	}

	/**
	 * Boot plugin services.
	 *
	 * @return void
	 */
	protected function boot() {
		RRP_Activator::maybe_upgrade();

		$this->settings = new RRP_Settings();
		$this->settings->maybe_add_defaults();
		$this->logger          = new RRP_Logger( $this->settings );
		$this->profile_manager = new RRP_Profile_Manager( $this->settings, $this->logger );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->points_manager = new RRP_Points_Manager( $this->settings, $this->logger, $this->profile_manager );
		$this->tracker        = new RRP_Referral_Tracker( $this->settings, $this->logger, $this->profile_manager );
		$this->email_manager      = new RRP_Email_Manager( $this->settings, $this->logger, $this->profile_manager );
		$this->expiration_manager = new RRP_Expiration_Manager( $this->settings, $this->logger, $this->profile_manager, $this->points_manager, $this->email_manager );
		$this->order_handler      = new RRP_Order_Handler( $this->settings, $this->logger, $this->profile_manager, $this->tracker, $this->points_manager, $this->email_manager );
		$this->my_account     = new RRP_My_Account( $this->settings, $this->profile_manager, $this->points_manager, $this->logger, $this->tracker );

		if ( is_admin() ) {
			$this->admin = new RRP_Admin( $this->settings, $this->logger, $this->profile_manager, $this->points_manager, $this->tracker, $this->expiration_manager );
		}
	}

	/**
	 * WooCommerce missing admin notice.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'RELOD Referral Points for WooCommerce требует активного WooCommerce.', 'relod-referral-points' ) . '</p></div>';
	}
}
