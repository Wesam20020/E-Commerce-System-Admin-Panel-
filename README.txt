PHONIX TÜRKİYE PHONE MARKETPLACE PATCH
======================================

Upload and overwrite these files over your current project.

What this patch does:
- Keeps the public language English.
- Repositions the store as a Turkey-focused smartphone marketplace.
- Changes the default currency to TRY and formats TRY prices as ₺ with Turkish separators.
- Updates the default catalog to iPhone, Samsung, Xiaomi, Honor, vivo, phone accessories, and wearables.
- Updates homepage, products, deals, new arrivals, search, wishlist, checkout, support, footer, navigation, and seeded SEO/support text.
- Adds local SVG product images for the new phone/accessory catalog.
- Redirects the old smartphones.html route to the full products page instead of an empty category.

After upload:
1) Upload the changed files preserving the same folder structure.
2) If you use the database-backed catalog, run database/seed.sql once from phpMyAdmin.
3) Open your domain root and test:
   - /products.php
   - /products.php?category=iphone
   - /products.php?category=android
   - /product.php?slug=samsung-galaxy-s24-ultra
   - /search.php?q=iphone
