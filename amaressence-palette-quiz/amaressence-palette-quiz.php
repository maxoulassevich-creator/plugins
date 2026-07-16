<?php
/**
 * Plugin Name: Amaressence Palette Quiz
 * Description: Квиз «Какая палитра у твоей amarèssence?» со свободным прохождением, полем email на финальном экране, ограничением «одно зачисление на аккаунт», обнулением баллов из админки и начислением через RELOD Referral Points.
 * Version: 1.5.0
 * Author: Amaressence
 * Text Domain: amaressence-palette-quiz
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'APQ_VERSION', '1.5.0' );
define( 'APQ_PLUGIN_FILE', __FILE__ );
define( 'APQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// HPOS compatibility (как в RELOD Referral Points).
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once APQ_PLUGIN_DIR . 'includes/class-apq-activator.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-settings.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-logger.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-points.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-account.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-results.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-quiz.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-trigger.php';
require_once APQ_PLUGIN_DIR . 'includes/class-apq-admin.php';

register_activation_hook( __FILE__, array( 'APQ_Activator', 'activate' ) );

/**
 * Singleton bootstrap.
 *
 * @return APQ_Plugin
 */
function apq() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new APQ_Plugin();
	}

	return $instance;
}

final class APQ_Plugin {

	/** @var APQ_Settings */
	public $settings;

	/** @var APQ_Logger */
	public $logger;

	/** @var APQ_Points */
	public $points;

	/** @var APQ_Account */
	public $account;

	/** @var APQ_Results */
	public $results;

	/** @var APQ_Quiz */
	public $quiz;

	/** @var APQ_Trigger */
	public $trigger;

	/** @var APQ_Admin */
	public $admin;

	public function __construct() {
		APQ_Activator::maybe_upgrade();

		$this->settings = new APQ_Settings();
		$this->settings->maybe_add_defaults();
		$this->logger  = new APQ_Logger( $this->settings );
		$this->points  = new APQ_Points( $this->settings, $this->logger );
		$this->account = new APQ_Account( $this->settings, $this->logger, $this->points );
		$this->results = new APQ_Results( $this->settings, $this->logger, $this->points );
		$this->quiz    = new APQ_Quiz( $this->settings, $this->logger, $this->results, $this->points, $this->account );
		$this->trigger = new APQ_Trigger( $this->settings, $this->logger );

		if ( is_admin() ) {
			$this->admin = new APQ_Admin( $this->settings, $this->logger, $this->results, $this->points );
		}
	}
}

add_action(
	'plugins_loaded',
	function() {
		apq();
	},
	25
);
