<?php
if (!defined('ABSPATH')) exit;

class Wrova_SEO_Service {

    public function write_seo_meta(int $post_id, array $meta): void {
        $title         = isset($meta['title'])         ? $meta['title']         : '';
        $description   = isset($meta['description'])   ? $meta['description']   : '';
        $focus_keyword = isset($meta['focus_keyword']) ? $meta['focus_keyword'] : '';

        if ($this->is_rank_math_active()) {
            update_post_meta($post_id, 'rank_math_title',         $title);
            update_post_meta($post_id, 'rank_math_description',   $description);
            update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
        } else {
            update_post_meta($post_id, '_wrova_seo_title',         $title);
            update_post_meta($post_id, '_wrova_seo_description',   $description);
            update_post_meta($post_id, '_wrova_seo_focus_keyword', $focus_keyword);
        }
    }

    public function update_image_alts(array $image_alts): void {
        foreach ($image_alts as $attachment_id => $alt) {
            update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', $alt);
        }
    }

    public function inject_schema(int $post_id, string $schema_json): void {
        if ($this->is_rank_math_active()) {
            update_post_meta($post_id, 'rank_math_schema', $schema_json);
        } else {
            update_post_meta($post_id, '_wrova_schema', $schema_json);
        }

        add_action('wp_head', function () use ($post_id, $schema_json) {
            if (!is_singular() || get_the_ID() !== $post_id) {
                return;
            }
            echo '<script type="application/ld+json">' . "\n" . $schema_json . "\n" . '</script>' . "\n";
        });
    }

    private function is_rank_math_active(): bool {
        return class_exists('RankMath');
    }
}
