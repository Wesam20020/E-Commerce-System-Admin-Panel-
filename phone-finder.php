<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/phone_matcher.php';
require_once __DIR__ . '/includes/ai_phone_recommender.php';

phone_finder_ensure_tables($pdo);

$currentPage = 'phone_finder';
$pageTitle = $siteName . ' | Find My Phone';
$pageDescription = 'Tell us what you need and Phonix will help you choose a suitable phone available in Turkey.';
$pageStyles = ['assets/css/phone_finder.css'];
$pageScripts = ['assets/js/phone_finder.js'];
$finderBrands = ['any' => 'No preference', 'Apple' => 'Apple', 'Samsung' => 'Samsung', 'Xiaomi' => 'Xiaomi', 'Honor' => 'Honor', 'vivo' => 'vivo', 'OPPO' => 'OPPO', 'realme' => 'realme'];
require __DIR__ . '/includes/partials_header.php';
?>
<section class="section phone-finder-hero">
    <div class="container phone-finder-hero__grid">
        <div class="phone-finder-hero__copy">
            <div class="breadcrumbs"><a href="<?= e(site_url('home')) ?>">Home</a> / <span>Find My Phone</span></div>
            <span class="phone-finder-pill"><span class="material-symbols-outlined" aria-hidden="true">tune</span>Phone buying guide</span>
            <h1>Tell us what you need. We’ll point you to the right phone.</h1>
            <p>Choose your budget, brand preference, and daily priorities. Phonix compares them with suitable options and helps you make a clearer buying decision.</p>
            <div class="phone-finder-hero__actions">
                <a class="primary-btn" href="#phoneFinderForm">Start now</a>
                <a class="secondary-btn" href="<?= e(site_url('products')) ?>">Browse phones</a>
            </div>
        </div>
        <div class="phone-finder-hero__panel glass-panel">
            <span class="material-symbols-outlined" aria-hidden="true">support_agent</span>
            <h2>Simple buying steps</h2>
            <ol>
                <li><strong>Tell us your needs</strong><span>Select your budget, brand, and the features that matter most.</span></li>
                <li><strong>Compare suitable phones</strong><span>Review clear options with prices, specs, and why they may suit you.</span></li>
                <li><strong>Ask for availability</strong><span>Send your preferred option and our team will follow up.</span></li>
            </ol>
        </div>
    </div>
</section>

<section class="section phone-finder-section" id="phoneFinderApp" data-phone-finder-endpoint="<?= e(site_url('api_phone_finder')) ?>">
    <div class="container phone-finder-layout">
        <form class="phone-finder-form glass-panel" id="phoneFinderForm" data-phone-finder-form>
            <div class="phone-finder-form__head">
                <div>
                    <span class="phone-finder-eyebrow">Step 1</span>
                    <h2>Your ideal phone</h2>
                    <p>Keep it simple: budget, operating system, main use, and a few priorities.</p>
                </div>
                <span class="phone-finder-form__badge">Prices in TRY</span>
            </div>

            <div class="phone-finder-fields">
                <label class="phone-finder-field">
                    <span>Minimum budget</span>
                    <input type="number" name="budget_min" min="0" step="500" placeholder="Optional">
                </label>
                <label class="phone-finder-field">
                    <span>Maximum budget</span>
                    <input type="number" name="budget_max" min="0" step="500" placeholder="Example: 25000">
                </label>
                <label class="phone-finder-field">
                    <span>Operating system</span>
                    <select name="os">
                        <option value="any">No preference</option>
                        <option value="ios">iPhone / iOS</option>
                        <option value="android">Android</option>
                    </select>
                </label>
                <label class="phone-finder-field">
                    <span>Brand</span>
                    <select name="brand">
                        <?php foreach ($finderBrands as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="phone-finder-field">
                    <span>Main use</span>
                    <select name="use_case">
                        <option value="daily">Daily use</option>
                        <option value="camera">Camera and content</option>
                        <option value="gaming">Gaming and performance</option>
                        <option value="battery">Battery first</option>
                        <option value="study">Study</option>
                        <option value="business">Business</option>
                    </select>
                </label>
                <label class="phone-finder-field">
                    <span>Minimum storage</span>
                    <select name="storage_min">
                        <option value="64">64GB</option>
                        <option value="128" selected>128GB</option>
                        <option value="256">256GB</option>
                        <option value="512">512GB</option>
                        <option value="1024">1TB</option>
                    </select>
                </label>
                <label class="phone-finder-field">
                    <span>Battery priority</span>
                    <select name="battery_priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
                <label class="phone-finder-field">
                    <span>Camera priority</span>
                    <select name="camera_priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
                <label class="phone-finder-field">
                    <span>Performance priority</span>
                    <select name="performance_priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
                <label class="phone-finder-check">
                    <input type="checkbox" name="need_5g" value="1" checked>
                    <span><strong>5G required</strong><small>Prefer modern network support.</small></span>
                </label>
                <label class="phone-finder-check">
                    <input type="checkbox" name="warranty_required" value="1" checked>
                    <span><strong>Warranty is important</strong><small>Prioritize listings with warranty/support wording.</small></span>
                </label>
                <label class="phone-finder-field phone-finder-field--full">
                    <span>Extra notes</span>
                    <textarea name="notes" rows="3" placeholder="Example: I need a strong camera, good battery, and official warranty."></textarea>
                </label>
            </div>

            <button class="phone-finder-submit primary-btn" type="submit">
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                Find matching phones
            </button>
        </form>

        <div class="phone-finder-results" aria-live="polite">
            <div class="phone-finder-empty glass-panel" data-phone-finder-empty>
                <span class="material-symbols-outlined" aria-hidden="true">tune</span>
                <h2>Your matches will appear here</h2>
                <p>Start with your real needs. We’ll show suitable phones and helpful alternatives.</p>
            </div>
            <div class="phone-finder-state glass-panel" data-phone-finder-loading hidden>
                <span class="material-symbols-outlined" aria-hidden="true">progress_activity</span>
                <strong>Finding suitable phones...</strong>
                <p>Checking the best options for your preferences.</p>
            </div>
            <div class="phone-finder-result-stack" data-phone-finder-results hidden></div>
        </div>
    </div>
</section>

<div class="phone-finder-modal" data-phone-request-modal hidden>
    <div class="phone-finder-modal__overlay" data-phone-request-close></div>
    <section class="phone-finder-modal__card glass-panel" role="dialog" aria-modal="true" aria-labelledby="phoneRequestTitle">
        <button class="phone-finder-modal__close" type="button" data-phone-request-close aria-label="Close request form"><span class="material-symbols-outlined">close</span></button>
        <span class="phone-finder-eyebrow">Phone request</span>
        <h2 id="phoneRequestTitle">Request this phone</h2>
        <p data-phone-request-summary>Send this phone request to the Phonix team. We will review it and follow up.</p>
        <form data-phone-request-form>
            <label class="phone-finder-field">
                <span>Your name</span>
                <input type="text" name="customer_name" value="<?= e((string) ($currentUser['name'] ?? '')) ?>" placeholder="Optional">
            </label>
            <label class="phone-finder-field">
                <span>Phone / WhatsApp</span>
                <input type="text" name="customer_phone" placeholder="Optional">
            </label>
            <p class="phone-finder-muted">Share your contact details and our team will check availability for you.</p>
            <button class="primary-btn phone-finder-modal__submit" type="submit">Send request</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/includes/partials_footer.php'; ?>
