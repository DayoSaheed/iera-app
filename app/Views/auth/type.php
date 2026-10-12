<?= \App\Core\View::partial('partials/steps', ['steps' => $steps, 'current' => $current]) ?>
<h1 class="auth-title">Welcome</h1>
<p class="muted">Register or sign in. First, tell us who you are.</p>
<form method="post" action="<?= e(url('/login/type')) ?>" class="type-chooser">
  <?= csrf_field() ?>
  <button class="type-tile tile-volunteer <?= ($selected ?? '') === 'volunteer' ? 'is-selected' : '' ?>" name="type" value="volunteer">
    <span class="tile-icon">🤲</span><span class="tile-title">Volunteer</span><span class="tile-sub">Da'ees, mentors, team leads and all iERA volunteers</span>
  </button>
  <button class="type-tile tile-newmuslim <?= ($selected ?? '') === 'newmuslim' ? 'is-selected' : '' ?>" name="type" value="newmuslim">
    <span class="tile-icon">🕌</span><span class="tile-title">New Muslim</span><span class="tile-sub">Get connected with a mentor and learning resources</span>
  </button>
</form>
<p class="auth-help">Admins and organization accounts sign in as <strong>Volunteer</strong>.</p>
