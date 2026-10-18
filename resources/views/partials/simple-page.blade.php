{{--
    A simple role page: header, sidebar, a title and intro, and one root section filled by a page script.
    Needs $role, $title, $intro, $root (the section's HTML), $css and $js (legacy file names), $chip; optional $tabs (top tab links).
    Used by the VPAA's Approved schedules / History and the Deans' and Program Chairs' Past timetables.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | {{ $title }}</title>
  @fonts
  <x-legacy-css name="schedule-page" />
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-css name="ui" />
  @foreach ($css as $name)
    <x-legacy-css :name="$name" />
  @endforeach
  <x-legacy-js name="page-loading" />
  @vite('resources/js/curriculum-pdf.js')
</head>
<body>
  <div class="app-shell">
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-left">
        <a href="dashboard.html" class="topbar-brand-link" aria-label="Go to dashboard">
          <div class="topbar-logo"><img src="../Attri/mculogo.jpg" alt="MCU logo" /></div>
          <div>
            <div class="topbar-title-main">MCU-NEX</div>
            <div class="topbar-title-sub">Registrar Room Allocation</div>
          </div>
        </a>
      </div>
      <div class="topbar-right">
        <span class="chip">{{ $chip }}</span>
        <div class="topbar-notify">
          <button class="topbar-notify-btn" type="button">
            <x-icon-bell />
            <span class="topbar-notify-dot"></span></button>
          <div class="notify-menu" role="menu"></div>
        </div>
        <x-profile-menu :role="$role" />
      </div>
    </header>
    <div class="topbar-tabs" aria-label="Page navigation">
      <x-app-nav-button />
      {!! $tabs ?? '' !!}
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
    </div>
    </div>
    <div class="layout-row">
      <x-app-nav :role="$role" />
      <main class="shell cb-shell">
        <header class="ui-page-head">
          <div>
            <h1 class="ui-page-title">{{ $title }}</h1>
            <p class="ui-page-sub">{{ $intro }}</p>
          </div>
        </header>
        {!! $root !!}
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
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  @foreach ($js as $name)
    <x-legacy-js :name="$name" />
  @endforeach
  <x-legacy-js name="notifications" defer />
</body>
</html>
