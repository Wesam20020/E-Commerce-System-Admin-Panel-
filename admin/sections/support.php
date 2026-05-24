<?php
require_once __DIR__ . '/../includes/layout.php';
$ctx = admin_boot('support');
$pdo = $ctx['pdo'];

store_ensure_support_tables($pdo);

$supportStatuses = ['new', 'open', 'resolved', 'archived'];

if (is_post_request()) {
    verify_csrf_or_fail($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['admin_action'] ?? '');
    try {
        if ($action === 'support_message_bulk') {
            $ids = admin_int_array_input('message_ids');
            $status = in_array($_POST['bulk_status'] ?? '', $supportStatuses, true) ? (string) $_POST['bulk_status'] : '';
            if (!$ids) {
                throw new RuntimeException('Select at least one support message.');
            }
            if ($status === '') {
                throw new RuntimeException('Choose a valid message status.');
            }
            [$placeholders, $bulkParams] = admin_sql_placeholders($ids, 'message');
            $readSql = $status === 'new' ? 'read_at = NULL' : 'read_at = COALESCE(read_at, NOW())';
            $pdo->prepare("UPDATE support_messages SET status = :status, {$readSql} WHERE id IN (" . implode(',', $placeholders) . ')')->execute(['status' => $status] + $bulkParams);
            admin_log_activity($pdo, 'support_messages_bulk_updated', 'support_message', null, count($ids) . ' messages set to ' . $status);
            flash_set('success', count($ids) . ' support messages updated.');
        } elseif ($action === 'faq_create' || $action === 'faq_update') {
            $question = admin_clean_text('question', 255);
            $answer = trim((string) ($_POST['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                throw new RuntimeException('FAQ question and answer are required.');
            }
            $payload = [
                'question' => $question,
                'answer' => mb_substr($answer, 0, 5000),
                'category' => admin_clean_text('category', 120) ?: null,
                'sort_order' => admin_int_input('sort_order'),
                'is_active' => admin_bool_from_post('is_active'),
            ];
            if ($action === 'faq_create') {
                $pdo->prepare('INSERT INTO support_faqs (question, answer, category, sort_order, is_active) VALUES (:question, :answer, :category, :sort_order, :is_active)')->execute($payload);
                $faqId = (int) $pdo->lastInsertId();
                admin_log_activity($pdo, 'faq_created', 'support_faq', $faqId, $question);
                flash_set('success', 'FAQ created.');
            } else {
                $payload['id'] = admin_int_input('faq_id');
                $pdo->prepare('UPDATE support_faqs SET question=:question, answer=:answer, category=:category, sort_order=:sort_order, is_active=:is_active WHERE id=:id LIMIT 1')->execute($payload);
                admin_log_activity($pdo, 'faq_updated', 'support_faq', (int) $payload['id'], $question);
                flash_set('success', 'FAQ updated.');
            }
        } elseif ($action === 'faq_delete') {
            $faqId = admin_int_input('faq_id');
            $pdo->prepare('DELETE FROM support_faqs WHERE id = :id LIMIT 1')->execute(['id' => $faqId]);
            admin_log_activity($pdo, 'faq_deleted', 'support_faq', $faqId, 'FAQ removed.');
            flash_set('success', 'FAQ deleted.');
        } elseif ($action === 'support_message_update') {
            $status = in_array($_POST['status'] ?? '', $supportStatuses, true) ? (string) $_POST['status'] : 'new';
            $messageId = admin_int_input('message_id');
            $adminNote = mb_substr(trim((string) ($_POST['admin_note'] ?? '')), 0, 3000) ?: null;
            $readSql = $status === 'new' ? 'read_at = NULL' : 'read_at = COALESCE(read_at, NOW())';
            $stmt = $pdo->prepare("UPDATE support_messages SET status = :status, admin_note = :admin_note, {$readSql} WHERE id = :id LIMIT 1");
            $stmt->execute(['status' => $status, 'admin_note' => $adminNote, 'id' => $messageId]);
            admin_log_activity($pdo, 'support_message_updated', 'support_message', $messageId, 'Status: ' . $status);
            flash_set('success', 'Support message updated.');
        } else {
            throw new RuntimeException('Unknown support action.');
        }
    } catch (Throwable $e) {
        admin_flash_from_exception($e);
    }
    admin_redirect('support', ['status' => (string) ($_GET['status'] ?? 'all')]);
}

$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, array_merge(['all'], $supportStatuses), true)) {
    $statusFilter = 'all';
}
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);

$messageWhere = [];
$messageParams = [];
if ($statusFilter !== 'all') {
    $messageWhere[] = 'status = :status';
    $messageParams['status'] = $statusFilter;
}
if ($search !== '') {
    $messageWhere[] = '(name LIKE :q OR email LIKE :q OR subject LIKE :q OR message LIKE :q OR order_number LIKE :q)';
    $messageParams['q'] = '%' . $search . '%';
}
$messageSql = 'SELECT * FROM support_messages';
if ($messageWhere) {
    $messageSql .= ' WHERE ' . implode(' AND ', $messageWhere);
}
$messageSql .= ' ORDER BY created_at DESC, id DESC LIMIT 80';

$faqs = admin_rows($pdo, 'SELECT * FROM support_faqs ORDER BY sort_order ASC, id DESC');
$messages = admin_rows($pdo, $messageSql, $messageParams);
$counts = [
    'new' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'new'"),
    'open' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'open'"),
    'resolved' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'resolved'"),
    'archived' => (int) admin_scalar($pdo, "SELECT COUNT(*) FROM support_messages WHERE status = 'archived'"),
    'faqs' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM support_faqs'),
    'published_faqs' => (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM support_faqs WHERE is_active = 1'),
];

admin_header('Support / FAQ', 'Manage public FAQs and process incoming customer support messages from the storefront.', 'support');
?>
<section class="admin-metrics-grid" aria-label="Support metrics">
    <?php admin_metric_card('New messages', (string) $counts['new'], 'mark_email_unread', 'Needs first response'); ?>
    <?php admin_metric_card('Open messages', (string) $counts['open'], 'support_agent', 'In progress'); ?>
    <?php admin_metric_card('Resolved', (string) $counts['resolved'], 'task_alt', 'Completed'); ?>
    <?php admin_metric_card('Published FAQs', (string) $counts['published_faqs'], 'quiz', $counts['faqs'] . ' total FAQs'); ?>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head">
        <div><p class="admin-eyebrow">Inbox</p><h2>Support messages</h2></div>
        <a class="admin-text-link" href="<?= e(admin_root_url('support.php')) ?>">Open public support page</a>
    </div>

    <form method="get" class="admin-filter-bar">
        <input type="hidden" name="section" value="support">
        <label class="admin-field"><span>Status</span><select name="status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
            <?php foreach ($supportStatuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label class="admin-field"><span>Search</span><input name="q" value="<?= e($search) ?>" placeholder="Name, email, order, subject..."></label>
        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Filter inbox</button></div>
    </form>

    <?php if (!$messages): ?>
        <?php admin_empty_state('No support messages', 'Customer messages from support.php will appear here.'); ?>
    <?php else: ?>
        <form method="post" class="admin-bulk-toolbar" id="supportBulkForm" data-admin-confirm="Update selected support messages?">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="admin_action" value="support_message_bulk">
            <label class="admin-field"><span>Set status</span><select name="bulk_status" required>
                <?php foreach ($supportStatuses as $status): ?>
                    <option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="admin-check-row admin-select-all-row"><input type="checkbox" data-admin-select-all="[data-support-row-check]"> Select all visible messages</label>
            <button class="admin-primary-btn" type="submit">Update selected</button>
        </form>
        <div class="admin-support-inbox">
            <?php foreach ($messages as $message): ?>
                <article class="admin-support-message">
                    <label class="admin-support-select"><input type="checkbox" name="message_ids[]" value="<?= (int) $message['id'] ?>" form="supportBulkForm" data-support-row-check><span>Select</span></label>
                    <div class="admin-support-message-main">
                        <div class="admin-support-message-head">
                            <div>
                                <strong><?= e($message['subject'] ?: 'Support request') ?></strong>
                                <small><?= e($message['created_at'] ? (string) $message['created_at'] : '') ?></small>
                            </div>
                            <span class="admin-pill <?= e(admin_status_class($message['status'])) ?>"><?= e(ucfirst((string) $message['status'])) ?></span>
                        </div>
                        <p><?= nl2br(e((string) $message['message'])) ?></p>
                        <div class="admin-support-meta">
                            <span><b>Sender</b><?= e($message['name']) ?></span>
                            <span><b>Email</b><?= e($message['email']) ?></span>
                            <?php if (!empty($message['phone'])): ?><span><b>Phone</b><?= e($message['phone']) ?></span><?php endif; ?>
                            <?php if (!empty($message['order_number'])): ?><span><b>Order</b><?= e($message['order_number']) ?></span><?php endif; ?>
                            <?php if (!empty($message['source_page'])): ?><span><b>Source</b><?= e($message['source_page']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <form method="post" class="admin-support-message-actions">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="admin_action" value="support_message_update">
                        <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
                        <label class="admin-field"><span>Status</span><select name="status">
                            <?php foreach ($supportStatuses as $status): ?>
                                <option value="<?= e($status) ?>" <?= $message['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label class="admin-field"><span>Admin note</span><textarea name="admin_note" rows="4" placeholder="Internal note, response status, next step..."><?= e((string) ($message['admin_note'] ?? '')) ?></textarea></label>
                        <button class="admin-table-btn" type="submit">Save message</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-two-col admin-support-layout">
    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">New FAQ</p><h2>Add help-center question</h2></div></div>
        <form method="post" class="admin-form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="admin_action" value="faq_create">
            <label class="admin-field"><span>Question</span><input name="question" required></label>
            <label class="admin-field"><span>Category</span><input name="category" placeholder="Shipping, Returns, Warranty..."></label>
            <label class="admin-field"><span>Sort order</span><input type="number" name="sort_order" value="0"></label>
            <label class="admin-field admin-field-wide"><span>Answer</span><textarea name="answer" rows="5" required></textarea></label>
            <div class="admin-check-row"><label><input type="checkbox" name="is_active" checked> Published</label></div>
            <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Create FAQ</button></div>
        </form>
    </article>

    <article class="admin-card glass-panel">
        <div class="admin-section-head"><div><p class="admin-eyebrow">FAQ Health</p><h2>Public knowledge base</h2></div></div>
        <div class="admin-action-list">
            <a href="<?= e(admin_page_url('support')) ?>"><span>Total FAQs</span><strong><?= (int) $counts['faqs'] ?></strong></a>
            <a href="<?= e(admin_page_url('support')) ?>"><span>Published FAQs</span><strong><?= (int) $counts['published_faqs'] ?></strong></a>
            <a href="<?= e(admin_root_url('support.php')) ?>"><span>Storefront support page</span><strong>Open</strong></a>
        </div>
    </article>
</section>

<section class="admin-card glass-panel">
    <div class="admin-section-head"><div><p class="admin-eyebrow">Help center</p><h2>FAQs</h2></div></div>
    <?php if (!$faqs): ?>
        <?php admin_empty_state('No FAQs yet', 'Create questions customers can read on the support page.'); ?>
    <?php else: ?>
        <div class="admin-stack-list">
            <?php foreach ($faqs as $faq): ?>
                <article class="admin-edit-panel">
                    <form method="post" class="admin-form-grid compact">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="admin_action" value="faq_update">
                        <input type="hidden" name="faq_id" value="<?= (int) $faq['id'] ?>">
                        <label class="admin-field"><span>Question</span><input name="question" value="<?= e($faq['question']) ?>" required></label>
                        <label class="admin-field"><span>Category</span><input name="category" value="<?= e($faq['category'] ?? '') ?>"></label>
                        <label class="admin-field"><span>Order</span><input type="number" name="sort_order" value="<?= (int) $faq['sort_order'] ?>"></label>
                        <label class="admin-field admin-field-wide"><span>Answer</span><textarea name="answer" rows="3" required><?= e($faq['answer']) ?></textarea></label>
                        <div class="admin-check-row"><label><input type="checkbox" name="is_active" <?= (int) $faq['is_active'] === 1 ? 'checked' : '' ?>> Published</label></div>
                        <div class="admin-form-actions"><button class="admin-primary-btn" type="submit">Save FAQ</button></div>
                    </form>
                    <form method="post" class="admin-delete-strip" data-admin-confirm="Delete this FAQ from the public help center?">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="admin_action" value="faq_delete">
                        <input type="hidden" name="faq_id" value="<?= (int) $faq['id'] ?>">
                        <button type="submit">Delete FAQ</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
