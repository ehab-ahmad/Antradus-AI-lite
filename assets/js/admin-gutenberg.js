(function () {
    'use strict';

    const data    = window.antradusData || {};
    const ajaxUrl = data.ajaxUrl || window.ajaxurl;
    const nonces  = data.nonces  || {};

    let generatedArticleText  = '';
    let generatedImageUrl     = '';
    let generatedAttachmentId = 0;
    var seoValues = { title: '', desc: '', keyword: '' };

    // ── Accordion toggles ─────────────────────────────────────────────────────

    document.querySelectorAll('.antradus-accordion-header').forEach(function (header) {
        header.addEventListener('click', function () {
            this.closest('.antradus-accordion').classList.toggle('is-open');
        });
    });

    // ── Topic / niche toggle ──────────────────────────────────────────────────

    var selectedTopic = '';

    document.querySelectorAll('.antradus-topic-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.antradus-topic-btn').forEach(function (b) { b.classList.remove('active'); });
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

    // ── Copy buttons ──────────────────────────────────────────────────────────

    var activeSeoPlugin = null;

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

    // ── Image button state (reads wp.data store) ──────────────────────────────

    function syncImageButtonState() {
        var btn = document.getElementById('antradus-image-btn');
        if (!btn) return;

        var instructions = (document.getElementById('antradus-image-instructions').value || '').trim();
        if (instructions) { btn.disabled = false; return; }

        try {
            var content = wp.data.select('core/editor').getEditedPostContent() || '';
            var words   = content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean).length;
            if (words >= 50) {
                btn.disabled = false;
                if (!generatedArticleText) {
                    generatedArticleText = extractImageContext(content);
                }
            } else {
                btn.disabled = true;
            }
        } catch (e) {
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

    wp.data.subscribe(debouncedSync);
    syncImageButtonState();

    // ── AJAX helpers ──────────────────────────────────────────────────────────

    async function ajaxPost(params) {
        var r = await fetch(ajaxUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    new URLSearchParams(params),
        });
        var raw = await r.text();
        try { return JSON.parse(raw); }
        catch (e) { return { success: false, data: 'Server error — invalid response:\n' + raw.substring(0, 200) }; }
    }

    // ── Generate article ──────────────────────────────────────────────────────

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
                var d = await ajaxPost({ action: 'antradus_fetch_url', url: url, nonce: nonces.fetchUrl });
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
            generatedArticleText = extractImageContext(result.article);
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
                if (result.meta_title) {
                    try { wp.data.dispatch('core/editor').editPost({ title: result.meta_title }); } catch (e) {}
                }
            }

            setStatus('Article crafted successfully.', 'success');
        } catch (e) {
            clearInterval(dotTimer);
            setStatus('Network error: ' + e.message, 'error');
        }

        setLoading(false);
    });

    // ── Generate image (synchronous) ──────────────────────────────────────────

    document.getElementById('antradus-image-btn').addEventListener('click', async function () {
        if (!generatedArticleText) {
            try {
                var content = wp.data.select('core/editor').getEditedPostContent() || '';
                generatedArticleText = extractImageContext(content);
            } catch (e) {}
        }

        var extraInstructions = (document.getElementById('antradus-image-instructions').value || '').trim();
        var instructionsOnly  = document.getElementById('antradus-instructions-only').checked;

        if (!generatedArticleText && !extraInstructions) {
            setImageStatus('Please generate content first, or add instructions above.', 'error');
            return;
        }

        setImageLoading(true);
        document.getElementById('antradus-image-ready-bar').style.display = 'none';
        var inlinePreview = document.getElementById('antradus-inline-preview');
        if (inlinePreview) inlinePreview.style.display = 'none';

        var imgDots = 0;
        var dotTimer = setInterval(function () {
            imgDots = (imgDots % 3) + 1;
            setImageStatus('Generating image' + '.'.repeat(imgDots));
        }, 700);

        try {
            var resp = await ajaxPost({
                action:             'antradus_generate_image',
                article_text:       instructionsOnly ? '' : generatedArticleText,
                extra_instructions: extraInstructions,
                instructions_only:  instructionsOnly ? '1' : '0',
                post_id:            getCurrentPostId(),
                nonce:              nonces.generateImage,
            });
            clearInterval(dotTimer);

            if (!resp.success) { setImageStatus('Error: ' + (resp.data || 'Unknown error'), 'error'); setImageLoading(false); return; }

            generatedImageUrl     = resp.data.url;
            generatedAttachmentId = resp.data.attachment_id;

            var inlineImg = document.getElementById('antradus-inline-img');
            if (inlineImg) inlineImg.src = generatedImageUrl;
            if (inlinePreview) inlinePreview.style.display = 'block';

            var bar = document.getElementById('antradus-image-ready-bar');
            bar.style.display = 'flex';
            document.getElementById('antradus-set-featured-btn').disabled = false;
            setImageStatus('Image created and saved to Media Library.', 'success');
        } catch (e) {
            clearInterval(dotTimer);
            setImageStatus('Network error: ' + e.message, 'error');
        }

        setImageLoading(false);
    });

    // ── Preview (Gutenberg: inline div toggle) ────────────────────────────────

    document.getElementById('antradus-preview-btn').addEventListener('click', function () {
        var p = document.getElementById('antradus-inline-preview');
        if (p) p.style.display = p.style.display === 'none' ? 'block' : 'none';
    });

    document.getElementById('antradus-modal-close').addEventListener('click', function () {
        document.getElementById('antradus-modal-overlay').style.display = 'none';
    });

    document.getElementById('antradus-modal-overlay').addEventListener('click', function (e) {
        if (e.target === this) this.style.display = 'none';
    });

    // ── Set featured image ────────────────────────────────────────────────────

    function getCurrentPostId() {
        try {
            return wp.data.select('core/editor').getCurrentPostId() || 0;
        } catch (e) {
            return data.postId || 0;
        }
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
            refreshFeaturedImageUi(generatedAttachmentId);
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
        var barBtn = document.getElementById('antradus-set-featured-btn');
        barBtn.textContent      = 'Featured Image Set!';
        barBtn.style.background = '#135e96';
        barBtn.disabled         = true;
    });

    function refreshFeaturedImageUi(attachmentId) {
        try {
            wp.data.dispatch('core/editor').editPost({ featured_media: attachmentId });
        } catch (e) {}
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function pasteIntoEditor(html) {
        try {
            var blocks = wp.blocks.rawHandler({ HTML: html });
            wp.data.dispatch('core/block-editor').resetBlocks(blocks);
        } catch (e) {
            try { wp.data.dispatch('core/editor').editPost({ content: html }); } catch (e2) {}
        }
    }

    function setLoading(on) {
        var btn = document.getElementById('antradus-generate-btn');
        btn.disabled    = on;
        btn.textContent = on ? 'Thinking...' : 'Start Writing';
    }

    function setImageLoading(on) {
        var btn = document.getElementById('antradus-image-btn');
        btn.disabled    = on;
        btn.textContent = on ? 'Drawing...' : '🎨 Image Generator';
    }

    function setStatus(msg, type) {
        var el = document.getElementById('antradus-status');
        el.textContent = msg;
        el.className   = type || '';
    }

    function setImageStatus(msg, type) {
        var el = document.getElementById('antradus-image-status');
        el.textContent = msg;
        el.className   = type || '';
    }

    function updateModeHint() {
        var hintEl = document.getElementById('antradus-mode-hint');
        if (!hintEl) return;
        var keyword = (document.getElementById('antradus-keyword') ? document.getElementById('antradus-keyword').value : '').trim();
        var url1    = (document.getElementById('antradus-url')     ? document.getElementById('antradus-url').value     : '').trim();

        if (!keyword && !url1) { hintEl.textContent = ''; hintEl.style.display = 'none'; return; }

        var badge, badgeClass, msg;
        if (!url1) {
            badge = 'ORIGINAL'; badgeClass = 'antradus-badge-green'; msg = 'Crafting an original article on your topic.';
        } else {
            badge = 'FROM SOURCE'; badgeClass = 'antradus-badge-blue';
            msg = keyword ? 'Keyword as anchor — facts drawn from the source.' : 'Fetching the source and writing a fresh article from a new angle.';
        }
        hintEl.innerHTML     = '<span class="antradus-hint-badge ' + badgeClass + '">' + badge + '</span>' + msg;
        hintEl.style.display = 'block';
    }

    function refreshSeoDetection() {
        var detected = detectSeoPlugin();
        if (detected === activeSeoPlugin) return;
        activeSeoPlugin = detected;
        updateSeoIndicator();
    }

    function detectSeoPlugin() {
        if (typeof window.YoastSEO !== 'undefined' || document.querySelector('[class*="yoast-seo"]')) return 'yoast';
        if (window.rankMath || document.querySelector('[class*="rank-math-editor"]')) return 'rankmath';
        return null;
    }

    function updateSeoIndicator() {
        var el     = document.getElementById('antradus-seo-indicator');
        var proBtn = document.getElementById('antradus-generate-meta-pro-btn');
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

    function fillSeoPlugin(title, desc, keyword) {
        seoValues.title   = title   || '';
        seoValues.desc    = desc    || '';
        seoValues.keyword = keyword || '';
    }

    function extractImageContext(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var h1 = doc.querySelector('h1');
        if (h1) return h1.textContent.trim();
        var h2 = doc.querySelector('h2');
        if (h2) return h2.textContent.trim();
        return doc.body.textContent.trim().split(/\s+/).slice(0, 12).join(' ');
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
