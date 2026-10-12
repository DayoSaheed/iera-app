<?php $title = 'Calendar'; $active = 'calendar'; ?>
<div class="grid" style="grid-template-columns: 1fr 300px">
  <div class="card" data-calendar data-scope="<?= \App\Core\Auth::isSuperadmin($u) ? 'all' : 'mine' ?>">
    <div class="cal-head">
      <button type="button" class="btn btn-sm" data-cal-prev>‹</button>
      <h2 data-cal-title style="margin:0;min-width:180px;text-align:center"></h2>
      <button type="button" class="btn btn-sm" data-cal-next-month>›</button>
      <span class="grow"></span>
      <div class="switch"><button type="button" data-cal-scope="mine" class="<?= \App\Core\Auth::isSuperadmin($u) ? '' : 'is-active' ?>">My teams</button><button type="button" data-cal-scope="all" class="<?= \App\Core\Auth::isSuperadmin($u) ? 'is-active' : '' ?>">All teams</button></div>
    </div>
    <div class="cal-grid" data-cal-grid></div>
    <div class="legend" data-cal-legend style="margin-top:12px"></div>
    <p class="muted small" style="margin-top:8px">Events with a white outline are ones you registered for. Click an event to open registration.</p>
    <div class="card" style="margin-top:16px"><h3>Next events</h3><ul class="check-list" data-cal-next></ul></div>
  </div>
</div>
<style>@media (max-width: 900px){ .grid[style*="300px"]{ grid-template-columns: 1fr !important; } }</style>
