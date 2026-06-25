<?php
/**
 * Plugin-Bootstrap: Hooks, Submodule und WooCommerce-Prüfung.
 *
 * @package Kipphard\WiederVerfuegbar
 */

namespace Kipphard\WiederVerfuegbar;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton-Einstiegspunkt.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private Konstruktor (Singleton).
	 */
	private function __construct() {}

	/**
	 * Aktivierung: Standard-Einstellungen anlegen + Datenbanktabelle erstellen.
	 */
	public static function activate() {
		if ( false === get_option( Helpers::OPT_SETTINGS, false ) ) {
			add_option( Helpers::OPT_SETTINGS, Helpers::defaults() );
		}
		Subscriptions::create_table();
	}

	/**
	 * Laufzeit-Hooks registrieren.
	 */
	public function boot() {
		load_plugin_textdomain(
			'wieder-verfuegbar',
			false,
			dirname( plugin_basename( WVB_FILE ) ) . '/languages'
		);

		// WooCommerce ist Pflicht – ohne es läuft nichts.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_woocommerce_missing' ) );
			return;
		}

		( new Frontend() )->hooks();
		( new Stock_Watch() )->hooks();

		if ( is_admin() ) {
			( new Admin() )->hooks();
		}

		// Pro-only: nur laden wenn die Datei im Build vorhanden ist.
		if ( class_exists( __NAMESPACE__ . '\\Double_Optin' ) ) {
			( new Double_Optin() )->hooks();
		}
	}

	/**
	 * Admin-Hinweis wenn WooCommerce nicht aktiv ist.
	 */
	public function notice_woocommerce_missing() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Wieder verfügbar', 'wieder-verfuegbar' ); ?>:</strong>
				<?php esc_html_e( 'WooCommerce muss installiert und aktiviert sein, damit dieses Plugin funktioniert.', 'wieder-verfuegbar' ); ?>
			</p>
		</div>
		<?php
	}
}
