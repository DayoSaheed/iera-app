<?php $title = 'Sent email'; ?>
<div style="max-width:760px;margin:24px auto;padding:0 16px">
  <p class="small muted">To: <?= e($log['volunteer_name']) ?> (<?= e($log['volunteer_email']) ?>) · <?= e(fmt_datetime($log['sent_at'])) ?> · <?= e($log['delivery_status']) ?></p>
  <h2 style="margin-top:0"><?= e($log['subject_line_sent']) ?></h2>
  <iframe sandbox="" srcdoc="<?= e((string) $log['body_html_sent']) ?>" style="width:100%;height:70vh;border:1px solid #e4e7eb;border-radius:10px;background:#fff"></iframe>
</div>
