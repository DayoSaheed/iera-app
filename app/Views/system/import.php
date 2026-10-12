<?php $title = 'Import legacy data'; $active = 'system'; ?>
<?= \App\Core\View::partial('system/_tabs', ['systab' => 'import']) ?>
<div class="grid-2">
  <div class="card"><h2>Import a MongoDB export</h2>
    <p class="small">Export each collection from the old database with <code>mongoexport --jsonArray</code> (or one JSON document per line) and import them <strong>in this order</strong>: teams → users → events → sub_events → reports → additional_user_info → form_templates. Re-importing is safe: existing records are updated.</p>
    <form method="post" action="<?= e(url('/system/import')) ?>" enctype="multipart/form-data" class="stack"><?= csrf_field() ?>
      <div class="field"><label>Collection</label><select name="collection"><?php foreach (\App\Services\MongoImporter::COLLECTIONS as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>JSON file</label><input type="file" name="file" accept=".json,application/json" required></div>
      <button class="btn btn-primary">Import</button>
    </form>
    <p class="small muted">For very large files (over the PHP upload limit) use <code>php tools/import.php &lt;collection&gt; &lt;file.json&gt;</code> from the command line.</p>
  </div>
  <div class="card"><h2>Last result</h2>
    <?php if (!$result): ?><p class="muted">No import yet.</p><?php else: ?><dl class="kv"><dt>Collection</dt><dd><?= e($result['collection']) ?></dd><dt>Imported</dt><dd><?= (int) $result['imported'] ?></dd><dt>Skipped</dt><dd><?= (int) $result['skipped'] ?></dd></dl><?php if ($result['errors']): ?><div class="pre"><?= e(implode("\n", $result['errors'])) ?></div><?php endif; ?><?php endif; ?>
  </div>
</div>
