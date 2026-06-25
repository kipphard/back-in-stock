<?php
/**
 * Plugin Name:       Wieder verfügbar – Back in Stock Notifications für WooCommerce
 * Plugin URI:        https://products.kipphard.com/wieder-verfuegbar
 * Description:       Benachrichtigt Kunden per E-Mail, sobald ein ausverkauftes WooCommerce-Produkt wieder auf Lager ist. Saubere UX, ehrlicher Umfang.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            André Kipphard
 * Author URI:        https://kipphard.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wieder-verfuegbar
 * Domain Path:       /languages
 *
 * @package Kipphard\WiederVerfuegbar
 */

defined( 'ABSPATH' ) || exit;

define( 'WVB_VERSION', '0.1.0' );
define( 'WVB_FILE', __FILE__ );
define( 'WVB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WVB_URL', plugin_dir_url( __FILE__ ) );
define( 'WVB_SLUG', 'wieder-verfuegbar' );

/**
 * Minimaler PSR-4-Autoloader für den Kipphard\WiederVerfuegbar\-Namespace.
 * Kipphard\WiederVerfuegbar\Foo_Bar → includes/class-foo-bar.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Kipphard\\WiederVerfuegbar\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		$path     = WVB_DIR . 'includes/' . $file;
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\Kipphard\WiederVerfuegbar\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Kipphard\WiederVerfuegbar\Plugin::instance()->boot();
	}
);
