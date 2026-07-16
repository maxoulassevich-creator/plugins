<?php
/**
 * Activation and schema upgrade handler.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Activator {

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install_or_update_schema();

		$settings = new RRP_Settings();
		$endpoint = $settings->get( 'account_endpoint', RRP_Settings::defaults()['account_endpoint'] );
		add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Run schema upgrades when plugin files were updated without reactivation.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		global $wpdb;

		$current_version = (string) get_option( 'rrp_db_version', '' );
		$clicks_table    = $wpdb->prefix . 'rrp_referral_clicks';
		$table_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $clicks_table ) );

		if ( RRP_VERSION !== $current_version || $clicks_table !== $table_exists ) {
			self::install_or_update_schema();
		}
	}

	/**
	 * Create or update database tables.
	 *
	 * @return void
	 */
	public static function install_or_update_schema() {
		require_once RRP_PLUGIN_DIR . 'includes/class-rrp-settings.php';
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$profiles_table  = $wpdb->prefix . 'rrp_profiles';
		$referrals_table = $wpdb->prefix . 'rrp_referrals';
		$clicks_table    = $wpdb->prefix . 'rrp_referral_clicks';
		$ledger_table    = $wpdb->prefix . 'rrp_points_ledger';

		$sql_profiles = "CREATE TABLE {$profiles_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(190) NOT NULL,
			referral_code varchar(64) NOT NULL,
			points_balance decimal(18,4) NOT NULL DEFAULT 0.0000,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_order_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			UNIQUE KEY referral_code (referral_code),
			KEY user_id (user_id)
		) {$charset_collate};";

		$sql_referrals = "CREATE TABLE {$referrals_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			click_id bigint(20) unsigned DEFAULT NULL,
			referrer_profile_id bigint(20) unsigned NOT NULL,
			referred_profile_id bigint(20) unsigned DEFAULT NULL,
			referred_user_id bigint(20) unsigned DEFAULT NULL,
			referred_email varchar(190) NOT NULL,
			referral_code varchar(64) NOT NULL,
			order_total decimal(18,4) NOT NULL DEFAULT 0.0000,
			reward_rate decimal(10,4) NOT NULL DEFAULT 0.0000,
			points_awarded decimal(18,4) NOT NULL DEFAULT 0.0000,
			status varchar(20) NOT NULL DEFAULT 'pending',
			reason varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			awarded_at datetime DEFAULT NULL,
			reversed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
			KEY click_id (click_id),
			KEY referrer_profile_id (referrer_profile_id),
			KEY referred_profile_id (referred_profile_id),
			KEY referred_email (referred_email),
			KEY status (status)
		) {$charset_collate};";

		$sql_clicks = "CREATE TABLE {$clicks_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			referrer_profile_id bigint(20) unsigned NOT NULL,
			referral_code varchar(64) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			visitor_hash varchar(64) DEFAULT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			user_agent_hash varchar(64) DEFAULT NULL,
			landing_url text DEFAULT NULL,
			referrer_url text DEFAULT NULL,
			converted_order_id bigint(20) unsigned DEFAULT NULL,
			converted_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'clicked',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY referrer_profile_id (referrer_profile_id),
			KEY referral_code (referral_code),
			KEY user_id (user_id),
			KEY converted_order_id (converted_order_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_ledger = "CREATE TABLE {$ledger_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			profile_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(190) NOT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			referral_id bigint(20) unsigned DEFAULT NULL,
			entry_type varchar(30) NOT NULL,
			points decimal(18,4) NOT NULL DEFAULT 0.0000,
			balance_after decimal(18,4) NOT NULL DEFAULT 0.0000,
			description text DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			expired_at datetime DEFAULT NULL,
			expiration_email_sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY profile_id (profile_id),
			KEY order_id (order_id),
			KEY referral_id (referral_id),
			KEY entry_type (entry_type),
			KEY expires_at (expires_at),
			KEY expired_at (expired_at),
			KEY expiration_email_sent_at (expiration_email_sent_at)
		) {$charset_collate};";

		dbDelta( $sql_profiles );
		dbDelta( $sql_referrals );
		dbDelta( $sql_clicks );
		dbDelta( $sql_ledger );

		$settings = new RRP_Settings();
		$settings->maybe_add_defaults();

		update_option( 'rrp_db_version', RRP_VERSION );
	}
}
