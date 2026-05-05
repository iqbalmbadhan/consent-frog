<?php
/**
 * Plugin Name: ConsentForge
 * Plugin URI:  https://consentforge.com
 * Description: Next-generation privacy consent platform built for the post-Digital Omnibus era. The first WordPress consent plugin designed for the 2026 EU GDPR framework.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:      ConsentForge
 * Author URI:  https://consentforge.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: consentforge
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CF_VERSION', '1.0.0' );
define( 'CF_PLUGIN_FILE', __FILE__ );
define( 'CF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CF_MIN_WP', '6.4' );
define( 'CF_MIN_PHP', '8.1' );

// PSR-4 style autoloader for ConsentForge\ namespace
spl_autoload_register( function ( string $class ): void {
    $prefix = 'ConsentForge\\';
    $len    = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative = substr( $class, $len );
    $parts    = explode( '\\', $relative );

    // Convert each namespace segment to directory path
    $dirs      = array_slice( $parts, 0, -1 );
    $classname = array_pop( $parts );

    // CamelCase -> kebab-case
    $filename = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $classname ) ) . '.php';

    $subdir = '';
    if ( ! empty( $dirs ) ) {
        // Map namespace segment to directory: Core -> core, Scanner -> scanner, etc.
        $subdir = strtolower( implode( '/', $dirs ) ) . '/';
    }

    $file = CF_PLUGIN_DIR . 'includes/' . $subdir . $filename;

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

register_activation_hook( __FILE__, [ 'ConsentForge\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ConsentForge\\Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
    ConsentForge\ConsentForge::instance();
} );
