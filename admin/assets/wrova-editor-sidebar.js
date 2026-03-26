/**
 * Wrova Editor Sidebar
 * Block Editor 右側 AI 產文 / 潤稿面板
 */
(function () {
    'use strict';

    const { registerPlugin }                               = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem }     = wp.editPost;
    const { createElement: el, useState, useEffect, Fragment } = wp.element;
    const {
        PanelBody, PanelRow,
        Button, TextareaControl, TextControl,
        CheckboxControl, Spinner, Notice,
        Flex, FlexItem, FlexBlock,
    } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const apiFetch = wp.apiFetch;

    /* ---- 情緒快選 ---- */
    const MOOD_PRESETS = [
        '浪漫', '清新優雅', '自然溫暖', '活潑歡樂', '沉靜細膩', '大器奢華',
    ];

    /* ---- 遞迴取所有 image blocks ---- */
    function extractImageBlocks(blocks) {
        const images = [];
        (blocks || []).forEach((block) => {
            if (block.name === 'core/image' && block.attributes.url) {
                images.push({
                    id:  block.attributes.id  || 0,
                    url: block.attributes.url,
                    alt: block.attributes.alt || '',
                });
            }
            if (block.name === 'core/gallery') {
                (block.attributes.images || []).forEach((img) => {
                    if (img.url) images.push({ id: img.id || 0, url: img.url, alt: img.alt || '' });
                });
                if (block.innerBlocks) images.push(...extractImageBlocks(block.innerBlocks));
            }
            if (block.innerBlocks && block.innerBlocks.length) {
                images.push(...extractImageBlocks(block.innerBlocks));
            }
        });
        // 去重（by url）
        return images.filter((img, i, arr) => arr.findIndex((x) => x.url === img.url) === i);
    }

    /* ---- strip HTML ---- */
    function stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
    }

    /* ============================================================
       Tab：產文
    ============================================================ */
    function TabGenerate({ postId }) {
        const [keywords,        setKeywords]        = useState('');
        const [customMood,      setCustomMood]       = useState('');
        const [selectedMoods,   setSelectedMoods]   = useState([]);
        const [existingContent, setExistingContent] = useState('');
        const [selectedImgIds,  setSelectedImgIds]  = useState([]);
        const [status,          setStatus]          = useState('idle'); // idle|loading|done|error
        const [message,         setMessage]         = useState('');

        const { editPost } = useDispatch('core/editor');

        /* 取得編輯器目前內容 */
        const currentContent = useSelect((s) => s('core/editor').getEditedPostContent());

        /* 取所有圖片 */
        const allImages = useSelect((s) => {
            const blocks = s('core/block-editor').getBlocks();
            const imgs   = extractImageBlocks(blocks);
            // 加上精選圖片
            const fid = s('core/editor').getEditedPostAttribute('featured_media');
            if (fid) {
                const media = s('core').getMedia(fid);
                if (media && media.source_url && !imgs.find((x) => x.id === fid)) {
                    imgs.unshift({ id: fid, url: media.source_url, alt: media.alt_text || '' });
                }
            }
            return imgs;
        });

        /* 圖片清單變動時不預設勾選，由使用者手動選擇 */

        function toggleMood(val) {
            setSelectedMoods((prev) =>
                prev.includes(val) ? prev.filter((m) => m !== val) : [...prev, val]
            );
        }

        function toggleImg(key) {
            setSelectedImgIds((prev) =>
                prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]
            );
        }

        function handleImportContent() {
            const text = stripHtml(currentContent);
            if (!text) return;
            setExistingContent(text.substring(0, 800));
        }

        async function handleGenerate() {
            if (!keywords.trim()) {
                setStatus('error');
                setMessage('請輸入關鍵字');
                return;
            }
            setStatus('loading');
            setMessage('');

            const moodAll = [
                ...selectedMoods,
                ...(customMood.trim() ? customMood.split(/[,，、\s]+/).filter(Boolean) : []),
            ];

            // 取出被勾選圖片的 numeric ID
            const chosenIds = allImages
                .filter((img) => selectedImgIds.includes(img.id || img.url) && img.id)
                .map((img) => img.id);

            try {
                const data = await apiFetch({
                    path: 'wrova/v1/generate',
                    method: 'POST',
                    data: {
                        keywords:         keywords.split(/[,，、\s]+/).filter(Boolean),
                        mood:             moodAll.join('、'),
                        image_ids:        chosenIds,
                        existing_content: existingContent,
                    },
                });

                if (data && data.content) {
                    editPost({ content: data.content });
                    if (data.title)           editPost({ title: data.title });
                    if (data.seo) {
                        if (data.seo.meta_title)       editPost({ meta: { rank_math_title:       data.seo.meta_title } });
                        if (data.seo.meta_description) editPost({ meta: { rank_math_description: data.seo.meta_description } });
                    }
                    setStatus('done');
                    setMessage('文章已生成，請確認內容後儲存。');
                } else {
                    throw new Error('AI 回傳格式異常');
                }
            } catch (err) {
                setStatus('error');
                setMessage(err.message || '生成失敗，請確認設定頁的 API Key。');
            }
        }

        return el(Fragment, null,

            /* 關鍵字 */
            el(PanelBody, { title: '關鍵字', initialOpen: true },
                el(TextareaControl, {
                    label: '輸入關鍵字（逗號 / 空格分隔）',
                    value: keywords,
                    onChange: setKeywords,
                    rows: 2,
                    placeholder: '婚攝, 台北婚禮, 戶外證婚',
                }),
            ),

            /* 代入現有文案 */
            el(PanelBody, { title: '參考現有文案', initialOpen: false },
                el(PanelRow, null,
                    el(Button, {
                        variant: 'secondary',
                        onClick: handleImportContent,
                        style: { marginBottom: '8px' },
                        disabled: !currentContent,
                    }, '⬇ 代入現有文案'),
                ),
                existingContent
                    ? el(Fragment, null,
                        el('p', { style: { fontSize: '11px', color: '#888', margin: '0 0 4px' } },
                            `已讀入 ${existingContent.length} 字（截至 800 字）`
                        ),
                        el(TextareaControl, {
                            value: existingContent,
                            onChange: setExistingContent,
                            rows: 4,
                            help: 'AI 將以此為基礎重新撰寫，融合關鍵字與情緒風格。',
                        }),
                    )
                    : el('p', { style: { fontSize: '12px', color: '#888', margin: 0 } },
                        '點按上方按鈕，讀入目前編輯器內容作為 AI 參考。'
                    ),
            ),

            /* 情緒 */
            el(PanelBody, { title: '氛圍情緒', initialOpen: true },
                el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '6px', marginBottom: '10px' } },
                    MOOD_PRESETS.map((m) =>
                        el('button', {
                            key: m,
                            type: 'button',
                            onClick: () => toggleMood(m),
                            style: {
                                padding: '4px 10px',
                                borderRadius: '12px',
                                border: '1px solid',
                                borderColor: selectedMoods.includes(m) ? '#1d2327' : '#ccc',
                                background:  selectedMoods.includes(m) ? '#1d2327' : '#fff',
                                color:       selectedMoods.includes(m) ? '#fff'    : '#1d2327',
                                cursor: 'pointer',
                                fontSize: '12px',
                            },
                        }, m)
                    ),
                ),
                el(TextControl, {
                    label: '自訂情緒（逗號分隔）',
                    value: customMood,
                    onChange: setCustomMood,
                    placeholder: '夢幻、朦朧感、故事感…',
                }),
            ),

            /* 圖片選擇 */
            el(PanelBody, { title: `圖片（${allImages.length} 張）`, initialOpen: true },
                allImages.length === 0
                    ? el('p', { style: { fontSize: '12px', color: '#888', margin: 0 } },
                        '文章中尚未插入任何圖片。在編輯器加入圖片後即可在此選擇。'
                    )
                    : el(Fragment, null,
                        el('p', { style: { fontSize: '11px', color: '#888', margin: '0 0 8px' } },
                            '勾選要讓 AI 分析的圖片：'
                        ),
                        el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px' } },
                            allImages.map((img) => {
                                const key = img.id || img.url;
                                return el('label', {
                                    key,
                                    style: {
                                        position: 'relative',
                                        cursor: 'pointer',
                                        border: selectedImgIds.includes(key) ? '2px solid #1d2327' : '2px solid transparent',
                                        borderRadius: '4px',
                                        overflow: 'hidden',
                                        display: 'block',
                                    },
                                },
                                    el('input', {
                                        type: 'checkbox',
                                        checked: selectedImgIds.includes(key),
                                        onChange: () => toggleImg(key),
                                        style: { position: 'absolute', top: '4px', left: '4px', zIndex: 1 },
                                    }),
                                    el('img', {
                                        src: img.url,
                                        alt: img.alt,
                                        style: {
                                            width: '100%',
                                            height: '70px',
                                            objectFit: 'cover',
                                            display: 'block',
                                            opacity: selectedImgIds.includes(key) ? 1 : 0.45,
                                        },
                                    }),
                                );
                            }),
                        ),
                    ),
            ),

            /* 產文按鈕 */
            el(PanelRow, null,
                el(Button, {
                    variant: 'primary',
                    onClick: handleGenerate,
                    isBusy: status === 'loading',
                    disabled: status === 'loading',
                    style: { width: '100%', justifyContent: 'center' },
                },
                    status === 'loading'
                        ? el(Fragment, null, el(Spinner), ' 生成中…')
                        : 'AI 產文'
                ),
            ),

            status !== 'idle' && el(PanelRow, null,
                el(Notice, {
                    status: status === 'done' ? 'success' : 'error',
                    isDismissible: true,
                    onRemove: () => { setStatus('idle'); setMessage(''); },
                }, message),
            ),
        );
    }

    /* ============================================================
       Tab：潤稿
    ============================================================ */
    function TabImprove({ postId }) {
        const [status,   setStatus]   = useState('idle');
        const [message,  setMessage]  = useState('');
        const [improved, setImproved] = useState(null);

        const { editPost }         = useDispatch('core/editor');
        const { insertBlocks }     = useDispatch('core/block-editor');
        const getEditedPostContent = useSelect((s) => s('core/editor').getEditedPostContent);

        /* 偵測是否為 Block Editor */
        const isBlockEditor = !!(wp.blocks && wp.blocks.rawHandler && insertBlocks);

        async function handleImprove() {
            const content = getEditedPostContent();
            if (!content || !content.trim()) {
                setStatus('error');
                setMessage('目前文章內容為空，請先撰寫內容。');
                return;
            }
            setStatus('loading');
            setMessage('');
            setImproved(null);
            try {
                const data = await apiFetch({
                    path: 'wrova/v1/improve',
                    method: 'POST',
                    data: { post_id: postId || 0, content },
                });
                if (data && data.improved_content) {
                    setImproved(data.improved_content);
                    setStatus('preview');
                } else {
                    throw new Error('AI 未回傳有效內容，請再試一次');
                }
            } catch (err) {
                setStatus('error');
                setMessage(
                    (err && err.message)
                        ? err.message
                        : '潤稿失敗，請確認設定頁的 API Key。'
                );
            }
        }

        function applyImproved() {
            if (isBlockEditor) {
                /* 剝掉 AI 常回傳的外層 <div> 包裹，讓 rawHandler 能正確辨識各標籤 */
                const stripped = improved
                    .replace(/^\s*<div[^>]*>\s*/i, '')
                    .replace(/\s*<\/div>\s*$/i, '')
                    .trim();

                let newBlocks = [];
                try {
                    newBlocks = wp.blocks.rawHandler({ HTML: stripped });
                } catch (e) {
                    newBlocks = [];
                }

                /* 若 rawHandler 仍只回傳單一 html block，改為手動拆解標籤 */
                if (
                    newBlocks.length === 0 ||
                    (newBlocks.length === 1 && newBlocks[0].name === 'core/html')
                ) {
                    newBlocks = manualHtmlToBlocks(stripped);
                }

                insertBlocks(newBlocks, 0);
            } else {
                /* Classic Editor：頂部插入 + 分隔線 + 原文 */
                const existing = getEditedPostContent() || '';
                editPost({ content: improved + '\n\n<hr/>\n\n' + existing });
            }
            setStatus('done');
            setMessage('已插入文章頂部，請確認後儲存。');
            setImproved(null);
        }

        /* 手動把 HTML 拆成 paragraph / heading blocks */
        function manualHtmlToBlocks(html) {
            const div = document.createElement('div');
            div.innerHTML = html;
            const blocks = [];
            div.childNodes.forEach((node) => {
                if (node.nodeType !== 1) return; // 只處理元素節點
                const tag = node.tagName.toLowerCase();
                const inner = node.innerHTML;
                if (/^h[1-6]$/.test(tag)) {
                    blocks.push(wp.blocks.createBlock('core/heading', {
                        level:   parseInt(tag[1], 10),
                        content: inner,
                    }));
                } else if (tag === 'ul' || tag === 'ol') {
                    blocks.push(wp.blocks.createBlock('core/list', {
                        ordered: tag === 'ol',
                        values:  inner,
                    }));
                } else {
                    /* p, div, blockquote, 其他 → 段落 */
                    blocks.push(wp.blocks.createBlock('core/paragraph', {
                        content: inner || node.textContent,
                    }));
                }
            });
            return blocks.length ? blocks : [
                wp.blocks.createBlock('core/html', { content: html }),
            ];
        }

        return el(Fragment, null,
            el(PanelBody, { title: '說明', initialOpen: true },
                el('p', { style: { fontSize: '12px', color: '#757575', margin: 0 } },
                    'AI 分析目前文章，強化 SEO 結構、加入 AEO 問答區塊、調整標題與段落節奏。潤稿結果可預覽後套用或放棄。'
                ),
            ),

            status !== 'preview' && el(PanelRow, null,
                el(Button, {
                    variant: 'primary',
                    onClick: handleImprove,
                    isBusy: status === 'loading',
                    disabled: status === 'loading',
                    style: { width: '100%', justifyContent: 'center' },
                },
                    status === 'loading'
                        ? el(Fragment, null, el(Spinner), ' 分析中…')
                        : 'AI 潤稿目前文章'
                ),
            ),

            status === 'preview' && el(PanelBody, { title: '潤稿完成', initialOpen: true },
                el('p', { style: { fontSize: '12px', color: '#888', margin: '0 0 10px' } },
                    'AI 潤稿內容將插入文章頂部，原本內容保留在下方，可自行對照後刪減。'
                ),
                el(Flex, { gap: 2 },
                    el(FlexItem, null,
                        el(Button, { variant: 'primary', onClick: applyImproved }, '套用'),
                    ),
                    el(FlexItem, null,
                        el(Button, {
                            variant: 'secondary',
                            onClick: () => { setStatus('idle'); setImproved(null); },
                        }, '放棄'),
                    ),
                ),
            ),

            (status === 'done' || status === 'error') && el(PanelRow, null,
                el(Notice, {
                    status: status === 'done' ? 'success' : 'error',
                    isDismissible: true,
                    onRemove: () => { setStatus('idle'); setMessage(''); },
                }, message),
            ),
        );
    }

    /* ============================================================
       主 Sidebar
    ============================================================ */
    function WrovaSidebar() {
        const [activeTab, setActiveTab] = useState('generate');
        const postId = useSelect((s) => s('core/editor').getCurrentPostId());

        const tabBtn = (tab, label) => el('button', {
            type: 'button',
            onClick: () => setActiveTab(tab),
            style: {
                flex: 1,
                padding: '8px 4px',
                border: 'none',
                borderBottom: activeTab === tab ? '2px solid #1d2327' : '2px solid transparent',
                background: 'transparent',
                color: activeTab === tab ? '#1d2327' : '#757575',
                cursor: 'pointer',
                fontWeight: activeTab === tab ? '600' : '400',
                fontSize: '13px',
                letterSpacing: '0.02em',
            },
        }, label);

        return el(Fragment, null,
            el(PluginSidebarMoreMenuItem, { target: 'wrova-sidebar' }, 'Wrova AI'),

            el(PluginSidebar, {
                name:  'wrova-sidebar',
                title: 'Wrova AI',
                icon:  'edit-large',
            },
                el('div', {
                    style: {
                        display: 'flex',
                        borderBottom: '1px solid #e0e0e0',
                        padding: '0 8px',
                        background: '#f9f9f9',
                    },
                },
                    tabBtn('generate', '✦ 產文'),
                    tabBtn('improve',  '↻ 潤稿'),
                ),

                activeTab === 'generate'
                    ? el(TabGenerate, { postId })
                    : el(TabImprove,  { postId }),
            ),
        );
    }

    registerPlugin('wrova-sidebar', {
        render: WrovaSidebar,
        icon: 'edit-large',
    });

}());
