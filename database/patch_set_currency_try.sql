-- Apply this once on existing installations if the site_settings table already contains USD.
UPDATE site_settings
SET setting_value = 'TRY'
WHERE setting_key = 'site_currency';
