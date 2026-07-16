<?php
/**
 * Uninstall RELOD Referral Points for WooCommerce.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'rrp_profiles',
	$wpdb->prefix . 'rrp_referrals',
	$wpdb->prefix . 'rrp_referral_clicks',
	$wpdb->prefix . 'rrp_points_ledger',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'rrp_settings' );
delete_option( 'rrp_logs' );
delete_option( 'rrp_db_version' );
delete_option( 'rrp_points_expiration_scheduled_hour' );

$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('_rrp_profile_id','_rrp_pending_referrer_profile_id','_rrp_pending_referral_code','_rrp_pending_referral_click_id')" );
