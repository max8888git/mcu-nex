@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | Curriculums</title>
@endverbatim
  @fonts
  <x-legacy-css name="schedule-page" />
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-css name="ui" />
  <x-legacy-css name="schedule-colleges" />
  <x-legacy-css name="curriculum-form" />
  <x-legacy-js name="page-loading" />
  @vite('resources/js/curriculum-pdf.js')
@verbatim
</head>
<body>
  <div class="app-shell">
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-left">
        <a href="../home-dashboard.html" class="topbar-brand-link" aria-label="Go to dashboard">
          <div class="topbar-logo"><img src="../../Attri/mculogo.jpg" alt="MCU logo" /></div>
          <div>
            <div class="topbar-title-main">MCU-NEX</div>
            <div class="topbar-title-sub">Registrar Room Allocation</div>
          </div>
        </a>
      </div>
      <div class="topbar-right">
@endverbatim
@if ($role === 'super-admin')
@verbatim
        <span class="chip">University Registrar</span>
@endverbatim
@else
@verbatim
        <span class="chip">Records Officer</span>
@endverbatim
@endif
@verbatim
        <div class="topbar-notify">
          <button class="topbar-notify-btn" type="button">
@endverbatim
            <x-icon-bell />
@verbatim
            <span class="topbar-notify-dot"></span></button>
          <div class="notify-menu" role="menu"></div>
        </div>
@endverbatim
        <x-profile-menu :role="$role" />
@verbatim
      </div>
    </header>
    <div class="topbar-tabs" aria-label="Curriculum navigation">
@endverbatim
      <x-app-nav-button />
@verbatim
      <a href="curriculums.html" class="topbar-tab active">Curriculums</a>
      <a href="review.html" class="topbar-tab">From the colleges</a>
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
    </div>
    </div>
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim
      <main class="shell cb-shell">
        <header class="ui-page-head">
          <div>
            <h1 class="ui-page-title">Curriculums</h1>
            <p class="ui-page-sub">Every curriculum in the system. Pick one to see it year by year, download it, or print it. The Deans and Program Chairs make and change curriculums; new ones arrive on From the colleges.</p>
          </div>
        </header>
@endverbatim
        <section class="card cb-card-wrap" id="clRoot" data-role="{{ $role }}" aria-live="polite"></section>
@verbatim
      </main>
    </div>
  </div>
  <script>
    (function () {
      var el = document.getElementById('topbar-datetime');
      if (!el) return;
      function tick() {
        var d = new Date();
        el.innerHTML = '<span class="topbar-datetime-date">' + d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) +
          '</span><span class="topbar-datetime-time">' + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true }) + '</span>';
      }
      tick();
      setInterval(tick, 1000);
    })();
  </script>
@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="room-types" />
  <x-legacy-js name="org-store" />
  <x-legacy-js name="curriculum-store" />
  <x-legacy-js name="curriculum-upload" />
  <x-legacy-js name="data-boot" />
  <x-legacy-js name="curriculum-form" />
  <x-legacy-js name="curriculum-library" />
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>
@endverbatim
