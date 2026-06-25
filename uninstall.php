<?php
/**
 * Plugin-Deinstallation: Option und benutzerdefinierte Tabelle entfernen.
 *
 * @package Kipphard\WiederVerfuegbar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'wvb_settings' );

$table = $wpdb->prefix . 'wvb_subscriptions';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
