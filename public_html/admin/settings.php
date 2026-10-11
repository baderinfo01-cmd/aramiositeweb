<?php
require_once 'admin_init.php';
admin_only();
function clean_html(string $h): string { return preg_replace('/<(\w+)[^>]*>/', '<$1>', strip_tags($h, '<br><strong><b><i><span><div><p><ul><li><a>')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf(); $act = $_POST['act'] ?? '';
    if ($act === 'general') {
        foreach (['site_name', 'contact_email', 'phone_number', 'whatsapp_number', 'facebook_link', 'twitter_link', 'instagram_link', 'youtube_link', 'promo_title', 'promo_subtitle', 'promo_link', 'promo_end_date', 'exchange_rate', 'msg_course_approved', 'msg_order_approved', 'msg_order_rejected', 'msg_trainer_paid'] as $k)
            if (isset($_POST[$k])) setSetting($k, trim((string)$_POST[$k]));
        foreach (['promo_title_en', 'promo_subtitle_en', 'promo_link_en'] as $k) if (isset($_POST[$k])) setSetting($k, trim((string)$_POST[$k]));
        setSetting('seo_description', trim((string)($_POST['seo_description_ar'] ?? '')), trim((string)($_POST['seo_description_en'] ?? '')));
        setSetting('about_us', trim((string)($_POST['about_us_ar'] ?? '')), trim((string)($_POST['about_us_en'] ?? '')));
        if (isset($_POST['exchange_rate']) && (float)$_POST['exchange_rate'] <= 0) setSetting('exchange_rate', '135');
        audit('settings_general'); flash('ok', d('تم حفظ الإعدادات.', 'Settings saved.'));
    } elseif ($act === 'payments') {
        setSetting('bank_details', clean_html((string)$_POST['bank_details'])); setSetting('agent_details', clean_html((string)$_POST['agent_details']));
        setSetting('paypal_email', trim((string)$_POST['paypal_email'])); audit('settings_payments'); flash('ok', d('تم حفظ بيانات الدفع.', 'Payment details saved.'));
    } elseif ($act === 'ai') {
        $prov = in_array($_POST['ai_provider'] ?? '', ['', 'anthropic', 'openai', 'gemini', 'compatible'], true) ? $_POST['ai_provider'] : '';
        setSetting('ai_provider', $prov); setSetting('ai_model', trim((string)$_POST['ai_model'])); setSetting('ai_base_url', trim((string)$_POST['ai_base_url']));
        setSetting('ai_enabled', !empty($_POST['ai_enabled']) ? '1' : '0');
        if (trim((string)($_POST['ai_api_key'] ?? '')) !== '') setSetting('ai_api_key_enc', ai_encrypt(trim((string)$_POST['ai_api_key'])));
        if (!empty($_POST['ai_clear_key'])) setSetting('ai_api_key_enc', '');
        audit('settings_ai', $prov); flash('ok', d('تم حفظ إعدادات الذكاء الاصطناعي.', 'AI settings saved.'));
    } elseif ($act === 'upload') {
        $type = ($_POST['image_type'] ?? '') === 'review' ? 'review' : 'gallery';
        $n = save_upload('image', $type === 'gallery' ? 'gallery' : 'reviews');
        if ($n) { $pdo->prepare("INSERT INTO gallery_images (image_name, image_type) VALUES (?,?)")->execute([$n, $type]); flash('ok', d('تم رفع الصورة.', 'Image uploaded.')); }
        else flash('err', d('تعذّر الرفع: الصيغ المسموحة JPG/PNG/WEBP حتى 5MB.', 'Upload failed: JPG/PNG/WEBP up to 5MB.'));
    } elseif ($act === 'delete_img') {
        $s = $pdo->prepare("SELECT image_name, image_type FROM gallery_images WHERE id=?"); $s->execute([(int)$_POST['id']]); $im = $s->fetch();
        if ($im) { $f = dirname(__DIR__) . '/uploads/' . ($im['image_type'] === 'gallery' ? 'gallery/' : 'reviews/') . basename($im['image_name']); if (is_file($f)) @unlink($f); $pdo->prepare("DELETE FROM gallery_images WHERE id=?")->execute([(int)$_POST['id']]); flash('ok', d('تم الحذف.', 'Deleted.')); }
    }
    redirect('settings.php#' . ($_POST['tab'] ?? 'general'));
}
$S = fn($k, $l = 'ar') => getSetting($pdo, $k, $l);
$gallery = $pdo->query("SELECT * FROM gallery_images WHERE image_type='gallery' ORDER BY id DESC")->fetchAll();
$reviews = $pdo->query("SELECT * FROM gallery_images WHERE image_type='review' ORDER BY id DESC")->fetchAll();
$ai = ai_config(); $aiLocked = (bool)app_config('ai_provider');
$dashTitle = d('الإعدادات العامة', 'Settings');
require '../includes/partials/dash_header.php';
$field = fn($k, $lb, $type = 'text', $dirx = '') => '<div class="col-md-6"><label class="form-label">' . e($lb) . '</label><input type="' . $type . '" name="' . $k . '" value="' . e($type === 'datetime-local' ? str_replace(' ', 'T', $GLOBALS['S']($k)) : $GLOBALS['S']($k)) . '" class="form-control" ' . ($dirx ? 'dir="' . $dirx . '"' : '') . '></div>';
?>
<ul class="nav nav-pills gap-1 mb-3" id="tabs" role="tablist">
  <?php foreach (['general' => ['fa-gear', d('عام', 'General')], 'payments' => ['fa-credit-card', d('الدفع', 'Payments')], 'ai' => ['fa-wand-magic-sparkles', d('الذكاء الاصطناعي', 'AI')], 'gallery' => ['fa-images', d('معرض الصور', 'Gallery')], 'reviews' => ['fa-comment-dots', d('صور التقييمات', 'Review images')]] as $k => [$ic, $lb]): ?>
    <li class="nav-item"><button class="nav-link <?= $k === 'general' ? 'active' : '' ?>" style="color:inherit" data-bs-toggle="pill" data-bs-target="#t_<?= $k ?>"><i class="fa-solid <?= $ic ?>"></i> <?= e($lb) ?></button></li><?php endforeach; ?></ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="t_general"><form method="post" class="dcard"><div class="dcard-b"><?= csrf_field() ?><input type="hidden" name="act" value="general"><input type="hidden" name="tab" value="general">
  <div class="row g-3"><?= $field('site_name', d('اسم الموقع', 'Site name')) ?><?= $field('contact_email', t('email'), 'email', 'ltr') ?><?= $field('phone_number', t('phone'), 'text', 'ltr') ?><?= $field('whatsapp_number', 'WhatsApp', 'text', 'ltr') ?>
  <?= $field('exchange_rate', d('سعر الصرف (1$ = ? د.ج)', 'Exchange rate (1 USD = ? DZD)'), 'number', 'ltr') ?><?= $field('facebook_link', 'Facebook', 'url', 'ltr') ?><?= $field('instagram_link', 'Instagram', 'url', 'ltr') ?><?= $field('youtube_link', 'YouTube', 'url', 'ltr') ?><?= $field('twitter_link', 'X / Twitter', 'url', 'ltr') ?>
  <div class="col-12"><hr><h6 class="fw-bold"><?= e(d('العرض الترويجي (العدّاد التنازلي)', 'Promo banner (countdown)')) ?></h6></div>
  <div class="col-md-6"><label class="form-label"><?= e(d('تاريخ الانتهاء', 'End date')) ?></label><input type="datetime-local" name="promo_end_date" class="form-control" value="<?= e(substr(str_replace(' ', 'T', $S('promo_end_date')), 0, 16)) ?>"></div><?= $field('promo_link', d('رابط الزر (عربي)', 'Button link (AR)'), 'text', 'ltr') ?>
  <?= $field('promo_title', d('العنوان (عربي)', 'Title (AR)')) ?><?= $field('promo_title_en', 'Title (EN)', 'text', 'ltr') ?><?= $field('promo_subtitle', d('الوصف (عربي)', 'Subtitle (AR)')) ?><?= $field('promo_subtitle_en', 'Subtitle (EN)', 'text', 'ltr') ?><?= $field('promo_link_en', 'Button link (EN)', 'text', 'ltr') ?>
  <div class="col-12"><hr><h6 class="fw-bold">SEO</h6></div>
  <div class="col-md-6"><label class="form-label">Meta (AR)</label><textarea name="seo_description_ar" rows="2" class="form-control"><?= e($S('seo_description', 'ar')) ?></textarea></div><div class="col-md-6"><label class="form-label">Meta (EN)</label><textarea name="seo_description_en" rows="2" class="form-control" dir="ltr"><?= e($S('seo_description', 'en')) ?></textarea></div>
  <div class="col-md-6"><label class="form-label"><?= e(d('نص إضافي في "من نحن" (عربي)', 'Extra About text (AR)')) ?></label><textarea name="about_us_ar" rows="3" class="form-control"><?= e(strip_tags($S('about_us', 'ar'))) ?></textarea></div><div class="col-md-6"><label class="form-label">Extra About text (EN)</label><textarea name="about_us_en" rows="3" class="form-control" dir="ltr"><?= e(strip_tags($S('about_us', 'en'))) ?></textarea></div>
  <div class="col-12"><hr><h6 class="fw-bold"><?= e(d('قوالب رسائل واتساب', 'WhatsApp templates')) ?></h6></div>
  <?= $field('msg_course_approved', d('الموافقة على دورة', 'Course approved')) ?><?= $field('msg_order_approved', d('تأكيد طلب', 'Order approved')) ?><?= $field('msg_order_rejected', d('رفض طلب', 'Order rejected')) ?><?= $field('msg_trainer_paid', d('سداد مدرب', 'Trainer paid')) ?>
  <div class="col-12"><button class="btn-aramio px-5"><?= e(t('save')) ?></button></div></div></div></form></div>

<div class="tab-pane fade" id="t_payments"><form method="post" class="dcard"><div class="dcard-b"><?= csrf_field() ?><input type="hidden" name="act" value="payments"><input type="hidden" name="tab" value="payments"><div class="row g-3">
  <div class="col-12"><label class="form-label"><?= e(d('بيانات التحويل البنكي (تظهر للمتدرب)', 'Bank transfer details (shown to trainees)')) ?></label><textarea name="bank_details" rows="5" class="form-control"><?= e($S('bank_details')) ?></textarea></div>
  <div class="col-12"><label class="form-label"><?= e(d('بيانات الوكلاء', 'Agent details')) ?></label><textarea name="agent_details" rows="3" class="form-control"><?= e($S('agent_details')) ?></textarea></div>
  <div class="col-md-6"><label class="form-label">PayPal</label><input name="paypal_email" class="form-control" dir="ltr" value="<?= e($S('paypal_email')) ?>"></div><div class="col-12"><button class="btn-aramio px-5"><?= e(t('save')) ?></button></div></div></div></form></div>

<div class="tab-pane fade" id="t_ai"><form method="post" class="dcard"><div class="dcard-b"><?= csrf_field() ?><input type="hidden" name="act" value="ai"><input type="hidden" name="tab" value="ai">
  <div class="ai-box mb-3"><i class="fa-solid fa-wand-magic-sparkles sparkle"></i> <?= e(d('اختر مزوّد الذكاء الاصطناعي وأدخل مفتاح API. يُخزَّن المفتاح مشفّرًا في قاعدة البيانات. الحالة الحالية:', 'Pick an AI provider and enter its API key. The key is stored encrypted. Current status:')) ?>
    <b class="<?= ai_available() ? 'text-success' : 'text-danger' ?>"><?= ai_available() ? e(d('مفعّل', 'Active') . ' — ' . $ai['provider'] . ' / ' . $ai['model']) : e(d('غير مفعّل', 'Not configured')) ?></b><?= $aiLocked ? ' <span class="st st-warn">config.local.php</span>' : '' ?></div>
  <div class="row g-3"><div class="col-md-4"><label class="form-label"><?= e(d('المزوّد', 'Provider')) ?></label><select name="ai_provider" class="form-select" <?= $aiLocked ? 'disabled' : '' ?>>
    <?php foreach (['' => '—', 'anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'compatible' => 'OpenAI-compatible (DeepSeek, Groq, OpenRouter, Ollama…)'] as $k => $lb): ?><option value="<?= $k ?>" <?= $ai['provider'] === $k ? 'selected' : '' ?>><?= e($lb) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><label class="form-label"><?= e(d('النموذج (اختياري)', 'Model (optional)')) ?></label><input name="ai_model" class="form-control" dir="ltr" value="<?= e(getSetting($pdo, 'ai_model')) ?>" placeholder="claude-sonnet-5-5 / gpt-4o-mini / gemini-2.0-flash"></div>
  <div class="col-md-4"><label class="form-label">Base URL (compatible)</label><input name="ai_base_url" class="form-control" dir="ltr" value="<?= e(getSetting($pdo, 'ai_base_url')) ?>" placeholder="https://api.deepseek.com/v1"></div>
  <div class="col-md-8"><label class="form-label">API Key</label><input type="password" name="ai_api_key" class="form-control" dir="ltr" autocomplete="new-password" placeholder="<?= getSetting($pdo, 'ai_api_key_enc') ? '•••••••• (' . e(d('محفوظ', 'saved')) . ')' : '' ?>"></div>
  <div class="col-md-4 d-flex align-items-end gap-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="ai_enabled" id="aien" <?= $ai['enabled'] ? 'checked' : '' ?>><label for="aien" class="form-check-label"><?= e(d('تفعيل', 'Enabled')) ?></label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="ai_clear_key" id="aicl"><label for="aicl" class="form-check-label small"><?= e(d('حذف المفتاح', 'Remove key')) ?></label></div></div>
  <div class="col-12 d-flex gap-2 flex-wrap"><button class="btn-aramio px-5"><?= e(t('save')) ?></button><button type="button" class="btn-soft btn-soft-info px-4" id="aiTest"><i class="fa-solid fa-plug"></i> <?= e(d('اختبار الاتصال', 'Test connection')) ?></button><span id="aiTestOut" class="align-self-center small"></span></div></div></div></form></div>

<?php foreach (['gallery' => [$gallery, 'gallery'], 'reviews' => [$reviews, 'review']] as $tab => [$imgs, $type]): ?>
<div class="tab-pane fade" id="t_<?= $tab ?>"><div class="dcard"><div class="dcard-b"><form method="post" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap mb-4"><?= csrf_field() ?><input type="hidden" name="act" value="upload"><input type="hidden" name="tab" value="<?= $tab ?>"><input type="hidden" name="image_type" value="<?= $type ?>">
  <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control" style="max-width:360px" required><button class="btn-aramio"><i class="fa-solid fa-upload"></i> <?= e(d('رفع', 'Upload')) ?></button></form>
  <div class="row g-3"><?php foreach ($imgs as $im): ?><div class="col-6 col-md-3"><div class="position-relative"><img src="../uploads/<?= $type === 'gallery' ? 'gallery' : 'reviews' ?>/<?= e($im['image_name']) ?>" loading="lazy" class="w-100 rounded-3" style="height:150px;object-fit:cover" alt="">
    <div class="position-absolute top-0 end-0 m-1"><?= action_btn('', ['act' => 'delete_img', 'id' => $im['id'], 'tab' => $tab], 'btn-soft-bad', d('حذف الصورة؟', 'Delete image?'), 'fa-trash') ?></div></div></div><?php endforeach; ?>
  <?php if (!$imgs): ?><div class="empty"><i class="fa-solid fa-images"></i></div><?php endif; ?></div></div></div></div><?php endforeach; ?>
</div>
<?php
$footExtra = '<script>if(location.hash){var b=document.querySelector("[data-bs-target=\'#t_"+location.hash.slice(1)+"\']");b&&new bootstrap.Tab(b).show()}
aiTest.addEventListener("click",function(){var o=document.getElementById("aiTestOut");o.textContent="…";AI_ASK("../api/ai_test.php",{},function(r){o.textContent=r.message},this)});</script>';
require '../includes/partials/dash_footer.php';
