/* Antradus AI — Settings page */
(function () {
    'use strict';

    // ── Show only the active provider section ─────────────────────────────────
    var providerSelect   = document.getElementById('antradus-provider-select');
    var providerSections = document.querySelectorAll('.antradus-provider-section[data-provider]');

    function showActiveProvider() {
        var active = providerSelect ? providerSelect.value : '';
        providerSections.forEach(function (el) {
            el.style.display = el.dataset.provider === active ? '' : 'none';
        });
    }

    if (providerSelect) {
        showActiveProvider();
        providerSelect.addEventListener('change', showActiveProvider);
    }

    // ── Image preset tabs ─────────────────────────────────────────────────────
    var tabGroup = document.getElementById('antradus-image-tab-group');
    if (tabGroup) {
        tabGroup.querySelectorAll('.antradus-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.dataset.tab;

                // Update buttons
                tabGroup.querySelectorAll('.antradus-tab-btn').forEach(function (b) {
                    b.classList.remove('is-active');
                });
                btn.classList.add('is-active');

                // Update panels
                tabGroup.querySelectorAll('.antradus-tab-panel').forEach(function (p) {
                    var show = p.id === 'antradus-image-tab-panel-' + tab;
                    p.style.display = show ? '' : 'none';
                    p.classList.toggle('is-active', show);
                });
            });
        });

        // Clicking a radio auto-updates the active badge on tab buttons
        tabGroup.querySelectorAll('input[name="antradus_image_preset"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                tabGroup.querySelectorAll('.antradus-tab-btn').forEach(function (b) {
                    var badge = b.querySelector('.antradus-tab-active-badge');
                    if (b.dataset.tab === radio.value) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'antradus-tab-active-badge';
                            badge.textContent = '✓';
                            b.appendChild(badge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                });
            });
        });
    }

    // ── Color cast toggle ─────────────────────────────────────────────────────
    var colorEnabled = document.getElementById('antradus-color-enabled');
    var colorRow     = document.getElementById('antradus-color-picker-row');
    if (colorEnabled && colorRow) {
        colorEnabled.addEventListener('change', function () {
            colorRow.style.display = colorEnabled.checked ? '' : 'none';
        });
    }

    // ── Fetch model list (text or image) ──────────────────────────────────────
    document.querySelectorAll('.antradus-fetch-models-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var provider = btn.dataset.provider;
            var type     = btn.dataset.type || 'text';
            var keyInput = document.querySelector('input[name="antradus_' + provider + '_api_key"]');
            var selectId = type === 'image'
                ? 'antradus-image-model-select-' + provider
                : 'antradus-model-select-' + provider;
            var statusId = type === 'image'
                ? 'antradus-image-model-status-' + provider
                : 'antradus-model-status-' + provider;
            var modelSel = document.getElementById(selectId);
            var statusEl = document.getElementById(statusId);
            var apiKey   = keyInput ? keyInput.value.trim() : '';
            var keySaved = keyInput ? keyInput.dataset.keySaved === '1' : false;

            // Block only if key is not saved AND not entered AND not openrouter
            if (!apiKey && !keySaved && provider !== 'openrouter') {
                statusEl.textContent = 'Enter your API key first, or save it then click Fetch.';
                statusEl.style.color = '#d63638';
                return;
            }

            btn.disabled    = true;
            btn.textContent = 'Fetching…';
            statusEl.textContent = '';
            statusEl.style.color = '#646970';

            var data = new FormData();
            data.append('action',   'antradus_fetch_models');
            data.append('nonce',    antradusSettings.nonce);
            data.append('provider', provider);
            data.append('type',     type);
            data.append('api_key',  apiKey);

            fetch(antradusSettings.ajaxUrl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) {
                        statusEl.textContent = 'Error: ' + res.data;
                        statusEl.style.color = '#d63638';
                        return;
                    }
                    var models   = res.data;
                    var savedVal = modelSel.dataset.saved || modelSel.value;
                    var found    = false;

                    modelSel.innerHTML = '';
                    models.forEach(function (m) {
                        var opt         = document.createElement('option');
                        opt.value       = m.id;
                        opt.textContent = m.name || m.id;
                        if (m.id === savedVal) { opt.selected = true; found = true; }
                        modelSel.appendChild(opt);
                    });

                    if (!found && savedVal) {
                        var kept         = document.createElement('option');
                        kept.value       = savedVal;
                        kept.textContent = savedVal + ' (saved)';
                        kept.selected    = true;
                        modelSel.insertBefore(kept, modelSel.firstChild);
                    }

                    statusEl.textContent = models.length + ' models loaded.';
                    statusEl.style.color = '#00a32a';
                })
                .catch(function () {
                    statusEl.textContent = 'Request failed.';
                    statusEl.style.color = '#d63638';
                })
                .finally(function () {
                    btn.disabled    = false;
                    btn.textContent = type === 'image' ? '↻ Fetch Image Models' : '↻ Fetch Models';
                });
        });
    });
}());
