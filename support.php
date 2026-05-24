<?php
require __DIR__ . '/includes/bootstrap.php';

store_ensure_support_tables($pdo);

$pageTitle = $siteName . ' - Support Center';
$pageDescription = 'Search the Phonix knowledge base, find support answers, and contact our team.';
$currentPage = 'support';
$includeSiteCss = false;
$pageStyles = ['assets/css/support.css'];
$supportEmail = store_setting($siteSettings, 'support_email', 'support@phonix.com');
$supportPhone = store_setting($siteSettings, 'support_phone', '1-800-PHONIX-1');
$supportChatLabel = store_setting($siteSettings, 'support_chat_label', 'Available 24/7');

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $honeypot = trim((string) ($_POST['company'] ?? ''));
    if ($honeypot !== '') {
        flash_set('success', 'Thanks. Your message was received.');
        redirect_to(site_url('support') . '#contact-support');
    }

    try {
        store_create_support_message($pdo, [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'order_number' => $_POST['order_number'] ?? '',
            'subject' => $_POST['subject'] ?? '',
            'message' => $_POST['message'] ?? '',
            'source_page' => 'support.php',
        ]);
        flash_set('success', 'Thanks. Your message was sent to support.');
        redirect_to(site_url('support') . '#contact-support');
    } catch (Throwable $e) {
        error_log('[Phonix support form] ' . $e->getMessage());
        set_old_input($_POST);
        flash_set('error', app_debug() ? $e->getMessage() : 'Your message could not be sent. Please review the form and try again.');
        redirect_to(site_url('support') . '#contact-support');
    }
}

$faqSearch = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);
$faqs = fetch_public_support_faqs($pdo, $faqSearch);
$faqGroups = [];
foreach ($faqs as $faq) {
    $category = trim((string) ($faq['category'] ?? '')) ?: 'General';
    $faqGroups[$category][] = $faq;
}

require __DIR__ . '/includes/partials_header.php';
?>
<section class="w-full max-w-container-max mx-auto px-gutter pt-section-padding pb-[80px] text-center">
    <p class="support-kicker">Support Center</p>
    <h1 class="font-h1 text-h1 text-on-surface mb-6">How can we help?</h1>
    <p class="font-body-lg text-body-lg text-on-surface-variant mb-12 max-w-2xl mx-auto">
        Search phone delivery, warranty, return, and payment guidance, or send a message directly to the support inbox.
    </p>
    <form class="max-w-3xl mx-auto relative glass-panel rounded-full overflow-hidden flex items-center p-2 focus-within:ring-2 focus-within:ring-primary/20 transition-all support-search" action="<?= e(site_url('support')) ?>" method="get">
        <span class="material-symbols-outlined text-outline ml-4 mr-2">search</span>
        <input name="q" value="<?= e($faqSearch) ?>" class="w-full bg-transparent border-none focus:ring-0 font-body-md text-body-md text-on-surface placeholder:text-outline-variant py-4 px-2 outline-none" placeholder="Search FAQs, returns, warranty, shipping..." type="search"/>
        <button class="bg-primary text-on-primary rounded-full px-8 py-4 font-label-caps text-label-caps uppercase hover:opacity-90 transition-opacity whitespace-nowrap" type="submit">
            Search
        </button>
    </form>
</section>

<section class="w-full max-w-container-max mx-auto px-gutter pb-section-padding">
    <?php if ($flashMessages !== []): ?>
        <div class="support-flash-stack">
            <?php foreach ($flashMessages as $flash): ?>
                <div class="support-flash <?= ($flash['type'] ?? '') === 'success' ? 'is-success' : 'is-error' ?>"><?= e((string) ($flash['message'] ?? '')) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-element-gap">
        <a class="glass-panel rounded-lg p-8 flex flex-col items-start hover:-translate-y-1 transition-transform duration-300 group cursor-pointer" href="<?= e(site_url('account')) ?>">
            <div class="support-icon-bubble"><span class="material-symbols-outlined">local_shipping</span></div>
            <h3 class="font-h3 text-h3 text-on-surface mb-2">Order Status</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">Track your phone package or view purchase history.</p>
        </a>
        <a class="glass-panel rounded-lg p-8 flex flex-col items-start hover:-translate-y-1 transition-transform duration-300 group cursor-pointer" href="#faq-returns">
            <div class="support-icon-bubble"><span class="material-symbols-outlined">assignment_return</span></div>
            <h3 class="font-h3 text-h3 text-on-surface mb-2">Returns</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">Read phone return rules or ask for support help.</p>
        </a>
        <a class="glass-panel rounded-lg p-8 flex flex-col items-start hover:-translate-y-1 transition-transform duration-300 group cursor-pointer" href="#faq-warranty">
            <div class="support-icon-bubble"><span class="material-symbols-outlined">verified_user</span></div>
            <h3 class="font-h3 text-h3 text-on-surface mb-2">Warranty</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">Check phone warranty and service guidance from the FAQ.</p>
        </a>
        <a class="glass-panel rounded-lg p-8 flex flex-col items-start hover:-translate-y-1 transition-transform duration-300 group cursor-pointer" href="#contact-support">
            <div class="support-icon-bubble"><span class="material-symbols-outlined">support_agent</span></div>
            <h3 class="font-h3 text-h3 text-on-surface mb-2">Contact</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">Send a message to the Phonix support team.</p>
        </a>
    </div>
</section>

<section class="w-full max-w-container-max mx-auto px-gutter pb-section-padding">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
        <div class="lg:col-span-8">
            <div class="support-section-title">
                <p class="support-kicker">Knowledge base</p>
                <h2 class="font-h2 text-h2 text-on-surface">Frequently Asked Questions</h2>
                <?php if ($faqSearch !== ''): ?>
                    <a class="support-clear-search" href="<?= e(site_url('support')) ?>">Clear search</a>
                <?php endif; ?>
            </div>

            <?php if (!$faqGroups): ?>
                <div class="glass-panel rounded-lg p-8 support-empty">
                    <span class="material-symbols-outlined">help</span>
                    <strong>No matching FAQs</strong>
                    <p>Try a different keyword, or send a message and support will reply from the inbox.</p>
                </div>
            <?php else: ?>
                <div class="space-y-8">
                    <?php foreach ($faqGroups as $category => $items): ?>
                        <section class="support-faq-group" id="faq-<?= e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $category) ?: 'general')) ?>">
                            <h3><?= e($category) ?></h3>
                            <div class="space-y-4">
                                <?php foreach ($items as $index => $faq): ?>
                                    <details class="glass-panel rounded-lg support-faq-item" <?= $index === 0 ? 'open' : '' ?>>
                                        <summary>
                                            <span><?= e($faq['question']) ?></span>
                                            <span class="material-symbols-outlined">expand_more</span>
                                        </summary>
                                        <p><?= nl2br(e((string) $faq['answer'])) ?></p>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="lg:col-span-4">
            <div class="glass-panel rounded-[2rem] p-8 sticky top-[100px] support-contact-card">
                <p class="support-kicker">Direct help</p>
                <h2 class="font-h3 text-h3 text-on-surface mb-4">Still need help?</h2>
                <p class="font-body-md text-body-md text-on-surface-variant mb-8">
                    Send a message and the Phonix support team will get back to you.
                </p>
                <div class="space-y-5 mb-8">
                    <div class="support-contact-row"><span class="material-symbols-outlined">chat</span><div><strong>Live Chat</strong><small><?= e($supportChatLabel) ?></small></div></div>
                    <div class="support-contact-row"><span class="material-symbols-outlined">mail</span><div><strong>Email Us</strong><small><?= e($supportEmail) ?></small></div></div>
                    <div class="support-contact-row"><span class="material-symbols-outlined">call</span><div><strong>Call Us</strong><small><?= e($supportPhone) ?></small></div></div>
                </div>

                <form id="contact-support" class="support-form" method="post" action="<?= e(site_url('support')) ?>#contact-support">
                    <?= csrf_field() ?>
                    <label class="support-hp-field"><span>Company</span><input name="company" tabindex="-1" autocomplete="off"></label>
                    <label><span>Name</span><input name="name" value="<?= e(old_input('name')) ?>" required autocomplete="name"></label>
                    <label><span>Email</span><input type="email" name="email" value="<?= e(old_input('email')) ?>" required autocomplete="email"></label>
                    <label><span>Phone</span><input name="phone" value="<?= e(old_input('phone')) ?>" autocomplete="tel"></label>
                    <label><span>Order number</span><input name="order_number" value="<?= e(old_input('order_number')) ?>" placeholder="PX-..."></label>
                    <label><span>Subject</span><input name="subject" value="<?= e(old_input('subject')) ?>" placeholder="Shipping, return, warranty, payment..."></label>
                    <label><span>Message</span><textarea name="message" rows="5" required><?= e(old_input('message')) ?></textarea></label>
                    <button class="support-submit" type="submit">Send message</button>
                </form>
            </div>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/partials_footer.php'; ?>
