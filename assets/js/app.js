
const PRODUCTS = [{"id": "apple-iphone-15-pro-max", "name": "Apple iPhone 15 Pro Max", "category": "iphone", "brand": "Apple", "price": 59999, "oldPrice": 67999, "badge": "Official Warranty", "desc": "256GB Natural Titanium, pro camera system, USB-C, and excellent battery life with Türkiye-ready warranty support.", "storage": ["256GB", "512GB", "1TB"], "colors": ["Natural Titanium", "Blue Titanium", "Black Titanium"], "rating": 4.9}, {"id": "samsung-galaxy-s24-ultra", "name": "Samsung Galaxy S24 Ultra", "category": "android", "brand": "Samsung", "price": 42999, "oldPrice": 48999, "badge": "Best Seller", "desc": "512GB Titanium Yellow, Galaxy AI features, S Pen, bright AMOLED display, and flagship zoom cameras.", "storage": ["256GB", "512GB", "1TB"], "colors": ["Titanium Yellow", "Titanium Gray", "Titanium Black"], "rating": 4.8}, {"id": "xiaomi-14-5g", "name": "Xiaomi 14 5G", "category": "android", "brand": "Xiaomi", "price": 29999, "oldPrice": 33499, "badge": "Deal", "desc": "512GB storage, Leica-inspired camera experience, fast charging, and compact flagship performance.", "storage": ["256GB", "512GB"], "colors": ["Black", "White", "Jade Green"], "rating": 4.7}, {"id": "honor-90-5g", "name": "Honor 90 5G", "category": "android", "brand": "Honor", "price": 18999, "oldPrice": 20999, "badge": "Free Shipping", "desc": "512GB storage, slim design, bright OLED screen, and balanced camera performance for daily use.", "storage": ["256GB", "512GB"], "colors": ["Emerald Green", "Midnight Black", "Diamond Silver"], "rating": 4.6}, {"id": "vivo-v30-5g", "name": "vivo V30 5G", "category": "android", "brand": "vivo", "price": 16999, "oldPrice": 18999, "badge": "New Arrival", "desc": "256GB storage, elegant design, strong selfie camera, and reliable 5G performance.", "storage": ["256GB", "512GB"], "colors": ["Aqua Blue", "Noble Black"], "rating": 4.5}, {"id": "apple-iphone-15", "name": "Apple iPhone 15", "category": "iphone", "brand": "Apple", "price": 38999, "oldPrice": 41999, "badge": "Popular", "desc": "128GB, Dynamic Island, 48MP main camera, USB-C, and polished Apple ecosystem experience.", "storage": ["128GB", "256GB", "512GB"], "colors": ["Black", "Blue", "Pink", "Green"], "rating": 4.9}, {"id": "samsung-galaxy-a55-5g", "name": "Samsung Galaxy A55 5G", "category": "android", "brand": "Samsung", "price": 15999, "oldPrice": 17999, "badge": "Value Pick", "desc": "256GB, Super AMOLED display, durable body, and balanced Samsung performance for daily use.", "storage": ["128GB", "256GB"], "colors": ["Awesome Navy", "Awesome Iceblue", "Awesome Lilac"], "rating": 4.7}, {"id": "xiaomi-redmi-note-13-pro-plus", "name": "Xiaomi Redmi Note 13 Pro+", "category": "android", "brand": "Xiaomi", "price": 13999, "oldPrice": 15999, "badge": "Top Rated", "desc": "512GB storage, curved AMOLED display, 200MP camera, and fast charging at a strong price.", "storage": ["256GB", "512GB"], "colors": ["Midnight Black", "Moonlight White", "Aurora Purple"], "rating": 4.6}, {"id": "samsung-galaxy-s23-fe", "name": "Samsung Galaxy S23 FE", "category": "android", "brand": "Samsung", "price": 18499, "oldPrice": 21499, "badge": "Outlet Deal", "desc": "256GB, flagship-inspired camera, AMOLED screen, and reliable Samsung performance at a lower price.", "storage": ["128GB", "256GB"], "colors": ["Graphite", "Mint", "Cream"], "rating": 4.6}, {"id": "apple-airpods-pro-usb-c", "name": "Apple AirPods Pro USB-C", "category": "accessories", "brand": "Apple", "price": 8499, "oldPrice": 9499, "badge": "Accessory", "desc": "Wireless earbuds with active noise cancellation, transparency mode, and USB-C charging case.", "storage": [], "colors": ["White"], "rating": 4.8}, {"id": "samsung-25w-usb-c-charger", "name": "Samsung 25W USB-C Charger", "category": "accessories", "brand": "Samsung", "price": 799, "oldPrice": 999, "badge": "Fast Charge", "desc": "Compact 25W USB-C wall charger for compatible Samsung and Android devices.", "storage": [], "colors": ["White", "Black"], "rating": 4.7}, {"id": "magsafe-compatible-clear-case", "name": "MagSafe Compatible Clear Case", "category": "accessories", "brand": "Phonix", "price": 1199, "oldPrice": 1499, "badge": "Case", "desc": "Clear protective case with magnetic alignment for iPhone 15 series.", "storage": [], "colors": ["Clear"], "rating": 4.5}, {"id": "galaxy-watch-6-classic", "name": "Galaxy Watch 6 Classic", "category": "wearables", "brand": "Samsung", "price": 7499, "oldPrice": 8499, "badge": "Wearable", "desc": "Classic smartwatch with health tracking, premium build, and Android phone integration.", "storage": [], "colors": ["Black", "Silver"], "rating": 4.7}];
const ORDERS = [{"id": "PX-TR-84729", "name": "Samsung Galaxy S24 Ultra 512GB", "status": "Delivered", "date": "Delivered in Istanbul", "price": "₺42.999"}, {"id": "PX-TR-84755", "name": "Apple iPhone 15 128GB", "status": "In Transit", "date": "Expected delivery in Ankara", "price": "₺38.999"}, {"id": "PX-TR-84803", "name": "MagSafe Compatible Clear Case", "status": "Processing", "date": "Preparing for shipment", "price": "₺1.199"}];
const FAQS = [["How long does phone delivery take in Turkey?", "In-stock phone orders are usually prepared within 1 business day. Delivery timing depends on the city and selected shipping method."], ["Are the phones covered by warranty?", "Each product page should clearly show its warranty status. Prefer official distributor warranty items when you need full local service confidence."], ["Can customers pay by card or installment?", "Available payment methods are shown during checkout. Installment options depend on the active payment choices."], ["Can a phone be returned?", "Returns depend on the return policy and product condition. Keep the original box, accessories, and invoice for any return or service request."]];
const BRANDS = {"Apple": "iPhone models, strong resale value, and a polished ecosystem experience.", "Samsung": "Android flagships, foldables, Galaxy AI features, and wide service coverage.", "Xiaomi": "High-value Android phones with strong storage, fast charging, and competitive pricing.", "Honor": "Slim Android phones with bright displays and strong daily performance.", "vivo": "Elegant 5G phones focused on design, portraits, and reliable battery life.", "Phonix": "Curated accessories, bundles, and phone-market services for Turkey."};

const $ = (s, p=document) => p.querySelector(s);
const $$ = (s, p=document) => [...p.querySelectorAll(s)];
const fmt = n => `₺${Number(n).toLocaleString('tr-TR')}`;
const slugToTitle = s => s.replace(/-/g,' ').replace(/\b\w/g, m => m.toUpperCase());
const qs = new URLSearchParams(location.search);

const store = {
  read(key, fallback){ try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } },
  write(key, value){ localStorage.setItem(key, JSON.stringify(value)); }
};

function getCart(){ return store.read('phonix-cart', [{id:'samsung-galaxy-s24-ultra', qty:1}, {id:'apple-airpods-pro-usb-c', qty:1}]); }
function setCart(v){ store.write('phonix-cart', v); updateHeaderCounts(); }
function getWishlist(){ return store.read('phonix-wishlist', ['apple-iphone-15-pro-max','samsung-galaxy-s24-ultra']); }
function setWishlist(v){ store.write('phonix-wishlist', v); updateHeaderCounts(); }

function cartCount(){ return getCart().reduce((a,b)=>a+(b.qty||1),0); }
function wishlistCount(){ return getWishlist().length; }

function updateHeaderCounts(){
  $$('.js-cart-count').forEach(el => el.textContent = cartCount());
  $$('.js-wishlist-count').forEach(el => el.textContent = wishlistCount());
}

function toggleTheme(){
  const root = document.documentElement;
  const isDark = root.classList.toggle('dark');
  localStorage.setItem('phonix-theme', isDark ? 'dark' : 'light');
  const btn = $('.js-theme');
  if(btn) btn.textContent = isDark ? '☀️' : '🌙';
}
function initTheme(){
  const pref = localStorage.getItem('phonix-theme');
  if(pref === 'dark') document.documentElement.classList.add('dark');
  const btn = $('.js-theme');
  if(btn) btn.textContent = document.documentElement.classList.contains('dark') ? '☀️' : '🌙';
}
function initHeader(){
  const menu = $('.mobile-nav');
  const toggle = $('.js-mobile-toggle');
  if(toggle && menu) toggle.addEventListener('click', () => menu.classList.toggle('open'));
  $('.js-theme')?.addEventListener('click', toggleTheme);
  $$('.js-search-form').forEach(form => {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const input = form.querySelector('input[name="q"]');
      const q = input?.value?.trim();
      location.href = `search.html?q=${encodeURIComponent(q || '')}`;
    });
  });
  updateHeaderCounts();
}

function isInWishlist(id){ return getWishlist().includes(id); }
function toggleWishlist(id){
  const list = getWishlist();
  const idx = list.indexOf(id);
  idx >= 0 ? list.splice(idx, 1) : list.unshift(id);
  setWishlist(list);
  renderPage();
}
function addToCart(id, qty=1){
  const cart = getCart();
  const found = cart.find(i => i.id === id);
  if(found) found.qty += qty;
  else cart.push({id, qty});
  setCart(cart);
  showToast(`${slugToTitle(id)} added to cart.`);
}
function setQty(id, qty){
  const cart = getCart().map(item => item.id === id ? {...item, qty:Math.max(1, qty)} : item);
  setCart(cart);
  renderPage();
}
function removeFromCart(id){
  setCart(getCart().filter(i => i.id !== id));
  renderPage();
}
function showToast(text){
  const toast = $('.js-toast');
  if(!toast) return;
  toast.textContent = text;
  toast.classList.add('show');
  toast.style.display='block';
  setTimeout(()=>{ toast.classList.remove('show'); toast.style.display='none'; }, 1800);
}

function productVisualClass(p){
  if(p.category === 'iphone') return 'phone';
  if(p.category === 'accessories') return 'accessories';
  if(p.category === 'wearables') return 'watch';
  if(p.category === 'android') return 'phone';
  return 'phone';
}

function productCard(p){
  return `
  <article class="product-card">
    <div class="product-top">
      <span class="badge">${p.badge || p.brand}</span>
      <button class="icon-btn" title="Wishlist" data-wishlist="${p.id}" style="position:relative">${isInWishlist(p.id) ? '♥' : '♡'}</button>
    </div>
    <a href="product.html?id=${p.id}">
      <div class="device-visual ${productVisualClass(p)}"><span>${p.name}</span></div>
    </a>
    <div class="product-meta">
      <div class="row gap"><strong class="product-title">${p.name}</strong></div>
      <small>${p.desc}</small>
    </div>
    <div class="price-row">
      <div class="price"><s>${fmt(p.oldPrice || p.price)}</s><strong>${fmt(p.price)}</strong></div>
      <button class="primary-btn" data-add="${p.id}">Add to Cart</button>
    </div>
  </article>`;
}

function bindProductActions(scope=document){
  $$('[data-add]', scope).forEach(btn => btn.addEventListener('click', () => addToCart(btn.dataset.add, 1)));
  $$('[data-wishlist]', scope).forEach(btn => btn.addEventListener('click', () => toggleWishlist(btn.dataset.wishlist)));
}

function renderHome(){
  const target = $('#home-featured');
  if(target) {
    target.innerHTML = PRODUCTS.filter(p => ['iphone','android','accessories','wearables'].includes(p.category)).slice(0,4).map(productCard).join('');
    bindProductActions(target);
  }
  const newTarget = $('#home-new');
  if(newTarget){
    newTarget.innerHTML = PRODUCTS.slice(0,3).map(productCard).join('');
    bindProductActions(newTarget);
  }
}

function filterProducts(predicate){
  return PRODUCTS.filter(predicate);
}

function renderListing(category){
  const grid = $('#listing-grid');
  if(!grid) return;
  let list = PRODUCTS.filter(p => p.category === category);
  const brandChecks = $$('input[name="brand"]:checked').map(i => i.value);
  if(brandChecks.length) list = list.filter(p => brandChecks.includes(p.brand));
  const price = $('input[name="price"]:checked')?.value;
  if(price === 'under-15000') list = list.filter(p => p.price < 15000);
  if(price === '15000-29999') list = list.filter(p => p.price >= 15000 && p.price <= 29999);
  if(price === '30000+') list = list.filter(p => p.price >= 30000);
  const sort = $('#sort')?.value || 'featured';
  if(sort === 'price-asc') list.sort((a,b)=>a.price-b.price);
  if(sort === 'price-desc') list.sort((a,b)=>b.price-a.price);
  if(sort === 'name') list.sort((a,b)=>a.name.localeCompare(b.name));
  grid.innerHTML = list.map(productCard).join('') || `<div class="empty-state card" style="grid-column:1/-1">No products match the current filters.</div>`;
  bindProductActions(grid);
  $('#listing-count') && ($('#listing-count').textContent = `${list.length} products`);
}

function renderDeals(){
  const grid = $('#deals-grid');
  if(!grid) return;
  const deals = PRODUCTS.filter(p => (p.oldPrice || 0) > p.price).slice(0,5);
  grid.innerHTML = deals.map(productCard).join('');
  bindProductActions(grid);
}
function renderNewArrivals(){
  const hero = $('#new-hero');
  const grid = $('#new-grid');
  const list = PRODUCTS.slice(0,4);
  if(hero){
    const p = list[0];
    hero.innerHTML = `
      <div style="max-width:420px">
        <span class="badge">${p.badge}</span>
        <h2 style="font-size:2.5rem;letter-spacing:-.05em;margin:1rem 0 .7rem">${p.name}</h2>
        <p class="muted">${p.desc}</p>
      </div>
      <div class="hero-visual" style="right:-1rem;bottom:-1.5rem;width:min(42%,320px)"></div>
      <div class="row gap" style="margin-top:auto;justify-content:space-between;align-items:end;position:relative;z-index:2">
        <strong style="font-size:2rem">${fmt(p.price)}</strong>
        <div class="row gap">
          <a class="outline-btn" href="product.html?id=${p.id}">View Product</a>
          <button class="primary-btn" data-add="${p.id}">Pre-order</button>
        </div>
      </div>`;
    bindProductActions(hero);
  }
  if(grid){
    grid.innerHTML = list.slice(1).map(p => `
      <article class="card tile">
        <span class="badge">${p.badge}</span>
        <h3 style="font-size:1.4rem;margin:.85rem 0 .35rem">${p.name}</h3>
        <p class="muted">${p.desc}</p>
        <div class="device-visual ${productVisualClass(p)}" style="height:170px;margin:1rem 0"><span>${p.name}</span></div>
        <div class="row space-between">
          <strong>${fmt(p.price)}</strong>
          <a class="small-link" href="product.html?id=${p.id}">Explore →</a>
        </div>
      </article>`).join('');
  }
}

function renderBrands(){
  const list = $('#brand-grid');
  const showcase = $('#brand-showcase');
  if(list){
    const brands = Object.entries(BRANDS);
    list.innerHTML = brands.map(([name, blurb]) => `
      <article class="brand-card">
        <span class="badge">Brand</span>
        <h3 style="font-size:1.5rem;margin:.75rem 0 .35rem">${name}</h3>
        <p class="muted">${blurb}</p>
        <a href="search.html?q=${encodeURIComponent(name)}" class="small-link" style="display:inline-block;margin-top:1rem">Shop ${name} →</a>
      </article>`).join('');
  }
  if(showcase){
    const appleish = [
      {name:'iPhone 15 Pro Max', price:'From ₺59.999'},
      {name:'Samsung Galaxy S24 Ultra', price:'From ₺42.999'},
      {name:'AirPods Pro USB-C', price:'From ₺8.499'},
    ];
    showcase.innerHTML = appleish.map(p => `
      <article class="product-card">
        <span class="badge">Apple</span>
        <div class="device-visual audio" style="height:180px"><span>${p.name}</span></div>
        <strong class="product-title">${p.name}</strong>
        <small>${p.price}</small>
      </article>`).join('');
  }
}

function renderProduct(){
  const target = $('#product-view');
  if(!target) return;
  const id = qs.get('id') || 'samsung-galaxy-s24-ultra';
  const p = PRODUCTS.find(x => x.id === id) || PRODUCTS[0];
  target.innerHTML = `
    <section class="gallery-card card">
      <div class="gallery-visual"><span>${p.name}</span></div>
      <div class="thumbs">
        <div class="thumb"></div><div class="thumb"></div><div class="thumb"></div><div class="thumb"></div>
      </div>
    </section>
    <section class="detail-card card">
      <span class="badge">${p.badge || 'In Stock'}</span>
      <h1 style="font-size:3rem;letter-spacing:-.06em;margin:.8rem 0 .4rem">${p.name}</h1>
      <p class="muted" style="font-size:1.05rem;line-height:1.75">${p.desc}</p>
      <div class="rating-row"><span class="stars">★★★★★</span><span>${p.rating.toFixed(1)} · 128 reviews</span></div>
      <div style="padding:1rem 0;border-top:1px solid var(--line);margin-top:1rem">
        <div class="small" style="text-transform:uppercase;letter-spacing:.08em;margin-bottom:.55rem">Storage Capacity</div>
        <div class="option-buttons">${(p.storage || ['256GB','512GB']).map((s,i) => `<button class="${i===0?'active':''}">${s}</button>`).join('')}</div>
      </div>
      <div style="padding:1rem 0;border-top:1px solid var(--line)">
        <div class="small" style="text-transform:uppercase;letter-spacing:.08em;margin-bottom:.55rem">Color Finish</div>
        <div class="option-buttons">${(p.colors || ['Silver','Black']).map((c,i) => `<button class="${i===0?'active':''}">${c}</button>`).join('')}</div>
      </div>
      <div class="row space-between" style="align-items:center;margin-top:1rem">
        <div>
          <div class="small">Price</div>
          <strong style="font-size:2.2rem;letter-spacing:-.06em">${fmt(p.price)}</strong>
        </div>
        <div class="qty">
          <button class="js-minus">−</button>
          <strong class="js-qty">1</strong>
          <button class="js-plus">+</button>
        </div>
      </div>
      <div class="row gap" style="margin-top:1rem;flex-wrap:wrap">
        <button class="primary-btn js-buy">Add to Cart</button>
        <button class="outline-btn" data-wishlist="${p.id}">${isInWishlist(p.id) ? 'Remove from Wishlist' : 'Add to Wishlist'}</button>
      </div>
      <div class="promo-strip card" style="margin-top:1rem">
        <div>
          <strong>Trade in your old device</strong>
          <p class="muted" style="margin:.35rem 0 0">Get upgrade support toward your next phone purchase in Turkey.</p>
        </div>
        <a class="chip-btn" href="deals.html">Calculate Value</a>
      </div>
    </section>`;
  let qty = 1;
  const qtyEl = $('.js-qty', target);
  $('.js-minus', target).addEventListener('click', () => { qty = Math.max(1, qty-1); qtyEl.textContent = qty; });
  $('.js-plus', target).addEventListener('click', () => { qty += 1; qtyEl.textContent = qty; });
  $('.js-buy', target).addEventListener('click', () => addToCart(p.id, qty));
  bindProductActions(target);
}

function renderSearch(){
  const q = (qs.get('q') || '').trim();
  const title = $('#search-title');
  const count = $('#search-count');
  const grid = $('#search-grid');
  if(title) title.textContent = q ? `Results for "${q}"` : 'Search Results';
  if(!grid) return;
  const needle = q.toLowerCase();
  let list = PRODUCTS.filter(p => !q || [p.name, p.brand, p.category, p.desc].join(' ').toLowerCase().includes(needle));
  if(count) count.textContent = `${list.length} matching products`;
  grid.innerHTML = list.map(productCard).join('') || `<div class="empty-state card" style="grid-column:1/-1">No matching products were found. Try another keyword.</div>`;
  bindProductActions(grid);
}

function renderWishlist(){
  const grid = $('#wishlist-grid');
  if(!grid) return;
  const items = getWishlist().map(id => PRODUCTS.find(p => p.id === id)).filter(Boolean);
  if(!items.length){
    grid.innerHTML = `<div class="card empty-state"><h3>Nothing here yet</h3><p class="muted">Your favorite items will appear here once you start exploring the store.</p><a class="primary-btn" href="products.php" style="display:inline-block;margin-top:1rem">Continue Shopping</a></div>`;
    return;
  }
  grid.innerHTML = items.map(p => `
    <article class="product-card">
      <div class="row space-between">
        <span class="badge">${p.category}</span>
        <button class="icon-btn" data-wishlist="${p.id}">♥</button>
      </div>
      <div class="device-visual ${productVisualClass(p)}"><span>${p.name}</span></div>
      <strong class="product-title">${p.name}</strong>
      <small>${p.desc}</small>
      <div class="row gap">
        <button class="primary-btn" data-add="${p.id}">Move to Cart</button>
        <a class="outline-btn" href="product.html?id=${p.id}">View</a>
      </div>
    </article>`).join('');
  bindProductActions(grid);
}

function renderAccount(){
  const stats = $('#account-stats');
  const ordersWrap = $('#account-orders');
  if(stats){
    stats.innerHTML = `
      <article class="stats-card"><h3>Total Orders</h3><strong>${ORDERS.length}</strong><small>Across all devices and accessories</small></article>
      <article class="stats-card"><h3>Wishlist Items</h3><strong>${wishlistCount()}</strong><small>Saved for later consideration</small></article>
      <article class="stats-card"><h3>Cart Items</h3><strong>${cartCount()}</strong><small>Ready for secure checkout</small></article>`;
  }
  if(ordersWrap){
    ordersWrap.innerHTML = ORDERS.map(o => {
      const cls = o.status === 'Delivered' ? 'delivered' : (o.status === 'In Transit' ? 'transit' : 'processing');
      return `<div class="order-item">
        <div>
          <div class="row gap"><strong>${o.id}</strong><span class="status ${cls}">${o.status}</span></div>
          <div style="margin-top:.45rem">${o.name}</div>
          <div class="muted small" style="margin-top:.25rem">${o.date}</div>
        </div>
        <div style="text-align:right">
          <strong>${o.price}</strong><div style="margin-top:.55rem"><button class="outline-btn">Track Order</button></div>
        </div>
      </div>`;
    }).join('');
  }
}

function renderSupport(){
  const faqWrap = $('#faq-list');
  if(faqWrap){
    faqWrap.innerHTML = FAQS.map(([q,a],i) => `
      <article class="faq-item ${i===1 ? 'open' : ''}">
        <div class="faq-trigger"><strong>${q}</strong><span>${i===1 ? '−' : '+'}</span></div>
        <div class="faq-content">${a}</div>
      </article>`).join('');
    $$('.faq-item', faqWrap).forEach(item => {
      item.querySelector('.faq-trigger').addEventListener('click', ()=> item.classList.toggle('open'));
    });
  }
}

function renderCheckout(){
  const cartWrap = $('#checkout-items');
  const summary = $('#checkout-summary');
  const notice = $('#checkout-success');
  const items = getCart().map(item => ({...PRODUCTS.find(p => p.id === item.id), qty:item.qty})).filter(Boolean);
  const subtotal = items.reduce((sum, i) => sum + i.price * i.qty, 0);
  const taxes = subtotal * 0.085;
  const total = subtotal + taxes;
  if(cartWrap){
    cartWrap.innerHTML = items.map(i => `
      <div class="order-item">
        <div class="row gap">
          <div class="device-visual ${productVisualClass(i)}" style="width:110px;height:110px;flex:0 0 110px"><span style="padding-top:54px;font-size:.8rem">${i.name}</span></div>
          <div>
            <strong>${i.name}</strong>
            <div class="muted small">${i.category} • ${i.brand}</div>
            <div class="qty" style="margin-top:.7rem">
              <button data-dec="${i.id}">−</button>
              <strong>${i.qty}</strong>
              <button data-inc="${i.id}">+</button>
            </div>
          </div>
        </div>
        <div style="text-align:right">
          <strong>${fmt(i.price * i.qty)}</strong>
          <div style="margin-top:.7rem"><button class="ghost-btn" data-remove="${i.id}">Remove</button></div>
        </div>
      </div>`).join('') || `<div class="empty-state card">Your cart is empty. <a class="small-link" href="products.php">Browse products</a></div>`;
    $$('[data-dec]', cartWrap).forEach(btn => btn.addEventListener('click', ()=> {
      const item = getCart().find(i => i.id === btn.dataset.dec); if(item) setQty(item.id, item.qty - 1);
    }));
    $$('[data-inc]', cartWrap).forEach(btn => btn.addEventListener('click', ()=> {
      const item = getCart().find(i => i.id === btn.dataset.inc); if(item) setQty(item.id, item.qty + 1);
    }));
    $$('[data-remove]', cartWrap).forEach(btn => btn.addEventListener('click', ()=> removeFromCart(btn.dataset.remove)));
  }
  if(summary){
    summary.innerHTML = `
      <div class="summary-line"><span>Subtotal (${items.reduce((a,b)=>a+b.qty,0)} items)</span><strong>${fmt(subtotal)}</strong></div>
      <div class="summary-line"><span>Shipping</span><strong>Free</strong></div>
      <div class="summary-line"><span>Estimated Taxes</span><strong>${fmt(taxes)}</strong></div>
      <div class="summary-total"><span>Total</span><span>${fmt(total)}</span></div>`;
  }
  $('#checkout-form')?.addEventListener('submit', e => {
    e.preventDefault();
    if(notice){ notice.classList.add('show'); notice.textContent = 'Order completed successfully. Your cart has been cleared.'; }
    setCart([]);
  });
}

function renderAdmin(){
  const metrics = $('#admin-metrics');
  const table = $('#admin-orders');
  const quick = $('#admin-quick');
  if(metrics){
    metrics.innerHTML = `
      <article class="metric-card"><h3>Total Revenue</h3><strong>$128,430</strong><div class="delta">+12.5% from last month</div></article>
      <article class="metric-card"><h3>Total Orders</h3><strong>2,486</strong><div class="delta">+8.2% from last month</div></article>
      <article class="metric-card"><h3>Active Products</h3><strong>${PRODUCTS.length}</strong><div class="delta">Products ready</div></article>
      <article class="metric-card"><h3>New Customers</h3><strong>184</strong><div class="delta">+4.1% from last month</div></article>`;
  }
  if(table){
    table.innerHTML = ORDERS.map(o => `<tr><td>${o.id}</td><td>${o.name}</td><td>${o.status}</td><td>${o.price}</td></tr>`).join('');
  }
  if(quick){
    quick.innerHTML = PRODUCTS.slice(0,4).map(p => `
      <div class="order-item">
        <div><strong>${p.name}</strong><div class="muted small">Stock: ${Math.floor(25 + Math.random()*100)}</div></div>
        <button class="outline-btn">Edit</button>
      </div>`).join('');
  }
}

function renderAuth(){
  $('#auth-form')?.addEventListener('submit', e => {
    e.preventDefault();
    showToast('Demo sign-in complete.');
    setTimeout(()=> location.href='account.html', 600);
  });
}

function renderPage(){
  const page = document.body.dataset.page;
  if(page === 'home') renderHome();
  if(page === 'smartphones') renderListing('smartphones');
  if(page === 'accessories') renderListing('accessories');
  if(page === 'deals') renderDeals();
  if(page === 'new-arrivals') renderNewArrivals();
  if(page === 'brands') renderBrands();
  if(page === 'product') renderProduct();
  if(page === 'search') renderSearch();
  if(page === 'wishlist') renderWishlist();
  if(page === 'account') renderAccount();
  if(page === 'support') renderSupport();
  if(page === 'checkout') renderCheckout();
  if(page === 'admin') renderAdmin();
  if(page === 'auth') renderAuth();

  if(page === 'smartphones' || page === 'accessories'){
    $$('input[name="brand"], input[name="price"], #sort').forEach(el => el.addEventListener('change', () => renderListing(page)));
  }
}
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initHeader();
  renderPage();
});
