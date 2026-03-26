<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap wrova-page">
    <h1>Wrova — 全新產文</h1>
    <p class="description">提供關鍵字、照片與情緒，由 AI 生成完整 SEO + AEO 文章。</p>

    <div id="wrova-module-a">

        <!-- ===== Step 1: 輸入 ===== -->
        <div class="wrova-step" id="wrova-step-1">
            <div class="card" style="max-width:860px; padding:24px 28px;">

                <!-- 關鍵字 -->
                <div class="wrova-field">
                    <label class="wrova-label">關鍵字</label>
                    <p class="description" style="margin-bottom:8px;">輸入後按 Enter 或逗號新增標籤</p>
                    <div id="wrova-keyword-chips" class="wrova-chip-input-wrap">
                        <input type="text" id="wrova-keyword-input" class="wrova-chip-text-input" placeholder="輸入關鍵字...">
                    </div>
                </div>

                <!-- 情緒 -->
                <div class="wrova-field">
                    <label class="wrova-label">情緒風格 <span class="description">（可多選）</span></label>
                    <div id="wrova-mood-chips" class="wrova-mood-wrap">
                        <?php
                        $moods = ['溫暖', '清新', '優雅', '活潑', '浪漫', '專業', '自然', '夢幻'];
                        foreach ($moods as $mood) {
                            echo '<button type="button" class="wrova-mood-chip" data-mood="' . esc_attr($mood) . '">' . esc_html($mood) . '</button>';
                        }
                        ?>
                    </div>
                </div>

                <!-- 圖片 -->
                <div class="wrova-field">
                    <label class="wrova-label">選取照片</label>
                    <div class="wrova-image-actions">
                        <button type="button" id="wrova-btn-media" class="button">
                            <span class="dashicons dashicons-images-alt2" style="vertical-align:middle;margin-right:4px;"></span>從媒體庫選取
                        </button>
                        <button type="button" id="wrova-btn-upload-trigger" class="button" style="margin-left:8px;">
                            <span class="dashicons dashicons-upload" style="vertical-align:middle;margin-right:4px;"></span>直接上傳
                        </button>
                        <input type="file" id="wrova-file-input" accept="image/*" multiple style="display:none;">
                    </div>
                    <div id="wrova-image-preview" class="wrova-image-preview-wrap"></div>
                </div>

                <!-- Prompt 範本 -->
                <div class="wrova-field">
                    <label class="wrova-label" for="wrova-template-select">Prompt 範本</label>
                    <select id="wrova-template-select" class="regular-text">
                        <option value="">載入中...</option>
                    </select>
                </div>

                <!-- 送出 -->
                <div class="wrova-field" style="margin-top:24px;">
                    <button type="button" id="wrova-btn-generate" class="button button-primary button-large">
                        開始生成
                    </button>
                    <span id="wrova-step1-error" class="wrova-inline-error" style="display:none;"></span>
                </div>

            </div>
        </div><!-- /step-1 -->

        <!-- ===== Step 2: Loading ===== -->
        <div class="wrova-step" id="wrova-step-2" style="display:none;">
            <div class="card wrova-loading-card" style="max-width:860px; padding:48px 28px; text-align:center;">
                <div class="wrova-spinner-wrap">
                    <span class="wrova-spinner"></span>
                </div>
                <p id="wrova-loading-text" class="wrova-loading-text">正在分析圖片...</p>
                <div class="wrova-loading-steps">
                    <span id="lstat-analyze" class="wrova-lstat active">分析圖片</span>
                    <span class="wrova-lstat-sep">›</span>
                    <span id="lstat-generate" class="wrova-lstat">生成文章</span>
                    <span class="wrova-lstat-sep">›</span>
                    <span id="lstat-done" class="wrova-lstat">完成</span>
                </div>
            </div>
        </div><!-- /step-2 -->

        <!-- ===== Step 3: 預覽確認 ===== -->
        <div class="wrova-step" id="wrova-step-3" style="display:none;">
            <div style="max-width:860px;">

                <!-- 文章標題 -->
                <div class="card wrova-preview-card">
                    <h3 class="wrova-section-title">文章標題</h3>
                    <input type="text" id="wrova-preview-title" class="large-text" style="font-size:18px; font-weight:600;">
                </div>

                <!-- 文章內容 -->
                <div class="card wrova-preview-card">
                    <h3 class="wrova-section-title">文章內容</h3>
                    <div id="wrova-preview-content" class="wrova-content-editor" contenteditable="true"></div>
                </div>

                <!-- SEO Accordion -->
                <div class="card wrova-preview-card">
                    <button type="button" class="wrova-accordion-toggle" aria-expanded="false" aria-controls="wrova-seo-panel">
                        <span class="dashicons dashicons-search" style="vertical-align:middle;margin-right:6px;"></span>
                        SEO 設定
                        <span class="wrova-accordion-arrow">▾</span>
                    </button>
                    <div id="wrova-seo-panel" class="wrova-accordion-panel" style="display:none;">
                        <div class="wrova-seo-grid">
                            <div class="wrova-field">
                                <label class="wrova-label" for="wrova-seo-title">SEO 標題</label>
                                <input type="text" id="wrova-seo-title" class="large-text">
                                <span id="wrova-seo-title-count" class="wrova-char-count">0 / 60</span>
                            </div>
                            <div class="wrova-field">
                                <label class="wrova-label" for="wrova-seo-desc">Meta Description</label>
                                <textarea id="wrova-seo-desc" class="large-text" rows="3"></textarea>
                                <span id="wrova-seo-desc-count" class="wrova-char-count">0 / 160</span>
                            </div>
                            <div class="wrova-field">
                                <label class="wrova-label" for="wrova-seo-keyword">Focus Keyword</label>
                                <input type="text" id="wrova-seo-keyword" class="regular-text">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ -->
                <div class="card wrova-preview-card">
                    <h3 class="wrova-section-title">FAQ</h3>
                    <div id="wrova-faq-list"></div>
                </div>

                <!-- 圖片 Alt Text -->
                <div class="card wrova-preview-card">
                    <h3 class="wrova-section-title">圖片 Alt Text</h3>
                    <div id="wrova-alt-list"></div>
                </div>

                <!-- 操作按鈕 -->
                <div class="wrova-preview-actions">
                    <button type="button" id="wrova-btn-publish" class="button button-primary button-large">
                        <span class="dashicons dashicons-upload" style="vertical-align:middle;margin-right:4px;"></span>發布文章
                    </button>
                    <button type="button" id="wrova-btn-draft" class="button button-large" style="margin-left:8px;">
                        <span class="dashicons dashicons-media-document" style="vertical-align:middle;margin-right:4px;"></span>存為草稿
                    </button>
                    <button type="button" id="wrova-btn-regenerate" class="button button-large" style="margin-left:8px; color:#d63638;">
                        <span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:4px;"></span>重新生成
                    </button>
                    <span id="wrova-publish-status" class="wrova-publish-status" style="display:none;"></span>
                </div>

            </div>
        </div><!-- /step-3 -->

        <!-- ===== Step 4: 發布結果 ===== -->
        <div class="wrova-step" id="wrova-step-4" style="display:none;">
            <div class="card" style="max-width:860px; padding:40px 28px; text-align:center;">
                <span class="dashicons dashicons-yes-alt" style="font-size:64px; width:64px; height:64px; color:#00a32a; display:block; margin:0 auto 16px;"></span>
                <h2 id="wrova-done-title" style="margin-bottom:8px;">文章已發布！</h2>
                <p id="wrova-done-link" style="margin-bottom:24px;"></p>
                <button type="button" id="wrova-btn-new" class="button button-primary">產生新文章</button>
            </div>
        </div><!-- /step-4 -->

    </div><!-- #wrova-module-a -->
</div><!-- .wrap -->


<style>
/* ============================
   Wrova Module A — Inline CSS
   ============================ */

#wrova-module-a .wrova-field {
    margin-bottom: 20px;
}

#wrova-module-a .wrova-label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 13px;
    color: #1d2327;
}

/* --- Keyword chip input --- */
.wrova-chip-input-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    min-height: 40px;
    padding: 6px 10px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    background: #fff;
    cursor: text;
    max-width: 600px;
    box-sizing: border-box;
}

.wrova-chip-input-wrap:focus-within {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

.wrova-chip-text-input {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 2px 4px !important;
    min-width: 120px;
    flex: 1;
    font-size: 13px;
    background: transparent;
}

.wrova-keyword-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #2271b1;
    color: #fff;
    border-radius: 100px;
    padding: 3px 10px 3px 12px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.wrova-keyword-chip .wrova-chip-remove {
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    opacity: 0.75;
    background: none;
    border: none;
    color: inherit;
    padding: 0;
    display: flex;
    align-items: center;
}

.wrova-keyword-chip .wrova-chip-remove:hover {
    opacity: 1;
}

/* --- Mood chips --- */
.wrova-mood-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    max-width: 600px;
}

.wrova-mood-chip {
    border: 2px solid #8c8f94;
    background: #fff;
    color: #50575e;
    border-radius: 100px;
    padding: 5px 16px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.wrova-mood-chip:hover {
    border-color: #2271b1;
    color: #2271b1;
}

.wrova-mood-chip.selected {
    border-color: #2271b1;
    background: #2271b1;
    color: #fff;
}

/* --- Image area --- */
.wrova-image-actions {
    margin-bottom: 12px;
}

.wrova-image-preview-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    min-height: 0;
    max-width: 600px;
}

.wrova-image-thumb {
    position: relative;
    width: 80px;
    height: 80px;
    border-radius: 6px;
    overflow: hidden;
    border: 2px solid #dcdcde;
    background: #f0f0f1;
    flex-shrink: 0;
}

.wrova-image-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.wrova-image-thumb .wrova-thumb-remove {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(0,0,0,0.6);
    color: #fff;
    font-size: 14px;
    line-height: 20px;
    text-align: center;
    cursor: pointer;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.15s;
}

.wrova-image-thumb:hover .wrova-thumb-remove {
    opacity: 1;
}

/* --- Loading --- */
.wrova-loading-card {
    text-align: center;
}

.wrova-spinner-wrap {
    margin-bottom: 20px;
}

.wrova-spinner {
    display: inline-block;
    width: 48px;
    height: 48px;
    border: 4px solid #dcdcde;
    border-top-color: #2271b1;
    border-radius: 50%;
    animation: wrova-spin 0.8s linear infinite;
}

@keyframes wrova-spin {
    to { transform: rotate(360deg); }
}

.wrova-loading-text {
    font-size: 16px;
    color: #50575e;
    margin-bottom: 16px;
}

.wrova-loading-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 12px;
}

.wrova-lstat {
    color: #8c8f94;
    padding: 3px 10px;
    border-radius: 100px;
    background: #f0f0f1;
    transition: all 0.2s;
}

.wrova-lstat.active {
    background: #2271b1;
    color: #fff;
}

.wrova-lstat.done {
    background: #00a32a;
    color: #fff;
}

.wrova-lstat-sep {
    color: #8c8f94;
}

/* --- Preview cards --- */
.wrova-preview-card {
    padding: 20px 24px;
    margin-bottom: 16px;
}

.wrova-section-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 14px;
    color: #1d2327;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f1;
}

/* --- Content editor --- */
.wrova-content-editor {
    min-height: 200px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    padding: 12px;
    font-size: 14px;
    line-height: 1.7;
    color: #1d2327;
    outline: none;
    background: #fff;
}

.wrova-content-editor:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

/* --- Accordion --- */
.wrova-accordion-toggle {
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    padding: 4px 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.wrova-accordion-toggle:hover {
    color: #2271b1;
}

.wrova-accordion-arrow {
    margin-left: auto;
    transition: transform 0.2s;
    font-size: 16px;
}

.wrova-accordion-toggle[aria-expanded="true"] .wrova-accordion-arrow {
    transform: rotate(180deg);
}

.wrova-accordion-panel {
    padding-top: 16px;
}

.wrova-seo-grid {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.wrova-char-count {
    font-size: 11px;
    color: #8c8f94;
    display: block;
    text-align: right;
    margin-top: 3px;
}

.wrova-char-count.over {
    color: #d63638;
    font-weight: 600;
}

/* --- FAQ list --- */
.wrova-faq-item {
    border: 1px solid #dcdcde;
    border-radius: 6px;
    padding: 14px;
    margin-bottom: 10px;
}

.wrova-faq-item label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8c8f94;
    display: block;
    margin-bottom: 4px;
}

.wrova-faq-item input,
.wrova-faq-item textarea {
    width: 100%;
    box-sizing: border-box;
}

/* --- Alt text list --- */
.wrova-alt-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.wrova-alt-item img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #dcdcde;
    flex-shrink: 0;
}

.wrova-alt-item input {
    flex: 1;
}

/* --- Actions --- */
.wrova-preview-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 32px;
}

.wrova-publish-status {
    font-size: 13px;
    padding: 6px 12px;
    border-radius: 4px;
}

.wrova-publish-status.success {
    background: #edfaef;
    color: #00a32a;
    border: 1px solid #00a32a;
}

.wrova-publish-status.error {
    background: #fcf0f1;
    color: #d63638;
    border: 1px solid #d63638;
}

.wrova-inline-error {
    color: #d63638;
    font-size: 13px;
    margin-left: 12px;
}

/* --- Card spacing inside wrap --- */
#wrova-module-a .card {
    margin-top: 0;
}
</style>


<script>
(function($) {
    'use strict';

    /* =========================================
       State
       ========================================= */
    var state = {
        keywords: [],
        moods: [],
        imageIds: [],        // WP attachment IDs (from media library)
        uploadedImages: [],  // { file, objectUrl, attachmentId (after upload) }
        templateId: '',
        generatedResult: null
    };

    /* =========================================
       Step navigation
       ========================================= */
    function showStep(n) {
        $('.wrova-step').hide();
        $('#wrova-step-' + n).show();
    }

    /* =========================================
       Step 1 — Keywords
       ========================================= */
    var $kwInput = $('#wrova-keyword-input');
    var $kwWrap  = $('#wrova-keyword-chips');

    function addKeyword(val) {
        val = val.trim().replace(/,+$/, '').trim();
        if (!val || state.keywords.indexOf(val) !== -1) return;
        state.keywords.push(val);
        renderKeywords();
    }

    function removeKeyword(val) {
        state.keywords = state.keywords.filter(function(k){ return k !== val; });
        renderKeywords();
    }

    function renderKeywords() {
        $kwWrap.find('.wrova-keyword-chip').remove();
        $.each(state.keywords, function(i, kw) {
            var $chip = $('<span class="wrova-keyword-chip"></span>').text(kw);
            var $rm   = $('<button type="button" class="wrova-chip-remove" aria-label="移除">✕</button>');
            $rm.on('click', function(){ removeKeyword(kw); });
            $chip.append($rm);
            $kwWrap.prepend($chip);  // insert before input
        });
        // move input to end
        $kwWrap.append($kwInput);
    }

    $kwInput.on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addKeyword($kwInput.val());
            $kwInput.val('');
        } else if (e.key === 'Backspace' && $kwInput.val() === '' && state.keywords.length > 0) {
            removeKeyword(state.keywords[state.keywords.length - 1]);
        }
    });

    $kwInput.on('blur', function() {
        if ($kwInput.val().trim()) {
            addKeyword($kwInput.val());
            $kwInput.val('');
        }
    });

    $kwWrap.on('click', function(e) {
        if (e.target === $kwWrap[0]) $kwInput.focus();
    });

    /* =========================================
       Step 1 — Mood chips
       ========================================= */
    $('#wrova-mood-chips').on('click', '.wrova-mood-chip', function() {
        var $btn  = $(this);
        var mood  = $btn.data('mood');
        var idx   = state.moods.indexOf(mood);
        if (idx === -1) {
            state.moods.push(mood);
            $btn.addClass('selected');
        } else {
            state.moods.splice(idx, 1);
            $btn.removeClass('selected');
        }
    });

    /* =========================================
       Step 1 — Image selection (media library)
       ========================================= */
    var mediaFrame;

    $('#wrova-btn-media').on('click', function() {
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        mediaFrame = wp.media({
            title: '選取照片',
            button: { text: '確認選取' },
            multiple: true,
            library: { type: 'image' }
        });
        mediaFrame.on('select', function() {
            var selection = mediaFrame.state().get('selection');
            selection.each(function(attachment) {
                var id    = attachment.get('id');
                var thumb = attachment.get('sizes') && attachment.get('sizes').thumbnail
                              ? attachment.get('sizes').thumbnail.url
                              : attachment.get('url');
                if (state.imageIds.indexOf(id) === -1) {
                    state.imageIds.push(id);
                    addImageThumb(thumb, 'media-' + id, function() {
                        state.imageIds = state.imageIds.filter(function(x){ return x !== id; });
                    });
                }
            });
        });
        mediaFrame.open();
    });

    /* =========================================
       Step 1 — Image upload (direct)
       ========================================= */
    $('#wrova-btn-upload-trigger').on('click', function() {
        $('#wrova-file-input').trigger('click');
    });

    $('#wrova-file-input').on('change', function(e) {
        var files = e.target.files;
        $.each(files, function(i, file) {
            var objUrl = URL.createObjectURL(file);
            var entry  = { file: file, objectUrl: objUrl, attachmentId: null };
            state.uploadedImages.push(entry);
            (function(ent) {
                addImageThumb(objUrl, 'upload-' + objUrl.split('/').pop(), function() {
                    state.uploadedImages = state.uploadedImages.filter(function(x){ return x !== ent; });
                });
            })(entry);
        });
        // reset so same file can be re-selected
        $(this).val('');
    });

    function addImageThumb(src, key, onRemove) {
        var $wrap = $('<div class="wrova-image-thumb" data-key="' + key + '"></div>');
        var $img  = $('<img alt="">').attr('src', src);
        var $rm   = $('<button type="button" class="wrova-thumb-remove" aria-label="移除"><span class="dashicons dashicons-no-alt"></span></button>');
        $rm.on('click', function() {
            $wrap.remove();
            if (typeof onRemove === 'function') onRemove();
        });
        $wrap.append($img).append($rm);
        $('#wrova-image-preview').append($wrap);
    }

    /* =========================================
       Step 1 — Load prompt templates
       ========================================= */
    function loadTemplates() {
        $.ajax({
            url: wrovaData.restUrl + 'templates',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wrovaData.nonce);
            },
            success: function(data) {
                var $sel = $('#wrova-template-select').empty();
                $sel.append('<option value="">— 選擇範本 —</option>');
                $.each(data, function(i, tpl) {
                    $sel.append($('<option></option>').val(tpl.slug).text(tpl.name));
                });
            },
            error: function() {
                $('#wrova-template-select').html('<option value="">（載入失敗）</option>');
            }
        });
    }
    loadTemplates();

    /* =========================================
       Step 1 → Step 2 → Step 3
       ========================================= */
    $('#wrova-btn-generate').on('click', function() {
        // Validation
        if (state.keywords.length === 0) {
            showError('step1', '請至少輸入一個關鍵字');
            return;
        }
        clearError('step1');
        showStep(2);
        setLoadingState('analyze');
        uploadPendingImages().then(function() {
            setLoadingState('generate');
            return callGenerate();
        }).then(function(result) {
            setLoadingState('done');
            state.generatedResult = result;
            setTimeout(function() {
                populatePreview(result);
                showStep(3);
            }, 600);
        }).catch(function(err) {
            showStep(1);
            showError('step1', err || '生成失敗，請稍後再試');
        });
    });

    /* =========================================
       Upload queued images → get attachment IDs
       ========================================= */
    function uploadPendingImages() {
        var pending = state.uploadedImages.filter(function(e){ return e.attachmentId === null; });
        if (pending.length === 0) return $.Deferred().resolve().promise();

        var promises = pending.map(function(entry) {
            var fd = new FormData();
            fd.append('file', entry.file, entry.file.name);

            return $.ajax({
                url: wrovaData.wpRestUrl + 'wp/v2/media',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wrovaData.nonce);
                },
                success: function(resp) {
                    entry.attachmentId = resp.id;
                    state.imageIds.push(resp.id);
                }
            });
        });

        return $.when.apply($, promises);
    }

    /* =========================================
       Call generate REST endpoint
       ========================================= */
    function callGenerate() {
        return $.ajax({
            url: wrovaData.restUrl + 'generate',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                keywords: state.keywords,
                mood: state.moods.join('、'),
                image_ids: state.imageIds,
                prompt_template: $('#wrova-template-select').val()
            }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wrovaData.nonce);
            }
        });
    }

    /* =========================================
       Loading step indicator
       ========================================= */
    function setLoadingState(phase) {
        var texts = {
            analyze: '正在分析圖片...',
            generate: '正在生成文章...',
            done: '完成'
        };
        $('#wrova-loading-text').text(texts[phase] || '');

        $('#lstat-analyze').removeClass('active done');
        $('#lstat-generate').removeClass('active done');
        $('#lstat-done').removeClass('active done');

        if (phase === 'analyze') {
            $('#lstat-analyze').addClass('active');
        } else if (phase === 'generate') {
            $('#lstat-analyze').addClass('done');
            $('#lstat-generate').addClass('active');
        } else if (phase === 'done') {
            $('#lstat-analyze, #lstat-generate').addClass('done');
            $('#lstat-done').addClass('active done');
        }
    }

    /* =========================================
       Populate Step 3 preview
       ========================================= */
    function populatePreview(result) {
        // Title
        $('#wrova-preview-title').val(result.title || '');

        // Content
        $('#wrova-preview-content').html(result.content || '');

        // SEO
        $('#wrova-seo-title').val(result.seo_title || '').trigger('input');
        $('#wrova-seo-desc').val(result.seo_description || '').trigger('input');
        $('#wrova-seo-keyword').val(result.seo_keyword || '');

        // FAQ
        var $faqList = $('#wrova-faq-list').empty();
        var faqs = result.faq || [];
        if (faqs.length === 0) {
            $faqList.append('<p class="description">（無 FAQ）</p>');
        } else {
            $.each(faqs, function(i, item) {
                var $item = $('<div class="wrova-faq-item"></div>');
                $item.append('<label>Q' + (i+1) + '</label>');
                $item.append($('<input type="text" class="large-text wrova-faq-q">').val(item.question || ''));
                $item.append('<label style="margin-top:8px;">A' + (i+1) + '</label>');
                $item.append($('<textarea class="large-text wrova-faq-a" rows="3"></textarea>').val(item.answer || ''));
                $faqList.append($item);
            });
        }

        // Alt text
        var $altList = $('#wrova-alt-list').empty();
        var alts = result.image_alts || [];
        if (alts.length === 0) {
            $altList.append('<p class="description">（無圖片）</p>');
        } else {
            $.each(alts, function(i, item) {
                var $row = $('<div class="wrova-alt-item"></div>');
                $row.append($('<img alt="">').attr('src', item.thumbnail || ''));
                $row.append($('<input type="text" class="regular-text wrova-alt-input">').val(item.alt || ''));
                $altList.append($row);
            });
        }
    }

    /* =========================================
       SEO char counters
       ========================================= */
    $('#wrova-seo-title').on('input', function() {
        var len  = $(this).val().length;
        var $cnt = $('#wrova-seo-title-count').text(len + ' / 60');
        $cnt.toggleClass('over', len > 60);
    });

    $('#wrova-seo-desc').on('input', function() {
        var len  = $(this).val().length;
        var $cnt = $('#wrova-seo-desc-count').text(len + ' / 160');
        $cnt.toggleClass('over', len > 160);
    });

    /* =========================================
       SEO Accordion
       ========================================= */
    $('.wrova-accordion-toggle').on('click', function() {
        var $btn    = $(this);
        var $panel  = $('#' + $btn.attr('aria-controls'));
        var expanded = $btn.attr('aria-expanded') === 'true';
        $btn.attr('aria-expanded', !expanded);
        $panel.slideToggle(200);
    });

    /* =========================================
       Step 3 → Publish
       ========================================= */
    function collectConfirmedContent() {
        var faqs = [];
        $('#wrova-faq-list .wrova-faq-item').each(function() {
            faqs.push({
                question: $(this).find('.wrova-faq-q').val(),
                answer: $(this).find('.wrova-faq-a').val()
            });
        });
        var alts = [];
        $('#wrova-alt-list .wrova-alt-item').each(function(i) {
            alts.push({
                index: i,
                alt: $(this).find('.wrova-alt-input').val()
            });
        });
        return {
            title:           $('#wrova-preview-title').val(),
            content:         $('#wrova-preview-content').html(),
            seo_title:       $('#wrova-seo-title').val(),
            seo_description: $('#wrova-seo-desc').val(),
            seo_keyword:     $('#wrova-seo-keyword').val(),
            faq:             faqs,
            image_alts:      alts
        };
    }

    function doPublish(status) {
        var $statusEl = $('#wrova-publish-status');
        $statusEl.removeClass('success error').text('發布中...').show();

        var payload = {
            result:            state.generatedResult,
            confirmed_content: collectConfirmedContent(),
            status:            status
        };

        $.ajax({
            url: wrovaData.restUrl + 'publish',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wrovaData.nonce);
            },
            success: function(resp) {
                $statusEl.addClass('success').text(status === 'publish' ? '發布成功！' : '已存為草稿');
                var label = status === 'publish' ? '文章已發布！' : '草稿已儲存！';
                $('#wrova-done-title').text(label);
                if (resp && resp.link) {
                    $('#wrova-done-link').html('文章連結：<a href="' + resp.link + '" target="_blank" rel="noopener">' + resp.link + '</a>');
                } else {
                    $('#wrova-done-link').text('');
                }
                setTimeout(function(){ showStep(4); }, 800);
            },
            error: function(xhr) {
                var msg = '發布失敗';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                $statusEl.addClass('error').text(msg);
            }
        });
    }

    $('#wrova-btn-publish').on('click', function()   { doPublish('publish'); });
    $('#wrova-btn-draft').on('click', function()      { doPublish('draft'); });

    /* =========================================
       Step 3 → Regenerate → back to Step 1
       ========================================= */
    $('#wrova-btn-regenerate').on('click', function() {
        showStep(1);
    });

    /* =========================================
       Step 4 → New article
       ========================================= */
    $('#wrova-btn-new').on('click', function() {
        // Reset state
        state.keywords = [];
        state.moods    = [];
        state.imageIds = [];
        state.uploadedImages = [];
        state.generatedResult = null;

        // Reset UI
        renderKeywords();
        $kwInput.val('');
        $('.wrova-mood-chip').removeClass('selected');
        $('#wrova-image-preview').empty();
        $('#wrova-template-select').val('');
        $('#wrova-step1-error').hide();
        $('#wrova-publish-status').hide();
        $('#wrova-faq-list').empty();
        $('#wrova-alt-list').empty();

        showStep(1);
    });

    /* =========================================
       Helpers
       ========================================= */
    function showError(ctx, msg) {
        if (ctx === 'step1') {
            $('#wrova-step1-error').text(msg).show();
        }
    }

    function clearError(ctx) {
        if (ctx === 'step1') {
            $('#wrova-step1-error').hide().text('');
        }
    }

    // Init
    showStep(1);

})(jQuery);
</script>
