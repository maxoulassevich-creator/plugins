<?php
/**
 * Uninstall: удаляет таблицу результатов и опции плагина.
 * Таблицы RELOD Referral Points (rrp_*) не трогаем — это чужие данные.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}apq_quiz_results" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'apq_settings' );
delete_option( 'apq_logs' );
delete_option( 'apq_db_version' );
