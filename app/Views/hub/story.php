<?php $title = $s['title']; $active = 'stories'; $breadcrumbs = ['Stories' => '/stories', $title => null]; ?>
<article class="card" style="max-width:860px">
  <?php if ($s['image_doc_id'] || $s['image_url']): ?><img src="<?= e($s['image_doc_id'] ? url('/documents/' . $s['image_doc_id']) : $s['image_url']) ?>" alt="" style="width:100%;max-height:380px;object-fit:cover;border-radius:10px;margin-bottom:16px"><?php endif; ?>
  <div class="choices"><span class="chip"><?= e($s['category'] ?: 'Story') ?></span><span class="chip"><?= e($s['continent'] ?: 'Global') ?><?= $s['country'] ? ' · ' . e($s['country']) : '' ?></span><?php if ($s['status'] !== 'published'): ?><span class="badge badge-pending"><?= e($s['status']) ?></span><?php endif; ?></div>
  <h1 style="margin-top:10px"><?= e($s['title']) ?></h1>
  <p class="muted small"><?= e($s['author']) ?><?= $s['published_date'] ? ' · ' . e(fmt_date($s['published_date'])) : '' ?></p>
  <div style="font-size:1.02rem;line-height:1.7"><?= nl2br(e((string) $s['content'])) ?></div>
  <?php if ($s['source_url']): ?><p style="margin-top:14px"><a class="btn btn-ghost" href="<?= e($s['source_url']) ?>" target="_blank" rel="noopener"><?= icon('external-link', '', 14) ?> Read the original</a></p><?php endif; ?>
</article>
