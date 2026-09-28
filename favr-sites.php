<?php
/**
 * Plugin Name:       Favr Sites
 * Plugin URI:        https://github.com/26am/favr-sites
 * Description:       The Favr experience on every client site, starting with a Favr dashboard for site editors.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Favr Sites
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       favr-sites
 *
 * @package FavrSites
 */

defined( 'ABSPATH' ) || exit;

define( 'FAVR_SITES_VERSION', '0.1.0' );
define( 'FAVR_SITES_FILE', __FILE__ );
define( 'FAVR_SITES_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAVR_SITES_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'FavrSites\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

\FavrSites\Plugin::boot();
