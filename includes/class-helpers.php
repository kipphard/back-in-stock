<?php
/**
 * Gemeinsame Hilfsmethoden: Rechte, Optionen, Sanitisierung.
 *
 * @package Kipphard\WiederVerfuegbar
 */

namespace Kipphard\WiederVerfuegbar;

defined( 'ABSPATH' ) || exit;

/**
 * Zustandslose Hilfsmethoden, die im gesamten Plugin genutzt werden.
 */
class Helpers {

	/** Erforderliche Berechtigung für alle Admin-Aktionen. */
	const CAP = 'manage_options';

	/** Options-Key für die Plugin-Einstellungen. */
	const OPT_SETTINGS = 'wvb_settings';

	/**
	 * Prüft ob die Pro-Lizenz aktiv ist. Standardmäßig false.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return (bool) apply_filters( 'wvb_is_pro', defined( 'WVB_PRO' ) && WVB_PRO );
	}

	/**
	 * Gibt den vollständigen Tabellennamen zurück.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wvb_subscriptions';
	}

	/**
	 * Liefert die Standard-Einstellungen des Plugins.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'heading'       => 'Benachrichtige mich, wenn wieder verfügbar',
			'button_label'  => 'Benachrichtige mich',
			'consent_text'  => 'Ich stimme zu, per E-Mail benachrichtigt zu werden, sobald dieses Produkt wieder verfügbar ist.',
			'email_subject' => 'Wieder verfügbar: {product}',
			'email_body'    => "Hallo,\n\ndas Produkt \"{product}\" ist jetzt wieder auf Lager.\n\nJetzt kaufen: {link}\n\nViele Grüße",
			'msg_success'   => 'Vielen Dank! Wir benachrichtigen Sie, sobald das Produkt wieder verfügbar ist.',
			'msg_error'     => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
		);
	}

	/**
	 * Liest eine einzelne Einstellung (mit Fallback auf den Standardwert).
	 *
	 * @param string $key Einstellungsschlüssel.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = (array) get_option( self::OPT_SETTINGS, array() );
		$defaults = self::defaults();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : null );
	}

	/**
	 * Sanitisiert die Einstellungsfelder streng pro Feld.
	 *
	 * @param array<string,mixed> $raw Rohe $_POST-Daten.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( array $raw ) {
		$defaults = self::defaults();

		return array(
			'heading'       => isset( $raw['heading'] ) ? sanitize_text_field( wp_unslash( $raw['heading'] ) ) : $defaults['heading'],
			'button_label'  => isset( $raw['button_label'] ) ? sanitize_text_field( wp_unslash( $raw['button_label'] ) ) : $defaults['button_label'],
			'consent_text'  => isset( $raw['consent_text'] ) ? sanitize_text_field( wp_unslash( $raw['consent_text'] ) ) : $defaults['consent_text'],
			'email_subject' => isset( $raw['email_subject'] ) ? sanitize_text_field( wp_unslash( $raw['email_subject'] ) ) : $defaults['email_subject'],
			'email_body'    => isset( $raw['email_body'] ) ? sanitize_textarea_field( wp_unslash( $raw['email_body'] ) ) : $defaults['email_body'],
			'msg_success'   => isset( $raw['msg_success'] ) ? sanitize_text_field( wp_unslash( $raw['msg_success'] ) ) : $defaults['msg_success'],
			'msg_error'     => isset( $raw['msg_error'] ) ? sanitize_text_field( wp_unslash( $raw['msg_error'] ) ) : $defaults['msg_error'],
		);
	}

	/**
	 * Prüft einen Admin-POST-Request: Berechtigung + Nonce. Bricht bei Fehler ab.
	 *
	 * @param string $action Nonce-Aktion.
	 * @param string $field  Nonce-Feldname.
	 */
	public static function guard_post( $action, $field = '_wpnonce' ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'wieder-verfuegbar' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, $field );
	}
}
