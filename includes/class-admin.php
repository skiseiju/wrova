<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wrova_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_notices', [ $this, 'check_rank_math' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            'Wrova',
            'Wrova',
            'edit_posts',
            'wrova',
            [ $this, 'page_module_a' ],
            'dashicons-edit-large',
            30
        );

        add_submenu_page( 'wrova', '全新產文', '全新產文', 'edit_posts', 'wrova', [ $this, 'page_module_a' ] );
        add_submenu_page( 'wrova', '文章優化', '文章優化', 'edit_posts', 'wrova-improve', [ $this, 'page_module_b' ] );
        add_submenu_page( 'wrova', 'Prompt 管理', 'Prompt 管理', 'manage_options', 'wrova-prompts', [ $this, 'page_prompts' ] );
        add_submenu_page( 'wrova', '設定', '設定', 'manage_options', 'wrova-settings', [ $this, 'page_settings' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'wrova' ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'wrova-admin',
            WROVA_PLUGIN_URL . 'admin/assets/wrova-admin.js',
            [ 'jquery', 'wp-api-fetch' ],
            WROVA_VERSION,
            true
        );
        wp_enqueue_style(
            'wrova-admin',
            WROVA_PLUGIN_URL . 'admin/assets/wrova-admin.css',
            [],
            WROVA_VERSION
        );
        wp_localize_script( 'wrova-admin', 'wrovaData', [
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'restUrl' => rest_url( 'wrova/v1/' ),
        ] );
    }

    public function check_rank_math(): void {
        if ( ! is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
            $screen = get_current_screen();
            if ( $screen && str_contains( $screen->id, 'wrova' ) ) {
                echo '<div class="notice notice-warning"><p><strong>Wrova：</strong>建議安裝 <a href="' . esc_url( admin_url( 'plugin-install.php?s=rank+math&tab=search&type=term' ) ) . '">Rank Math SEO</a> 以使用完整 SEO 功能。</p></div>';
            }
        }
    }

    public function page_module_a(): void {
        require_once WROVA_PLUGIN_DIR . 'admin/pages/page-module-a.php';
    }

    public function page_module_b(): void {
        require_once WROVA_PLUGIN_DIR . 'admin/pages/page-module-b.php';
    }

    public function page_prompts(): void {
        require_once WROVA_PLUGIN_DIR . 'admin/pages/page-prompts.php';
    }

    public function page_settings(): void {
        require_once WROVA_PLUGIN_DIR . 'admin/pages/page-settings.php';
    }
}
