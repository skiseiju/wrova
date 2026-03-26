<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wrova-page">
    <h1>Wrova — 文章優化</h1>
    <p class="description">選取現有文章，由 AI 重寫後並排比對，逐段確認後更新。</p>

    <div id="wrova-module-b">

        <!-- ===== Step 1：選取文章 ===== -->
        <div class="wrova-step" id="wrova-step-1">
            <div class="wrova-card">
                <h2 class="wrova-step-title">步驟 1 / 3 — 選取文章</h2>

                <div class="wrova-search-wrap">
                    <input
                        type="text"
                        id="wrova-search-input"
                        class="widefat"
                        placeholder="輸入文章標題搜尋..."
                        autocomplete="off"
                    />
                    <div id="wrova-search-dropdown" class="wrova-dropdown" style="display:none;"></div>
                </div>

                <div id="wrova-post-info" class="wrova-post-info" style="display:none;">
                    <div class="wrova-post-meta">
                        <h3 id="wrova-post-title-display"></h3>
                        <div class="wrova-meta-row">
                            <span class="wrova-meta-item">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <span id="wrova-post-date"></span>
                            </span>
                            <span class="wrova-meta-item">
                                <span class="dashicons dashicons-editor-alignleft"></span>
                                <span id="wrova-post-words"></span> 字
                            </span>
                            <span class="wrova-meta-item">
                                <span class="dashicons dashicons-format-image"></span>
                                <span id="wrova-post-images"></span> 張圖片
                            </span>
                        </div>
                    </div>
                    <button type="button" id="wrova-start-btn" class="button button-primary button-hero wrova-start-btn">
                        <span class="dashicons dashicons-controls-play"></span> 開始優化
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== Step 2：Loading ===== -->
        <div class="wrova-step" id="wrova-step-2" style="display:none;">
            <div class="wrova-card wrova-loading-card">
                <div class="wrova-spinner-wrap">
                    <div class="wrova-spinner"></div>
                </div>
                <p id="wrova-loading-text" class="wrova-loading-text">正在讀取文章內容...</p>
                <div class="wrova-progress-bar-wrap">
                    <div class="wrova-progress-bar" id="wrova-progress-bar"></div>
                </div>
            </div>
        </div>

        <!-- ===== Step 3：並排預覽確認 ===== -->
        <div class="wrova-step" id="wrova-step-3" style="display:none;">

            <!-- 頂部：標題 + SEO accordion -->
            <div class="wrova-card wrova-header-card">
                <div class="wrova-title-row">
                    <label class="wrova-label">文章標題</label>
                    <input type="text" id="wrova-edit-title" class="widefat" />
                </div>

                <div class="wrova-accordion">
                    <button type="button" class="wrova-accordion-toggle" id="wrova-seo-toggle">
                        <span class="dashicons dashicons-search"></span> SEO 設定
                        <span class="wrova-accordion-arrow dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="wrova-accordion-body" id="wrova-seo-body" style="display:none;">
                        <div class="wrova-seo-grid">
                            <div class="wrova-form-group">
                                <label>SEO 標題</label>
                                <input type="text" id="wrova-seo-title" class="widefat" />
                            </div>
                            <div class="wrova-form-group">
                                <label>焦點關鍵字</label>
                                <input type="text" id="wrova-seo-keyword" class="widefat" />
                            </div>
                            <div class="wrova-form-group wrova-form-group--full">
                                <label>Meta 描述</label>
                                <textarea id="wrova-seo-desc" class="widefat" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 並排兩欄標頭 -->
            <div class="wrova-compare-header">
                <div class="wrova-col-label wrova-col-label--original">
                    <span class="dashicons dashicons-media-text"></span> 原文
                </div>
                <div class="wrova-col-label wrova-col-label--improved">
                    <span class="dashicons dashicons-star-filled"></span> AI 新版
                </div>
            </div>

            <!-- 段落並排容器 -->
            <div id="wrova-paragraphs-container"></div>

            <!-- 底部 sticky 操作欄 -->
            <div class="wrova-sticky-bar">
                <div class="wrova-quick-actions">
                    <button type="button" class="button" id="wrova-select-all-original">
                        <span class="dashicons dashicons-backup"></span> 全選原文
                    </button>
                    <button type="button" class="button" id="wrova-select-all-improved">
                        <span class="dashicons dashicons-star-filled"></span> 全選新版
                    </button>
                </div>
                <div class="wrova-confirm-actions">
                    <button type="button" class="button" id="wrova-cancel-btn">
                        取消
                    </button>
                    <button type="button" class="button button-primary" id="wrova-confirm-btn">
                        <span class="dashicons dashicons-yes-alt"></span> 確認更新文章
                    </button>
                </div>
            </div>

        </div>

        <!-- 全域訊息 -->
        <div id="wrova-notice" class="wrova-notice" style="display:none;"></div>

    </div><!-- /#wrova-module-b -->
</div><!-- /.wrap -->

<style>
/* ---- 基礎 ---- */
#wrova-module-b {
    max-width: 1200px;
    margin-top: 16px;
    position: relative;
}

.wrova-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}

.wrova-step-title {
    margin: 0 0 20px;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

/* ---- Step 1：搜尋 ---- */
.wrova-search-wrap {
    position: relative;
    max-width: 520px;
}

.wrova-search-wrap input {
    font-size: 15px;
    padding: 10px 14px;
    height: auto;
    border-radius: 4px;
}

.wrova-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    border-radius: 0 0 4px 4px;
    z-index: 9999;
    max-height: 320px;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}

.wrova-dropdown-item {
    display: flex;
    flex-direction: column;
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background .15s;
}
.wrova-dropdown-item:last-child { border-bottom: none; }
.wrova-dropdown-item:hover { background: #f0f6ff; }
.wrova-dropdown-item .item-title { font-weight: 500; color: #1d2327; }
.wrova-dropdown-item .item-date { font-size: 12px; color: #8c8f94; margin-top: 2px; }

.wrova-dropdown-empty {
    padding: 12px 14px;
    color: #8c8f94;
    font-size: 13px;
}

.wrova-post-info {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.wrova-post-meta h3 {
    margin: 0 0 8px;
    font-size: 16px;
    color: #1d2327;
}

.wrova-meta-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.wrova-meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: #50575e;
}

.wrova-meta-item .dashicons {
    font-size: 15px;
    width: 15px;
    height: 15px;
    color: #72aee6;
}

.wrova-start-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

/* ---- Step 2：Loading ---- */
.wrova-loading-card {
    text-align: center;
    padding: 60px 24px;
}

.wrova-spinner-wrap {
    margin-bottom: 24px;
}

.wrova-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #e0e0e0;
    border-top-color: #0073aa;
    border-radius: 50%;
    animation: wrova-spin 0.8s linear infinite;
    margin: 0 auto;
}

@keyframes wrova-spin {
    to { transform: rotate(360deg); }
}

.wrova-loading-text {
    font-size: 15px;
    color: #50575e;
    margin: 0 0 20px;
}

.wrova-progress-bar-wrap {
    width: 280px;
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    margin: 0 auto;
    overflow: hidden;
}

.wrova-progress-bar {
    height: 100%;
    background: #0073aa;
    border-radius: 2px;
    width: 0%;
    transition: width 0.4s ease;
}

/* ---- Step 3：標題 + SEO ---- */
.wrova-header-card {
    margin-bottom: 16px;
}

.wrova-title-row {
    margin-bottom: 16px;
}

.wrova-label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #1d2327;
}

.wrova-title-row input {
    font-size: 16px;
    font-weight: 600;
    padding: 10px 12px;
    height: auto;
    border-radius: 4px;
}

/* ---- Accordion ---- */
.wrova-accordion-toggle {
    width: 100%;
    text-align: left;
    background: none;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #50575e;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background .15s;
}
.wrova-accordion-toggle:hover { background: #f6f7f7; }
.wrova-accordion-toggle .dashicons { font-size: 16px; width: 16px; height: 16px; }
.wrova-accordion-arrow { margin-left: auto; transition: transform .2s; }
.wrova-accordion-toggle.open .wrova-accordion-arrow { transform: rotate(180deg); }

.wrova-accordion-body {
    padding: 16px 4px 4px;
}

.wrova-seo-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.wrova-form-group--full {
    grid-column: 1 / -1;
}

.wrova-form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #50575e;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

/* ---- 欄標題 ---- */
.wrova-compare-header {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 8px;
    padding: 0 4px;
}

.wrova-col-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 12px;
    border-radius: 4px;
}

.wrova-col-label--original {
    background: #f0f0f0;
    color: #50575e;
}

.wrova-col-label--improved {
    background: #e8f0fe;
    color: #0073aa;
}

/* ---- 段落列 ---- */
.wrova-para-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
}

.wrova-para-cell {
    border: 2px solid transparent;
    border-radius: 6px;
    overflow: hidden;
    transition: border-color .2s;
}

.wrova-para-cell--original {
    background: #f9f9f9;
}

.wrova-para-cell--improved {
    background: #f0f6ff;
}

.wrova-para-content {
    padding: 14px 16px;
    font-size: 14px;
    line-height: 1.7;
    color: #1d2327;
    min-height: 60px;
}

.wrova-para-cell--improved .wrova-para-content[contenteditable="true"] {
    outline: none;
    cursor: text;
}

.wrova-para-cell--improved .wrova-para-content[contenteditable="true"]:focus {
    background: #e8f2ff;
}

.wrova-para-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    padding: 8px 12px;
    border-top: 1px solid rgba(0,0,0,.06);
    background: inherit;
}

.wrova-choice-btn {
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 20px;
    cursor: pointer;
    border: 1px solid #ccc;
    background: #fff;
    color: #50575e;
    transition: all .15s;
    font-weight: 500;
}

.wrova-choice-btn:hover {
    border-color: #999;
}

/* 選中狀態 */
.wrova-para-row.use-original .wrova-para-cell--original {
    border-color: #999;
}
.wrova-para-row.use-original .wrova-btn-original {
    background: #555;
    color: #fff;
    border-color: #555;
}

.wrova-para-row.use-improved .wrova-para-cell--improved {
    border-color: #0073aa;
}
.wrova-para-row.use-improved .wrova-btn-improved {
    background: #0073aa;
    color: #fff;
    border-color: #0073aa;
}

/* ---- Sticky 操作欄 ---- */
.wrova-sticky-bar {
    position: sticky;
    bottom: 0;
    background: #fff;
    padding: 14px 20px;
    box-shadow: 0 -2px 8px rgba(0,0,0,.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-top: 1px solid #e0e0e0;
    z-index: 100;
    border-radius: 0 0 6px 6px;
    flex-wrap: wrap;
}

.wrova-quick-actions,
.wrova-confirm-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.wrova-quick-actions .button {
    display: flex;
    align-items: center;
    gap: 4px;
}

.wrova-quick-actions .button .dashicons,
.wrova-confirm-actions .button .dashicons {
    font-size: 15px;
    width: 15px;
    height: 15px;
    margin-top: 1px;
}

#wrova-confirm-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    padding: 8px 18px;
    height: auto;
}

/* ---- 全域訊息 ---- */
.wrova-notice {
    padding: 12px 16px;
    border-left: 4px solid #00a32a;
    background: #f0faf4;
    border-radius: 4px;
    font-size: 14px;
    color: #1d2327;
    margin-top: 16px;
}

.wrova-notice.error {
    border-color: #d63638;
    background: #fef8f8;
}

/* ---- 響應式 ---- */
@media (max-width: 900px) {
    .wrova-para-row,
    .wrova-compare-header,
    .wrova-seo-grid {
        grid-template-columns: 1fr;
    }
    .wrova-col-label--original { display: none; }
    .wrova-compare-header { gap: 0; }
}
</style>

<script>
(function($) {
    'use strict';

    /* ---- 狀態 ---- */
    var selectedPostId   = null;
    var selectedPostData = null;   // { id, title, date, words, images }
    var improveResult    = null;   // API 回傳的 { original_paragraphs, improved_paragraphs, ... }

    /* ---- 工具函式 ---- */
    function showStep(n) {
        $('.wrova-step').hide();
        $('#wrova-step-' + n).show();
    }

    function showNotice(msg, isError) {
        var $n = $('#wrova-notice');
        $n.removeClass('error').text(msg);
        if (isError) $n.addClass('error');
        $n.show();
        setTimeout(function() { $n.fadeOut(); }, 5000);
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function countWords(htmlStr) {
        var text = $('<div>').html(htmlStr).text();
        // 中文字元每個算一個字，英文以空白分詞
        var cjk = (text.match(/[\u4e00-\u9fff\u3400-\u4dbf]/g) || []).length;
        var latin = (text.replace(/[\u4e00-\u9fff\u3400-\u4dbf]/g, ' ').match(/\S+/g) || []).length;
        return cjk + latin;
    }

    function countImages(htmlStr) {
        return ($('<div>').html(htmlStr).find('img').length);
    }

    /* ---- Step 1：搜尋 ---- */
    var searchTimer = null;

    $('#wrova-search-input').on('input', function() {
        var query = $(this).val().trim();
        clearTimeout(searchTimer);
        var $dropdown = $('#wrova-search-dropdown');

        if (query.length < 2) {
            $dropdown.hide().empty();
            return;
        }

        searchTimer = setTimeout(function() {
            fetch('/wp-json/wp/v2/posts?search=' + encodeURIComponent(query) + '&per_page=10', {
                headers: { 'X-WP-Nonce': wrovaData.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(posts) {
                $dropdown.empty();
                if (!posts.length) {
                    $dropdown.append('<div class="wrova-dropdown-empty">找不到符合的文章</div>');
                } else {
                    posts.forEach(function(post) {
                        var date = new Date(post.date).toLocaleDateString('zh-TW');
                        var $item = $('<div class="wrova-dropdown-item">')
                            .data('post', post)
                            .append('<span class="item-title">' + escHtml(post.title.rendered) + '</span>')
                            .append('<span class="item-date">' + date + '</span>');
                        $dropdown.append($item);
                    });
                }
                $dropdown.show();
            })
            .catch(function() {
                $dropdown.html('<div class="wrova-dropdown-empty">搜尋失敗，請稍後再試</div>').show();
            });
        }, 280);
    });

    $(document).on('click', '.wrova-dropdown-item', function() {
        var post = $(this).data('post');
        selectPost(post);
        $('#wrova-search-dropdown').hide();
        $('#wrova-search-input').val(post.title.rendered);
    });

    // 點擊外部關閉下拉
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.wrova-search-wrap').length) {
            $('#wrova-search-dropdown').hide();
        }
    });

    function selectPost(post) {
        selectedPostId = post.id;
        var words  = countWords(post.content ? post.content.rendered : '');
        var images = countImages(post.content ? post.content.rendered : '');
        var date   = new Date(post.date).toLocaleDateString('zh-TW');

        selectedPostData = { id: post.id, title: post.title.rendered, date: date, words: words, images: images };

        $('#wrova-post-title-display').text(post.title.rendered);
        $('#wrova-post-date').text(date);
        $('#wrova-post-words').text(words);
        $('#wrova-post-images').text(images);
        $('#wrova-post-info').show();
    }

    /* ---- Step 1 → Step 2 ---- */
    $('#wrova-start-btn').on('click', function() {
        if (!selectedPostId) return;
        showStep(2);
        runImprove();
    });

    /* ---- Step 2：進度動畫 ---- */
    var loadingMessages = [
        { text: '正在讀取文章內容...', progress: 15 },
        { text: '正在分析圖片...', progress: 40 },
        { text: 'AI 重寫中...', progress: 70 },
        { text: '整理結果中...', progress: 90 },
        { text: '完成', progress: 100 }
    ];
    var loadingStep = 0;
    var loadingTimer = null;

    function startLoadingAnimation() {
        loadingStep = 0;
        updateLoadingUI(loadingMessages[0]);

        loadingTimer = setInterval(function() {
            loadingStep++;
            if (loadingStep < loadingMessages.length - 1) {
                updateLoadingUI(loadingMessages[loadingStep]);
            }
        }, 1800);
    }

    function stopLoadingAnimation() {
        clearInterval(loadingTimer);
        updateLoadingUI(loadingMessages[loadingMessages.length - 1]);
    }

    function updateLoadingUI(msg) {
        $('#wrova-loading-text').text(msg.text);
        $('#wrova-progress-bar').css('width', msg.progress + '%');
    }

    /* ---- API：improve ---- */
    function runImprove() {
        startLoadingAnimation();

        fetch(wrovaData.restUrl + 'improve', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': wrovaData.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ post_id: selectedPostId })
        })
        .then(function(r) {
            if (!r.ok) return r.json().then(function(d) { throw new Error(d.message || 'API 錯誤'); });
            return r.json();
        })
        .then(function(data) {
            stopLoadingAnimation();
            improveResult = data;
            setTimeout(function() { buildStep3(data); }, 400);
        })
        .catch(function(err) {
            stopLoadingAnimation();
            showNotice('優化失敗：' + err.message, true);
            showStep(1);
        });
    }

    /* ---- Step 3：建構 UI ---- */
    function buildStep3(data) {
        // 標題
        $('#wrova-edit-title').val(
            data.title || (selectedPostData ? selectedPostData.title : '')
        );

        // SEO
        if (data.seo_meta) {
            $('#wrova-seo-title').val(data.seo_meta.title || '');
            $('#wrova-seo-desc').val(data.seo_meta.description || '');
            $('#wrova-seo-keyword').val(data.seo_meta.focus_keyword || '');
        }

        // 段落
        var $container = $('#wrova-paragraphs-container').empty();
        var origParas = data.original_paragraphs || [];
        var impParas  = data.improved_paragraphs  || [];
        var maxLen    = Math.max(origParas.length, impParas.length);

        for (var i = 0; i < maxLen; i++) {
            var origText = origParas[i] || '';
            var impText  = impParas[i]  || '';

            var $row = $('<div class="wrova-para-row use-original" data-index="' + i + '">');

            /* 左欄：原文（唯讀） */
            var $origCell = $('<div class="wrova-para-cell wrova-para-cell--original">');
            $origCell.append('<div class="wrova-para-content">' + escHtml(origText) + '</div>');
            var $origActions = $('<div class="wrova-para-actions">');
            $origActions.append(
                '<button type="button" class="wrova-choice-btn wrova-btn-original" data-choice="original">用原文</button>'
            );
            $origCell.append($origActions);

            /* 右欄：AI 新版（可編輯） */
            var $impCell = $('<div class="wrova-para-cell wrova-para-cell--improved">');
            $impCell.append('<div class="wrova-para-content" contenteditable="true">' + escHtml(impText) + '</div>');
            var $impActions = $('<div class="wrova-para-actions">');
            $impActions.append(
                '<button type="button" class="wrova-choice-btn wrova-btn-improved" data-choice="improved">用新版</button>'
            );
            $impCell.append($impActions);

            $row.append($origCell).append($impCell);
            $container.append($row);
        }

        showStep(3);
    }

    /* ---- Step 3：選擇段落 ---- */
    $(document).on('click', '.wrova-choice-btn', function() {
        var choice = $(this).data('choice');
        var $row   = $(this).closest('.wrova-para-row');
        $row.removeClass('use-original use-improved').addClass('use-' + choice);
    });

    /* ---- 全選按鈕 ---- */
    $('#wrova-select-all-original').on('click', function() {
        $('.wrova-para-row').removeClass('use-original use-improved').addClass('use-original');
    });

    $('#wrova-select-all-improved').on('click', function() {
        $('.wrova-para-row').removeClass('use-original use-improved').addClass('use-improved');
    });

    /* ---- SEO Accordion ---- */
    $('#wrova-seo-toggle').on('click', function() {
        $(this).toggleClass('open');
        $('#wrova-seo-body').slideToggle(200);
    });

    /* ---- 取消 ---- */
    $('#wrova-cancel-btn').on('click', function() {
        if (!confirm('確定要取消嗎？所有優化結果將不會儲存。')) return;
        improveResult = null;
        selectedPostId = null;
        selectedPostData = null;
        $('#wrova-search-input').val('');
        $('#wrova-post-info').hide();
        showStep(1);
    });

    /* ---- 確認更新 ---- */
    $('#wrova-confirm-btn').on('click', function() {
        if (!selectedPostId) return;

        /* 收集使用者選擇的段落 */
        var confirmedParagraphs = [];
        $('.wrova-para-row').each(function() {
            var $row = $(this);
            if ($row.hasClass('use-improved')) {
                // 取可編輯欄的最新文字
                confirmedParagraphs.push($row.find('.wrova-para-cell--improved .wrova-para-content').text());
            } else {
                confirmedParagraphs.push($row.find('.wrova-para-cell--original .wrova-para-content').text());
            }
        });

        var seoMeta = {
            title:         $('#wrova-seo-title').val(),
            description:   $('#wrova-seo-desc').val(),
            focus_keyword: $('#wrova-seo-keyword').val()
        };

        var imageAlts = {};
        if (improveResult && improveResult.image_alts) {
            imageAlts = improveResult.image_alts;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('更新中...');

        fetch(wrovaData.restUrl + 'publish', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': wrovaData.nonce,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                post_id:              selectedPostId,
                module:               'improve',
                title:                $('#wrova-edit-title').val(),
                confirmed_paragraphs: confirmedParagraphs,
                seo_meta:             seoMeta,
                image_alts:           imageAlts
            })
        })
        .then(function(r) {
            if (!r.ok) return r.json().then(function(d) { throw new Error(d.message || '更新失敗'); });
            return r.json();
        })
        .then(function() {
            showNotice('文章已成功更新！');
            // 重置回 Step 1
            improveResult    = null;
            selectedPostId   = null;
            selectedPostData = null;
            $('#wrova-search-input').val('');
            $('#wrova-post-info').hide();
            showStep(1);
        })
        .catch(function(err) {
            showNotice('更新失敗：' + err.message, true);
        })
        .finally(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> 確認更新文章');
        });
    });

})(jQuery);
</script>
