<?php $title = 'Form template: ' . $t['label']; $active = 'system'; $breadcrumbs = ['System Admin' => '/system', 'Form templates' => '/system/form-templates', $t['label'] => null]; ?>
<div class="grid" style="grid-template-columns: 3fr 2fr">
  <div class="card">
    <form method="post" action="<?= e(url('/system/form-templates/' . $t['id'])) ?>" class="stack"><?= csrf_field() ?>
      <div class="field"><label>Label</label><input type="text" name="label" value="<?= e($t['label']) ?>"></div>
      <div class="field"><label>Fields (JSON array)</label><textarea name="fields_json" class="mono-editor" spellcheck="false"><?= e($json) ?></textarea></div>
      <div class="form-actions"><button class="btn btn-ghost" name="action" value="reset" formnovalidate onclick="return confirm('Reset this template to the built-in default?')">Reset to default</button><a class="btn btn-ghost" href="<?= e(url('/system/form-templates')) ?>">Back</a><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
  <div class="card-stack">
    <div class="card"><h3>Field reference</h3>
      <p class="small">Each field: <code>{"type","name","label","required","options":[{"label","value"}],"multiple","dynamic","condition":{"name","value","not"},"info","placeholder","min","max","step","rows","hidden","editable"}</code></p>
      <p class="small"><strong>Types:</strong> section (starts a new step), text, email, tel, textarea, number, checkbox, select, city_auto, date, time, datetime, upload (max = files), text_copy (info + links in options), typography.</p>
      <p class="small"><strong>dynamic</strong> sources for selects: teams, parents, newmuslims, mentors, volunteers.</p>
      <p class="small"><strong>condition</strong> shows the field only when another field equals the value (add <code>"not": true</code> to invert).</p>
    </div>
    <div class="card"><h3>Preview</h3><div class="pre" style="max-height:420px;overflow:auto"><?= \App\Services\FormRenderer::render($t['fields'], [], ['viewOnly' => true]) ?></div></div>
  </div>
</div>
<style>@media (max-width: 1000px){ .grid[style*="3fr 2fr"]{ grid-template-columns: 1fr !important; } }</style>
