(function () {
    const app = document.querySelector('[data-phone-finder-endpoint]');
    if (!app) return;

    const endpoint = app.getAttribute('data-phone-finder-endpoint');
    const form = document.querySelector('[data-phone-finder-form]');
    const emptyState = document.querySelector('[data-phone-finder-empty]');
    const loadingState = document.querySelector('[data-phone-finder-loading]');
    const resultsWrap = document.querySelector('[data-phone-finder-results]');
    const modal = document.querySelector('[data-phone-request-modal]');
    const requestForm = document.querySelector('[data-phone-request-form]');
    const requestSummary = document.querySelector('[data-phone-request-summary]');
    const csrf = document.body ? document.body.getAttribute('data-csrf') || '' : '';

    function rootRelativeAsset(path) {
        path = String(path || '').trim().replace(/\\/g, '/');
        if (!path) return '';
        if (/^(https?:|data:|blob:)/i.test(path)) return path;
        if (path.charAt(0) === '/') return path;
        if (/^(assets|uploads)\//.test(path)) return path;
        return path;
    }

    function generatedPhoneImage(label) {
        label = String(label || 'Requested Phone').replace(/[<>&"']/g, '').slice(0, 80) || 'Requested Phone';
        const hue = Array.from(label).reduce(function (acc, ch) { return (acc + ch.charCodeAt(0)) % 360; }, 210);
        const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="900" viewBox="0 0 900 900">'
            + '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="hsl(' + hue + ',72%,48%)"/><stop offset="1" stop-color="hsl(' + ((hue + 44) % 360) + ',72%,58%)"/></linearGradient></defs>'
            + '<rect width="900" height="900" rx="92" fill="url(#g)"/><circle cx="190" cy="160" r="120" fill="#fff" opacity=".16"/><circle cx="760" cy="720" r="170" fill="#fff" opacity=".12"/>'
            + '<rect x="295" y="120" width="310" height="650" rx="54" fill="#111827"/><rect x="318" y="156" width="264" height="560" rx="34" fill="#f8fafc"/>'
            + '<rect x="350" y="220" width="200" height="200" rx="42" fill="#1e293b" opacity=".94"/><circle cx="408" cy="282" r="34" fill="#fff" opacity=".88"/><circle cx="492" cy="282" r="34" fill="#fff" opacity=".88"/><circle cx="408" cy="362" r="30" fill="#fff" opacity=".82"/>'
            + '<text x="450" y="550" text-anchor="middle" font-family="Arial,sans-serif" font-size="38" font-weight="800" fill="#0f172a">Phone Request</text>'
            + '<text x="450" y="610" text-anchor="middle" font-family="Arial,sans-serif" font-size="28" font-weight="700" fill="#0f172a">' + label + '</text></svg>';
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    let lastPreferences = null;
    let selectedCandidate = null;
    let selectedSource = 'ai';

    function setHidden(el, hidden) {
        if (!el) return;
        el.hidden = hidden;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char];
        });
    }

    function getPreferences() {
        const data = new FormData(form);
        return {
            budget_min: data.get('budget_min') || '',
            budget_max: data.get('budget_max') || '',
            os: data.get('os') || 'any',
            brand: data.get('brand') || 'any',
            use_case: data.get('use_case') || 'daily',
            storage_min: data.get('storage_min') || '128',
            battery_priority: data.get('battery_priority') || 'medium',
            camera_priority: data.get('camera_priority') || 'medium',
            performance_priority: data.get('performance_priority') || 'medium',
            need_5g: data.get('need_5g') === '1',
            warranty_required: data.get('warranty_required') === '1',
            notes: data.get('notes') || ''
        };
    }

    async function postJson(payload) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(Object.assign({ _csrf: csrf }, payload))
        });
        const json = await response.json().catch(function () { return {}; });
        if (!response.ok || !json.ok) {
            throw new Error(json.message || 'Request failed.');
        }
        return json;
    }

    function phoneName(item) {
        return [item.brand, item.model, item.variant].filter(Boolean).join(' ') || item.model || 'Requested phone';
    }

    function aiPlaceholderImage(item) {
        return generatedPhoneImage(phoneName(item || {}));
    }

    function imageFallbackHandler(event) {
        const img = event.target;
        if (!img) return;
        const generated = img.getAttribute('data-generated-fallback') || generatedPhoneImage(img.getAttribute('alt') || 'Requested Phone');
        if (img.dataset.fallbackApplied === '1') {
            // Last-resort: use a generated SVG data URI. If the browser CSP blocks data
            // images too, keep the media box visible through CSS background rather than
            // leaving a broken external image icon.
            img.src = generated;
            img.classList.add('phone-finder-img--fallback');
            return;
        }
        img.dataset.fallbackApplied = '1';
        img.removeAttribute('data-phone-image-src');
        const fallback = rootRelativeAsset(img.getAttribute('data-fallback-image') || '') || generated;
        img.src = fallback;
        img.classList.add('phone-finder-img--fallback');
    }

    function isPlaceholderImage(src) {
        return !src || String(src).indexOf('phone-ai-placeholder.svg') !== -1 || String(src).indexOf('data:image/svg+xml') === 0;
    }

    function bindImageFallbacks(root) {
        if (!root) return;
        root.querySelectorAll('img[data-fallback-image]').forEach(function (img) {
            img.removeEventListener('error', imageFallbackHandler);
            img.addEventListener('error', imageFallbackHandler);

            const realSrc = rootRelativeAsset(img.getAttribute('data-phone-image-src') || '');
            if (!realSrc || isPlaceholderImage(realSrc) || img.dataset.realImageLoaded === '1') {
                return;
            }

            // Set the real image only after the error listener is bound. This prevents
            // fast-loading broken URLs from leaving an empty image box before JS attaches.
            img.dataset.realImageLoaded = '1';
            img.src = realSrc;
        });
    }

    function aiImageSrc(item) {
        if (item && item.request_image && item.request_image.url) return rootRelativeAsset(item.request_image.url);
        if (item && item.image_url) return rootRelativeAsset(item.image_url);
        if (item && item.image) return rootRelativeAsset(item.image);
        if (item && item.thumbnail) return rootRelativeAsset(item.thumbnail);
        return aiPlaceholderImage(item || {});
    }

    function imageFallbackSrc(item) {
        if (item && item.image_fallback_url) return rootRelativeAsset(item.image_fallback_url);
        return aiPlaceholderImage(item || {});
    }

    function renderCatalogCard(item) {
        const reasons = (item.reasons || []).map(function (reason) {
            return '<li>' + escapeHtml(reason) + '</li>';
        }).join('');
        const specs = (item.specs || []).slice(0, 4).map(function (spec) {
            return '<span>' + escapeHtml(spec.name || 'Spec') + ': ' + escapeHtml(spec.value || '') + '</span>';
        }).join('');
        const image = rootRelativeAsset(item.image || '');
        return '<article class="phone-finder-card phone-finder-card--catalog glass-panel">'
            + '<div class="phone-finder-card__media"><img src="' + escapeHtml(generatedPhoneImage(item.name || 'Phone')) + '" data-phone-image-src="' + escapeHtml(image) + '" alt="' + escapeHtml(item.name || 'Phone') + '" data-fallback-image="' + escapeHtml(generatedPhoneImage(item.name || 'Phone')) + '" data-generated-fallback="' + escapeHtml(generatedPhoneImage(item.name || 'Phone')) + '"></div>'
            + '<div class="phone-finder-card__body">'
            + '<div class="phone-finder-card__top"><span class="phone-finder-tag">Available option</span><strong>' + escapeHtml(item.score || 0) + '% match</strong></div>'
            + '<h3>' + escapeHtml(item.name || '') + '</h3>'
            + '<p>' + escapeHtml(item.short_description || '') + '</p>'
            + '<div class="phone-finder-specs">' + specs + '</div>'
            + '<ul class="phone-finder-reasons">' + reasons + '</ul>'
            + '<div class="phone-finder-card__actions">'
            + '<strong class="phone-finder-price">' + escapeHtml(item.price_formatted || '') + '</strong>'
            + '<a class="primary-btn" href="' + escapeHtml(item.url || '#') + '">View product</a>'
            + '</div></div></article>';
    }

    function renderAiCard(item, index) {
        const specs = (item.key_specs || []).slice(0, 6).map(function (spec) {
            return '<span>' + escapeHtml(spec) + '</span>';
        }).join('');
        const image = aiImageSrc(item);
        const fallbackImage = imageFallbackSrc(item);
        const name = phoneName(item);
        const generatedFallback = generatedPhoneImage(name);
        return '<article class="phone-finder-card phone-finder-card--ai glass-panel">'
            + '<div class="phone-finder-card__media phone-finder-card__media--ai"><img src="' + escapeHtml(fallbackImage || generatedFallback) + '" data-phone-image-src="' + escapeHtml(image) + '" alt="' + escapeHtml(name) + '" loading="lazy" referrerpolicy="no-referrer" data-fallback-image="' + escapeHtml(fallbackImage || generatedFallback) + '" data-generated-fallback="' + escapeHtml(generatedFallback) + '"></div>'
            + '<div class="phone-finder-card__body">'
            + '<div class="phone-finder-card__top"><span class="phone-finder-tag phone-finder-tag--ai">Possible option</span><strong>By request</strong></div>'
            + '<h3>' + escapeHtml(name) + '</h3>'
            + '<p>' + escapeHtml(item.why_it_matches || '') + '</p>'
            + '<div class="phone-finder-specs">' + specs + '</div>'
            + '<div class="phone-finder-ai-note"><span class="material-symbols-outlined" aria-hidden="true">info</span><span>' + escapeHtml(item.estimated_price_range_try || 'Price will be confirmed by our team') + '</span></div>'
            + '<div class="phone-finder-card__actions">'
            + '<span class="phone-finder-muted">Our team will confirm availability.</span>'
            + '<button class="primary-btn" type="button" data-request-ai-index="' + index + '">Request this phone</button>'
            + '</div></div></article>';
    }

    function renderAiSearchCta(ai) {
        const message = ai && ai.used && ai.message
            ? '<p>' + escapeHtml(ai.message) + '</p>'
            : '<p>The first results are available now. You can also ask us to check more suitable phones for you.</p>';
        return '<section class="phone-finder-group phone-finder-ai-cta">'
            + '<div class="phone-finder-state glass-panel">'
            + '<span class="material-symbols-outlined" aria-hidden="true">support_agent</span>'
            + '<strong>Need more options?</strong>'
            + message
            + '<button class="primary-btn" type="button" data-run-ai-search>Show more options</button>'
            + '</div></section>';
    }

    function renderResults(data) {
        setHidden(emptyState, true);
        setHidden(loadingState, true);
        setHidden(resultsWrap, false);

        const catalog = data.catalog_matches || [];
        const ai = data.ai || {};
        const aiItems = ai.recommendations || [];
        const parts = [];

        if (catalog.length) {
            parts.push('<section class="phone-finder-group"><div class="phone-finder-group__head"><span class="phone-finder-eyebrow">Available now</span><h2>Best matches for you</h2><p>Available phones that closely match your choices.</p></div>' + catalog.map(renderCatalogCard).join('') + '</section>');
        }

        if (aiItems.length) {
            parts.push('<section class="phone-finder-group"><div class="phone-finder-group__head"><span class="phone-finder-eyebrow">Possible options</span><h2>Phones our team can check for you</h2><p>Our team can confirm availability and pricing before purchase.</p></div>' + aiItems.map(renderAiCard).join('') + '</section>');
        } else if (catalog.length || (ai && ai.message)) {
            parts.push(renderAiSearchCta(ai));
        }

        if (!parts.length) {
            parts.push('<div class="phone-finder-empty glass-panel"><span class="material-symbols-outlined" aria-hidden="true">search_off</span><h2>No match found</h2><p>Try widening your budget or reducing strict requirements.</p></div>');
        }

        resultsWrap.innerHTML = parts.join('');
        bindImageFallbacks(resultsWrap);
        resultsWrap._aiItems = aiItems;
    }

    function openRequestModal(candidate, source) {
        selectedCandidate = candidate;
        selectedSource = source || 'ai';
        if (requestSummary) {
            requestSummary.textContent = 'Request: ' + [candidate.brand, candidate.model, candidate.variant].filter(Boolean).join(' ') + '. Our team will review availability and contact you.';
        }
        setHidden(modal, false);
        document.documentElement.classList.add('phone-finder-modal-open');
    }

    function closeRequestModal() {
        setHidden(modal, true);
        document.documentElement.classList.remove('phone-finder-modal-open');
    }

    if (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            lastPreferences = getPreferences();
            setHidden(emptyState, true);
            setHidden(resultsWrap, true);
            setHidden(loadingState, false);
            if (resultsWrap) resultsWrap.innerHTML = '';
            try {
                const data = await postJson({ action: 'find', preferences: lastPreferences });
                lastPreferences = data.preferences || lastPreferences;
                renderResults(data);
            } catch (error) {
                setHidden(loadingState, true);
                setHidden(resultsWrap, false);
                resultsWrap.innerHTML = '<div class="phone-finder-state glass-panel"><span class="material-symbols-outlined" aria-hidden="true">error</span><strong>Could not search phones</strong><p>' + escapeHtml(error.message) + '</p></div>';
            }
        });
    }

    if (resultsWrap) {
        resultsWrap.addEventListener('click', async function (event) {
            const aiSearchButton = event.target.closest('[data-run-ai-search]');
            if (aiSearchButton) {
                event.preventDefault();
                lastPreferences = lastPreferences || getPreferences();
                aiSearchButton.disabled = true;
                aiSearchButton.textContent = 'Looking for more options...';
                setHidden(loadingState, false);
                try {
                    const data = await postJson({ action: 'find', force_ai: true, preferences: lastPreferences });
                    lastPreferences = data.preferences || lastPreferences;
                    renderResults(data);
                } catch (error) {
                    setHidden(loadingState, true);
                    const errorBox = document.createElement('div');
                    errorBox.className = 'phone-finder-state glass-panel';
                    errorBox.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">error</span><strong>Could not show more options</strong><p>' + escapeHtml(error.message) + '</p>';
                    resultsWrap.prepend(errorBox);
                }
                return;
            }

            const button = event.target.closest('[data-request-ai-index]');
            if (!button) return;
            const index = Number(button.getAttribute('data-request-ai-index'));
            const items = resultsWrap._aiItems || [];
            if (!items[index]) return;
            openRequestModal(items[index], 'ai');
        });
    }

    document.querySelectorAll('[data-phone-request-close]').forEach(function (button) {
        button.addEventListener('click', closeRequestModal);
    });

    if (requestForm) {
        requestForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!selectedCandidate) return;
            const data = new FormData(requestForm);
            const submit = requestForm.querySelector('button[type="submit"]');
            if (submit) submit.disabled = true;
            try {
                const response = await postJson({
                    action: 'request',
                    source: selectedSource,
                    candidate: selectedCandidate,
                    preferences: lastPreferences || {},
                    customer_name: data.get('customer_name') || '',
                    customer_phone: data.get('customer_phone') || ''
                });
                closeRequestModal();
                if (resultsWrap) {
                    const success = document.createElement('div');
                    success.className = 'phone-finder-success glass-panel';
                    success.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">check_circle</span><strong>Request sent</strong><p>' + escapeHtml(response.message || 'Thanks. Our team will review it and contact you if follow-up is needed.') + '</p>';
                    resultsWrap.prepend(success);
                }
            } catch (error) {
                if (requestSummary) requestSummary.textContent = error.message;
            } finally {
                if (submit) submit.disabled = false;
            }
        });
    }
})();
