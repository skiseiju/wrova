<?php
if (!defined('ABSPATH')) exit;

class Wrova_Content_Builder {

    public function build(array $ai_response): string {
        $excerpt = isset($ai_response['excerpt']) ? $ai_response['excerpt'] : '';
        $content = isset($ai_response['content']) ? $ai_response['content'] : '';
        $faq     = isset($ai_response['faq']) && is_array($ai_response['faq']) ? $ai_response['faq'] : [];

        $html = '';

        if ($excerpt !== '') {
            $html .= '<div class="wrova-featured-snippet">' . wp_kses_post($excerpt) . '</div>';
        }

        $html .= $content;

        if (!empty($faq)) {
            $html .= $this->build_faq_block($faq);
        }

        return $html;
    }

    public function build_faq_block(array $faq): string {
        if (empty($faq)) {
            return '';
        }

        $html = '<div class="wrova-faq"><h2>' . esc_html__('常見問題', 'wrova') . '</h2>';

        foreach ($faq as $item) {
            $question = isset($item['question']) ? $item['question'] : '';
            $answer   = isset($item['answer'])   ? $item['answer']   : '';

            if ($question === '' && $answer === '') {
                continue;
            }

            $html .= '<div class="wrova-faq-item" itemscope itemtype="https://schema.org/Question">';
            $html .= '<h3 itemprop="name">' . esc_html($question) . '</h3>';
            $html .= '<div itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">';
            $html .= '<div itemprop="text">' . wp_kses_post($answer) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    public function build_schema(array $ai_response, int $post_id = 0): string {
        $title       = isset($ai_response['seo_title'])       ? $ai_response['seo_title']       : (isset($ai_response['title']) ? $ai_response['title'] : '');
        $description = isset($ai_response['seo_description']) ? $ai_response['seo_description'] : (isset($ai_response['excerpt']) ? $ai_response['excerpt'] : '');
        $faq         = isset($ai_response['faq']) && is_array($ai_response['faq']) ? $ai_response['faq'] : [];

        $blog_name    = get_bloginfo('name');
        $date_today   = date('Y-m-d');
        $post_url     = $post_id > 0 ? get_permalink($post_id) : '';
        $image_url    = '';

        if ($post_id > 0) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                $src = wp_get_attachment_image_url($thumbnail_id, 'full');
                if ($src) {
                    $image_url = $src;
                }
            }

            if ($image_url === '') {
                preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', get_post_field('post_content', $post_id), $matches);
                if (!empty($matches[1])) {
                    $image_url = $matches[1];
                }
            }
        }

        $article_schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Article',
            'headline' => $title,
            'description' => $description,
            'author' => [
                '@type' => 'Organization',
                'name'  => $blog_name,
            ],
            'datePublished' => $date_today,
        ];

        if ($post_url) {
            $article_schema['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $post_url];
        }

        if ($image_url) {
            $article_schema['image'] = $image_url;
        }

        $graph = [$article_schema];

        if (!empty($faq)) {
            $faq_entities = [];
            foreach ($faq as $item) {
                $question = isset($item['question']) ? $item['question'] : '';
                $answer   = isset($item['answer'])   ? $item['answer']   : '';
                if ($question === '') {
                    continue;
                }
                $faq_entities[] = [
                    '@type'          => 'Question',
                    'name'           => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $answer,
                    ],
                ];
            }

            if (!empty($faq_entities)) {
                $graph[] = [
                    '@context'   => 'https://schema.org',
                    '@type'      => 'FAQPage',
                    'mainEntity' => $faq_entities,
                ];
            }
        }

        return wp_json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function build_diff(string $original_html, string $improved_html): array {
        $original_paragraphs  = $this->split_into_paragraphs($original_html);
        $improved_paragraphs  = $this->split_into_paragraphs($improved_html);

        $count = max(count($original_paragraphs), count($improved_paragraphs));
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'original' => isset($original_paragraphs[$i])  ? $original_paragraphs[$i]  : '',
                'improved' => isset($improved_paragraphs[$i])  ? $improved_paragraphs[$i]  : '',
            ];
        }

        return $result;
    }

    private function split_into_paragraphs(string $html): array {
        if (trim($html) === '') {
            return [];
        }

        $pattern = '/(<(?:p|h2|h3|ul|ol|blockquote)[\s>].*?<\/(?:p|h2|h3|ul|ol|blockquote)>)/is';
        preg_match_all($pattern, $html, $matches);

        if (empty($matches[1])) {
            return [trim($html)];
        }

        return array_values(array_filter(array_map('trim', $matches[1])));
    }
}
