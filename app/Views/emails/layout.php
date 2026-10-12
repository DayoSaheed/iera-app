<?php /** @var string $body @var string $subject @var array $vars */ $__b = \App\Models\Setting::brand(); ?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title><?= e($subject) ?></title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 0">
<tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden">
<tr><td style="background:<?= e($__b['dark']) ?>;color:#ffffff;padding:20px 28px;font-size:18px;font-weight:bold;border-bottom:4px solid <?= e($__b['accent']) ?>"><?= e($vars['app_name'] ?? 'iERA App') ?></td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.6"><?= $body ?></td></tr>
<tr><td style="padding:16px 28px;background:#f9fafb;color:#6b7280;font-size:12px">
<?= e($vars['org_name'] ?? '') ?> · <a href="<?= e($vars['app_url'] ?? '') ?>" style="color:<?= e($__b['primary']) ?>"><?= e($vars['app_url'] ?? '') ?></a><br>
This message was sent automatically by the <?= e($vars['app_name'] ?? 'iERA App') ?>. Questions? <a href="mailto:<?= e($vars['support_email'] ?? '') ?>" style="color:<?= e($__b['primary']) ?>"><?= e($vars['support_email'] ?? '') ?></a>
</td></tr>
</table>
</td></tr></table>
</body></html>
