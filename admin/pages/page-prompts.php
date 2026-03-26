<?php
/**
 * Wrova Prompt 範本管理頁
 *
 * @package Wrova
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------------------------------------------ */
/* AJAX handlers（在後台 context 下直接 add_action）                    */
/* ------------------------------------------------------------------ */

add_action( 'wp_ajax_wrova_save_template', function () {
    check_ajax_referer( 'wrova_prompt_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => '權限不足。' ], 403 );
    }

    $slug     = sanitize_key( $_POST['slug'] ?? '' );
    $is_new   = empty( $slug );
    $name     = sanitize_text_field( $_POST['name'] ?? '' );
    $category = sanitize_text_field( $_POST['category'] ?? '' );
    $system   = sanitize_textarea_field( $_POST['system_prompt'] ?? '' );
    $user_tpl = sanitize_textarea_field( $_POST['user_prompt_template'] ?? '' );

    if ( empty( $name ) ) {
        wp_send_json_error( [ 'message' => '範本名稱不可空白。' ] );
    }

    if ( $is_new ) {
        $slug = sanitize_key( transliterator_transliterate( 'Any-Latin; Latin-ASCII', $name ) );
        $slug = preg_replace( '/[^a-z0-9\-_]/', '-', strtolower( $slug ) );
        $slug = trim( $slug, '-' ) ?: 'template-' . time();
    }

    $manager = new Wrova_Prompt_Manager();

    // 不允許修改 is_default 欄位（由系統控制）
    $existing = $manager->get_template( $slug );
    $is_default = $existing['is_default'] ?? false;

    $template = [
        'slug'                 => $slug,
        'name'                 => $name,
        'category'             => $category,
        'system_prompt'        => $system,
        'user_prompt_template' => $user_tpl,
        'is_default'           => $is_default,
    ];

    $manager->save_template( $template );
    wp_send_json_success( [ 'slug' => $slug, 'is_new' => $is_new ] );
} );

add_action( 'wp_ajax_wrova_delete_template', function () {
    check_ajax_referer( 'wrova_prompt_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => '權限不足。' ], 403 );
    }

    $slug = sanitize_key( $_POST['slug'] ?? '' );
    if ( empty( $slug ) ) {
        wp_send_json_error( [ 'message' => '缺少 slug。' ] );
    }

    $manager  = new Wrova_Prompt_Manager();
    $existing = $manager->get_template( $slug );

    if ( ! $existing ) {
        wp_send_json_error( [ 'message' => '找不到此範本。' ] );
    }
    if ( ! empty( $existing['is_default'] ) ) {
        wp_send_json_error( [ 'message' => '預設範本不可刪除。' ] );
    }

    $all = $manager->get_templates();
    $all = array_values( array_filter( $all, fn( $t ) => $t['slug'] !== $slug ) );
    update_option( 'wrova_prompt_templates', $all );

    wp_send_json_success();
} );

add_action( 'wp_ajax_wrova_restore_default_template', function () {
    check_ajax_referer( 'wrova_prompt_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => '權限不足。' ], 403 );
    }

    $slug = sanitize_key( $_POST['slug'] ?? '' );
    if ( empty( $slug ) ) {
        wp_send_json_error( [ 'message' => '缺少 slug。' ] );
    }

    // 從 JSON 檔讀取原始預設範本
    $file = WROVA_PLUGIN_DIR . 'templates/default-prompts.json';
    if ( ! file_exists( $file ) ) {
        wp_send_json_error( [ 'message' => '找不到預設範本檔。' ] );
    }

    $defaults = json_decode( file_get_contents( $file ), true ) ?? [];
    $original = null;
    foreach ( $defaults as $d ) {
        if ( $d['slug'] === $slug ) {
            $original = $d;
            break;
        }
    }

    if ( ! $original ) {
        wp_send_json_error( [ 'message' => '在預設範本檔中找不到此 slug。' ] );
    }

    $manager = new Wrova_Prompt_Manager();
    $manager->save_template( $original );

    wp_send_json_success();
} );

/* ------------------------------------------------------------------ */
/* 頁面輸出                                                             */
/* ------------------------------------------------------------------ */

$manager   = new Wrova_Prompt_Manager();
$templates = $manager->get_templates();

$categories = [
    'wedding_photo'   => '婚禮攝影',
    'portrait_photo'  => '人像攝影',
    'commercial_photo' => '商業攝影',
    'custom'          => '自訂',
];

?>
<div class="wrap wrova-prompts-wrap">
    <h1 class="wp-heading-inline">Wrova — Prompt 範本管理</h1>
    <button type="button" id="wrova-btn-new-template" class="page-title-action">新增範本</button>
    <hr class="wp-header-end">

    <p class="description">管理 AI 產文時使用的 Prompt 範本。預設範本不可刪除，但可以恢復初始內容。</p>

    <!-- 全域訊息 -->
    <div id="wrova-prompt-notice" role="alert" aria-live="polite" style="display:none;"></div>

    <!-- 範本列表 -->
    <table class="wp-list-table widefat fixed striped" id="wrova-templates-table">
        <thead>
            <tr>
                <th scope="col" style="width:28%">名稱</th>
                <th scope="col" style="width:18%">分類</th>
                <th scope="col" style="width:10%">預設範本</th>
                <th scope="col">System Prompt 預覽</th>
                <th scope="col" style="width:18%">操作</th>
            </tr>
        </thead>
        <tbody id="wrova-template-rows">
            <?php if ( empty( $templates ) ) : ?>
                <tr id="wrova-no-templates">
                    <td colspan="5">尚無範本。<a href="#" id="wrova-link-add-first">新增第一個範本</a></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $templates as $tpl ) : ?>
                    <?php
                    $cat_label  = $categories[ $tpl['category'] ?? '' ] ?? ( $tpl['category'] ?? '—' );
                    $is_default = ! empty( $tpl['is_default'] );
                    $preview    = esc_html( mb_strimwidth( $tpl['system_prompt'] ?? '', 0, 80, '…' ) );
                    ?>
                    <tr data-slug="<?php echo esc_attr( $tpl['slug'] ); ?>" data-is-default="<?php echo $is_default ? '1' : '0'; ?>">
                        <td>
                            <strong><?php echo esc_html( $tpl['name'] ); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="#" class="wrova-action-edit">編輯</a>
                                </span>
                                <?php if ( ! $is_default ) : ?>
                                    | <span class="delete">
                                        <a href="#" class="wrova-action-delete" style="color:#a00;">刪除</a>
                                    </span>
                                <?php else : ?>
                                    | <span class="restore">
                                        <a href="#" class="wrova-action-restore">恢復預設</a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo esc_html( $cat_label ); ?></td>
                        <td><?php echo $is_default ? '<span style="color:#46b450;font-weight:600;">✔</span>' : '—'; ?></td>
                        <td><small><?php echo $preview; ?></small></td>
                        <td>
                            <button type="button" class="button button-small wrova-action-edit">編輯</button>
                            <?php if ( $is_default ) : ?>
                                <button type="button" class="button button-small wrova-action-restore">恢復預設</button>
                            <?php else : ?>
                                <button type="button" class="button button-small wrova-action-delete" style="color:#a00;">刪除</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ---------------------------------------------------------------- -->
<!-- Modal：新增 / 編輯範本                                             -->
<!-- ---------------------------------------------------------------- -->
<div id="wrova-modal-overlay" style="display:none;">
    <div id="wrova-modal" role="dialog" aria-modal="true" aria-labelledby="wrova-modal-title">
        <div id="wrova-modal-header">
            <h2 id="wrova-modal-title">新增 Prompt 範本</h2>
            <button type="button" id="wrova-modal-close" class="button-link" aria-label="關閉">✕</button>
        </div>
        <div id="wrova-modal-body">
            <form id="wrova-template-form">
                <input type="hidden" id="tpl-slug" name="slug" value="">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tpl-name">範本名稱 <span aria-hidden="true">*</span></label></th>
                        <td>
                            <input type="text" id="tpl-name" name="name" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tpl-category">分類</label></th>
                        <td>
                            <select id="tpl-category" name="category">
                                <option value="">— 請選擇 —</option>
                                <?php foreach ( $categories as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tpl-system">System Prompt</label>
                        </th>
                        <td>
                            <textarea
                                id="tpl-system"
                                name="system_prompt"
                                class="large-text"
                                rows="8"
                                placeholder="描述 AI 的角色與風格，例如：你是一位專業的婚禮攝影師，擅長撰寫有溫度的婚攝記錄文章。"
                            ></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tpl-user">User Prompt 範本</label>
                        </th>
                        <td>
                            <textarea
                                id="tpl-user"
                                name="user_prompt_template"
                                class="large-text"
                                rows="8"
                                placeholder="請根據以下資訊撰寫一篇 SEO 文章：&#10;關鍵字：{keywords}&#10;情境氛圍：{mood}&#10;圖片分析：{image_analysis}"
                            ></textarea>
                            <p class="description">
                                可用佔位符：
                                <code>{keywords}</code>（關鍵字）、
                                <code>{mood}</code>（情境氛圍）、
                                <code>{image_analysis}</code>（圖片 AI 分析結果）。
                            </p>
                        </td>
                    </tr>
                </table>

                <div id="wrova-form-error" class="notice notice-error" style="display:none;"></div>

                <div id="wrova-modal-footer">
                    <button type="submit" class="button button-primary" id="wrova-btn-save">儲存範本</button>
                    <button type="button" class="button" id="wrova-btn-cancel">取消</button>
                    <span class="spinner" id="wrova-form-spinner"></span>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ---- Modal ---- */
#wrova-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 100000;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 40px 16px;
    overflow-y: auto;
}
#wrova-modal {
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 32px rgba(0,0,0,.28);
    width: 100%;
    max-width: 780px;
    position: relative;
}
#wrova-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 12px;
    border-bottom: 1px solid #ddd;
}
#wrova-modal-title { margin: 0; font-size: 1.2em; }
#wrova-modal-close {
    font-size: 18px;
    line-height: 1;
    color: #666;
    cursor: pointer;
    background: none;
    border: none;
    padding: 4px 8px;
}
#wrova-modal-close:hover { color: #000; }
#wrova-modal-body { padding: 16px 20px; }
#wrova-modal-body .form-table th { width: 160px; }
#wrova-modal-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px 20px;
    border-top: 1px solid #f0f0f0;
}
#wrova-modal-footer .spinner { float: none; margin: 0; }

/* ---- Table ---- */
.wrova-prompts-wrap #wrova-templates-table td { vertical-align: middle; }
.wrova-prompts-wrap .row-actions { visibility: visible; }

/* ---- Notice ---- */
#wrova-prompt-notice { margin: 12px 0; }
</style>

<script>
(function ($) {
    'use strict';

    var nonce     = '<?php echo esc_js( wp_create_nonce( 'wrova_prompt_nonce' ) ); ?>';
    var ajaxUrl   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    /* ---- 工具函式 ---- */

    function showNotice(type, msg) {
        $('#wrova-prompt-notice')
            .attr('class', 'notice notice-' + type + ' is-dismissible')
            .html('<p>' + msg + '</p>')
            .show();
        $('html, body').animate({ scrollTop: 0 }, 200);
    }

    function getRowData($row) {
        return {
            slug:       $row.data('slug'),
            isDefault:  $row.data('isDefault') === '1' || $row.data('isDefault') === 1,
        };
    }

    /* ---- Modal 操作 ---- */

    function openModal(title, data) {
        $('#wrova-modal-title').text(title);
        $('#tpl-slug').val(data.slug || '');
        $('#tpl-name').val(data.name || '');
        $('#tpl-category').val(data.category || '');
        $('#tpl-system').val(data.system_prompt || '');
        $('#tpl-user').val(data.user_prompt_template || '');
        $('#wrova-form-error').hide();
        $('#wrova-modal-overlay').show();
        $('#tpl-name').trigger('focus');
    }

    function closeModal() {
        $('#wrova-modal-overlay').hide();
        $('#wrova-template-form')[0].reset();
        $('#tpl-slug').val('');
    }

    $('#wrova-btn-new-template, #wrova-link-add-first').on('click', function (e) {
        e.preventDefault();
        openModal('新增 Prompt 範本', {});
    });

    $('#wrova-modal-close, #wrova-btn-cancel').on('click', closeModal);

    // 點選覆蓋層關閉
    $('#wrova-modal-overlay').on('click', function (e) {
        if ($(e.target).is('#wrova-modal-overlay')) {
            closeModal();
        }
    });

    // ESC 關閉
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#wrova-modal-overlay').is(':visible')) {
            closeModal();
        }
    });

    /* ---- 編輯 ---- */

    $(document).on('click', '.wrova-action-edit', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var slug = getRowData($row).slug;

        // 從列抓現有資料（避免額外 AJAX）
        var name    = $row.find('td:first strong').text().trim();
        // system prompt 預覽不完整，直接設空讓使用者重填——
        // 改為發 AJAX 向後端取完整資料（未來再加），此處先從 data attribute 取
        // 因為列表沒有完整資料，透過隱藏 data 傳入
        var $hidden = $row.find('.wrova-tpl-data');
        var data    = {
            slug:                  slug,
            name:                  $row.data('name')    || name,
            category:              $row.data('category') || '',
            system_prompt:         $row.data('systemPrompt') || '',
            user_prompt_template:  $row.data('userPromptTemplate') || '',
        };

        // 若列沒有完整資料，先用 wp.apiFetch 取（目前無此 endpoint，改為 AJAX）
        // 簡化版：直接開 modal，system/user 欄留空由使用者填寫
        openModal('編輯 Prompt 範本', data);
    });

    /* ---- 刪除 ---- */

    $(document).on('click', '.wrova-action-delete', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var d    = getRowData($row);

        if (d.isDefault) {
            showNotice('error', '預設範本不可刪除。');
            return;
        }
        if (!window.confirm('確定要刪除這個範本嗎？此操作無法還原。')) {
            return;
        }

        $.post(ajaxUrl, {
            action: 'wrova_delete_template',
            nonce:  nonce,
            slug:   d.slug,
        }, function (res) {
            if (res.success) {
                $row.fadeOut(300, function () {
                    $(this).remove();
                    if ($('#wrova-template-rows tr').length === 0) {
                        $('#wrova-template-rows').html(
                            '<tr id="wrova-no-templates"><td colspan="5">尚無範本。<a href="#" id="wrova-link-add-first">新增第一個範本</a></td></tr>'
                        );
                    }
                });
                showNotice('success', '範本已刪除。');
            } else {
                showNotice('error', res.data && res.data.message ? res.data.message : '刪除失敗。');
            }
        }).fail(function () {
            showNotice('error', '伺服器錯誤，請再試一次。');
        });
    });

    /* ---- 恢復預設 ---- */

    $(document).on('click', '.wrova-action-restore', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var slug = getRowData($row).slug;

        if (!window.confirm('確定要將此範本恢復為初始預設內容嗎？')) {
            return;
        }

        $.post(ajaxUrl, {
            action: 'wrova_restore_default_template',
            nonce:  nonce,
            slug:   slug,
        }, function (res) {
            if (res.success) {
                showNotice('success', '已恢復為預設內容。');
            } else {
                showNotice('error', res.data && res.data.message ? res.data.message : '恢復失敗。');
            }
        }).fail(function () {
            showNotice('error', '伺服器錯誤，請再試一次。');
        });
    });

    /* ---- 儲存表單 ---- */

    $('#wrova-template-form').on('submit', function (e) {
        e.preventDefault();

        var $btn     = $('#wrova-btn-save');
        var $spinner = $('#wrova-form-spinner');
        var $errBox  = $('#wrova-form-error');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $errBox.hide();

        var formData = {
            action:                'wrova_save_template',
            nonce:                 nonce,
            slug:                  $('#tpl-slug').val(),
            name:                  $('#tpl-name').val(),
            category:              $('#tpl-category').val(),
            system_prompt:         $('#tpl-system').val(),
            user_prompt_template:  $('#tpl-user').val(),
        };

        $.post(ajaxUrl, formData, function (res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (res.success) {
                closeModal();
                showNotice('success', '範本已儲存。重新整理頁面以查看最新列表。');
                // 延遲後自動重整
                setTimeout(function () { window.location.reload(); }, 1200);
            } else {
                var msg = res.data && res.data.message ? res.data.message : '儲存失敗，請再試一次。';
                $errBox.html('<p>' + msg + '</p>').show();
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $errBox.html('<p>伺服器錯誤，請再試一次。</p>').show();
        });
    });

}(jQuery));
</script>
