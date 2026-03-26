<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wrova_Core {

    private static ?Wrova_Core $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if ( is_admin() ) {
            new Wrova_Admin();
        }
        new Wrova_REST_Controller();
    }

    public static function activate(): void {
        update_option( 'wrova_version', WROVA_VERSION );
        if ( ! get_option( 'wrova_settings' ) ) {
            update_option( 'wrova_settings', [
                'ai_provider'              => 'gemini',
                'gemini_api_key'           => '',
                'claude_api_key'           => '',
                'gemini_model'             => 'gemini-2.0-flash-exp',
                'claude_model'             => 'claude-sonnet-4-6',
                'default_prompt_template'  => 'wedding',
            ] );
        }
    }

    public static function deactivate(): void {
        // 保留設定，不刪除
    }
}
