</main>
<footer class="bg-slate-50 dark:bg-slate-950 font-['Manrope'] text-sm text-slate-500 dark:text-slate-400 w-full pt-24 pb-12 border-t border-slate-100 dark:border-slate-800 mt-auto">
    <div class="max-w-7xl mx-auto px-12 grid grid-cols-1 md:grid-cols-4 gap-12">
        <div>
            <a class="text-xl font-bold text-slate-900 dark:text-white mb-4 block hover:opacity-80 transition-opacity" href="<?= e(site_url('home')) ?>"><?= e($siteName) ?></a>
            <p>© <?= date('Y') ?> <?= e($siteName) ?>. <?= e(store_setting($siteSettings ?? [], 'footer_tagline', 'Official phones, trusted warranty, fast delivery across Turkey.')) ?></p>
            <p class="mt-3"><?= e(store_setting($siteSettings ?? [], 'support_email', 'support@phonixturkiye.com')) ?><?php $footerPhone = store_setting($siteSettings ?? [], 'support_phone', ''); ?><?php if ($footerPhone !== ''): ?> · <?= e($footerPhone) ?><?php endif; ?></p>
        </div>
        <div class="flex flex-col space-y-3">
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('support')) ?>">Support</a>
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('brands')) ?>">Brands</a>
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('deals')) ?>">Deals</a>
        </div>
        <div class="flex flex-col space-y-3">
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('products')) ?>">All Phones</a>
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('products', ['category' => 'iphone'])) ?>">iPhone</a>
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('products', ['category' => 'accessories'])) ?>">Accessories</a>
        </div>
        <div class="flex flex-col space-y-3">
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('account')) ?>">Account</a>
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('wishlist')) ?>">Wishlist</a>
            <a class="text-slate-500 hover:text-blue-500 transition-colors hover:translate-x-1 transition-transform duration-200 opacity-100 hover:opacity-70" href="<?= e(site_url('checkout')) ?>">Checkout</a>
        </div>
    </div>
</footer>
<?php $siteJsUrl = function_exists('phonix_asset_url') ? phonix_asset_url('assets/js/site.js') : site_url('assets/js/site.js'); ?>
<script src="<?= e($siteJsUrl) ?>"></script>
<?php foreach (($pageScripts ?? []) as $pageScript): ?>
    <script src="<?= e(function_exists('phonix_asset_url') ? phonix_asset_url((string) $pageScript) : site_url((string) $pageScript)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
