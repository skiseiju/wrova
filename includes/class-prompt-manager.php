<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wrova_Prompt_Manager {

    public function get_templates(): array {
        $saved = get_option( 'wrova_prompt_templates', [] );
        if ( ! empty( $saved ) ) {
            return $saved;
        }
        return $this->get_default_templates();
    }

    public function get_template( string $slug ): ?array {
        foreach ( $this->get_templates() as $tpl ) {
            if ( $tpl['slug'] === $slug ) {
                return $tpl;
            }
        }
        return null;
    }

    public function build( string $template_slug, array $vars ): string {
        $tpl = $this->get_template( $template_slug );
        if ( ! $tpl ) {
            $tpl = $this->get_default_templates()[0];
        }
        $prompt = $tpl['user_prompt_template'];
        foreach ( $vars as $key => $value ) {
            $prompt = str_replace( '{' . $key . '}', $value, $prompt );
        }
        return $prompt;
    }

    public function save_template( array $template ): void {
        $templates = $this->get_templates();
        $found = false;
        foreach ( $templates as &$tpl ) {
            if ( $tpl['slug'] === $template['slug'] ) {
                $tpl = $template;
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            $templates[] = $template;
        }
        update_option( 'wrova_prompt_templates', $templates );
    }

    private function get_default_templates(): array {
        $file = WROVA_PLUGIN_DIR . 'templates/default-prompts.json';
        if ( file_exists( $file ) ) {
            return json_decode( file_get_contents( $file ), true ) ?? [];
        }
        return [];
    }
}
