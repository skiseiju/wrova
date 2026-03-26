<?php
/**
 * Plugin Name: Wrova
 * Plugin URI:  https://github.com/skiseiju/wrova
 * Description: AI 驅動的 WordPress SEO + AEO 文章生成外掛
 * Version:     1.0.4
 * Author:      skiseiju
 * Author URI:  https://skiseiju.com
 * License:     GPL-2.0+
 * Text Domain: wrova
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WROVA_VERSION', '1.0.4' );
define( 'WROVA_PLUGIN_FILE', __FILE__ );
define( 'WROVA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WROVA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( function ( string $class ) {
    $prefix = 'Wrova_';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
    $file = WROVA_PLUGIN_DIR . 'includes/class-' . $relative . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

register_activation_hook( __FILE__, [ 'Wrova_Core', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Wrova_Core', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    Wrova_Core::get_instance();
} );

// sk-github-updater
require_once WROVA_PLUGIN_DIR . 'updater/sk-github-updater.php';
add_action( 'init', function () {
    $settings = get_option( 'wrova_settings', [] );
    $repo     = $settings['github_repo'] ?? 'skiseiju/wrova';
    $token    = $settings['github_token'] ?? '';

    if ( $repo && class_exists( 'SK_GitHub_Updater' ) ) {
        SK_GitHub_Updater::register( [
            'type'         => 'plugin',
            'slug'         => 'wrova',
            'repo'         => $repo,
            'access_token' => $token,
        ] );
    }
} );
