(function () {
  'use strict';

  const root = document.querySelector('.admin-console[data-admin-version][data-admin-version-url]');
  if (!root) return;

  const content = document.querySelector('[data-admin-content]');
  const sectionUrl = root.getAttribute('data-admin-section-url') || 'ajax.php';
  const initialSection = root.getAttribute('data-admin-initial-section') || 'index';
  const sidebar = document.querySelector('.admin-sidebar-panel');
  const menuButtons = Array.from(document.querySelectorAll('[data-admin-menu-toggle]'));
  const menuClosers = Array.from(document.querySelectorAll('[data-admin-menu-close]'));
  const compactStorageKey = 'phonix_admin_compact_mode';
  let adminCompactMode = false;

  function setAdminCompactMode(enabled, persist) {
    adminCompactMode = Boolean(enabled);
    document.body.classList.toggle('admin-compact-mode', adminCompactMode);
    root.classList.toggle('admin-compact-mode', adminCompactMode);
    document.querySelectorAll('[data-admin-compact-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', adminCompactMode ? 'true' : 'false');
      button.innerHTML = adminCompactMode
        ? '<span class="material-symbols-outlined">density_large</span><span>Comfort</span>'
        : '<span class="material-symbols-outlined">density_medium</span><span>Compact</span>';
    });
    if (persist !== false) {
      try { window.localStorage.setItem(compactStorageKey, adminCompactMode ? '1' : '0'); } catch (error) { /* ignore localStorage errors */ }
    }
  }

  try {
    setAdminCompactMode(window.localStorage.getItem(compactStorageKey) === '1', false);
  } catch (error) {
    setAdminCompactMode(false, false);
  }

  document.addEventListener('toggle', function (event) {
    const menu = event.target && event.target.closest ? event.target.closest('.admin-more-menu, .admin-side-more, .published-row-menu, .published-more-views, .published-advanced-filters') : null;
    if (!menu || !menu.open) return;
    document.querySelectorAll('.admin-more-menu[open], .admin-side-more[open], .published-row-menu[open], .published-more-views[open], .published-advanced-filters[open]').forEach((other) => {
      if (other !== menu && !other.contains(menu)) other.open = false;
    });
  }, true);

  document.addEventListener('click', function (event) {
    if (event.target && event.target.closest && event.target.closest('.admin-more-menu, .admin-side-more, .published-row-menu, .published-more-views, .published-advanced-filters')) return;
    document.querySelectorAll('.admin-more-menu[open], .admin-side-more[open], .published-row-menu[open], .published-more-views[open], .published-advanced-filters[open]').forEach((menu) => {
      menu.open = false;
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.admin-more-menu[open], .admin-side-more[open], .published-row-menu[open], .published-more-views[open], .published-advanced-filters[open]').forEach((menu) => {
      menu.open = false;
    });
  });


  document.addEventListener('click', function (event) {
    const toggle = event.target && event.target.closest ? event.target.closest('[data-admin-compact-toggle]') : null;
    if (!toggle) return;
    event.preventDefault();
    setAdminCompactMode(!adminCompactMode, true);
  });

  let currentSection = initialSection;
  let currentParams = new URLSearchParams(window.location.search);
  if (!currentParams.get('section')) currentParams.set('section', initialSection);

  function activeDirtyProductEditor() {
    return content ? content.querySelector('[data-product-editor][data-product-dirty="1"]') : null;
  }

  function confirmLeavingProductEditor() {
    const dirtyEditor = activeDirtyProductEditor();
    if (!dirtyEditor) return true;
    const ok = window.confirm('You have unsaved product changes. Leave this editor without saving?');
    if (ok) dirtyEditor.dataset.productDirty = '0';
    return ok;
  }

  function normalizeSection(section) {
    const allowed = ['index', 'notifications', 'system_health', 'maintenance_tools', 'email', 'homepage', 'deals', 'seo', 'products', 'product_edit', 'product_requests', 'inventory', 'media', 'categories', 'brands', 'orders', 'customers', 'coupons', 'shipping_payments', 'support', 'reports', 'exports', 'settings', 'admin_users'];
    section = String(section || 'index').replace(/[^a-z0-9_-]/gi, '') || 'index';
    return allowed.indexOf(section) === -1 ? 'index' : section;
  }

  function ajaxUrl(section, params) {
    const url = new URL(sectionUrl, window.location.href);
    const query = new URLSearchParams(params || '');
    url.search = '';
    url.searchParams.set('section', normalizeSection(section));
    query.forEach((value, key) => {
      if (key !== 'section') url.searchParams.set(key, value);
    });
    url.searchParams.set('_', Date.now().toString());
    return url;
  }

  function publicUrl(section, params) {
    const url = new URL('index.php', window.location.href);
    const query = new URLSearchParams(params || '');
    section = normalizeSection(section);
    if (section !== 'index') url.searchParams.set('section', section);
    query.forEach((value, key) => {
      if (key !== 'section' && key !== '_') url.searchParams.set(key, value);
    });
    return url;
  }

  function setActive(section) {
    section = normalizeSection(section);
    document.querySelectorAll('[data-admin-section-link]').forEach((link) => {
      link.classList.toggle('is-active', link.getAttribute('data-admin-section-link') === section);
    });
    document.querySelectorAll('.admin-side-more').forEach((menu) => {
      const hasActive = Boolean(menu.querySelector('[data-admin-section-link="' + section + '"]'));
      menu.classList.toggle('is-active', hasActive);
      if (hasActive) menu.open = true;
    });
  }

  function setMobileMenuOpen(open) {
    document.body.classList.toggle('admin-menu-open', Boolean(open));
    if (sidebar) sidebar.classList.toggle('is-open', Boolean(open));
    menuButtons.forEach((button) => {
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    menuClosers.forEach((item) => {
      if (item.classList && item.classList.contains('admin-mobile-overlay')) {
        item.hidden = !open;
      }
    });
  }

  function slugifyProduct(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\u0600-\u06ff]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 180);
  }

  function moneyForPreview(value, currency) {
    const amount = Number(value || 0);
    const safeAmount = Number.isFinite(amount) ? amount : 0;
    const code = String(currency || 'TRY').toUpperCase();
    if (code === 'TRY') {
      return '₺' + safeAmount.toLocaleString('tr-TR', { maximumFractionDigits: 0 });
    }
    return safeAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + code;
  }

  function mediaPublicPreview(path) {
    path = String(path || '').trim();
    if (!path) return '';
    if (/^(https?:|blob:|data:)?\/\//i.test(path) || /^blob:/i.test(path) || /^data:/i.test(path) || path.indexOf('/') === 0) return path;
    return '../' + path.replace(/^\.\//, '');
  }

  function productEditorFileUrls(input) {
    if (!input || !input.files || !input.files.length) return [];
    const files = Array.from(input.files).filter((file) => file && file.type && file.type.indexOf('image/') === 0);
    const signature = files.map((file) => [file.name, file.size, file.lastModified].join(':')).join('|');
    if (input.__phonixPreviewSignature !== signature) {
      if (Array.isArray(input.__phonixPreviewUrls)) {
        input.__phonixPreviewUrls.forEach((url) => {
          try { URL.revokeObjectURL(url); } catch (error) { /* ignore preview cleanup errors */ }
        });
      }
      input.__phonixPreviewSignature = signature;
      input.__phonixPreviewUrls = files.map((file) => URL.createObjectURL(file));
    }
    return Array.isArray(input.__phonixPreviewUrls) ? input.__phonixPreviewUrls : [];
  }

  function productEditorFirstFileUrl(input) {
    const urls = productEditorFileUrls(input);
    return urls.length ? urls[0] : '';
  }

  function productEditorFileNames(input) {
    if (!input || !input.files || !input.files.length) return [];
    return Array.from(input.files).map((file) => file.name || 'Image');
  }

  function benefitRow(value) {
    const row = document.createElement('div');
    row.className = 'admin-benefit-row';
    row.setAttribute('data-benefit-row', '');
    row.innerHTML = '<input name="benefits[]" placeholder="Example: Fast charging and all-day battery life" aria-label="Product benefit"><div class="admin-benefit-actions"><details class="admin-more-menu admin-row-more"><summary aria-label="Benefit row actions"><span class="material-symbols-outlined">more_horiz</span></summary><div class="admin-more-panel"><button type="button" data-benefit-move="up">Move up</button><button type="button" data-benefit-move="down">Move down</button><button type="button" class="danger" data-benefit-remove>Remove</button></div></details></div>';
    row.querySelector('[name="benefits[]"]').value = value || '';
    return row;
  }

  function getProductBenefits(editor) {
    const seen = new Set();
    const benefits = [];
    editor.querySelectorAll('[name="benefits[]"]').forEach((input) => {
      const value = String(input.value || '').trim().replace(/^[-•*\s]+/u, '');
      const key = value.toLowerCase();
      if (value && !seen.has(key)) {
        seen.add(key);
        benefits.push(value);
      }
    });
    const bulk = editor.querySelector('[name="benefits_text"]');
    if (bulk && bulk.value.trim()) {
      bulk.value.split(/\r?\n/).forEach((line) => {
        const value = String(line || '').trim().replace(/^[-•*\s]+/u, '');
        const key = value.toLowerCase();
        if (value && !seen.has(key)) {
          seen.add(key);
          benefits.push(value);
        }
      });
    }
    return benefits;
  }

  function getProductGalleryPreviewItems(editor) {
    const seen = new Set();
    const items = [];
    const add = (url, label) => {
      url = String(url || '').trim();
      if (!url || seen.has(url)) return;
      seen.add(url);
      items.push({ url, label: label || 'Gallery image' });
    };

    add(getProductImagePreview(editor), 'Main image');
    editor.querySelectorAll('[data-gallery-card]').forEach((card) => add(card.getAttribute('data-image-path') || '', 'Current gallery'));
    editor.querySelectorAll('[data-media-path]').forEach((tile) => {
      const selected = tile.querySelector('input[type="checkbox"]:checked, input[type="radio"]:checked');
      if (selected) add(tile.getAttribute('data-media-path') || '', 'Media Library');
    });
    const manual = editor.querySelector('[name="gallery_paths"]');
    if (manual && manual.value.trim()) {
      manual.value.split(/\r?\n/).forEach((line) => add(line, 'Manual path'));
    }
    const upload = editor.querySelector('[data-gallery-upload]');
    productEditorFileUrls(upload).forEach((url, index) => add(url, productEditorFileNames(upload)[index] || 'Uploaded gallery'));
    return items;
  }

  function renderProductUploadPreviews(editor) {
    const mainInput = editor.querySelector('[data-main-image-upload]');
    const mainPreview = editor.querySelector('[data-main-upload-preview]');
    if (mainPreview) {
      const mainUrl = productEditorFirstFileUrl(mainInput);
      const mainName = productEditorFileNames(mainInput)[0] || '';
      mainPreview.innerHTML = '';
      mainPreview.hidden = !mainUrl;
      if (mainUrl) {
        const img = document.createElement('img');
        img.src = mainUrl;
        img.alt = mainName || 'Selected main image';
        const label = document.createElement('small');
        label.textContent = mainName || 'Selected main image';
        mainPreview.append(img, label);
      }
    }

    const galleryInput = editor.querySelector('[data-gallery-upload]');
    const galleryPreview = editor.querySelector('[data-gallery-upload-preview]');
    if (galleryPreview) {
      const urls = productEditorFileUrls(galleryInput);
      const names = productEditorFileNames(galleryInput);
      galleryPreview.innerHTML = '';
      galleryPreview.hidden = urls.length === 0;
      urls.slice(0, 8).forEach((url, index) => {
        const item = document.createElement('span');
        const img = document.createElement('img');
        img.src = url;
        img.alt = names[index] || 'Selected gallery image';
        const text = document.createElement('small');
        text.textContent = names[index] || ('Image ' + (index + 1));
        item.append(img, text);
        galleryPreview.appendChild(item);
      });
      if (urls.length > 8) {
        const more = document.createElement('em');
        more.textContent = '+' + (urls.length - 8) + ' more';
        galleryPreview.appendChild(more);
      }
    }
  }

  function specRow(name, value) {
    const row = document.createElement('div');
    row.className = 'admin-spec-row';
    row.setAttribute('data-spec-row', '');
    row.innerHTML = '<input name="spec_names[]" placeholder="Specification name" aria-label="Specification name"><input name="spec_values[]" placeholder="Specification value" aria-label="Specification value"><div class="admin-spec-actions"><details class="admin-more-menu admin-row-more"><summary aria-label="Spec row actions"><span class="material-symbols-outlined">more_horiz</span></summary><div class="admin-more-panel"><button type="button" data-spec-move="up">Move up</button><button type="button" data-spec-move="down">Move down</button><button type="button" class="danger" data-spec-remove>Remove</button></div></details></div>';
    row.querySelector('[name="spec_names[]"]').value = name || '';
    row.querySelector('[name="spec_values[]"]').value = value || '';
    return row;
  }

  function variantRow(type, value) {
    const row = document.createElement('div');
    row.className = 'admin-variant-row';
    row.setAttribute('data-variant-row', '');
    row.innerHTML = '<input name="variant_types[]" placeholder="Option type, e.g. storage or color" aria-label="Option type" list="variant-type-options"><input name="variant_values[]" placeholder="Option value, e.g. 256GB or Midnight Black" aria-label="Option value"><div class="admin-variant-actions"><details class="admin-more-menu admin-row-more"><summary aria-label="Option row actions"><span class="material-symbols-outlined">more_horiz</span></summary><div class="admin-more-panel"><button type="button" data-variant-move="up">Move up</button><button type="button" data-variant-move="down">Move down</button><button type="button" class="danger" data-variant-remove>Remove</button></div></details></div>';
    row.querySelector('[name="variant_types[]"]').value = type || '';
    row.querySelector('[name="variant_values[]"]').value = value || '';
    return row;
  }

  function getProductImagePreview(editor) {
    const mainUpload = editor.querySelector('[data-main-image-upload]');
    const uploadedPreview = productEditorFirstFileUrl(mainUpload);
    if (uploadedPreview) return uploadedPreview;
    const primaryPath = editor.querySelector('input[name="primary_image_path"]:checked');
    if (primaryPath && primaryPath.value && primaryPath.value !== '__typed') return primaryPath.value;
    const primaryMedia = editor.querySelector('input[name="primary_media_id"]:checked');
    if (primaryMedia) {
      const tile = primaryMedia.closest('[data-media-path]');
      if (tile) return tile.getAttribute('data-media-path') || '';
    }
    const typed = editor.querySelector('[data-preview-image-input]');
    return typed ? typed.value : '';
  }

  function getProductSpecPairs(editor) {
    const rows = Array.from(editor.querySelectorAll('[data-spec-row]'));
    const specs = [];
    rows.forEach((row) => {
      const name = row.querySelector('[name="spec_names[]"]');
      const value = row.querySelector('[name="spec_values[]"]');
      const n = name ? name.value.trim() : '';
      const v = value ? value.value.trim() : '';
      if (n && v) specs.push([n, v]);
    });
    return specs;
  }

  function getProductVariantPairs(editor) {
    const rows = Array.from(editor.querySelectorAll('[data-variant-row]'));
    const variants = [];
    rows.forEach((row) => {
      const type = row.querySelector('[name="variant_types[]"]');
      const value = row.querySelector('[name="variant_values[]"]');
      const t = type ? type.value.trim() : '';
      const v = value ? value.value.trim() : '';
      if (t && v) variants.push([t, v]);
    });
    return variants;
  }

  function getProductVariantDuplicateKeys(editor) {
    const seen = new Set();
    const duplicates = new Set();
    getProductVariantPairs(editor).forEach((pair) => {
      const key = pair[0].toLowerCase() + '|' + pair[1].toLowerCase();
      if (seen.has(key)) duplicates.add(pair[0] + ': ' + pair[1]);
      seen.add(key);
    });
    return Array.from(duplicates);
  }

  function getProductSpecDuplicateNames(editor) {
    const names = Array.from(editor.querySelectorAll('[name="spec_names[]"]'))
      .map((input) => input.value.trim().toLowerCase())
      .filter(Boolean);
    const seen = new Set();
    const duplicates = new Set();
    names.forEach((name) => {
      if (seen.has(name)) duplicates.add(name);
      seen.add(name);
    });
    return Array.from(duplicates);
  }

  function parseProductSpecLines(raw) {
    return String(raw || '').split(/\r?\n/).map((line) => {
      const cleaned = line.trim().replace(/^[-•*\s]+/u, '');
      if (!cleaned) return null;
      const parts = cleaned.split(/\s*(?:\||:|=|–|—)\s*/u);
      if (parts.length < 2) return null;
      const name = parts.shift().trim();
      const value = parts.join(': ').trim();
      return name && value ? [name, value] : null;
    }).filter(Boolean);
  }

  function parseProductBenefitLines(raw) {
    return String(raw || '').split(/\r?\n/).map((line) => line.trim().replace(/^[-•*\s]+/u, '')).filter(Boolean);
  }

  function parseProductVariantLines(raw) {
    const pairs = [];
    String(raw || '').split(/\r?\n/).forEach((line) => {
      const cleaned = line.trim().replace(/^[-•*\s]+/u, '');
      if (!cleaned) return;
      const parts = cleaned.split(/\s*(?:\||:|=|–|—)\s*/u);
      if (parts.length < 2) return;
      const type = parts.shift().trim();
      const valuesText = parts.join(': ').trim();
      if (!type || !valuesText) return;
      valuesText.split(/\s*,\s*/u).forEach((value) => {
        const optionValue = value.trim();
        if (optionValue) pairs.push([type, optionValue]);
      });
    });
    return pairs;
  }

  function setProductEditorFeedback(editor, selector, message, type) {
    const slot = editor.querySelector(selector);
    if (!slot) return;
    slot.textContent = message || '';
    slot.classList.toggle('is-warning', type === 'warning');
    slot.classList.toggle('is-success', type === 'success');
  }

  function cleanProductSpecRows(editor) {
    const rowsWrap = editor.querySelector('[data-spec-rows]');
    if (!rowsWrap) return { kept: 0, removed: 0 };
    const seen = new Set();
    let removed = 0;
    Array.from(rowsWrap.querySelectorAll('[data-spec-row]')).forEach((row) => {
      const name = row.querySelector('[name="spec_names[]"]');
      const value = row.querySelector('[name="spec_values[]"]');
      const n = name ? name.value.trim() : '';
      const v = value ? value.value.trim() : '';
      const key = n.toLowerCase();
      if (!n && !v) {
        row.remove();
        removed += 1;
        return;
      }
      if (key && seen.has(key)) {
        row.remove();
        removed += 1;
        return;
      }
      if (name) name.value = n;
      if (value) value.value = v;
      if (key) seen.add(key);
    });
    if (!rowsWrap.querySelector('[data-spec-row]')) rowsWrap.appendChild(specRow('', ''));
    return { kept: rowsWrap.querySelectorAll('[data-spec-row]').length, removed };
  }

  function cleanProductBenefitRows(editor) {
    const rowsWrap = editor.querySelector('[data-benefit-rows]');
    if (!rowsWrap) return { kept: 0, removed: 0 };
    const seen = new Set();
    let removed = 0;
    Array.from(rowsWrap.querySelectorAll('[data-benefit-row]')).forEach((row) => {
      const input = row.querySelector('[name="benefits[]"]');
      const text = input ? String(input.value || '').trim().replace(/^[-•*\s]+/u, '') : '';
      const key = text.toLowerCase();
      if (!text) {
        row.remove();
        removed += 1;
        return;
      }
      if (key && seen.has(key)) {
        row.remove();
        removed += 1;
        return;
      }
      if (input) input.value = text;
      seen.add(key);
    });
    if (!rowsWrap.querySelector('[data-benefit-row]')) rowsWrap.appendChild(benefitRow(''));
    return { kept: rowsWrap.querySelectorAll('[data-benefit-row]').length, removed };
  }

  function cleanProductVariantRows(editor) {
    const rowsWrap = editor.querySelector('[data-variant-rows]');
    if (!rowsWrap) return { kept: 0, removed: 0 };
    const seen = new Set();
    let removed = 0;
    Array.from(rowsWrap.querySelectorAll('[data-variant-row]')).forEach((row) => {
      const type = row.querySelector('[name="variant_types[]"]');
      const value = row.querySelector('[name="variant_values[]"]');
      const t = type ? String(type.value || '').trim().toLowerCase().replace(/\s+/g, '_') : '';
      const v = value ? String(value.value || '').trim() : '';
      const key = t + '|' + v.toLowerCase();
      if (!t && !v) {
        row.remove();
        removed += 1;
        return;
      }
      if (t && v && seen.has(key)) {
        row.remove();
        removed += 1;
        return;
      }
      if (type) type.value = t;
      if (value) value.value = v;
      if (t && v) seen.add(key);
    });
    if (!rowsWrap.querySelector('[data-variant-row]')) rowsWrap.appendChild(variantRow('', ''));
    return { kept: rowsWrap.querySelectorAll('[data-variant-row]').length, removed };
  }

  function updateProductMediaTools(editor) {
    const filter = editor.querySelector('[data-media-filter]');
    const selectedOnly = editor.querySelector('[data-media-selected-only]');
    const tiles = Array.from(editor.querySelectorAll('[data-media-path]'));
    const needle = filter ? filter.value.trim().toLowerCase() : '';
    let selectedCount = 0;
    let visibleCount = 0;
    tiles.forEach((tile) => {
      const isSelected = Boolean(tile.querySelector('input[type="checkbox"]:checked, input[type="radio"]:checked'));
      if (isSelected) selectedCount += 1;
      const text = (tile.getAttribute('data-media-search-text') || tile.getAttribute('data-media-path') || tile.textContent || '').toLowerCase();
      const matchesSearch = !needle || text.indexOf(needle) !== -1;
      const matchesSelected = !selectedOnly || !selectedOnly.checked || isSelected;
      const visible = matchesSearch && matchesSelected;
      tile.hidden = !visible;
      if (visible) visibleCount += 1;
    });
    const count = editor.querySelector('[data-media-selected-count]');
    if (count) count.textContent = String(selectedCount);
    const grid = editor.querySelector('[data-media-library-grid]');
    if (grid) grid.classList.toggle('is-filtered-empty', tiles.length > 0 && visibleCount === 0);
  }

  function setReadyItem(editor, key, ok) {
    const item = editor.querySelector('[data-ready-item="' + key + '"]');
    if (!item) return;
    item.classList.toggle('is-ok', Boolean(ok));
    item.classList.toggle('is-missing', !ok);
    const icon = item.querySelector('.material-symbols-outlined');
    if (icon) icon.textContent = ok ? 'check_circle' : 'radio_button_unchecked';
  }

  function updateProductPreview(editor) {
    const currency = editor.getAttribute('data-currency') || 'TRY';
    const name = editor.querySelector('[data-preview-name]');
    const brand = editor.querySelector('[data-preview-brand]');
    const type = editor.querySelector('[data-product-type-select]');
    const badge = editor.querySelector('[data-preview-badge]');
    const status = editor.querySelector('[data-preview-status]');
    const price = editor.querySelector('[data-preview-price]');
    const discount = editor.querySelector('[data-preview-discount-input]');
    const stock = editor.querySelector('[data-preview-stock]');
    const shortText = editor.querySelector('[data-preview-short]');
    const category = editor.querySelector('[data-ready-category]');
    const specs = getProductSpecPairs(editor);
    const variants = getProductVariantPairs(editor);
    const benefits = getProductBenefits(editor);

    const titleOut = editor.querySelector('[data-preview-title]');
    if (titleOut) titleOut.textContent = (name && name.value.trim()) || 'Untitled product';
    const metaOut = editor.querySelector('[data-preview-meta]');
    if (metaOut) metaOut.textContent = (brand && brand.value.trim()) || (type && type.options[type.selectedIndex] ? type.options[type.selectedIndex].textContent : 'General product');
    const badgeOut = editor.querySelector('[data-preview-badge-out]');
    if (badgeOut) {
      const value = badge ? badge.value.trim() : '';
      badgeOut.textContent = value;
      badgeOut.hidden = !value;
    }
    const shortOut = editor.querySelector('[data-preview-short-out]');
    if (shortOut) shortOut.textContent = (shortText && shortText.value.trim()) || 'Short product description will appear here.';
    const priceOut = editor.querySelector('[data-preview-price-out]');
    if (priceOut) priceOut.textContent = moneyForPreview(price ? price.value : 0, currency);
    const compareOut = editor.querySelector('[data-preview-compare-out]');
    const currentPrice = Number(price ? price.value : 0);
    const discountPercent = Number(discount && discount.value.trim() !== '' ? discount.value : 0);
    const validDiscount = Number.isFinite(discountPercent) && discountPercent > 0 && discountPercent <= 95 && currentPrice > 0;
    const comparePrice = validDiscount ? currentPrice / (1 - (discountPercent / 100)) : 0;
    const discountLabel = Number.isInteger(discountPercent) ? String(discountPercent) : discountPercent.toFixed(2).replace(/\.?0+$/, '');
    if (compareOut) {
      compareOut.textContent = moneyForPreview(comparePrice, currency);
      compareOut.hidden = !validDiscount;
    }
    const discountOut = editor.querySelector('[data-preview-discount]');
    if (discountOut) {
      if (validDiscount) {
        discountOut.textContent = discountLabel + '% off';
        discountOut.hidden = false;
      } else {
        discountOut.hidden = true;
        discountOut.textContent = '';
      }
    }

    const warning = editor.querySelector('[data-price-warning]');
    if (warning) {
      if (discount && discount.value.trim() !== '' && (!Number.isFinite(discountPercent) || discountPercent < 0 || discountPercent > 95)) {
        warning.textContent = 'Discount must be between 0 and 95%.';
        warning.classList.add('is-warning');
      } else if (validDiscount) {
        warning.textContent = discountLabel + '% discount will be shown in preview.';
        warning.classList.remove('is-warning');
      } else {
        warning.textContent = 'Write 0 or leave empty to keep the product at regular price.';
        warning.classList.remove('is-warning');
      }
    }

    const imgPath = getProductImagePreview(editor);
    const img = editor.querySelector('[data-preview-img]');
    const imgEmpty = editor.querySelector('[data-preview-img-empty]');
    if (img) {
      const wrap = img.closest('.admin-product-preview-image');
      if (wrap) wrap.classList.remove('is-broken');
      img.onload = function () { if (wrap) wrap.classList.remove('is-broken'); };
      img.onerror = function () { if (wrap && imgPath) wrap.classList.add('is-broken'); };
      img.src = mediaPublicPreview(imgPath);
      img.hidden = !imgPath;
    }
    if (imgEmpty) imgEmpty.hidden = Boolean(imgPath);

    const previewGallery = editor.querySelector('[data-preview-gallery]');
    if (previewGallery) {
      previewGallery.innerHTML = '';
      const galleryItems = getProductGalleryPreviewItems(editor).slice(0, 6);
      galleryItems.forEach((item) => {
        const thumb = document.createElement('span');
        const thumbImg = document.createElement('img');
        thumbImg.src = mediaPublicPreview(item.url);
        thumbImg.alt = item.label || 'Product gallery preview';
        thumb.appendChild(thumbImg);
        previewGallery.appendChild(thumb);
      });
      previewGallery.hidden = galleryItems.length <= 1;
    }

    const optionsOut = editor.querySelector('[data-preview-options]');
    if (optionsOut) {
      optionsOut.innerHTML = '';
      const grouped = {};
      variants.forEach((pair) => {
        const key = pair[0];
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(pair[1]);
      });
      Object.keys(grouped).slice(0, 3).forEach((key) => {
        const line = document.createElement('span');
        line.textContent = key + ': ' + grouped[key].slice(0, 4).join(', ');
        optionsOut.appendChild(line);
      });
      optionsOut.hidden = Object.keys(grouped).length === 0;
    }

    const specOut = editor.querySelector('[data-preview-specs]');

    const benefitsOut = editor.querySelector('[data-preview-benefits]');
    if (benefitsOut) {
      benefitsOut.innerHTML = '';
      benefits.slice(0, 4).forEach((benefit) => {
        const item = document.createElement('span');
        item.textContent = benefit;
        benefitsOut.appendChild(item);
      });
      benefitsOut.hidden = benefits.length === 0;
    }
    if (specOut) {
      specOut.innerHTML = '';
      specs.slice(0, 3).forEach((pair) => {
        const item = document.createElement('span');
        item.textContent = pair[0] + ': ' + pair[1];
        specOut.appendChild(item);
      });
    }

    const numericStock = Number(stock ? stock.value : 0);
    const button = editor.querySelector('[data-preview-button]');
    const selectedStatus = status ? status.value : 'active';
    if (button) {
      const disabled = selectedStatus === 'draft' || selectedStatus === 'archived' || selectedStatus === 'out_of_stock' || numericStock <= 0;
      button.textContent = selectedStatus === 'draft' || selectedStatus === 'archived' ? 'Unavailable' : (numericStock <= 0 || selectedStatus === 'out_of_stock' ? 'Sold out' : 'Add to cart');
      button.classList.toggle('is-disabled-preview', disabled);
    }
    const stockHint = editor.querySelector('[data-stock-hint]');
    if (stockHint) {
      stockHint.textContent = numericStock <= 0 ? 'Stock is zero; visitors will see a sold-out state.' : 'Stock controls whether the visitor can buy this product.';
      stockHint.classList.toggle('is-warning', numericStock <= 0 && selectedStatus === 'active');
    }

    setReadyItem(editor, 'name', Boolean(name && name.value.trim()));
    setReadyItem(editor, 'price', currentPrice > 0);
    setReadyItem(editor, 'image', Boolean(imgPath));
    setReadyItem(editor, 'description', Boolean(shortText && shortText.value.trim()));
    setReadyItem(editor, 'category', Boolean(category && Number(category.value) > 0));
    setReadyItem(editor, 'specs', specs.length > 0);
    setReadyItem(editor, 'benefits', benefits.length > 0);
    setReadyItem(editor, 'storefront', selectedStatus === 'active' || selectedStatus === 'out_of_stock');

    const items = Array.from(editor.querySelectorAll('[data-ready-item]'));
    const okCount = items.filter((item) => item.classList.contains('is-ok')).length;
    const readyTitle = editor.querySelector('[data-ready-title]');
    if (readyTitle) readyTitle.textContent = okCount === items.length ? 'Ready to publish' : (okCount >= 4 ? 'Almost ready' : 'Needs work');
    const progressWrap = editor.querySelector('[data-ready-progress-wrap]');
    if (progressWrap) {
      progressWrap.className = progressWrap.className.replace(/\badmin-ready-score-\d+\b/g, '').trim();
      progressWrap.classList.add('admin-ready-score-' + okCount);
    }
    const shortCount = editor.querySelector('[data-short-count]');
    if (shortCount && shortText) shortCount.textContent = String(shortText.value.length);
    const fullText = editor.querySelector('[data-full-description]');
    const fullCount = editor.querySelector('[data-full-count]');
    if (fullText && fullCount) fullCount.textContent = String(fullText.value.length);
    const specCount = editor.querySelector('[data-spec-filled-count]');
    if (specCount) specCount.textContent = String(specs.length);
    const variantCount = editor.querySelector('[data-variant-filled-count]');
    if (variantCount) variantCount.textContent = String(variants.length);
    const benefitCount = editor.querySelector('[data-benefit-filled-count]');
    if (benefitCount) benefitCount.textContent = String(benefits.length);
    const finalOptions = editor.querySelector('[data-final-review-options]');
    if (finalOptions) finalOptions.textContent = String(variants.length);
    const finalGallery = editor.querySelector('[data-final-review-gallery]');
    if (finalGallery) finalGallery.textContent = String(getProductGalleryPreviewItems(editor).length);
    const finalBenefits = editor.querySelector('[data-final-review-benefits]');
    if (finalBenefits) finalBenefits.textContent = String(benefits.length);
    updateProductStatusAdvisor(editor);
    updateGalleryEmptyState(editor);
    renderProductUploadPreviews(editor);
    updateProductMediaTools(editor);
    updateProductValidation(editor, 'live');
  }

  function setProductFieldError(editor, key, message) {
    const slot = editor.querySelector('[data-field-error="' + key + '"]');
    if (slot) slot.textContent = message || '';
    const field = slot ? slot.closest('.admin-field') : null;
    if (field) field.classList.toggle('has-error', Boolean(message));
  }

  function productFieldTab(editor, selector, fallback) {
    const node = editor.querySelector(selector);
    const panel = node && node.closest ? node.closest('[data-editor-tab]') : null;
    return panel ? panel.getAttribute('data-editor-tab') || fallback : fallback;
  }

  function updateProductValidation(editor, mode) {
    const name = editor.querySelector('[name="name"]');
    const slug = editor.querySelector('[name="slug"]');
    const price = editor.querySelector('[name="price"]');
    const discount = editor.querySelector('[name="discount_percent"]');
    const stock = editor.querySelector('[name="stock"]');
    const image = editor.querySelector('[name="image"]');
    const category = editor.querySelector('[name="category_id"]');
    const shortText = editor.querySelector('[name="short_description"]');
    const status = editor.querySelector('[name="product_status"]');
    const specs = getProductSpecPairs(editor);
    const variants = getProductVariantPairs(editor);
    const issues = [];
    const errors = [];
    const addIssue = (key, message, tab, blocking) => {
      issues.push({ key, message, tab: tab || 'overview', blocking: Boolean(blocking) });
      if (blocking) errors.push(key);
      setProductFieldError(editor, key, blocking ? message : '');
    };

    ['name', 'slug', 'price', 'discount_percent', 'stock', 'image', 'short_description', 'specs', 'variants'].forEach((key) => setProductFieldError(editor, key, ''));

    const nameValue = name ? name.value.trim() : '';
    if (!nameValue) addIssue('name', 'Product name is required.', 'overview', true);
    const slugValue = slug ? slug.value.trim() : '';
    if (slugValue && !/^[a-z0-9\u0600-\u06ff][a-z0-9\u0600-\u06ff-]*[a-z0-9\u0600-\u06ff]$/i.test(slugValue)) {
      addIssue('slug', 'Slug should contain letters, numbers, and dashes only.', 'overview', true);
    }

    const currentPrice = Number(price ? price.value : 0);
    const discountPercent = Number(discount && discount.value.trim() !== '' ? discount.value : 0);
    if (!Number.isFinite(currentPrice) || currentPrice <= 0) addIssue('price', 'Price must be greater than zero.', 'pricing', true);
    if (discount && discount.value.trim() !== '' && (!Number.isFinite(discountPercent) || discountPercent < 0 || discountPercent > 95)) addIssue('discount_percent', 'Discount must be between 0 and 95%.', 'pricing', true);
    const stockValue = Number(stock ? stock.value : 0);
    if (!Number.isFinite(stockValue) || stockValue < 0) addIssue('stock', 'Stock cannot be negative.', 'pricing', true);

    const imgPath = getProductImagePreview(editor);
    if (!imgPath) issues.push({ key: 'image', message: 'Main image is missing; product page will look incomplete.', tab: 'media', blocking: false });
    if (category && Number(category.value || 0) <= 0) issues.push({ key: 'category', message: 'Category is missing; storefront filtering will be weaker.', tab: 'overview', blocking: false });
    if (!shortText || !shortText.value.trim()) issues.push({ key: 'short_description', message: 'Short description is empty; product intro will be weak.', tab: 'descriptions', blocking: false });
    if (!specs.length) issues.push({ key: 'specs', message: 'No filled specifications; product page specs section will be empty.', tab: 'specs', blocking: false });
    const halfFilledSpec = Array.from(editor.querySelectorAll('[data-spec-row]')).some((row) => {
      const name = row.querySelector('[name="spec_names[]"]');
      const value = row.querySelector('[name="spec_values[]"]');
      const hasName = Boolean(name && name.value.trim());
      const hasValue = Boolean(value && value.value.trim());
      return hasName !== hasValue;
    });
    if (halfFilledSpec) issues.push({ key: 'specs', message: 'Some specification rows are incomplete and will not appear on the product page.', tab: 'specs', blocking: false });
    const duplicateSpecs = getProductSpecDuplicateNames(editor);
    if (duplicateSpecs.length) addIssue('specs', 'Duplicate specification names found; keep each visible product-page spec unique.', 'specs', true);
    const duplicateVariants = getProductVariantDuplicateKeys(editor);
    if (duplicateVariants.length) addIssue('variants', 'Duplicate product options found; each option type/value pair should be unique.', 'options', true);
    const halfFilledVariant = Array.from(editor.querySelectorAll('[data-variant-row]')).some((row) => {
      const type = row.querySelector('[name="variant_types[]"]');
      const value = row.querySelector('[name="variant_values[]"]');
      const hasType = Boolean(type && type.value.trim());
      const hasValue = Boolean(value && value.value.trim());
      return hasType !== hasValue;
    });
    if (halfFilledVariant) addIssue('variants', 'Each product option row needs both a type and a value.', 'options', true);
    if (variants.length > 0) {
      const groups = new Set(variants.map((pair) => pair[0].toLowerCase()));
      if (groups.size > 3) issues.push({ key: 'variants', message: 'More than three option groups can make the product page selector crowded.', tab: 'options', blocking: false });
    }
    if (status && (status.value === 'draft' || status.value === 'archived')) issues.push({ key: 'storefront', message: 'This product is hidden from visitors because its status is ' + status.options[status.selectedIndex].textContent + '.', tab: 'overview', blocking: false });

    const byTab = {};
    issues.forEach((issue) => { byTab[issue.tab] = (byTab[issue.tab] || 0) + 1; });
    editor.querySelectorAll('[data-tab-issue-count]').forEach((badge) => {
      const count = byTab[badge.getAttribute('data-tab-issue-count')] || 0;
      badge.textContent = String(count);
      badge.hidden = count === 0;
    });

    const summary = editor.querySelector('[data-product-validation-summary]');
    const list = editor.querySelector('[data-product-validation-list]');
    if (summary && list) {
      list.innerHTML = '';
      issues.filter((issue) => issue.blocking).forEach((issue) => {
        const item = document.createElement('li');
        item.className = 'is-error';
        item.textContent = issue.message;
        item.addEventListener('click', () => {
          const tab = editor.querySelector('[data-product-tab="' + issue.tab + '"]');
          if (tab) tab.click();
        });
        list.appendChild(item);
      });
      summary.hidden = errors.length === 0;
      summary.classList.toggle('has-blocking-errors', errors.length > 0);
    }

    const saveButton = editor.querySelector('[data-product-save-button]');
    if (saveButton) saveButton.classList.toggle('has-validation-errors', errors.length > 0);
    const help = editor.querySelector('[data-save-help]');
    if (help) {
      help.textContent = errors.length ? 'Required fields need fixes.' : (issues.length ? 'Warnings are shown quietly in the checklist.' : 'Product page fields look complete.');
      help.classList.toggle('is-warning', errors.length > 0);
    }
    if (mode === 'submit' && errors.length) {
      const first = issues.find((issue) => issue.blocking) || issues[0];
      if (first) {
        const tab = editor.querySelector('[data-product-tab="' + first.tab + '"]');
        if (tab) tab.click();
      }
      if (summary) summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return false;
    }
    return true;
  }

  function updateGalleryEmptyState(editor) {
    const empty = editor.querySelector('[data-gallery-empty]');
    const list = editor.querySelector('[data-gallery-list]');
    if (empty) empty.hidden = Boolean(list && list.querySelector('[data-gallery-card]'));
  }

  function updateProductStatusAdvisor(editor) {
    const wrap = editor.querySelector('[data-product-status-advisor]');
    const text = editor.querySelector('[data-product-status-advisor-text]');
    const button = editor.querySelector('[data-product-status-suggestion]');
    const status = editor.querySelector('[name="product_status"]');
    const stock = editor.querySelector('[name="stock"]');
    if (!wrap || !text || !button || !status || !stock) return;
    const stockValue = Number(stock.value || 0);
    let suggested = '';
    let message = '';
    let label = '';
    if (status.value === 'active' && stockValue <= 0) {
      suggested = 'out_of_stock';
      message = 'Stock is zero while status is Active. Visitors will see a sold-out state; setting status to Out of stock is clearer.';
      label = 'Set out of stock';
    } else if (status.value === 'out_of_stock' && stockValue > 0) {
      suggested = 'active';
      message = 'Stock is available while status is Out of stock. Set it Active if visitors should be able to buy it.';
      label = 'Set active';
    } else if (status.value === 'draft' || status.value === 'archived') {
      suggested = '';
      message = 'This product is currently hidden from the public storefront.';
      label = '';
    }
    wrap.hidden = !message;
    text.textContent = message;
    button.hidden = !suggested;
    button.textContent = label;
    button.setAttribute('data-product-status-suggestion', suggested);
  }

  function initProductEditor(editor) {
    if (!editor || editor.dataset.productEditorProBound === '1') return;
    editor.dataset.productEditorProBound = '1';
    editor.dataset.productDirty = '0';

    const tabs = Array.from(editor.querySelectorAll('[data-product-tab]'));
    const panels = Array.from(editor.querySelectorAll('[data-product-tab-panel]'));
    const activateTab = (key) => {
      tabs.forEach((button) => button.classList.toggle('is-active', button.getAttribute('data-product-tab') === key));
      panels.forEach((panel) => {
        const active = panel.getAttribute('data-product-tab-panel') === key;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
      });
    };
    tabs.forEach((button) => {
      button.addEventListener('click', () => activateTab(button.getAttribute('data-product-tab') || 'overview'));
    });

    const nameInput = editor.querySelector('[data-preview-name]');
    const slugInput = editor.querySelector('[data-slug-input]');
    const slugButton = editor.querySelector('[data-product-slug-generate]');
    if (slugButton && slugInput) {
      slugButton.addEventListener('click', () => {
        slugInput.value = slugifyProduct(nameInput ? nameInput.value : slugInput.value);
        editor.dispatchEvent(new Event('input', { bubbles: true }));
      });
    }

    const specRows = editor.querySelector('[data-spec-rows]');
    const typeSelect = editor.querySelector('[data-product-type-select]');
    let templates = {};
    try { templates = JSON.parse(editor.getAttribute('data-spec-templates') || '{}') || {}; } catch (error) { templates = {}; }
    const addSpecRow = (name, value) => {
      if (specRows) specRows.appendChild(specRow(name || '', value || ''));
      editor.dataset.productDirty = '1';
      updateProductPreview(editor);
    };
    const applyTemplate = () => {
      if (!specRows || !typeSelect) return;
      const fields = templates[typeSelect.value] || templates.general || [];
      const existing = new Set(Array.from(specRows.querySelectorAll('[name="spec_names[]"]')).map((input) => input.value.trim().toLowerCase()).filter(Boolean));
      fields.forEach((field) => {
        if (!existing.has(String(field).toLowerCase())) {
          addSpecRow(field, '');
          existing.add(String(field).toLowerCase());
        }
      });
    };
    const addButton = editor.querySelector('[data-spec-add-row]');
    if (addButton) addButton.addEventListener('click', () => addSpecRow('', ''));
    const templateButton = editor.querySelector('[data-spec-apply-template]');
    if (templateButton) templateButton.addEventListener('click', applyTemplate);
    const specImportButton = editor.querySelector('[data-spec-import]');
    if (specImportButton) {
      specImportButton.addEventListener('click', () => {
        const bulk = editor.querySelector('[name="specs_text"]');
        const pairs = parseProductSpecLines(bulk ? bulk.value : '');
        if (!pairs.length) {
          setProductEditorFeedback(editor, '[data-spec-import-feedback]', 'No valid pasted specs found. Use Name: Value per line.', 'warning');
          return;
        }
        const existing = new Set(getProductSpecPairs(editor).map((pair) => pair[0].toLowerCase()));
        let added = 0;
        pairs.forEach((pair) => {
          const key = pair[0].toLowerCase();
          if (!existing.has(key)) {
            addSpecRow(pair[0], pair[1]);
            existing.add(key);
            added += 1;
          }
        });
        if (bulk && added > 0) bulk.value = '';
        cleanProductSpecRows(editor);
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        setProductEditorFeedback(editor, '[data-spec-import-feedback]', added ? ('Imported ' + added + ' specs into editable rows.') : 'All pasted specs already exist.', added ? 'success' : 'warning');
      });
    }
    const specCleanButton = editor.querySelector('[data-spec-clean]');
    if (specCleanButton) {
      specCleanButton.addEventListener('click', () => {
        const result = cleanProductSpecRows(editor);
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        setProductEditorFeedback(editor, '[data-spec-import-feedback]', result.removed ? ('Cleaned ' + result.removed + ' empty or duplicate rows.') : 'Specs already look clean.', result.removed ? 'success' : 'warning');
      });
    }
    editor.querySelectorAll('[data-spec-preset]').forEach((button) => {
      button.addEventListener('click', () => {
        const preset = button.getAttribute('data-spec-preset') || '';
        const rows = preset === 'shipping'
          ? [['Dimensions', ''], ['Weight', ''], ['Warranty', ''], ['Package contents', '']]
          : [];
        const existing = new Set(Array.from(editor.querySelectorAll('[name="spec_names[]"]')).map((input) => input.value.trim().toLowerCase()).filter(Boolean));
        let added = 0;
        rows.forEach((pair) => {
          const key = pair[0].toLowerCase();
          if (!existing.has(key)) {
            addSpecRow(pair[0], pair[1]);
            existing.add(key);
            added += 1;
          }
        });
        setProductEditorFeedback(editor, '[data-spec-import-feedback]', added ? ('Added ' + added + ' shipping specs.') : 'Shipping specs already exist.', added ? 'success' : 'warning');
      });
    });
    if (typeSelect) typeSelect.addEventListener('change', () => { applyTemplate(); updateProductPreview(editor); });
    const benefitRows = editor.querySelector('[data-benefit-rows]');
    const addBenefitRow = (value) => {
      if (benefitRows) benefitRows.appendChild(benefitRow(value || ''));
      editor.dataset.productDirty = '1';
      updateProductPreview(editor);
    };
    const benefitAddButton = editor.querySelector('[data-benefit-add-row]');
    if (benefitAddButton) benefitAddButton.addEventListener('click', () => addBenefitRow(''));
    const benefitImportButton = editor.querySelector('[data-benefit-import]');
    if (benefitImportButton) {
      benefitImportButton.addEventListener('click', () => {
        const bulk = editor.querySelector('[name="benefits_text"]');
        const rows = parseProductBenefitLines(bulk ? bulk.value : '');
        if (!rows.length) {
          setProductEditorFeedback(editor, '[data-benefit-import-feedback]', 'No valid pasted benefits found.', 'warning');
          return;
        }
        const existing = new Set(getProductBenefits(editor).map((item) => item.toLowerCase()));
        let added = 0;
        rows.forEach((item) => {
          const key = item.toLowerCase();
          if (!existing.has(key)) {
            addBenefitRow(item);
            existing.add(key);
            added += 1;
          }
        });
        if (bulk && added > 0) bulk.value = '';
        cleanProductBenefitRows(editor);
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        setProductEditorFeedback(editor, '[data-benefit-import-feedback]', added ? ('Imported ' + added + ' benefits into editable rows.') : 'All pasted benefits already exist.', added ? 'success' : 'warning');
      });
    }
    const benefitCleanButton = editor.querySelector('[data-benefit-clean]');
    if (benefitCleanButton) {
      benefitCleanButton.addEventListener('click', () => {
        const result = cleanProductBenefitRows(editor);
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        setProductEditorFeedback(editor, '[data-benefit-import-feedback]', result.removed ? ('Cleaned ' + result.removed + ' empty or duplicate rows.') : 'Benefits already look clean.', result.removed ? 'success' : 'warning');
      });
    }
    editor.querySelectorAll('[data-benefit-preset]').forEach((button) => {
      button.addEventListener('click', () => {
        const presets = [
          'Designed for daily use with reliable performance',
          'Clean product condition with clear specifications',
          'Ready to ship with updated stock information'
        ];
        const existing = new Set(getProductBenefits(editor).map((item) => item.toLowerCase()));
        presets.forEach((item) => {
          if (!existing.has(item.toLowerCase())) {
            addBenefitRow(item);
            existing.add(item.toLowerCase());
          }
        });
      });
    });


    const variantRows = editor.querySelector('[data-variant-rows]');
    const addVariantRow = (type, value) => {
      if (variantRows) variantRows.appendChild(variantRow(type || '', value || ''));
      editor.dataset.productDirty = '1';
      updateProductPreview(editor);
    };
    const variantAddButton = editor.querySelector('[data-variant-add-row]');
    if (variantAddButton) variantAddButton.addEventListener('click', () => addVariantRow('', ''));
    const variantImportButton = editor.querySelector('[data-variant-import]');
    if (variantImportButton) {
      variantImportButton.addEventListener('click', () => {
        const bulk = editor.querySelector('[name="variants_text"]');
        const rows = parseProductVariantLines(bulk ? bulk.value : '');
        if (!rows.length) {
          setProductEditorFeedback(editor, '[data-variant-import-feedback]', 'No valid pasted options found.', 'warning');
          return;
        }
        const existing = new Set(getProductVariantPairs(editor).map((pair) => pair[0].toLowerCase() + '|' + pair[1].toLowerCase()));
        let added = 0;
        rows.forEach((pair) => {
          const type = pair[0].trim().toLowerCase().replace(/\s+/g, '_');
          const value = pair[1].trim();
          const key = type + '|' + value.toLowerCase();
          if (type && value && !existing.has(key)) {
            addVariantRow(type, value);
            existing.add(key);
            added += 1;
          }
        });
        if (bulk && added > 0) bulk.value = '';
        cleanProductVariantRows(editor);
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        setProductEditorFeedback(editor, '[data-variant-import-feedback]', added ? ('Imported ' + added + ' options into editable rows.') : 'All pasted options already exist.', added ? 'success' : 'warning');
      });
    }
    const variantCleanButton = editor.querySelector('[data-variant-clean]');
    if (variantCleanButton) {
      variantCleanButton.addEventListener('click', () => {
        const result = cleanProductVariantRows(editor);
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        setProductEditorFeedback(editor, '[data-variant-import-feedback]', result.removed ? ('Cleaned ' + result.removed + ' empty or duplicate option rows.') : 'Options already look clean.', result.removed ? 'success' : 'warning');
      });
    }
    editor.querySelectorAll('[data-variant-preset]').forEach((button) => {
      button.addEventListener('click', () => {
        const preset = button.getAttribute('data-variant-preset') || '';
        const presets = {
          color: [['color', 'Black'], ['color', 'White'], ['color', 'Blue']],
          size: [['size', 'Small'], ['size', 'Medium'], ['size', 'Large']],
          finish: [['finish', 'Matte'], ['finish', 'Glossy'], ['finish', 'Premium']],
          storage: [['storage', '128GB'], ['storage', '256GB'], ['storage', '512GB']]
        };
        const rows = presets[preset] || presets.storage;
        const existing = new Set(getProductVariantPairs(editor).map((pair) => pair[0].toLowerCase() + '|' + pair[1].toLowerCase()));
        rows.forEach((pair) => {
          const key = pair[0].toLowerCase() + '|' + pair[1].toLowerCase();
          if (!existing.has(key)) {
            addVariantRow(pair[0], pair[1]);
            existing.add(key);
          }
        });
        setProductEditorFeedback(editor, '[data-variant-import-feedback]', 'Added ' + preset + ' preset options.', 'success');
      });
    });

    const statusSuggestion = editor.querySelector('[data-product-status-suggestion]');
    if (statusSuggestion) {
      statusSuggestion.addEventListener('click', () => {
        const nextStatus = statusSuggestion.getAttribute('data-product-status-suggestion') || '';
        const status = editor.querySelector('[name="product_status"]');
        if (status && nextStatus) {
          status.value = nextStatus;
          editor.dataset.productDirty = '1';
          updateProductPreview(editor);
        }
      });
    }

    editor.addEventListener('submit', (event) => {
      if (!updateProductValidation(editor, 'submit')) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }
      editor.dataset.productDirty = '0';
    });

    editor.addEventListener('click', (event) => {
      const removeSpec = event.target.closest ? event.target.closest('[data-spec-remove]') : null;
      if (removeSpec) {
        const row = removeSpec.closest('[data-spec-row]');
        if (row) row.remove();
        editor.dataset.productDirty = '1';
        if (specRows && !specRows.querySelector('[data-spec-row]')) addSpecRow('', '');
        updateProductPreview(editor);
        return;
      }
      const specMove = event.target.closest ? event.target.closest('[data-spec-move]') : null;
      if (specMove) {
        const row = specMove.closest('[data-spec-row]');
        const list = row ? row.parentNode : null;
        if (row && list) {
          if (specMove.getAttribute('data-spec-move') === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
          if (specMove.getAttribute('data-spec-move') === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        }
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        return;
      }
      const removeBenefit = event.target.closest ? event.target.closest('[data-benefit-remove]') : null;
      if (removeBenefit) {
        const row = removeBenefit.closest('[data-benefit-row]');
        if (row) row.remove();
        editor.dataset.productDirty = '1';
        if (benefitRows && !benefitRows.querySelector('[data-benefit-row]')) addBenefitRow('');
        updateProductPreview(editor);
        return;
      }
      const benefitMove = event.target.closest ? event.target.closest('[data-benefit-move]') : null;
      if (benefitMove) {
        const row = benefitMove.closest('[data-benefit-row]');
        const list = row ? row.parentNode : null;
        if (row && list) {
          if (benefitMove.getAttribute('data-benefit-move') === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
          if (benefitMove.getAttribute('data-benefit-move') === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        }
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        return;
      }
      const removeVariant = event.target.closest ? event.target.closest('[data-variant-remove]') : null;
      if (removeVariant) {
        const row = removeVariant.closest('[data-variant-row]');
        if (row) row.remove();
        editor.dataset.productDirty = '1';
        if (variantRows && !variantRows.querySelector('[data-variant-row]')) addVariantRow('', '');
        updateProductPreview(editor);
        return;
      }
      const variantMove = event.target.closest ? event.target.closest('[data-variant-move]') : null;
      if (variantMove) {
        const row = variantMove.closest('[data-variant-row]');
        const list = row ? row.parentNode : null;
        if (row && list) {
          if (variantMove.getAttribute('data-variant-move') === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
          if (variantMove.getAttribute('data-variant-move') === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        }
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        return;
      }
      const removeGallery = event.target.closest ? event.target.closest('[data-gallery-remove]') : null;
      if (removeGallery) {
        const card = removeGallery.closest('[data-gallery-card]');
        if (card) card.remove();
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
        return;
      }
      const moveButton = event.target.closest ? event.target.closest('[data-gallery-move]') : null;
      if (moveButton) {
        const card = moveButton.closest('[data-gallery-card]');
        const list = card ? card.parentNode : null;
        if (card && list) {
          if (moveButton.getAttribute('data-gallery-move') === 'up' && card.previousElementSibling) list.insertBefore(card, card.previousElementSibling);
          if (moveButton.getAttribute('data-gallery-move') === 'down' && card.nextElementSibling) list.insertBefore(card.nextElementSibling, card);
        }
        editor.dataset.productDirty = '1';
        updateProductPreview(editor);
      }
    });

    editor.addEventListener('input', () => {
      editor.dataset.productDirty = '1';
      const saveState = editor.querySelector('[data-save-state]');
      if (saveState) saveState.textContent = 'Unsaved changes';
      updateProductPreview(editor);
    });
    editor.addEventListener('change', () => {
      editor.dataset.productDirty = '1';
      updateProductPreview(editor);
    });
    const mediaFilter = editor.querySelector('[data-media-filter]');
    if (mediaFilter) mediaFilter.addEventListener('input', () => updateProductMediaTools(editor));
    const mediaSelectedOnly = editor.querySelector('[data-media-selected-only]');
    if (mediaSelectedOnly) mediaSelectedOnly.addEventListener('change', () => updateProductMediaTools(editor));

    updateProductPreview(editor);
  }

  function adminFlash(message, type) {
    if (!content) return;
    const stack = content.querySelector('.admin-flash-stack') || document.createElement('div');
    stack.className = 'admin-flash-stack';
    const item = document.createElement('div');
    item.className = 'admin-flash ' + (type || 'success');
    item.setAttribute('role', 'alert');
    item.textContent = String(message || 'Done.');
    stack.prepend(item);
    if (!stack.parentNode) content.prepend(stack);
    window.setTimeout(() => item.remove(), 4500);
  }

  function initPublishedProducts(center) {
    if (!center || center.dataset.publishedProductsReady === '1') return;
    center.dataset.publishedProductsReady = '1';

    const apiUrl = center.getAttribute('data-api-url') || 'api/products.php';
    const csrf = center.getAttribute('data-csrf') || '';
    const drawer = center.querySelector('[data-product-drawer]');
    const selectionBar = center.querySelector('.published-products-selectionbar');
    const selectedCount = center.querySelector('[data-published-selected-count]');
    const checkboxes = Array.from(center.querySelectorAll('[data-published-row-check]'));
    const selectAll = center.querySelector('[data-published-select-all]');
    const densityToggle = center.querySelector('[data-published-density-toggle]');
    const densityKey = 'phonix.publishedProducts.density';
    const savedViewsKey = 'phonix.publishedProducts.savedViews';
    const saveViewButton = center.querySelector('[data-published-save-view]');
    const refreshButton = center.querySelector('[data-published-refresh]');
    const savedViewsWrap = center.querySelector('[data-published-saved-views-wrap]');
    const savedViewsSelect = center.querySelector('[data-published-saved-views]');
    const loadSavedViewButton = center.querySelector('[data-published-load-view]');
    const deleteSavedViewButton = center.querySelector('[data-published-delete-view]');
    let activeProductId = '';
    let activeProductRow = null;

    function activeRow() {
      if (activeProductRow && activeProductRow.isConnected) return activeProductRow;
      if (!activeProductId) return null;
      return Array.from(center.querySelectorAll('[data-published-product-row]')).find((row) => row.getAttribute('data-product-id') === activeProductId) || null;
    }

    function selectedBoxes() {
      return checkboxes.filter((box) => box.checked && !box.disabled);
    }

    function selectedIds() {
      return selectedBoxes().map((box) => box.value).filter(Boolean);
    }

    function updateSelection() {
      const count = selectedBoxes().length;
      if (selectedCount) selectedCount.textContent = count + ' selected';
      if (selectionBar) selectionBar.hidden = count === 0;
      if (selectAll) {
        selectAll.checked = count > 0 && count === checkboxes.length;
        selectAll.indeterminate = count > 0 && count < checkboxes.length;
      }
    }

    function applyDensityMode(mode) {
      const compact = mode === 'compact';
      center.classList.toggle('is-compact', compact);
      if (densityToggle) densityToggle.innerHTML = compact ? '<span class="material-symbols-outlined">density_large</span> Comfort' : '<span class="material-symbols-outlined">density_medium</span> Compact';
    }

    applyDensityMode(window.localStorage ? window.localStorage.getItem(densityKey) : '');

    if (densityToggle) {
      densityToggle.addEventListener('click', () => {
        const nextMode = center.classList.contains('is-compact') ? 'comfort' : 'compact';
        try { window.localStorage.setItem(densityKey, nextMode); } catch (error) {}
        applyDensityMode(nextMode);
      });
    }

    function readSavedViews() {
      try {
        const raw = window.localStorage ? window.localStorage.getItem(savedViewsKey) : '';
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.filter((item) => item && item.name && item.query) : [];
      } catch (error) {
        return [];
      }
    }

    function writeSavedViews(items) {
      try { window.localStorage.setItem(savedViewsKey, JSON.stringify(items.slice(0, 12))); } catch (error) {}
    }

    function refreshSavedViewsUi() {
      if (!savedViewsSelect || !savedViewsWrap) return;
      const items = readSavedViews();
      savedViewsSelect.innerHTML = '<option value="">Choose a saved view...</option>';
      items.forEach((item, index) => {
        const option = document.createElement('option');
        option.value = String(index);
        option.textContent = item.name;
        savedViewsSelect.appendChild(option);
      });
      savedViewsWrap.hidden = items.length === 0;
    }

    function currentPublishedQuery() {
      const params = new URLSearchParams(currentParams.toString());
      params.set('section', 'products');
      params.delete('_');
      return params.toString();
    }

    refreshSavedViewsUi();

    if (saveViewButton) {
      saveViewButton.addEventListener('click', () => {
        const name = window.prompt('Name this product view:');
        if (!name || !name.trim()) return;
        const items = readSavedViews().filter((item) => item.name !== name.trim());
        items.unshift({ name: name.trim().slice(0, 60), query: currentPublishedQuery(), savedAt: new Date().toISOString() });
        writeSavedViews(items);
        refreshSavedViewsUi();
        adminFlash('View saved locally.', 'success');
      });
    }

    if (loadSavedViewButton) {
      loadSavedViewButton.addEventListener('click', () => {
        if (!savedViewsSelect || savedViewsSelect.value === '') return;
        const item = readSavedViews()[Number(savedViewsSelect.value)];
        if (!item || !item.query) return;
        const params = new URLSearchParams(item.query);
        params.set('section', 'products');
        loadSection('products', params, true);
      });
    }

    if (deleteSavedViewButton) {
      deleteSavedViewButton.addEventListener('click', () => {
        if (!savedViewsSelect || savedViewsSelect.value === '') return;
        const index = Number(savedViewsSelect.value);
        const items = readSavedViews();
        items.splice(index, 1);
        writeSavedViews(items);
        refreshSavedViewsUi();
        adminFlash('Saved view deleted.', 'success');
      });
    }

    if (refreshButton) {
      refreshButton.addEventListener('click', () => {
        loadSection(currentSection, currentParams, false);
      });
    }

    async function postProductAction(action, productId, extra) {
      const formData = new FormData();
      formData.append('_csrf', csrf);
      formData.append('admin_action', action);
      formData.append('product_id', productId || activeProductId || '0');
      if (extra) {
        Object.keys(extra).forEach((key) => formData.append(key, extra[key]));
      }
      const response = await fetch(apiUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload || !payload.ok) {
        throw new Error((payload && payload.message) || 'Product action failed.');
      }
      return payload;
    }

    function rowFromEventTarget(target) {
      return target && target.closest ? target.closest('[data-published-product-row]') : null;
    }

    function setText(selector, value) {
      if (!drawer) return;
      const node = drawer.querySelector(selector);
      if (node) node.textContent = value || '—';
    }

    function setDrawerPill(selector, label, statusClass) {
      if (!drawer) return;
      const node = drawer.querySelector(selector);
      if (!node) return;
      node.className = 'admin-pill ' + (statusClass || 'neutral');
      node.textContent = label || '—';
    }

    function productEditUrl(id) {
      const base = new URL('index.php', window.location.href);
      base.searchParams.set('section', 'product_edit');
      base.searchParams.set('id', id);
      return base.pathname + '?' + base.searchParams.toString();
    }

    async function copyStorefrontLink(row) {
      const url = row ? row.getAttribute('data-product-url') : '';
      if (!url) return;
      try {
        await navigator.clipboard.writeText(new URL(url, window.location.href).href);
        adminFlash('Storefront link copied.', 'success');
      } catch (error) {
        window.prompt('Copy storefront link:', new URL(url, window.location.href).href);
      }
    }

    function moneyPreview(value) {
      const numeric = Number(value || 0);
      return Number.isFinite(numeric) ? numeric.toFixed(2) : '0.00';
    }

    function updateDrawerChangePreview() {
      if (!drawer) return;
      const row = activeRow();
      if (!row) return;
      const originalStock = Number(row.getAttribute('data-product-stock') || '0');
      const originalPrice = Number(row.getAttribute('data-product-price') || '0');
      const originalCompare = row.getAttribute('data-product-compare') || '';
      const stockInput = drawer.querySelector('[data-drawer-stock-input]');
      const priceInput = drawer.querySelector('[data-drawer-price-input]');
      const discountInput = drawer.querySelector('[data-drawer-discount-input]');
      const stockPreview = drawer.querySelector('[data-drawer-stock-preview]');
      const pricePreview = drawer.querySelector('[data-drawer-price-preview]');
      if (stockPreview && stockInput) {
        const nextStock = Math.max(0, Number(stockInput.value || 0));
        const delta = nextStock - originalStock;
        stockPreview.textContent = 'Before: ' + originalStock + ' → After: ' + nextStock + (delta === 0 ? ' · no change' : ' · delta ' + (delta > 0 ? '+' : '') + delta);
        stockPreview.classList.toggle('is-danger', nextStock === 0);
      }
      if (pricePreview && priceInput) {
        const nextPrice = Number(priceInput.value || 0);
        const discountPercent = Number(discountInput && discountInput.value !== '' ? discountInput.value : 0);
        const nextCompare = discountPercent > 0 && discountPercent <= 95 && nextPrice > 0 ? nextPrice / (1 - (discountPercent / 100)) : 0;
        const originalDiscount = originalCompare && Number(originalCompare) > originalPrice && Number(originalCompare) > 0 ? Math.round(((Number(originalCompare) - originalPrice) / Number(originalCompare)) * 100) : 0;
        pricePreview.textContent = 'Price: ' + moneyPreview(originalPrice) + ' → ' + moneyPreview(nextPrice) + ' · Discount: ' + originalDiscount + '% → ' + (discountPercent > 0 ? discountPercent + '%' : '0%') + (nextCompare > 0 ? ' · Original shown: ' + moneyPreview(nextCompare) : '');
        pricePreview.classList.toggle('is-danger', nextPrice <= 0 || discountPercent < 0 || discountPercent > 95 || (originalPrice > 0 && nextPrice < originalPrice * 0.7));
      }
    }

    function renderDrawerActivity(items) {
      if (!drawer) return;
      const list = drawer.querySelector('[data-drawer-activity]');
      if (!list) return;
      list.innerHTML = '';
      if (!items || !items.length) {
        const li = document.createElement('li');
        li.textContent = 'No recent activity found for this product.';
        list.appendChild(li);
        return;
      }
      items.forEach((item) => {
        const li = document.createElement('li');
        const strong = document.createElement('strong');
        strong.textContent = String(item.action || 'activity').replace(/_/g, ' ');
        const small = document.createElement('small');
        small.textContent = item.created_at || '';
        const p = document.createElement('p');
        p.textContent = item.details || 'No details.';
        li.append(strong, small, p);
        list.appendChild(li);
      });
    }

    async function loadDrawerActivity(productId) {
      if (!drawer || !productId) return;
      renderDrawerActivity([{ action: 'loading', details: 'Loading recent activity...', created_at: '' }]);
      try {
        const payload = await postProductAction('product_activity', productId);
        renderDrawerActivity(payload.items || []);
      } catch (error) {
        renderDrawerActivity([{ action: 'activity unavailable', details: error.message || 'Could not load recent activity.', created_at: '' }]);
      }
    }

    function openDrawer(row, focusTarget) {
      if (!drawer || !row) return;
      activeProductId = row.getAttribute('data-product-id') || '';
      activeProductRow = row;
      const imageUrl = row.getAttribute('data-product-image-url') || '';
      const image = drawer.querySelector('[data-drawer-image]');
      const empty = drawer.querySelector('[data-drawer-image-empty]');
      if (image && empty) {
        if (imageUrl) {
          image.src = imageUrl;
          image.alt = row.getAttribute('data-product-name') || 'Product image';
          image.hidden = false;
          empty.hidden = true;
        } else {
          image.removeAttribute('src');
          image.hidden = true;
          empty.hidden = false;
        }
      }
      setText('[data-drawer-name]', row.getAttribute('data-product-name') || 'Product');
      setText('[data-drawer-short]', row.getAttribute('data-product-short') || 'No short description.');
      setText('[data-drawer-sku]', row.getAttribute('data-product-sku') || '—');
      setText('[data-drawer-category]', row.getAttribute('data-product-category') || '—');
      setText('[data-drawer-brand]', (row.getAttribute('data-product-brand') || '—') + ' · ' + (row.getAttribute('data-product-type') || 'General product'));
      setText('[data-drawer-sales]', (row.getAttribute('data-product-qty') || '0') + ' sold · ' + (row.getAttribute('data-product-revenue') || '0'));

      const status = row.getAttribute('data-product-status') || 'active';
      const statusClass = status === 'active' ? 'good' : (status === 'out_of_stock' ? 'warning' : 'neutral');
      setDrawerPill('[data-drawer-status]', row.getAttribute('data-product-status-label') || status, statusClass);
      const readyState = row.getAttribute('data-product-ready-state') || 'Ready';
      const readyClass = readyState === 'Ready' ? 'good' : (readyState === 'Needs review' ? 'warning' : 'danger');
      setDrawerPill('[data-drawer-readiness]', (row.getAttribute('data-product-readiness') || '') + ' ' + readyState, readyClass);

      const featured = drawer.querySelector('[data-drawer-featured]');
      if (featured) featured.hidden = row.getAttribute('data-product-featured') !== '1';

      const actionPlan = drawer.querySelector('[data-drawer-action-plan]');
      if (actionPlan) {
        actionPlan.className = 'published-drawer-action-plan ' + (row.getAttribute('data-product-action-class') || 'neutral');
      }
      setText('[data-drawer-action-title]', row.getAttribute('data-product-action-title') || 'Recommended action');
      setText('[data-drawer-action-detail]', row.getAttribute('data-product-action-detail') || 'Review this product before customers see outdated information.');
      setText('[data-drawer-action-label]', row.getAttribute('data-product-action-label') || 'Review later');

      const issueList = drawer.querySelector('[data-drawer-issues]');
      if (issueList) {
        issueList.innerHTML = '';
        let issues = [];
        try { issues = JSON.parse(row.getAttribute('data-product-issues') || '[]') || []; } catch (error) { issues = []; }
        if (!issues.length) {
          const li = document.createElement('li');
          li.textContent = 'No issues. This product is ready for customers.';
          issueList.appendChild(li);
        } else {
          issues.forEach((issue) => {
            const li = document.createElement('li');
            li.textContent = issue && issue.label ? issue.label : 'Needs review';
            issueList.appendChild(li);
          });
        }
      }

      const checklist = drawer.querySelector('[data-drawer-checklist]');
      if (checklist) {
        checklist.innerHTML = '';
        let checks = {};
        try { checks = JSON.parse(row.getAttribute('data-product-checklist') || '{}') || {}; } catch (error) { checks = {}; }
        Object.keys(checks).forEach((key) => {
          const check = checks[key] || {};
          const li = document.createElement('li');
          li.className = check.ok ? 'is-ok' : 'is-missing';
          const icon = document.createElement('span');
          icon.className = 'material-symbols-outlined';
          icon.textContent = check.ok ? 'check_circle' : 'error';
          const label = document.createElement('strong');
          label.textContent = check.label || key;
          const note = document.createElement('small');
          note.textContent = check.ok ? 'OK' : (check.issue || 'Needs review');
          li.append(icon, label, note);
          checklist.appendChild(li);
        });
      }

      drawer.querySelectorAll('input[name="product_id"]').forEach((input) => { input.value = activeProductId; });
      const stockInput = drawer.querySelector('[data-drawer-stock-input]');
      if (stockInput) stockInput.value = row.getAttribute('data-product-stock') || '0';
      const priceInput = drawer.querySelector('[data-drawer-price-input]');
      if (priceInput) priceInput.value = row.getAttribute('data-product-price') || '0';
      const discountInput = drawer.querySelector('[data-drawer-discount-input]');
      if (discountInput) {
        const rowPrice = Number(row.getAttribute('data-product-price') || '0');
        const rowCompare = Number(row.getAttribute('data-product-compare') || '0');
        const rowDiscount = rowCompare > rowPrice && rowCompare > 0 ? ((rowCompare - rowPrice) / rowCompare) * 100 : 0;
        discountInput.value = rowDiscount > 0 ? String(Number(rowDiscount.toFixed(2))) : '';
      }

      const edit = drawer.querySelector('[data-drawer-edit]');
      if (edit) edit.href = productEditUrl(activeProductId);
      const view = drawer.querySelector('[data-drawer-view]');
      if (view) view.href = row.getAttribute('data-product-url') || '#';
      updateDrawerChangePreview();

      drawer.hidden = false;
      drawer.setAttribute('aria-hidden', 'false');
      document.body.classList.add('published-product-drawer-open');
      const focusSelector = focusTarget === 'stock' ? '[data-drawer-stock-input]' : (focusTarget === 'price' ? '[data-drawer-price-input]' : '[data-product-drawer-close]');
      const focusNode = drawer.querySelector(focusSelector);
      if (focusNode) focusNode.focus({ preventScroll: true });
      if (drawer.querySelector('[data-drawer-activity]')) {
        loadDrawerActivity(activeProductId);
      }
    }

    function closeDrawer() {
      if (!drawer) return;
      drawer.hidden = true;
      drawer.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('published-product-drawer-open');
      activeProductId = '';
      activeProductRow = null;
    }

    checkboxes.forEach((box) => box.addEventListener('change', updateSelection));
    if (selectAll) {
      selectAll.addEventListener('change', () => {
        checkboxes.forEach((box) => {
          if (!box.disabled) box.checked = selectAll.checked;
        });
        updateSelection();
      });
    }
    const clear = center.querySelector('[data-published-clear-selection]');
    if (clear) {
      clear.addEventListener('click', () => {
        checkboxes.forEach((box) => { box.checked = false; });
        updateSelection();
      });
    }

    const exportSelected = center.querySelector('[data-published-export-selected]');
    if (exportSelected) {
      exportSelected.addEventListener('click', () => {
        const ids = selectedIds();
        if (!ids.length) {
          adminFlash('Select at least one product to export.', 'error');
          return;
        }
        const base = exportSelected.getAttribute('data-export-base') || '';
        const separator = base.indexOf('?') === -1 ? '?' : '&';
        window.location.href = base + separator + 'ids=' + encodeURIComponent(ids.join(','));
      });
    }

    const filterForm = center.querySelector('[data-published-filter-form]');
    if (filterForm) {
      filterForm.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', () => filterForm.requestSubmit());
      });
    }

    const bulkForm = center.querySelector('[data-published-bulk-form]');
    if (bulkForm) {
      bulkForm.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        const action = submitter && submitter.name === 'bulk_action' ? submitter.value : '';
        const ids = selectedIds();
        if (!ids.length) {
          event.preventDefault();
          adminFlash('Select at least one published product first.', 'error');
          return;
        }
        const selectedNames = selectedBoxes().slice(0, 4).map((box) => {
          const row = box.closest('[data-published-product-row]');
          return row ? row.getAttribute('data-product-name') : ('#' + box.value);
        }).filter(Boolean);
        const labels = {
          feature: 'mark selected products as featured',
          unfeature: 'remove selected products from featured',
          sync_stock_status: 'sync stock/status for selected products',
          mark_draft: 'move selected published products to draft',
          archive: 'archive selected published products',
          set_stock: 'set stock for selected products',
          set_category: 'set category for selected products',
          set_discount: 'set discount for selected products',
          clear_discount: 'clear discounts from selected products',
          set_brand: 'set brand for selected products'
        };
        const destructive = action === 'archive' || action === 'mark_draft' || action === 'set_stock';
        const preview = selectedNames.join(', ') + (ids.length > selectedNames.length ? ' +' + (ids.length - selectedNames.length) + ' more' : '');
        const message = 'You are about to ' + (labels[action] || 'update selected products') + '.\n\nSelected: ' + ids.length + '\n' + preview + (destructive ? '\n\nThis can change storefront visibility or purchasing state.' : '');
        if (!window.confirm(message)) event.preventDefault();
      });
    }

    center.querySelectorAll('[data-drawer-stock-input], [data-drawer-price-input], [data-drawer-discount-input]').forEach((input) => {
      input.addEventListener('input', updateDrawerChangePreview);
      input.addEventListener('change', updateDrawerChangePreview);
    });

    center.addEventListener('click', async (event) => {
      const open = event.target.closest ? event.target.closest('[data-product-preview-open]') : null;
      if (open) {
        event.preventDefault();
        openDrawer(rowFromEventTarget(open), open.getAttribute('data-product-drawer-focus') || '');
        return;
      }

      const close = event.target.closest ? event.target.closest('[data-product-drawer-close]') : null;
      if (close) {
        event.preventDefault();
        closeDrawer();
        return;
      }
      if (drawer && event.target === drawer) {
        closeDrawer();
        return;
      }

      const copyButton = event.target.closest ? event.target.closest('[data-published-copy-link], [data-drawer-copy-link]') : null;
      if (copyButton) {
        event.preventDefault();
        await copyStorefrontLink(rowFromEventTarget(copyButton) || activeRow());
        return;
      }

      const drawerAction = event.target.closest ? event.target.closest('[data-drawer-action]') : null;
      const rowAction = event.target.closest ? event.target.closest('[data-published-product-action]') : null;
      const actionButton = drawerAction || rowAction;
      if (!actionButton) return;
      event.preventDefault();
      const row = rowFromEventTarget(actionButton);
      const productId = row ? row.getAttribute('data-product-id') : activeProductId;
      const rawAction = actionButton.getAttribute(drawerAction ? 'data-drawer-action' : 'data-published-product-action') || '';
      const actionMap = {
        toggle_featured: 'product_toggle_featured',
        sync_status: 'product_sync_status',
        draft: 'product_draft',
        archive: 'product_archive',
        duplicate: 'product_duplicate'
      };
      const apiAction = actionMap[rawAction];
      if (!apiAction || !productId) return;
      if ((rawAction === 'archive' || rawAction === 'draft') && !window.confirm('Apply this action to the selected product?')) return;
      actionButton.setAttribute('aria-busy', 'true');
      try {
        const payload = await postProductAction(apiAction, productId);
        adminFlash(payload.message || 'Product updated.', 'success');
        closeDrawer();
        loadSection(currentSection, currentParams, false);
      } catch (error) {
        adminFlash(error.message || 'Product action failed.', 'error');
      } finally {
        actionButton.removeAttribute('aria-busy');
      }
    });

    center.querySelectorAll('[data-drawer-stock-form], [data-drawer-price-form]').forEach((form) => {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const submit = event.submitter || form.querySelector('button[type="submit"]');
        if (submit) submit.setAttribute('aria-busy', 'true');
        const fd = new FormData(form);
        try {
          const extra = {};
          fd.forEach((value, key) => {
            if (key !== '_csrf' && key !== 'admin_action' && key !== 'product_id') extra[key] = value;
          });
          const actionName = String(fd.get('admin_action') || '');
          const row = activeRow();
          if (row && actionName === 'product_quick_stock') {
            const nextStock = Number(extra.new_stock || 0);
            if (nextStock === 0 && !window.confirm('This sets stock to zero and the product may become out of stock. Continue?')) return;
          }
          if (row && actionName === 'product_quick_price') {
            const originalPrice = Number(row.getAttribute('data-product-price') || '0');
            const nextPrice = Number(extra.price || 0);
            if (originalPrice > 0 && nextPrice < originalPrice * 0.7 && !window.confirm('This reduces the price by more than 30%. Continue?')) return;
          }
          const payload = await postProductAction(actionName, String(fd.get('product_id') || activeProductId), extra);
          adminFlash(payload.message || 'Product updated.', 'success');
          closeDrawer();
          loadSection(currentSection, currentParams, false);
        } catch (error) {
          adminFlash(error.message || 'Product update failed.', 'error');
        } finally {
          if (submit) submit.removeAttribute('aria-busy');
        }
      });
    });

    updateSelection();
  }

  function polishCurrentSection() {
    if (!content) return;

    content.querySelectorAll('.admin-table-wrap').forEach((wrap) => {
      if (!wrap.hasAttribute('tabindex')) wrap.setAttribute('tabindex', '0');
      if (!wrap.hasAttribute('aria-label')) wrap.setAttribute('aria-label', 'Scrollable admin table');
      wrap.classList.toggle('has-horizontal-scroll', wrap.scrollWidth > wrap.clientWidth + 4);
    });

    content.querySelectorAll('[data-product-editor]').forEach(initProductEditor);
    content.querySelectorAll('[data-published-products]').forEach(initPublishedProducts);

    content.querySelectorAll('form').forEach((form) => {
      if (!form.hasAttribute('autocomplete')) form.setAttribute('autocomplete', 'off');
    });
  }

  menuButtons.forEach((button) => {
    button.addEventListener('click', function () {
      setMobileMenuOpen(!document.body.classList.contains('admin-menu-open'));
    });
  });

  menuClosers.forEach((closer) => {
    closer.addEventListener('click', function () {
      setMobileMenuOpen(false);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setMobileMenuOpen(false);
  });

  function setLoading() {
    if (!content) return;
    content.classList.add('is-loading');
    if (!content.innerHTML.trim()) {
      content.innerHTML = '<div class="admin-loading-card glass-panel"><span class="material-symbols-outlined">progress_activity</span><strong>Loading admin section...</strong></div>';
    }
  }

  function readSectionFromUrl(url) {
    try {
      const parsed = new URL(url, window.location.href);
      if (!parsed.pathname.endsWith('/admin/index.php') && !parsed.pathname.endsWith('/admin/')) return null;
      return {
        section: normalizeSection(parsed.searchParams.get('section') || 'index'),
        params: parsed.searchParams
      };
    } catch (error) {
      return null;
    }
  }

  async function loadSection(section, params, pushState) {
    if (!content || !window.fetch) return;
    section = normalizeSection(section);
    params = new URLSearchParams(params || '');
    params.set('section', section);
    currentSection = section;
    currentParams = params;
    setActive(section);
    setLoading();

    try {
      const response = await fetch(ajaxUrl(section, params), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
      });
      const html = await response.text();
      if (!response.ok) throw new Error(html || 'Could not load admin section.');
      content.innerHTML = html;
      content.classList.remove('is-loading');
      polishCurrentSection();
      setAdminCompactMode(adminCompactMode, false);
      setMobileMenuOpen(false);
      const fragment = content.querySelector('.admin-section-fragment');
      const title = fragment ? fragment.getAttribute('data-admin-title') : 'Admin Console';
      const activeFromFragment = fragment ? fragment.getAttribute('data-admin-active') : '';
      if (activeFromFragment) setActive(activeFromFragment);
      if (title) document.title = title + ' | Phonix Admin';
      if (pushState !== false) {
        window.history.pushState({ section, params: params.toString() }, '', publicUrl(section, params));
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
      content.classList.remove('is-loading');
      content.innerHTML = '<section class="admin-card glass-panel admin-load-error"><div class="admin-empty-state"><span class="material-symbols-outlined">error</span><strong>Section could not load</strong><p>' + String(error.message || error).replace(/[<>&]/g, '') + '</p><button type="button" class="admin-table-btn" data-admin-retry>Try again</button></div></section>';
    }
  }

  document.addEventListener('click', function (event) {
    const selectAll = event.target && event.target.closest ? event.target.closest('[data-admin-select-all]') : null;
    if (selectAll) {
      const targetSelector = selectAll.getAttribute('data-admin-select-all');
      if (targetSelector) {
        document.querySelectorAll(targetSelector).forEach((box) => {
          if (!box.disabled) box.checked = selectAll.checked;
        });
      }
    }

    const retry = event.target && event.target.closest ? event.target.closest('[data-admin-retry]') : null;
    if (retry) {
      event.preventDefault();
      loadSection(currentSection, currentParams, false);
      return;
    }

    const link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!link || link.target || link.hasAttribute('download')) return;
    const info = readSectionFromUrl(link.href);
    if (!info) return;
    event.preventDefault();
    if (!confirmLeavingProductEditor()) return;
    loadSection(info.section, info.params, true);
  });

  document.addEventListener('submit', async function (event) {
    const form = event.target;
    if (!form || !form.closest || !form.closest('[data-admin-content]')) return;
    const confirmation = form.getAttribute('data-admin-confirm');
    if (confirmation && !window.confirm(confirmation)) return;
    event.preventDefault();

    const method = String(form.getAttribute('method') || 'get').toLowerCase();
    const formData = new FormData(form);

    if (method === 'get') {
      const params = new URLSearchParams(formData);
      params.set('section', currentSection);
      loadSection(currentSection, params, true);
      return;
    }

    const submitter = event.submitter;
    if (submitter && submitter.name) formData.append(submitter.name, submitter.value || '');
    form.classList.add('is-submitting');
    if (submitter) submitter.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(ajaxUrl(currentSection, currentParams), {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      const text = await response.text();
      let payload = null;
      try { payload = JSON.parse(text); } catch (error) { payload = null; }
      if (!response.ok || !payload || !payload.ok) {
        throw new Error((payload && payload.message) || text || 'Admin action failed.');
      }
      const dirtyEditor = activeDirtyProductEditor();
      if (dirtyEditor) dirtyEditor.dataset.productDirty = '0';
      const nextUrl = payload.url ? new URL(payload.url, window.location.href) : publicUrl(payload.section || currentSection, new URLSearchParams());
      const nextInfo = readSectionFromUrl(nextUrl.href) || { section: payload.section || currentSection, params: new URLSearchParams() };
      loadSection(nextInfo.section, nextInfo.params, true);
    } catch (error) {
      const message = document.createElement('div');
      message.className = 'admin-flash error';
      message.setAttribute('role', 'alert');
      message.textContent = String(error.message || error).replace(/[<>&]/g, '');
      const stack = content.querySelector('.admin-flash-stack') || document.createElement('div');
      stack.className = 'admin-flash-stack';
      stack.prepend(message);
      if (!stack.parentNode) content.prepend(stack);
      message.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    } finally {
      form.classList.remove('is-submitting');
      if (submitter) submitter.removeAttribute('aria-busy');
    }
  });

  window.addEventListener('resize', function () {
    polishCurrentSection();
    if (window.innerWidth > 1180) setMobileMenuOpen(false);
  });

  window.addEventListener('popstate', function () {
    if (!confirmLeavingProductEditor()) {
      window.history.pushState({ section: currentSection, params: currentParams.toString() }, '', publicUrl(currentSection, currentParams));
      return;
    }
    const params = new URLSearchParams(window.location.search);
    const section = normalizeSection(params.get('section') || 'index');
    loadSection(section, params, false);
  });

  window.addEventListener('beforeunload', function (event) {
    if (!activeDirtyProductEditor()) return;
    event.preventDefault();
    event.returnValue = '';
  });

  if (content) {
    const params = new URLSearchParams(window.location.search);
    const section = normalizeSection(params.get('section') || initialSection);
    loadSection(section, params, false);
  }

  polishCurrentSection();

  const initialVersion = root.getAttribute('data-admin-version') || '';
  const versionUrl = root.getAttribute('data-admin-version-url') || '';
  if (!initialVersion || !versionUrl || !window.fetch) return;

  let lastVersion = initialVersion;
  let isDirty = false;
  let banner = null;

  const markDirty = () => { isDirty = true; };
  document.addEventListener('input', function (event) {
    if (event.target && event.target.closest && event.target.closest('.admin-console form')) {
      markDirty();
    }
  }, true);

  document.addEventListener('submit', function () {
    isDirty = false;
  }, true);

  function buildBanner() {
    if (banner) return banner;
    banner = document.createElement('div');
    banner.className = 'admin-live-update-banner';
    banner.setAttribute('role', 'status');
    banner.innerHTML = '<strong>New admin updates are available.</strong><span>The console detected changed files.</span><button type="button">Apply now</button>';
    const button = banner.querySelector('button');
    if (button) {
      button.addEventListener('click', function () {
        window.location.reload();
      });
    }
    document.body.appendChild(banner);
    return banner;
  }

  function safeToAutoReload() {
    const active = document.activeElement;
    const editing = active && active.closest && active.closest('.admin-console form');
    return !isDirty && !editing;
  }

  function handleNewVersion(version) {
    if (!version || version === lastVersion) return;
    lastVersion = version;
    if (safeToAutoReload()) {
      window.location.reload();
      return;
    }
    buildBanner().classList.add('is-visible');
  }

  async function checkVersion() {
    try {
      const response = await fetch(versionUrl + (versionUrl.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) return;
      const payload = await response.json();
      if (payload && payload.ok) {
        handleNewVersion(String(payload.version || ''));
      }
    } catch (error) {
      // Silent by design; the next interval will retry.
    }
  }

  window.setInterval(checkVersion, 15000);
})();
