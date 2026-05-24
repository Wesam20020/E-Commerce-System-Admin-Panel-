Phonix Türkiye - Real Phone Demo Data Patch

What this patch includes:
- database/patch_real_phone_demo_data.sql
  Import this into your existing database to add/update real-world demo products.
  It is written with ON DUPLICATE KEY UPDATE, so it can safely update existing product rows by slug.

- database/seed.sql
  Replacement seed file for fresh installations.

- assets/images/*.svg
  Clean local demo product illustrations matching the inserted product image paths.

Included demo products:
- Apple iPhone 15 Pro Max
- Apple iPhone 15
- Samsung Galaxy S24 Ultra
- Samsung Galaxy A55 5G
- Samsung Galaxy S23 FE
- Xiaomi 14 5G
- Xiaomi Redmi Note 13 Pro+
- HONOR 90 5G
- vivo V30 5G
- Apple AirPods Pro USB-C
- Samsung 25W USB-C Charger
- MagSafe Compatible Clear Case
- Galaxy Watch 6 Classic

Important:
- All content remains in English.
- Currency is TRY and prices are demo marketplace prices, not official live retail prices.
- Product names and core specs are based on real device families for testing UI, filters, product pages, checkout, and admin workflows.

How to apply:
1. Upload the assets/images files to public_html/assets/images/.
2. Import database/patch_real_phone_demo_data.sql into your existing database from phpMyAdmin.
3. If you are installing from zero, you can use database/seed.sql as the new seed file instead.
