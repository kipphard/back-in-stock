<?php
/**
 * Datenbank-Speicher für Benachrichtigungs-Abonnements.
 *
 * @package Kipphard\WiederVerfuegbar
 */

namespace Kipphard\WiederVerfuegbar;

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet die benutzerdefinierte Tabelle für Abonnements.
 */
class Subscriptions {

	/**
	 * Liefert das CREATE TABLE Statement für dbDelta.
	 *
	 * @return string
	 */
	public static function schema() {
		global $wpdb;
		$table      = Helpers::table();
		$charset_collate = $wpdb->get_charset_collate();

		// Doppeltes Leerzeichen nach Feldname ist dbDelta-Pflicht.
		return "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT(20) UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  token CHAR(32) NOT NULL DEFAULT '',
  created DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY product_status (product_id, status)
) {$charset_collate};";
	}

	/**
	 * Legt die Tabelle an (oder aktualisiert sie via dbDelta).
	 */
	public static function create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema() );
	}

	/**
	 * Fügt ein neues Abonnement ein, sofern noch kein aktives für diese Kombination existiert.
	 *
	 * @param int    $product_id Produkt-ID.
	 * @param string $email      Bereits sanitisierte und validierte E-Mail-Adresse.
	 * @return bool True bei Erfolg, false wenn bereits eingetragen oder DB-Fehler.
	 */
	public static function add( $product_id, $email ) {
		global $wpdb;
		$table = Helpers::table();

		// Doppeleintrag verhindern: Prüfen ob bereits aktives Abo vorhanden.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND email = %s AND status = 'active' LIMIT 1",
				$product_id,
				$email
			)
		);

		if ( $existing ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'product_id' => $product_id,
				'email'      => $email,
				'status'     => 'active',
				'token'      => wp_generate_password( 32, false ),
				'created'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * Liefert alle aktiven Abonnements für ein Produkt.
	 *
	 * @param int $product_id Produkt-ID.
	 * @return array<int,array{id:int,email:string}>
	 */
	public static function active_for_product( $product_id ) {
		global $wpdb;
		$table = Helpers::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email FROM {$table} WHERE product_id = %d AND status = 'active'",
				$product_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Markiert eine Liste von Abonnements als versendet.
	 *
	 * @param int[] $ids Liste von Abonnement-IDs.
	 */
	public static function mark_notified( array $ids ) {
		global $wpdb;
		$table = Helpers::table();

		if ( empty( $ids ) ) {
			return;
		}

		// Sicher: IDs durch absint() filtern, dann als Integer-Platzhalter in prepare.
		$clean_ids    = array_map( 'absint', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $clean_ids ), '%d' ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'notified' WHERE id IN ({$placeholders})",
				$clean_ids
			)
		);
	}

	/**
	 * Gibt die Anzahl aktiver Abonnements zurück (für Admin-Übersicht).
	 *
	 * @return int
	 */
	public static function count_active() {
		global $wpdb;
		$table = Helpers::table();

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'active'"
		);
	}
}
