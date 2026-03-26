<?php
/**
 * Wrova 設定頁
 *
 * @package Wrova
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 處理表單送出
$saved_notice = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['_wpnonce'] ) ) {
    check_admin_referer( 'wrova_settings_nonce' );

    $existing = get_option( 'wrova_settings', [] );

    $new_gemini_key = sanitize_text_field( $_POST['gemini_api_key'] ?? '' );
    $new_claude_key = sanitize_text_field( $_POST['claude_api_key'] ?? '' );

    // 若 API Key 欄位仍是遮罩值（以 * 開頭），保留舊值
    if ( preg_match( '/^\*+/', $new_gemini_key ) ) {
        $new_gemini_key = $existing['gemini_api_key'] ?? '';
    }
    if ( preg_match( '/^\*+/', $new_claude_key ) ) {
        $new_claude_key = $existing['claude_api_key'] ?? '';
    }

    $settings = [
        'ai_provider'             => sanitize_text_field( $_POST['ai_provider'] ?? 'gemini' ),
        'gemini_api_key'          => $new_gemini_key,
        'gemini_model'            => sanitize_text_field( $_POST['gemini_model'] ?? 'gemini-2.0-flash-exp' ),
        'claude_api_key'          => $new_claude_key,
        'claude_model'            => sanitize_text_field( $_POST['claude_model'] ?? 'claude-sonnet-4-6' ),
        'github_repo'             => sanitize_text_field( $_POST['github_repo'] ?? '' ),
        'default_prompt_template' => sanitize_text_field( $_POST['default_prompt_template'] ?? '' ),
    ];

    update_option( 'wrova_settings', $settings );
    $saved_notice = '<div class="notice notice-success is-dismissible"><p>設定已儲存。</p></div>';
}

$settings = get_option( 'wrova_settings', [] );

$ai_provider             = $settings['ai_provider'] ?? 'gemini';
$gemini_api_key          = $settings['gemini_api_key'] ?? '';
$gemini_model            = $settings['gemini_model'] ?? 'gemini-2.0-flash-exp';
$claude_api_key          = $settings['claude_api_key'] ?? '';
$claude_model            = $settings['claude_model'] ?? 'claude-sonnet-4-6';
$github_repo             = $settings['github_repo'] ?? '';
$default_prompt_template = $settings['default_prompt_template'] ?? '';

// 取得 Prompt 範本清單
$prompt_manager   = new Wrova_Prompt_Manager();
$prompt_templates = $prompt_manager->get_templates();

// 產生遮罩顯示（只顯示末 4 碼，其餘用 * 填充）
function wrova_mask_api_key( string $key ): string {
    if ( empty( $key ) ) {
        return '';
    }
    $visible = substr( $key, -4 );
    $masked  = str_repeat( '*', min( 20, max( 8, strlen( $key ) - 4 ) ) );
    return $masked . $visible;
}

?>
<div class="wrap wrova-settings-wrap">
    <h1>Wrova 設定</h1>

    <?php echo $saved_notice; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'wrova_settings_nonce' ); ?>

        <nav class="nav-tab-wrapper wrova-nav-tabs">
            <a href="#" class="nav-tab nav-tab-active" data-wrova-tab="tab-ai">AI 模型</a>
            <a href="#" class="nav-tab" data-wrova-tab="tab-prompt">Prompt 設定</a>
            <a href="#" class="nav-tab" data-wrova-tab="tab-update">自動更新</a>
        </nav>

        <!-- Tab：AI 模型設定 -->
        <div id="tab-ai" class="wrova-tab-pane">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ai_provider">使用的 AI 模型</label></th>
                    <td>
                        <select name="ai_provider" id="ai_provider">
                            <option value="gemini" <?php selected( $ai_provider, 'gemini' ); ?>>Gemini（Google）</option>
                            <option value="claude" <?php selected( $ai_provider, 'claude' ); ?>>Claude（Anthropic）</option>
                        </select>
                        <p class="description">選擇用於產文的 AI 服務。</p>
                    </td>
                </tr>
            </table>

            <hr>
            <h3>Gemini 設定</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="gemini_api_key">Gemini API Key</label></th>
                    <td>
                        <div class="wrova-key-row">
                            <input
                                type="password"
                                name="gemini_api_key"
                                id="gemini_api_key"
                                class="regular-text wrova-key-input"
                                value="<?php echo esc_attr( wrova_mask_api_key( $gemini_api_key ) ); ?>"
                                autocomplete="new-password"
                                data-has-value="<?php echo ! empty( $gemini_api_key ) ? '1' : '0'; ?>"
                            >
                            <button type="button" class="button wrova-btn-toggle-key" aria-label="顯示或隱藏 API Key" data-target="gemini_api_key">
                                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="button wrova-btn-test" data-provider="gemini">
                                測試連線
                            </button>
                            <span class="wrova-test-status" id="wrova-test-gemini" aria-live="polite"></span>
                        </div>
                        <p class="description">
                            前往 <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a> 取得 API Key。
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gemini_model">Gemini 模型版本</label></th>
                    <td>
                        <input
                            type="text"
                            name="gemini_model"
                            id="gemini_model"
                            class="regular-text"
                            value="<?php echo esc_attr( $gemini_model ); ?>"
                        >
                        <p class="description">
                            預設：<code>gemini-2.0-flash-exp</code>。
                            其他選項：<code>gemini-1.5-pro</code>、<code>gemini-1.5-flash</code>。
                        </p>
                    </td>
                </tr>
            </table>

            <hr>
            <h3>Claude 設定</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="claude_api_key">Claude API Key</label></th>
                    <td>
                        <div class="wrova-key-row">
                            <input
                                type="password"
                                name="claude_api_key"
                                id="claude_api_key"
                                class="regular-text wrova-key-input"
                                value="<?php echo esc_attr( wrova_mask_api_key( $claude_api_key ) ); ?>"
                                autocomplete="new-password"
                                data-has-value="<?php echo ! empty( $claude_api_key ) ? '1' : '0'; ?>"
                            >
                            <button type="button" class="button wrova-btn-toggle-key" aria-label="顯示或隱藏 API Key" data-target="claude_api_key">
                                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="button wrova-btn-test" data-provider="claude">
                                測試連線
                            </button>
                            <span class="wrova-test-status" id="wrova-test-claude" aria-live="polite"></span>
                        </div>
                        <p class="description">
                            前往 <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">Anthropic Console</a> 取得 API Key。
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="claude_model">Claude 模型版本</label></th>
                    <td>
                        <input
                            type="text"
                            name="claude_model"
                            id="claude_model"
                            class="regular-text"
                            value="<?php echo esc_attr( $claude_model ); ?>"
                        >
                        <p class="description">
                            預設：<code>claude-sonnet-4-6</code>。
                            其他選項：<code>claude-opus-4-5</code>、<code>claude-haiku-3-5</code>。
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab：Prompt 設定 -->
        <div id="tab-prompt" class="wrova-tab-pane" hidden>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="default_prompt_template">預設 Prompt 範本</label></th>
                    <td>
                        <select name="default_prompt_template" id="default_prompt_template">
                            <option value="">— 請選擇 —</option>
                            <?php foreach ( $prompt_templates as $tpl ) : ?>
                                <option
                                    value="<?php echo esc_attr( $tpl['slug'] ); ?>"
                                    <?php selected( $default_prompt_template, $tpl['slug'] ); ?>
                                >
                                    <?php echo esc_html( $tpl['name'] ); ?>
                                    <?php if ( ! empty( $tpl['is_default'] ) ) : ?>（系統預設）<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            產文時若未指定範本，套用此設定。
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wrova-prompts' ) ); ?>">管理 Prompt 範本 →</a>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab：自動更新 -->
        <div id="tab-update" class="wrova-tab-pane" hidden>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="github_repo">GitHub Repo</label></th>
                    <td>
                        <input
                            type="text"
                            name="github_repo"
                            id="github_repo"
                            class="regular-text"
                            value="<?php echo esc_attr( $github_repo ); ?>"
                            placeholder="owner/repo"
                        >
                        <p class="description">
                            供 sk-github-updater 使用，格式：<code>owner/repo</code>
                            （例如：<code>skiseiju/wrova</code>）。
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( '儲存設定' ); ?>
    </form>
</div>

<style>
.wrova-settings-wrap .wrova-nav-tabs { margin-bottom: 0; }
.wrova-settings-wrap .wrova-tab-pane { padding-top: 8px; }

.wrova-settings-wrap .wrova-key-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.wrova-settings-wrap .wrova-key-row .regular-text {
    flex: 1 1 260px;
    max-width: 380px;
    font-family: monospace;
}
.wrova-settings-wrap .wrova-test-status {
    font-size: 13px;
    margin-left: 2px;
}
.wrova-settings-wrap .wrova-test-status.is-loading { color: #888; }
.wrova-settings-wrap .wrova-test-status.is-success { color: #46b450; font-weight: 600; }
.wrova-settings-wrap .wrova-test-status.is-error   { color: #dc3232; font-weight: 600; }
</style>

<script>
(function ($) {
    'use strict';

    /* ---- Tab 切換 ---- */
    $('.wrova-nav-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();
        var target = $(this).data('wrovaTab');
        $('.wrova-nav-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.wrova-tab-pane').attr('hidden', true);
        $('#' + target).removeAttr('hidden');
    });

    /* ---- API Key 顯示 / 隱藏切換 ---- */
    $('.wrova-btn-toggle-key').on('click', function () {
        var targetId = $(this).data('target');
        var $input   = $('#' + targetId);
        var $icon    = $(this).find('.dashicons');
        var isPass   = $input.attr('type') === 'password';

        if ( isPass ) {
            // 若目前是遮罩值，清空讓使用者輸入真實 key
            if ( $input.data('hasValue') === '1' || $input.data('hasValue') === 1 ) {
                $input.val('').data('hasValue', 0);
            }
            $input.attr('type', 'text');
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // 使用者手動輸入時，取消遮罩保護標記
    $('.wrova-key-input').on('input', function () {
        $(this).data('hasValue', 0);
    });

    /* ---- 測試連線（呼叫 REST endpoint） ---- */
    $('.wrova-btn-test').on('click', function () {
        var provider = $(this).data('provider');
        var $status  = $('#wrova-test-' + provider);

        $status.removeClass('is-success is-error').addClass('is-loading').text('測試中…');

        wp.apiFetch({
            path: 'wrova/v1/test-connection',
            method: 'POST',
            data: { provider: provider },
        }).then(function () {
            $status.removeClass('is-loading is-error').addClass('is-success').text('✔ 連線成功');
        }).catch(function (err) {
            var msg = (err && err.message) ? err.message : '連線失敗，請確認 API Key 與模型版本。';
            $status.removeClass('is-loading is-success').addClass('is-error').text('✘ ' + msg);
        });
    });

}(jQuery));
</script>
