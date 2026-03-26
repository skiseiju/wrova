<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wrova_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'wrova/v1', '/generate', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_generate' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );

        register_rest_route( 'wrova/v1', '/improve', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_improve' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );

        register_rest_route( 'wrova/v1', '/publish', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_publish' ],
            'permission_callback' => fn() => current_user_can( 'publish_posts' ),
        ] );

        register_rest_route( 'wrova/v1', '/analyze-images', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_analyze_images' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );

        register_rest_route( 'wrova/v1', '/templates', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_templates' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );

        register_rest_route( 'wrova/v1', '/templates', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_save_template' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'wrova/v1', '/templates/(?P<slug>[a-z0-9_-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'handle_delete_template' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
        ] );
    }

    // Module A：全新產文
    public function handle_generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $keywords        = array_map( 'sanitize_text_field', (array) ( $request->get_param( 'keywords' ) ?? [] ) );
        $mood             = sanitize_text_field( $request->get_param( 'mood' ) ?? '' );
        $image_ids        = array_map( 'intval', (array) ( $request->get_param( 'image_ids' ) ?? [] ) );
        $prompt_template  = sanitize_text_field( $request->get_param( 'prompt_template' ) ?? 'wedding' );
        $existing_content = sanitize_textarea_field( $request->get_param( 'existing_content' ) ?? '' );

        if ( empty( $keywords ) ) {
            return new WP_Error( 'missing_keywords', '請提供至少一個關鍵字', [ 'status' => 400 ] );
        }

        // 分析圖片
        $image_analysis = [];
        if ( ! empty( $image_ids ) ) {
            $analyzer       = new Wrova_Image_Analyzer();
            $analysis_raw   = $analyzer->analyze( $image_ids );
            $image_analysis = $analyzer->merge_results( $analysis_raw );
        }

        // 組合 prompt
        $manager = new Wrova_Prompt_Manager();
        $tpl     = $manager->get_template( $prompt_template );

        $vars = [
            'keywords'       => implode( '、', $keywords ),
            'mood'           => $mood,
            'image_analysis' => empty( $image_analysis )
                ? '（無照片分析）'
                : implode( '；', $image_analysis['description'] ?? [] ) . '。關鍵字：' . implode( '、', $image_analysis['keywords'] ?? [] ),
        ];

        $prompt        = $manager->build( $prompt_template, $vars );
        $system_prompt = $tpl ? $tpl['system_prompt'] : '';

        // 若有現有文案，附加到 prompt 末尾供 AI 參考
        if ( ! empty( $existing_content ) ) {
            $prompt .= "\n\n【參考現有文案】\n請在保留原文精神與攝影師風格語氣的前提下，結合以上關鍵字與情緒重新撰寫：\n\n" . mb_substr( $existing_content, 0, 800 );
        }

        // 準備圖片資料給 AI
        $images = [];
        if ( ! empty( $image_ids ) ) {
            $analyzer_obj = new Wrova_Image_Analyzer();
            foreach ( $image_ids as $id ) {
                $data = $this->get_image_data_for_ai( $id );
                if ( $data ) {
                    $images[] = $data;
                }
            }
        }

        // 呼叫 AI
        $ai     = new Wrova_AI_Provider_Service();
        $result = $ai->generate( $prompt, $system_prompt, $images );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // 組合圖片 alt text 建議
        $image_alts = [];
        foreach ( $image_ids as $id ) {
            $image_alts[ $id ] = isset( $result['focus_keyword'] )
                ? $result['focus_keyword'] . ' - ' . get_the_title( $id )
                : get_the_title( $id );
        }

        // 建立 Schema
        $builder    = new Wrova_Content_Builder();
        $schema_raw = $builder->build_schema( $result, 0 );

        return new WP_REST_Response( array_merge( $result, [
            'image_alts' => $image_alts,
            'schema_json' => $schema_raw,
        ] ) );
    }

    // Module B：文章優化（支援 sidebar 直接傳 content）
    public function handle_improve( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id       = intval( $request->get_param( 'post_id' ) );
        $direct_content = sanitize_textarea_field( $request->get_param( 'content' ) ?? '' );

        // 取得文章物件（允許 post_id=0 的新草稿）
        $post       = $post_id ? get_post( $post_id ) : null;
        $post_title = $post ? $post->post_title : '';

        // 優先用 sidebar 傳來的即時內容；fallback 才用 DB
        $source_content = $direct_content ?: ( $post ? $post->post_content : '' );

        if ( empty( trim( wp_strip_all_tags( $source_content ) ) ) ) {
            return new WP_Error( 'empty_content', '文章內容為空，無法優化', [ 'status' => 400 ] );
        }

        // 取得文章內附圖（有 post 才抓）
        $image_ids = $post ? $this->get_post_image_ids( $post ) : [];

        // 組合 improve prompt
        $prompt = "請優化以下繁體中文文章，讓它更符合 SEO 與 AEO 要求：\n\n" .
                  ( $post_title ? "標題：{$post_title}\n\n" : '' ) .
                  "內容：\n" . wp_strip_all_tags( $source_content ) . "\n\n" .
                  "要求：\n" .
                  "- 標題改為問句格式\n" .
                  "- H2/H3 使用問句\n" .
                  "- 開頭加 Featured Snippet 摘要\n" .
                  "- 結尾加 5 個 FAQ\n" .
                  "- 保持繁體中文，字數相近或更豐富\n\n" .
                  "請嚴格只以 JSON 格式回傳，不要加說明或 markdown：\n" .
                  '{"title":"","content":"完整HTML文章","excerpt":"","faq":[{"question":"","answer":""}],"seo_title":"","seo_description":"","focus_keyword":""}';

        $system_prompt = '你是專業的繁體中文 SEO 文章編輯，擅長在保留原文精神的前提下優化文章結構與搜尋能見度。請嚴格只回傳 JSON，不含任何說明文字。';

        // 準備圖片
        $images = [];
        foreach ( array_slice( $image_ids, 0, 3 ) as $id ) {
            $img_data = $this->get_image_data_for_ai( $id );
            if ( $img_data ) {
                $images[] = $img_data;
            }
        }

        $ai     = new Wrova_AI_Provider_Service();
        $result = $ai->generate( $prompt, $system_prompt, $images );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $improved_html = $result['content'] ?? '';
        if ( empty( $improved_html ) ) {
            return new WP_Error( 'empty_result', 'AI 未回傳有效的文章內容，請再試一次', [ 'status' => 500 ] );
        }

        // 切割段落供並排比對
        $builder    = new Wrova_Content_Builder();
        $diff       = $builder->build_diff( $source_content, $improved_html );
        $image_alts = [];
        foreach ( $image_ids as $id ) {
            $image_alts[ $id ] = $result['focus_keyword'] ?? get_the_title( $id );
        }

        return new WP_REST_Response( [
            'improved_content'    => $improved_html,
            'original_title'      => $post_title,
            'paragraphs'          => $diff,
            'title'               => $result['title'] ?? $post_title,
            'excerpt'             => $result['excerpt'] ?? '',
            'faq'                 => $result['faq'] ?? [],
            'seo_title'           => $result['seo_title'] ?? '',
            'seo_description'     => $result['seo_description'] ?? '',
            'focus_keyword'       => $result['focus_keyword'] ?? '',
            'image_alts'          => $image_alts,
        ] );
    }

    // 發布（Module A + B 共用）
    public function handle_publish( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id           = intval( $request->get_param( 'post_id' ) ?? 0 );
        $module            = sanitize_text_field( $request->get_param( 'module' ) ?? 'new' );
        $status            = sanitize_text_field( $request->get_param( 'status' ) ?? 'publish' );
        $title             = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
        $confirmed_content = wp_kses_post( $request->get_param( 'confirmed_content' ) ?? '' );
        $seo_meta          = (array) ( $request->get_param( 'seo_meta' ) ?? [] );
        $image_alts        = (array) ( $request->get_param( 'image_alts' ) ?? [] );
        $faq               = (array) ( $request->get_param( 'faq' ) ?? [] );
        $prompt_template   = sanitize_text_field( $request->get_param( 'prompt_template' ) ?? '' );

        // 組合最終 HTML（加入 featured snippet + FAQ）
        $builder = new Wrova_Content_Builder();
        $final_content = $confirmed_content;
        if ( ! empty( $faq ) ) {
            $final_content .= $builder->build_faq_block( $faq );
        }

        $post_data = [
            'post_title'   => $title,
            'post_content' => $final_content,
            'post_status'  => in_array( $status, [ 'publish', 'draft' ], true ) ? $status : 'draft',
            'post_type'    => 'post',
        ];

        if ( $post_id ) {
            $post_data['ID'] = $post_id;
            $result_id       = wp_update_post( $post_data, true );
        } else {
            $result_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result_id ) ) {
            return $result_id;
        }

        // 寫入 SEO meta
        if ( ! empty( $seo_meta ) ) {
            $seo_service = new Wrova_SEO_Service();
            $seo_service->write_seo_meta( $result_id, [
                'title'         => sanitize_text_field( $seo_meta['title'] ?? '' ),
                'description'   => sanitize_textarea_field( $seo_meta['description'] ?? '' ),
                'focus_keyword' => sanitize_text_field( $seo_meta['focus_keyword'] ?? '' ),
            ] );
        }

        // 更新圖片 alt text
        if ( ! empty( $image_alts ) ) {
            $seo_service = new Wrova_SEO_Service();
            $sanitized_alts = [];
            foreach ( $image_alts as $id => $alt ) {
                $sanitized_alts[ intval( $id ) ] = sanitize_text_field( $alt );
            }
            $seo_service->update_image_alts( $sanitized_alts );
        }

        // 寫入 Schema
        $schema_raw = $request->get_param( 'schema_json' ) ?? '';
        if ( $schema_raw ) {
            $seo_service = new Wrova_SEO_Service();
            $seo_service->inject_schema( $result_id, $schema_raw );
        }

        // 寫入 Wrova postmeta
        update_post_meta( $result_id, '_wrova_generated', '1' );
        update_post_meta( $result_id, '_wrova_module', $module );
        update_post_meta( $result_id, '_wrova_generated_at', time() );
        if ( $prompt_template ) {
            update_post_meta( $result_id, '_wrova_prompt_template', $prompt_template );
        }

        return new WP_REST_Response( [
            'post_id'  => $result_id,
            'post_url' => get_permalink( $result_id ),
            'edit_url' => get_edit_post_link( $result_id, 'raw' ),
        ] );
    }

    // 分析圖片
    public function handle_analyze_images( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $image_ids = array_map( 'intval', (array) ( $request->get_param( 'image_ids' ) ?? [] ) );

        if ( empty( $image_ids ) ) {
            return new WP_Error( 'missing_images', '請提供圖片 ID', [ 'status' => 400 ] );
        }

        $analyzer = new Wrova_Image_Analyzer();
        $results  = $analyzer->analyze( $image_ids );

        return new WP_REST_Response( $results );
    }

    // 取得 prompt 範本列表
    public function handle_get_templates( WP_REST_Request $request ): WP_REST_Response {
        $manager = new Wrova_Prompt_Manager();
        return new WP_REST_Response( $manager->get_templates() );
    }

    // 儲存 prompt 範本
    public function handle_save_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $template = [
            'slug'                  => sanitize_key( $request->get_param( 'slug' ) ?? '' ),
            'name'                  => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
            'category'              => sanitize_text_field( $request->get_param( 'category' ) ?? 'custom' ),
            'system_prompt'         => sanitize_textarea_field( $request->get_param( 'system_prompt' ) ?? '' ),
            'user_prompt_template'  => sanitize_textarea_field( $request->get_param( 'user_prompt_template' ) ?? '' ),
            'is_default'            => false,
        ];

        if ( empty( $template['slug'] ) || empty( $template['name'] ) ) {
            return new WP_Error( 'missing_fields', '請填寫 slug 與名稱', [ 'status' => 400 ] );
        }

        $manager = new Wrova_Prompt_Manager();
        $manager->save_template( $template );

        return new WP_REST_Response( [ 'success' => true ] );
    }

    // 刪除 prompt 範本
    public function handle_delete_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $slug    = sanitize_key( $request->get_param( 'slug' ) );
        $manager = new Wrova_Prompt_Manager();
        $tpl     = $manager->get_template( $slug );

        if ( ! $tpl ) {
            return new WP_Error( 'not_found', '找不到此範本', [ 'status' => 404 ] );
        }

        if ( ! empty( $tpl['is_default'] ) ) {
            return new WP_Error( 'cannot_delete_default', '預設範本不可刪除', [ 'status' => 403 ] );
        }

        $templates = array_filter( $manager->get_templates(), fn( $t ) => $t['slug'] !== $slug );
        update_option( 'wrova_prompt_templates', array_values( $templates ) );

        return new WP_REST_Response( [ 'success' => true ] );
    }

    // 取得文章內所有圖片 attachment ID
    private function get_post_image_ids( WP_Post $post ): array {
        $ids = [];

        // 特色圖片
        $thumbnail_id = get_post_thumbnail_id( $post->ID );
        if ( $thumbnail_id ) {
            $ids[] = $thumbnail_id;
        }

        // 內文附圖
        $attachments = get_posts( [
            'post_type'      => 'attachment',
            'post_parent'    => $post->ID,
            'post_mime_type' => 'image',
            'posts_per_page' => 10,
            'fields'         => 'ids',
        ] );
        $ids = array_merge( $ids, $attachments );

        // 從內文 img src 找 attachment ID
        preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $matches );
        foreach ( $matches[1] as $src ) {
            $id = attachment_url_to_postid( $src );
            if ( $id ) {
                $ids[] = $id;
            }
        }

        return array_unique( array_filter( $ids ) );
    }

    // 取得圖片 base64 資料給 AI
    private function get_image_data_for_ai( int $attachment_id ): ?array {
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return null;
        }
        $mime = get_post_mime_type( $attachment_id );
        if ( ! $mime || ! str_starts_with( $mime, 'image/' ) ) {
            return null;
        }
        $content = file_get_contents( $file );
        if ( ! $content ) {
            return null;
        }
        return [
            'mime_type' => $mime,
            'data'      => base64_encode( $content ),
        ];
    }
}
