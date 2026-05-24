(function () {
  const root = document.documentElement;
  const body = document.body;
  const $ = (selector, parent = document) => parent.querySelector(selector);
  const $$ = (selector, parent = document) => Array.from(parent.querySelectorAll(selector));
  const storage = {
    read(key, fallback) {
      try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
      } catch (error) {
        return fallback;
      }
    },
    write(key, value) {
      localStorage.setItem(key, JSON.stringify(value));
    },
    remove(key) {
      localStorage.removeItem(key);
    }
  };

  const store = {
    endpoint: body?.dataset.storeEndpoint || '',
    csrf: body?.dataset.csrf || '',
    mode: 'local',
    state: {
      cart: { items: [], count: 0, subtotal: 0, subtotal_formatted: '' },
      wishlist: { items: [], ids: [], count: 0 },
      auth: { logged_in: false }
    }
  };

  function getLocalCart() {
    return storage.read('phonix-cart-db', []);
  }

  function getLocalWishlist() {
    return storage.read('phonix-wishlist-db', []);
  }

  function setLocalCart(items) {
    storage.write('phonix-cart-db', items);
  }

  function setLocalWishlist(items) {
    storage.write('phonix-wishlist-db', items);
  }

  function applyLocalState() {
    const cartItems = getLocalCart();
    const wishlistIds = getLocalWishlist().map((id) => Number(id)).filter(Boolean);
    const count = cartItems.reduce((sum, item) => sum + Number(item.qty || 1), 0);
    store.state = {
      cart: { items: cartItems, count, subtotal: 0, subtotal_formatted: '' },
      wishlist: { items: [], ids: wishlistIds, count: wishlistIds.length },
      auth: { logged_in: false }
    };
  }

  function applyRemoteState(state) {
    if (!state || typeof state !== 'object') return;
    store.state = {
      cart: state.cart || { items: [], count: 0, subtotal: 0, subtotal_formatted: '' },
      wishlist: state.wishlist || { items: [], ids: [], count: 0 },
      auth: state.auth || { logged_in: false }
    };
  }

  function renderCounts() {
    const cartCount = Number(store.state.cart?.count || 0);
    const wishlistCount = Number(store.state.wishlist?.count || 0);
    $$('.js-cart-count').forEach((node) => { node.textContent = String(cartCount); });
    $$('.js-wishlist-count').forEach((node) => { node.textContent = String(wishlistCount); });
  }

  function showToast(text) {
    const toast = $('.js-toast');
    if (!toast) return;
    toast.textContent = text;
    toast.classList.add('show');
    clearTimeout(window.__phonixToastTimer);
    window.__phonixToastTimer = setTimeout(() => toast.classList.remove('show'), 1800);
  }

  function toggleTheme() {
    const dark = root.classList.toggle('dark');
    localStorage.setItem('phonix-theme-live', dark ? 'dark' : 'light');
    const btn = $('.js-theme');
    if (btn) btn.textContent = dark ? '☀️' : '🌙';
  }

  function initTheme() {
    const saved = localStorage.getItem('phonix-theme-live');
    if (saved === 'dark') {
      root.classList.add('dark');
    }
    const btn = $('.js-theme');
    if (btn) btn.textContent = root.classList.contains('dark') ? '☀️' : '🌙';
  }

  function syncWishlistButtons() {
    const wishlist = Array.isArray(store.state.wishlist?.ids) ? store.state.wishlist.ids : [];
    $$('.js-wishlist-toggle').forEach((button) => {
      const id = Number(button.dataset.productId || 0);
      const active = wishlist.includes(id);
      if (button.dataset.labelMode === 'text') {
        button.textContent = active ? 'Saved to wishlist' : 'Save to wishlist';
      } else {
        button.textContent = active ? '♥' : '♡';
      }
      button.classList.toggle('is-active', active);
      if (button.hasAttribute('aria-pressed')) {
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      }
    });
  }

  async function apiGetState() {
    const response = await fetch(store.endpoint, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) {
      throw new Error(payload.message || 'Could not load store state.');
    }
    return payload.state;
  }

  async function apiPost(action, data) {
    const response = await fetch(store.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': store.csrf
      },
      body: JSON.stringify({ _csrf: store.csrf, action, ...data })
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) {
      throw new Error(payload.message || 'Store update failed.');
    }
    applyRemoteState(payload.state);
    renderCounts();
    syncWishlistButtons();
    return payload;
  }

  async function syncLegacyStateOnce() {
    if (store.mode !== 'remote') return;
    const legacyCart = getLocalCart();
    const legacyWishlist = getLocalWishlist();
    const hasLegacyState = legacyCart.length > 0 || legacyWishlist.length > 0;

    if (!hasLegacyState) {
      storage.write('phonix-store-migrated-v1', true);
      return;
    }

    try {
      const payload = await apiPost('sync_client_state', { cart: legacyCart, wishlist: legacyWishlist });
      storage.remove('phonix-cart-db');
      storage.remove('phonix-wishlist-db');
      storage.write('phonix-store-migrated-v1', true);
      if (payload.message) showToast(payload.message);
    } catch (error) {
      console.error(error);
    }
  }

  async function initializeStoreMode() {
    if (!store.endpoint || !store.csrf) {
      applyLocalState();
      renderCounts();
      syncWishlistButtons();
      return;
    }
    try {
      const state = await apiGetState();
      store.mode = 'remote';
      applyRemoteState(state);
      renderCounts();
      syncWishlistButtons();
      await syncLegacyStateOnce();
      const refreshed = await apiGetState();
      applyRemoteState(refreshed);
      renderCounts();
      syncWishlistButtons();
    } catch (error) {
      console.error(error);
      applyLocalState();
      renderCounts();
      syncWishlistButtons();
    }
  }

  function initWishlist() {
    $$('.js-wishlist-toggle').forEach((button) => {
      button.addEventListener('click', async () => {
        const id = Number(button.dataset.productId || 0);
        if (!id) return;
        try {
          if (store.mode === 'remote') {
            const payload = await apiPost('toggle_wishlist', { product_id: id });
            showToast(payload.message || 'Wishlist updated.');
          } else {
            const wishlist = getLocalWishlist();
            const index = wishlist.indexOf(id);
            if (index >= 0) {
              wishlist.splice(index, 1);
              showToast('Removed from wishlist.');
            } else {
              wishlist.unshift(id);
              showToast('Saved to wishlist.');
            }
            setLocalWishlist(wishlist);
            applyLocalState();
            renderCounts();
            syncWishlistButtons();
          }
        } catch (error) {
          showToast(error.message || 'Wishlist update failed.');
        }
      });
    });
    syncWishlistButtons();
  }

  function initCart() {
    $$('.js-add-to-cart').forEach((button) => {
      button.addEventListener('click', async () => {
        const id = Number(button.dataset.productId || 0);
        const name = button.dataset.productName || 'Product';
        if (!id) return;
        try {
          if (store.mode === 'remote') {
            const payload = await apiPost('add_cart', { product_id: id, qty: 1 });
            showToast(payload.message || (name + ' added to cart.'));
          } else {
            const cart = getLocalCart();
            const existing = cart.find((item) => item.id === id);
            if (existing) {
              existing.qty += 1;
            } else {
              cart.push({ id, qty: 1, name });
            }
            setLocalCart(cart);
            applyLocalState();
            renderCounts();
            showToast(name + ' added to cart.');
          }
        } catch (error) {
          showToast(error.message || 'Could not add this item to the cart.');
        }
      });
    });
  }

  function initStorePageActions() {
    $$('[data-store-action]').forEach((button) => {
      button.addEventListener('click', async () => {
        if (store.mode !== 'remote') {
          showToast('Please refresh to use live cart controls.');
          return;
        }
        const action = button.dataset.storeAction;
        const productId = Number(button.dataset.productId || 0);
        const delta = Number(button.dataset.delta || 0);
        const row = button.closest('[data-cart-row]');
        const optionHash = button.dataset.optionHash || row?.dataset.optionHash || '';
        let qty = Number(button.dataset.qty || 0);
        if (!qty && row) {
          qty = Number(row.dataset.qty || 0) + delta;
        }
        try {
          let payload;
          if (action === 'update_cart') {
            payload = await apiPost('update_cart', { product_id: productId, qty, option_hash: optionHash });
          } else if (action === 'remove_cart') {
            payload = await apiPost('remove_cart', { product_id: productId, option_hash: optionHash });
          } else if (action === 'move_wishlist_to_cart') {
            payload = await apiPost('move_wishlist_to_cart', { product_id: productId });
          } else {
            return;
          }
          showToast(payload.message || 'Updated.');
          window.location.reload();
        } catch (error) {
          showToast(error.message || 'Could not update this item.');
        }
      });
    });
  }

  function initAutoSubmitControls() {
    $$('[data-auto-submit="change"]').forEach((control) => {
      control.addEventListener('change', () => {
        if (control.form) {
          control.form.submit();
        }
      });
    });
  }

  function initMobileNav() {
    const toggle = $('.js-mobile-toggle');
    const menu = $('.mobile-nav');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', () => {
      menu.classList.toggle('open');
    });
  }

  initTheme();
  initializeStoreMode().then(() => {
    initWishlist();
    initCart();
    initStorePageActions();
  });
  initMobileNav();
  initAutoSubmitControls();
  $('.js-theme')?.addEventListener('click', toggleTheme);
})();
