<?php
/**
 * Deactivation handler.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Deactivator {

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-settings.php';
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-expiration-manager.php';

		$settings = new RRP_Settings();
		$endpoint = $settings->get( 'account_endpoint', RRP_Settings::defaults()['account_endpoint'] );

		if ( $endpoint ) {
			add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
		}

		RRP_Expiration_Manager::clear_schedule();

		flush_rewrite_rules();
	}
}
