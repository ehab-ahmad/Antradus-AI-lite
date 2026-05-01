(function () {
    'use strict';

    const data = window.antradusData || {};
    const ajaxUrl = data.ajaxUrl || window.ajaxurl;
    const postId  = data.postId  || 0;
    const nonces  = data.nonces  || {};

    let generatedArticleText  = '';
    let generatedImageUrl     = '';
    let generatedAttachmentId = 0;
    var seoValues = { title: '', desc: '', keyword: '' };

    const editorWp      = (typeof wp !== 'undefined') ? wp : null;
    const isBlockEditor = !!(editorWp && editorWp.blocks && editorWp.data);

    // ── Accordion toggles ─────────────────────────────────────────────────────

    document.querySelectorAll('.antradus-accordion-header').forEach(function (header) {
        header.addEventListener('click', function () {
            var accordion = this.closest('.antradus-accordion');
            accordion.classList.toggle('is-open');
        });
    });

    // ── Topic / niche toggle ──────────────────────────────────────────────────

    var selectedTopic = '';

    document.querySelectorAll('.antradus-topic-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.antradus-topic-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            if (selectedTopic === this.dataset.topic) {
                selectedTopic = '';
            } else {
                this.classList.add('active');
                selectedTopic = this.dataset.topic;
            }
        });
    });

    // ── Mode hint ─────────────────────────────────────────────────────────────

    ['antradus-keyword', 'antradus-url'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updateModeHint);
    });
    updateModeHint();

    // ── Copy / Insert meta buttons ────────────────────────────────────────────

    var activeSeoPlugin = null;

    function refreshSeoDetection() {
        var detected = detectSeoPlugin();
        if (detected === activeSeoPlugin) return;
        activeSeoPlugin = detected;
        updateSeoIndicator();
    }

    document.querySelectorAll('.antradus-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(this.dataset.target);
            if (!target) return;
            var text    = target.textContent.trim();
            var origTxt = this.textContent;
            var self    = this;
            copyToClipboard(text);
            this.textContent = 'Copied!';
            setTimeout(function () { self.textContent = origTxt; }, 1800);
        });
    });

    refreshSeoDetection();
    setTimeout(refreshSeoDetection, 800);
    setTimeout(refreshSeoDetection, 2500);

    // ── Word count observer (enables image button when post has content) ──────

    function getEditorText() {
        if (typeof tinymce !== 'undefined') {
            var ed = tinymce.get('content');
            if (ed && !ed.isHidden()) return ed.getContent({ format: 'text' }).trim();
        }
        var ta = document.getElementById('content');
        if (ta && ta.value.trim()) return ta.value.replace(/<[^>]+>/g, ' ').trim();
        if (editorWp && editorWp.data) {
            try {
                var sel = editorWp.data.select('core/editor') || editorWp.data.select('core/block-editor');
                if (sel && sel.getEditedPostContent) {
                    var content = sel.getEditedPostContent();
                    if (content) return content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                }
            } catch (e) {}
        }
        return '';
    }

    function syncImageButtonState() {
        var btn = document.getElementById('antradus-image-btn');
        if (!btn) return;
        var instructions = (document.getElementById('antradus-image-instructions').value || '').trim();
        if (instructions) { btn.disabled = false; return; }

        if (isBlockEditor && editorWp && editorWp.data) {
            try {
                var content = editorWp.data.select('core/editor').getEditedPostContent() || '';
                var words   = content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean).length;
                if (words >= 50) {
                    btn.disabled = false;
                    if (!generatedArticleText) generatedArticleText = content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 800);
                } else {
                    btn.disabled = true;
                }
            } catch (e) {}
            return;
        }

        // Classic editor: read TinyMCE directly (handles pre-existing content on page load)
        if (typeof tinymce !== 'undefined') {
            var ed = tinymce.get('content');
            if (ed && !ed.isHidden()) {
                var edText = ed.getContent({ format: 'text' }).trim();
                var words  = edText ? edText.split(/\s+/).filter(Boolean).length : 0;
                if (words >= 50) {
                    btn.disabled = false;
                    if (!generatedArticleText) generatedArticleText = edText.substring(0, 800);
                } else {
                    btn.disabled = true;
                }
                return;
            }
        }

        // Fallback: #wp-word-count (text-only editor mode or TinyMCE not yet ready)
        var wcEl  = document.getElementById('wp-word-count');
        if (!wcEl) return;
        var match = wcEl.textContent.match(/\d+/);
        var words = match ? parseInt(match[0], 10) : 0;
        if (words >= 50) {
            btn.disabled = false;
            if (!generatedArticleText) generatedArticleText = getEditorText().substring(0, 800);
        } else {
            btn.disabled = true;
        }
    }

    var instrEl = document.getElementById('antradus-image-instructions');
    if (instrEl) instrEl.addEventListener('input', syncImageButtonState);

    var syncDebounceTimer;
    function debouncedSync() {
        clearTimeout(syncDebounceTimer);
        syncDebounceTimer = setTimeout(syncImageButtonState, 300);
    }

    function startWordCountObserver() {
        if (isBlockEditor) {
            if (editorWp && editorWp.data) {
                editorWp.data.subscribe(debouncedSync);
            }
            syncImageButtonState();
            return;
        }
        var wcEl = document.getElementById('wp-word-count');
        if (!wcEl) { setTimeout(startWordCountObserver, 500); return; }
        syncImageButtonState();
        var observer = new MutationObserver(syncImageButtonState);
        observer.observe(wcEl, { childList: true, subtree: true, characterData: true });
    }
    startWordCountObserver();

    // Classic editor: re-check button state once TinyMCE finishes loading (pre-existing content)
    if (!isBlockEditor) {
        if (typeof tinymce !== 'undefined') {
            tinymce.on('AddEditor', function (e) { e.editor.on('init', syncImageButtonState); });
        }
        setTimeout(syncImageButtonState, 1200);
        setTimeout(syncImageButtonState, 2500);
    }

    // ── AJAX helpers ──────────────────────────────────────────────────────────

    async function ajaxPost(params) {
        var r = await fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params),
        });
        var raw = await r.text();
        try { return JSON.parse(raw); }
        catch (e) { return { success: false, data: 'Server error — invalid response:\n' + raw.substring(0, 200) }; }
    }

    async function fetchSourceUrl(url) {
        return ajaxPost({ action: 'antradus_fetch_url', url: url, nonce: nonces.fetchUrl });
    }

    // ── Generate content ──────────────────────────────────────────────────────

    document.getElementById('antradus-generate-btn').addEventListener('click', async function () {
        var keyword  = (document.getElementById('antradus-keyword').value || '').trim();
        var url      = (document.getElementById('antradus-url') ? document.getElementById('antradus-url').value : '').trim();
        var style    = document.getElementById('antradus-style').value;
        var tone     = document.getElementById('antradus-tone').value;
        var lang     = document.getElementById('antradus-lang').value;
        var inclFaq  = document.getElementById('antradus-faq').checked;
        var inclMeta = document.getElementById('antradus-meta').checked;

        if (!keyword && !url) { setStatus('Please enter a topic or a source URL.', 'error'); return; }

        setLoading(true);
        document.getElementById('antradus-meta-output').style.display = 'none';
        document.getElementById('antradus-image-ready-bar').style.display = 'none';
        document.getElementById('antradus-image-btn').disabled = true;
        generatedArticleText = '';

        var sourceText = '';
        if (url) {
            setStatus('Fetching source content...');
            try {
                var d = await fetchSourceUrl(url);
                if (!d.success) { setStatus('Could not fetch URL: ' + (d.data || 'Unknown error'), 'error'); setLoading(false); return; }
                sourceText = d.data;
            } catch (e) { setStatus('Network error: ' + e.message, 'error'); setLoading(false); return; }
        }

        var genDots = 0;
        var dotTimer = setInterval(function () {
            genDots = (genDots % 3) + 1;
            setStatus('Crafting your article' + '.'.repeat(genDots));
        }, 700);

        try {
            var resp = await ajaxPost({
                action:    'antradus_generate',
                keyword:   keyword,
                source:    sourceText,
                style:     style,
                tone:      tone,
                lang:      lang,
                niche:     selectedTopic,
                incl_faq:  inclFaq  ? '1' : '0',
                incl_meta: inclMeta ? '1' : '0',
                nonce:     nonces.generate,
            });
            clearInterval(dotTimer);

            if (!resp.success) { setStatus('Error: ' + (resp.data || 'Unknown error'), 'error'); setLoading(false); return; }

            var result = resp.data;
            pasteIntoEditor(result.article);
            generatedArticleText = result.article.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 800);
            document.getElementById('antradus-image-btn').disabled = false;

            if (result.meta_title || result.meta_desc) {
                document.getElementById('antradus-meta-title-val').textContent = result.meta_title || '';
                document.getElementById('antradus-meta-desc-val').textContent  = result.meta_desc  || '';
                var kwDisplay = document.getElementById('antradus-keyword-display');
                var kwRow     = document.getElementById('antradus-keyword-row');
                if (kwDisplay && kwRow) {
                    var focusKw = result.focus_keyword || keyword;
                    kwDisplay.textContent = focusKw;
                    kwRow.style.display   = focusKw ? '' : 'none';
                }
                document.getElementById('antradus-meta-output').style.display = '';
                fillSeoPlugin(result.meta_title, result.meta_desc, result.focus_keyword || keyword);
            }

            setStatus('Article crafted successfully.', 'success');
        } catch (e) {
            clearInterval(dotTimer);
            setStatus('Network error: ' + e.message, 'error');
        }

        setLoading(false);
    });

    // ── Generate image ────────────────────────────────────────────────────────

    document.getElementById('antradus-image-btn').addEventListener('click', async function () {
        if (!generatedArticleText) {
            generatedArticleText = getEditorText().replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 800);
        }

        var extraInstructions = (document.getElementById('antradus-image-instructions').value || '').trim();
        var instructionsOnly  = document.getElementById('antradus-instructions-only').checked;

        if (!generatedArticleText && !extraInstructions) {
            setImageStatus('Please generate content first, or add instructions above.', 'error');
            return;
        }

        setImageLoading(true);
        setImageStatus('Starting image generation...');
        document.getElementById('antradus-image-ready-bar').style.display = 'none';

        try {
            var resp = await ajaxPost({
                action:             'antradus_generate_image',
                article_text:       instructionsOnly ? '' : generatedArticleText,
                extra_instructions: extraInstructions,
                instructions_only:  instructionsOnly ? '1' : '0',
                post_id:            getCurrentPostId(),
                nonce:              nonces.generateImage,
            });

            if (!resp.success) { setImageStatus('Error: ' + (resp.data || 'Unknown error'), 'error'); setImageLoading(false); return; }

            var jobId      = resp.data.job_id;
            var elapsed    = 0;
            var maxSeconds = 180;
            var interval   = 3000;
            var dots       = 0;

            while (elapsed < maxSeconds) {
                await new Promise(function (r) { setTimeout(r, interval); });
                elapsed += interval / 1000;
                dots = (dots % 3) + 1;
                setImageStatus('Generating image' + '.'.repeat(dots) + ' (' + elapsed + 's)');

                try {
                    var check = await ajaxPost({
                        action: 'antradus_check_image_job',
                        job_id: jobId,
                        nonce:  nonces.checkImageJob,
                    });

                    if (!check.success) {
                        setImageStatus('Error: ' + (check.data || 'Unknown error'), 'error');
                        setImageLoading(false);
                        return;
                    }

                    if (check.data.status === 'done') {
                        generatedImageUrl     = check.data.result.url;
                        generatedAttachmentId = check.data.result.attachment_id;
                        document.getElementById('antradus-modal-img').src = generatedImageUrl;
                        var bar = document.getElementById('antradus-image-ready-bar');
                        bar.style.display = 'flex';
                        document.getElementById('antradus-set-featured-btn').disabled = false;
                        if (isBlockEditor) {
                            var inlineImg     = document.getElementById('antradus-inline-img');
                            var inlinePreview = document.getElementById('antradus-inline-preview');
                            if (inlineImg) inlineImg.src = generatedImageUrl;
                            if (inlinePreview) inlinePreview.style.display = 'block';
                        }
                        setImageStatus('Image created and saved to Media Library.', 'success');
                        setImageLoading(false);
                        return;
                    }
                    // status is pending or running — keep polling
                } catch (pollErr) {
                    // transient network hiccup — keep polling
                }
            }

            setImageStatus(
                'Image generation timed out after ' + maxSeconds + 's. ' +
                'The model may be overloaded or your hosting server interrupted the request — try a lighter/faster model in Settings → Antradus AI.',
                'error'
            );

        } catch (e) { setImageStatus('Network error: ' + e.message, 'error'); }

        setImageLoading(false);
    });

    // ── Modal ─────────────────────────────────────────────────────────────────

    document.getElementById('antradus-preview-btn').addEventListener('click', function () {
        if (isBlockEditor) {
            var p = document.getElementById('antradus-inline-preview');
            if (p) p.style.display = p.style.display === 'none' ? 'block' : 'none';
            return;
        }
        document.getElementById('antradus-modal-overlay').style.display = 'flex';
    });

    document.getElementById('antradus-modal-close').addEventListener('click', function () {
        document.getElementById('antradus-modal-overlay').style.display = 'none';
    });

    document.getElementById('antradus-modal-overlay').addEventListener('click', function (e) {
        if (e.target === this) this.style.display = 'none';
    });

    // ── Set featured image ────────────────────────────────────────────────────

    function getCurrentPostId() {
        if (postId) return postId;
        var el = document.getElementById('post_ID');
        if (el) {
            var val = parseInt(el.value, 10);
            if (val) return val;
        }
        if (editorWp && editorWp.data) {
            try {
                var sel = editorWp.data.select('core/editor');
                if (sel && sel.getCurrentPostId) return sel.getCurrentPostId() || 0;
            } catch (e) {}
        }
        return 0;
    }

    async function doSetFeatured(btn, updateImageStatus) {
        if (!generatedAttachmentId) return;
        var currentPostId = getCurrentPostId();
        if (!currentPostId) return;
        btn.disabled    = true;
        btn.textContent = 'Setting...';
        if (updateImageStatus) setImageStatus('Setting as featured image...');

        try {
            var resp = await ajaxPost({
                action:        'antradus_set_featured',
                attachment_id: generatedAttachmentId,
                post_id:       currentPostId,
                nonce:         nonces.setFeatured,
            });

            if (!resp.success) {
                if (updateImageStatus) setImageStatus('Error: ' + (resp.data || 'Unknown error'), 'error');
                btn.disabled    = false;
                btn.textContent = '★ Set as Featured';
                return;
            }

            btn.textContent      = 'Featured Set ✓';
            btn.style.background = '#005580';
            if (updateImageStatus) setImageStatus('Featured image set successfully.', 'success');

            refreshFeaturedImageUi(generatedAttachmentId, currentPostId);

        } catch (e) {
            if (updateImageStatus) setImageStatus('Network error: ' + e.message, 'error');
            btn.disabled    = false;
            btn.textContent = '★ Set as Featured';
        }
    }

    document.getElementById('antradus-set-featured-btn').addEventListener('click', function () {
        doSetFeatured(this, true);
    });

    document.getElementById('antradus-modal-set-featured').addEventListener('click', function () {
        doSetFeatured(this, false);
        var barBtn          = document.getElementById('antradus-set-featured-btn');
        barBtn.textContent  = 'Featured Image Set!';
        barBtn.style.background = '#135e96';
        barBtn.disabled     = true;
    });

    // ── Featured image UI refresh ─────────────────────────────────────────────

    function refreshFeaturedImageUi(attachmentId, pid) {
        // Block editor: dispatch to core/editor so the panel updates
        if (editorWp && editorWp.data) {
            try {
                editorWp.data.dispatch('core/editor').editPost({ featured_media: attachmentId });
            } catch (e) {}
        }

        // Classic editor: wp.media.featuredImage.set() redraws #postimagediv
        var wpRef = (editorWp && editorWp.media) ? editorWp
                  : (typeof wp !== 'undefined' ? wp : null);
        if (!wpRef || !wpRef.media || !wpRef.media.featuredImage) return;

        // Ensure the internal post ID is correct (may be 0 on new posts)
        if (wpRef.media.view && wpRef.media.view.settings && wpRef.media.view.settings.post) {
            if (!wpRef.media.view.settings.post.id && pid) {
                wpRef.media.view.settings.post.id = pid;
            }
        }

        wpRef.media.featuredImage.set(attachmentId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function setLoading(on) {
        var btn     = document.getElementById('antradus-generate-btn');
        btn.disabled    = on;
        btn.textContent = on ? 'Thinking...' : 'Start Writing';
    }

    function setImageLoading(on) {
        var btn     = document.getElementById('antradus-image-btn');
        btn.disabled    = on;
        btn.textContent = on ? 'Drawing...' : '🎨 Image Generator';
    }

    function setStatus(msg, type) {
        var el     = document.getElementById('antradus-status');
        el.textContent = msg;
        el.className   = type || '';
    }

    function setImageStatus(msg, type) {
        var el     = document.getElementById('antradus-image-status');
        el.textContent = msg;
        el.className   = type || '';
    }

    function pasteIntoEditor(html) {
        if (isBlockEditor && editorWp && editorWp.blocks && editorWp.data) {
            try {
                var blocks = editorWp.blocks.rawHandler({ HTML: html });
                editorWp.data.dispatch('core/block-editor').resetBlocks(blocks);
            } catch (e) {
                editorWp.data.dispatch('core/editor').editPost({ content: html });
            }
            return;
        }
        if (typeof tinymce !== 'undefined') {
            var ed = tinymce.get('content');
            if (ed && !ed.isHidden()) { ed.setContent(html); ed.fire('change'); return; }
        }
        var ta = document.getElementById('content');
        if (ta) { ta.value = html; ta.dispatchEvent(new Event('input')); return; }
    }

    function updateModeHint() {
        var hintEl = document.getElementById('antradus-mode-hint');
        if (!hintEl) return;

        var keyword = (document.getElementById('antradus-keyword') ? document.getElementById('antradus-keyword').value : '').trim();
        var url1    = (document.getElementById('antradus-url')     ? document.getElementById('antradus-url').value     : '').trim();

        if (!keyword && !url1) {
            hintEl.textContent   = '';
            hintEl.style.display = 'none';
            return;
        }

        var badge, badgeClass, msg;

        if (!url1) {
            badge      = 'ORIGINAL';
            badgeClass = 'antradus-badge-green';
            msg        = 'Crafting an original article on your topic.';
        } else {
            badge      = 'FROM SOURCE';
            badgeClass = 'antradus-badge-blue';
            msg        = keyword
                ? 'Keyword as anchor — facts drawn from the source.'
                : 'Fetching the source and writing a fresh article from a new angle.';
        }

        hintEl.innerHTML     = '<span class="antradus-hint-badge ' + badgeClass + '">' + badge + '</span>' + msg;
        hintEl.style.display = 'block';
    }

    // ── SEO plugin detection ──────────────────────────────────────────────────

    function detectSeoPlugin() {
        // Classic editor
        if (document.getElementById('yoast_wpseo_title')) return 'yoast';
        if (document.getElementById('rank_math_seo') ||
            document.querySelector('.rank-math-metabox') ||
            document.querySelector('[id*="rank_math"][class*="postbox"]')) return 'rankmath';
        // Block editor — each plugin registers a global object
        if (typeof window.YoastSEO !== 'undefined' || document.querySelector('[class*="yoast-seo"]')) return 'yoast';
        if (window.rankMath || document.querySelector('[class*="rank-math-editor"]')) return 'rankmath';
        return null;
    }

    function updateSeoIndicator() {
        var el      = document.getElementById('antradus-seo-indicator');
        var proBtn  = document.getElementById('antradus-generate-meta-pro-btn');

        if (activeSeoPlugin === 'yoast') {
            if (el) {
                el.className = 'antradus-pro-notice';
                el.innerHTML = '<span class="antradus-pro-notice-icon">🔒</span>'
                    + '<div><strong>Yoast SEO detected — Auto-generate and insert is a Pro feature.</strong>'
                    + 'Copy the values below and paste them manually into Yoast.</div>'
                    + '<a href="' + (data.proUrl || '') + '" target="_blank" rel="noopener" class="antradus-pro-upgrade-link">Upgrade Now →</a>';
                el.style.display = '';
            }
            if (proBtn) proBtn.textContent = '🔒 Generate Yoast Meta from Existing Article — Pro Feature';
        } else if (activeSeoPlugin === 'rankmath') {
            if (el) {
                el.className = 'antradus-pro-notice';
                el.innerHTML = '<span class="antradus-pro-notice-icon">🔒</span>'
                    + '<div><strong>Rank Math detected — Auto-generate and insert is a Pro feature.</strong>'
                    + 'Copy the values below and paste them manually into Rank Math.</div>'
                    + '<a href="' + (data.proUrl || '') + '" target="_blank" rel="noopener" class="antradus-pro-upgrade-link">Upgrade Now →</a>';
                el.style.display = '';
            }
            if (proBtn) proBtn.textContent = '🔒 Generate Rank Math Meta from Existing Article — Pro Feature';
        } else {
            if (el) { el.className = ''; el.innerHTML = ''; el.style.display = 'none'; }
            if (proBtn) proBtn.textContent = '🔒 Generate Meta from Existing Article — Pro Feature';
        }
    }

    // ── fillSeoPlugin — called after generation ───────────────────────────────

    function fillSeoPlugin(title, desc, keyword) {
        seoValues.title   = title   || '';
        seoValues.desc    = desc    || '';
        seoValues.keyword = keyword || '';
    }

    // ── Shared utility ────────────────────────────────────────────────────────

    function setNativeValue(el, value) {
        var proto = (el.tagName === 'TEXTAREA')
            ? window.HTMLTextAreaElement.prototype
            : window.HTMLInputElement.prototype;
        var desc = Object.getOwnPropertyDescriptor(proto, 'value');
        if (desc && desc.set) {
            desc.set.call(el, value);
        } else {
            el.value = value;
        }
        el.dispatchEvent(new Event('input',  { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function () { copyFallback(text); });
        } else {
            copyFallback(text);
        }
    }

    function copyFallback(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }


})();
