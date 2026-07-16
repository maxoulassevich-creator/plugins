<?php
/**
 * Activation: schema for quiz results.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Activator {

	public static function activate() {
		self::install_or_update_schema();
	}

	public static function maybe_upgrade() {
		if ( APQ_VERSION !== (string) get_option( 'apq_db_version', '' ) ) {
			self::install_or_update_schema();
		}
	}

	public static function install_or_update_schema() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = $wpdb->prefix . 'apq_quiz_results';

		// Ограничение «одна попытка на email» управляется кодом и настройкой, а не UNIQUE-индексом.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(190) NOT NULL,
			session_id varchar(64) DEFAULT NULL,
			answers longtext DEFAULT NULL,
			profile_id varchar(64) NOT NULL DEFAULT '',
			points decimal(18,4) NOT NULL DEFAULT 0.0000,
			points_status varchar(20) NOT NULL DEFAULT 'none',
			ip_hash varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			awarded_at datetime DEFAULT NULL,
			awarded_order_id bigint(20) unsigned DEFAULT NULL,
			revoked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY user_id (user_id),
			KEY points_status (points_status),
			KEY profile_id (profile_id)
		) {$charset_collate};";

		dbDelta( $sql );
		self::replace_unique_email_index( $table );

		$settings = new APQ_Settings();
		$settings->maybe_add_defaults();

		update_option( 'apq_db_version', APQ_VERSION );
	}

	protected static function replace_unique_email_index( $table ) {
		global $wpdb;

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return;
		}

		$indexes = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'email'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique  = false;

		foreach ( $indexes as $index ) {
			if ( isset( $index['Non_unique'] ) && '0' === (string) $index['Non_unique'] ) {
				$unique = true;
				break;
			}
		}

		if ( $unique ) {
			$wpdb->query( "ALTER TABLE {$table} DROP INDEX email" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$indexes = array();
		}

		if ( empty( $indexes ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD KEY email (email)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
