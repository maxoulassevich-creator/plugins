<?php
/**
 * Plugin Name: RELOD Referral Points for WooCommerce
 * Plugin URI: https://example.com/
 * Description: Кастомный WooCommerce-плагин с реферальными ссылками, баллами, email-уведомлениями, логами и разделом в личном кабинете.
 * Version: 1.3.0
 * Author: OpenAI
 * Author URI: https://openai.com/
 * Text Domain: relod-referral-points
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.4
 * WC tested up to: 10.5
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RRP_VERSION', '1.3.0' );
define( 'RRP_PLUGIN_FILE', __FILE__ );
define( 'RRP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RRP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RRP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once RRP_PLUGIN_DIR . 'includes/class-rrp-activator.php';
require_once RRP_PLUGIN_DIR . 'includes/class-rrp-deactivator.php';
require_once RRP_PLUGIN_DIR . 'includes/class-rrp-plugin.php';

register_activation_hook( __FILE__, array( 'RRP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RRP_Deactivator', 'deactivate' ) );

function rrp() {
	return RRP_Plugin::instance();
}

add_action(
	'plugins_loaded',
	function() {
		rrp();
	},
	20
);
