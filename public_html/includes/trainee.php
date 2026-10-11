<?php
/** أدوات خاصة بلوحة المتدرب */
function video_embed(string $url): array {
    $u = trim($url);
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $u, $m)) return ['iframe', 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0'];
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $u, $m)) return ['iframe', 'https://player.vimeo.com/video/' . $m[1]];
    if (preg_match('#drive\.google\.com/file/d/([A-Za-z0-9_-]+)#', $u, $m)) return ['iframe', 'https://drive.google.com/file/d/' . $m[1] . '/preview'];
    if (preg_match('#^https://[^\s]+\.(mp4|webm|ogg)(\?.*)?$#i', $u)) return ['video', $u];
    if (preg_match('#^https://#i', $u) && filter_var($u, FILTER_VALIDATE_URL)) return ['link', $u];
    return ['none', ''];
}
function pay_details(): array {
    global $pdo;
    $clean = fn($h) => preg_replace('/<(\w+)[^>]*>/', '<$1>', strip_tags((string)$h, '<br><strong><b><i><span><div><p><ul><li><a>'));
    return ['bank' => $clean(getSetting($pdo, 'bank_details')), 'paypal' => trim((string)getSetting($pdo, 'paypal_email')), 'agent' => $clean(getSetting($pdo, 'agent_details'))];
}
/** نموذج رفع إيصال الدفع (يُستخدم للدورات والاستشارات) */
function pay_form(string $kind, int $id, $usd, $dzd, string $idPrefix): void {
    $pd = pay_details(); $both = $usd > 0 && $dzd > 0; ?>
<form method="post" enctype="multipart/form-data" class="d-grid gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="pay"><input type="hidden" name="<?= e($kind) ?>_id" value="<?= $id ?>">
  <div class="row g-2"><div class="col-6"><select name="payment_method" class="form-select pm-sel" data-t="<?= e($idPrefix) ?>"><option value="bank"><?= e(d('تحويل بنكي', 'Bank transfer')) ?></option><?php if ($pd['paypal']): ?><option value="paypal">PayPal</option><?php endif; ?><option value="agent"><?= e(d('عبر وكيل', 'Via agent')) ?></option></select></div>
  <div class="col-6"><select name="currency" class="form-select"><?php if ($usd > 0): ?><option value="usd"><?= e(money_usd($usd)) ?></option><?php endif; ?><?php if ($dzd > 0): ?><option value="dzd" <?= !$both && !($usd > 0) ? 'selected' : '' ?>><?= e(money_dzd($dzd)) ?></option><?php endif; ?></select></div></div>
  <div class="ai-box small" id="<?= e($idPrefix) ?>_bank"><?= $pd['bank'] ?: e(d('تواصل مع الإدارة للحصول على بيانات التحويل.', 'Contact us for bank details.')) ?></div>
  <div class="ai-box small d-none" id="<?= e($idPrefix) ?>_paypal">PayPal: <b dir="ltr"><?= e($pd['paypal']) ?></b></div>
  <div class="ai-box small d-none" id="<?= e($idPrefix) ?>_agent"><?= $pd['agent'] ?: e(d('تواصل معنا لمعرفة الوكلاء.', 'Contact us to find an agent.')) ?></div>
  <label class="form-label mb-0 small fw-bold"><?= e(d('صورة الوصل (JPG/PNG/PDF)', 'Receipt (JPG/PNG/PDF)')) ?></label><input type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" class="form-control" required>
  <button class="btn-aramio"><i class="fa-solid fa-upload"></i> <?= e(d('إرسال الوصل للمراجعة', 'Submit receipt')) ?></button></form>
<?php }
function pay_js(): string {
    return '<script>document.querySelectorAll(".pm-sel").forEach(function(s){s.addEventListener("change",function(){["bank","paypal","agent"].forEach(function(k){var b=document.getElementById(s.dataset.t+"_"+k);b&&b.classList.toggle("d-none",k!==s.value)})})});</script>';
}
/** يعالج رفع الإيصال: يعيد [ok(bool), رسالة] ويتحقق من الملكية والمبلغ من قاعدة البيانات */
function pay_receipt_save(): ?string {
    return save_upload('receipt', 'receipts', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'], 6291456);
}
