/**
 * Wrova Editor Sidebar
 * 在 Block Editor 右側面板提供 AI 產文 / 優化功能
 */
(function () {
    'use strict';

    const { registerPlugin }                        = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { createElement: el, useState, Fragment }   = wp.element;
    const {
        PanelBody, PanelRow,
        Button, TextareaControl, TextControl,
        SelectControl, CheckboxControl,
        Spinner, Notice, Flex, FlexItem,
        __experimentalInputControl: InputControl,
    } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const apiFetch = wp.apiFetch;

    /* ---- 心情選項 ---- */
    const MOOD_OPTIONS = [
        { label: '浪漫', value: 'romantic' },
        { label: '清新優雅', value: 'elegant' },
        { label: '自然溫暖', value: 'natural' },
        { label: '活潑歡樂', value: 'joyful' },
        { label: '沉靜細膩', value: 'serene' },
        { label: '大器奢華', value: 'luxurious' },
    ];

    /* ============================================================
       子元件：產文（Module A）
    ============================================================ */
    function TabGenerate({ postId }) {
        const [keywords, setKeywords]   = useState('');
        const [moods, setMoods]         = useState([]);
        const [status, setStatus]       = useState('idle'); // idle | loading | done | error
        const [message, setMessage]     = useState('');

        const { editPost } = useDispatch('core/editor');
        const { getEditedPostContent, getEditedPostAttribute } = useSelect(
            (select) => select('core/editor')
        );

        /* 讀取目前附件 IDs（已附加到這篇文章的圖片） */
        const attachedImages = useSelect((select) => {
            if (!postId) return [];
            // 嘗試從 featured image + 文章內容 img 解析（簡化：只取 featured image）
            const featuredId = select('core/editor').getEditedPostAttribute('featured_media');
            return featuredId ? [featuredId] : [];
        });

        function toggleMood(value) {
            setMoods((prev) =>
                prev.includes(value) ? prev.filter((m) => m !== value) : [...prev, value]
            );
        }

        async function handleGenerate() {
            if (!keywords.trim()) {
                setStatus('error');
                setMessage('請輸入關鍵字');
                return;
            }
            setStatus('loading');
            setMessage('');
            try {
                const data = await apiFetch({
                    path: 'wrova/v1/generate',
                    method: 'POST',
                    data: {
                        keywords:   keywords.split(/[,，\s]+/).filter(Boolean),
                        mood:       moods,
                        image_ids:  attachedImages,
                        post_id:    postId || 0,
                    },
                });

                if (data && data.content) {
                    /* 把生成的 HTML 寫入編輯器 */
                    editPost({ content: data.content });

                    /* 若有 SEO meta，一併更新 */
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
                setMessage(err.message || '生成失敗，請確認 API Key 設定。');
            }
        }

        return el(Fragment, null,
            el(PanelBody, { title: '關鍵字', initialOpen: true },
                el(TextareaControl, {
                    label: '輸入關鍵字（逗號或空格分隔）',
                    value: keywords,
                    onChange: setKeywords,
                    rows: 3,
                    placeholder: '婚攝, 台北婚禮, 戶外證婚',
                }),
            ),

            el(PanelBody, { title: '氛圍情緒', initialOpen: true },
                el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
                    MOOD_OPTIONS.map((opt) =>
                        el(CheckboxControl, {
                            key: opt.value,
                            label: opt.label,
                            checked: moods.includes(opt.value),
                            onChange: () => toggleMood(opt.value),
                        })
                    )
                ),
            ),

            el(PanelBody, { title: '圖片來源', initialOpen: false },
                el('p', { style: { fontSize: '12px', color: '#757575', margin: '0 0 8px' } },
                    attachedImages.length > 0
                        ? `已偵測 ${attachedImages.length} 張圖片（含精選圖片），AI 將自動分析。`
                        : '尚未設定精選圖片。建議先上傳照片再產文，AI 會分析圖片情境。'
                ),
            ),

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
                    onRemove: () => setStatus('idle'),
                }, message),
            ),
        );
    }

    /* ============================================================
       子元件：優化（Module B）
    ============================================================ */
    function TabImprove({ postId }) {
        const [status, setStatus]   = useState('idle');
        const [message, setMessage] = useState('');
        const [preview, setPreview] = useState(null); // { original, improved }

        const { editPost }              = useDispatch('core/editor');
        const getEditedPostContent      = useSelect((s) => s('core/editor').getEditedPostContent);

        async function handleImprove() {
            const currentContent = getEditedPostContent();
            if (!currentContent || currentContent.trim() === '') {
                setStatus('error');
                setMessage('目前文章內容為空，請先撰寫內容再優化。');
                return;
            }
            setStatus('loading');
            setMessage('');
            setPreview(null);
            try {
                const data = await apiFetch({
                    path: 'wrova/v1/improve',
                    method: 'POST',
                    data: { post_id: postId, content: currentContent },
                });

                if (data && data.improved_content) {
                    setPreview({ original: currentContent, improved: data.improved_content });
                    setStatus('preview');
                    setMessage('');
                } else {
                    throw new Error('AI 回傳格式異常');
                }
            } catch (err) {
                setStatus('error');
                setMessage(err.message || '優化失敗，請確認 API Key 設定。');
            }
        }

        function applyImproved() {
            if (!preview) return;
            editPost({ content: preview.improved });
            setStatus('done');
            setMessage('已套用優化版本，請確認後儲存。');
            setPreview(null);
        }

        function discardImproved() {
            setPreview(null);
            setStatus('idle');
        }

        return el(Fragment, null,
            el(PanelBody, { title: '優化說明', initialOpen: true },
                el('p', { style: { fontSize: '12px', color: '#757575', margin: 0 } },
                    'AI 會分析目前文章，強化 SEO 結構、AEO 問答區塊、語氣與段落節奏。優化結果可選擇套用或放棄。'
                ),
            ),

            !preview && el(PanelRow, null,
                el(Button, {
                    variant: 'primary',
                    onClick: handleImprove,
                    isBusy: status === 'loading',
                    disabled: status === 'loading',
                    style: { width: '100%', justifyContent: 'center' },
                },
                    status === 'loading'
                        ? el(Fragment, null, el(Spinner), ' 分析中…')
                        : 'AI 優化目前文章'
                ),
            ),

            /* 預覽操作列 */
            preview && el(PanelBody, { title: '優化結果', initialOpen: true },
                el('p', { style: { fontSize: '12px', color: '#757575', marginTop: 0 } },
                    'AI 已完成分析。套用後可在編輯器中逐段確認差異。'
                ),
                el(Flex, { gap: 2 },
                    el(FlexItem, null,
                        el(Button, { variant: 'primary', onClick: applyImproved }, '套用優化版本'),
                    ),
                    el(FlexItem, null,
                        el(Button, { variant: 'secondary', onClick: discardImproved }, '放棄'),
                    ),
                ),
            ),

            status !== 'idle' && status !== 'preview' && status !== 'loading' && el(PanelRow, null,
                el(Notice, {
                    status: status === 'done' ? 'success' : 'error',
                    isDismissible: true,
                    onRemove: () => setStatus('idle'),
                }, message),
            ),

            status === 'error' && el(PanelRow, null,
                el(Notice, {
                    status: 'error',
                    isDismissible: true,
                    onRemove: () => setStatus('idle'),
                }, message),
            ),
        );
    }

    /* ============================================================
       主元件：Sidebar
    ============================================================ */
    function WrovaSidebar() {
        const [activeTab, setActiveTab] = useState('generate');

        const postId = useSelect((select) =>
            select('core/editor').getCurrentPostId()
        );

        const tabStyle = (tab) => ({
            padding: '8px 12px',
            border: 'none',
            background: activeTab === tab ? '#1d2327' : 'transparent',
            color: activeTab === tab ? '#fff' : '#1d2327',
            cursor: 'pointer',
            fontWeight: activeTab === tab ? '600' : '400',
            borderRadius: '3px 3px 0 0',
            fontSize: '13px',
        });

        return el(Fragment, null,
            el(PluginSidebarMoreMenuItem, { target: 'wrova-sidebar' }, 'Wrova AI'),

            el(PluginSidebar, {
                name: 'wrova-sidebar',
                title: 'Wrova AI',
                icon: 'edit-large',
            },
                /* Tab 列 */
                el('div', {
                    style: {
                        display: 'flex',
                        borderBottom: '2px solid #1d2327',
                        padding: '8px 8px 0',
                        background: '#f6f7f7',
                    },
                },
                    el('button', { style: tabStyle('generate'), onClick: () => setActiveTab('generate') }, '✦ 產文'),
                    el('button', { style: tabStyle('improve'),  onClick: () => setActiveTab('improve')  }, '↻ 優化'),
                ),

                /* Tab 內容 */
                activeTab === 'generate'
                    ? el(TabGenerate, { postId })
                    : el(TabImprove,  { postId }),
            ),
        );
    }

    registerPlugin('wrova-sidebar', {
        render: WrovaSidebar,
        icon:   'edit-large',
    });

}());
