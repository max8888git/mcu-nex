@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | Final report</title>
@endverbatim
  @fonts
  <x-legacy-css name="schedule-page" />
  <x-legacy-css name="final-report" />
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-js name="page-loading" />
@verbatim
</head>
<body>
@endverbatim
@if ($role === 'super-admin')
@verbatim
  <div class="app-shell" id="schedPage" data-view="sections" data-readonly="true" data-room-layout="table" data-actor="super_admin" data-page="report">
@endverbatim
@else
@verbatim
  <div class="app-shell" id="schedPage" data-view="sections" data-readonly="true" data-room-layout="table" data-actor="admin" data-page="report">
@endverbatim
@endif
@verbatim
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-left">
        <a href="home-dashboard.html" class="topbar-brand-link" aria-label="Go to dashboard">
          <div class="topbar-logo"><img src="../Attri/mculogo.jpg" alt="MCU logo" /></div>
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
    <div class="topbar-tabs" aria-label="Reports">
@endverbatim
      <x-app-nav-button />
@verbatim
      <button type="button" class="topbar-tab active" data-ws-tab="final">Final report</button>
      <a class="topbar-tab" href="finished-curriculums.html">Finished curriculums</a>
      <a class="topbar-tab" href="past-terms.html">Past terms</a>
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
    </div>
    </div>
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim
      <main class="shell">
        <header class="page-header">
          <h1 class="page-title" id="schedPageTitle">Final report</h1>
@endverbatim
@if ($role === 'super-admin')
@verbatim
          <p class="page-sub" id="schedPageSub">The timetable the VPAA approved, for every college. What’s still waiting for the VPAA is in Create schedule → For approval. Filters only change what you see.</p>
@endverbatim
@else
@verbatim
          <p class="page-sub" id="schedPageSub">The timetable the VPAA approved, for every college. What’s still waiting for the VPAA is in Create schedule → For approval. Filters only change what you see.</p>
@endverbatim
@endif
@verbatim
        </header>
        <section class="card">
          <p class="sched-term-banner" id="schedTermBanner"></p>
          <div class="fr-colleges" id="frColleges" aria-live="polite"></div>
          <p class="sched-workspace-hint" id="schedWorkspaceHint"></p>
          <div id="schedSavesPanel" hidden></div>
          <div id="schedWorkspace">
          <div class="sched-form">
            <div class="sched-field">
              <label for="schedYear">Academic year</label>
              <select id="schedYear"></select>
            </div>
            <div class="sched-field">
              <label for="schedSemester">Semester</label>
              <select id="schedSemester">
                <option value="1">1st semester</option>
                <option value="2">2nd semester</option>
                <option value="summer">Summer</option>
              </select>
            </div>
          </div>
          <div class="sched-actions" id="schedWorkspaceActions"></div>
          <div class="sched-actions">
            <button type="button" class="sched-btn sched-btn--ghost" id="schedPrint">Print</button>
            <p class="sched-status" id="schedStatus"></p>
          </div>
          <div class="sched-tabs">
            <button type="button" class="sched-tab is-active" data-sched-view="sections">By section</button>
            <button type="button" class="sched-tab" data-sched-view="rooms">By room</button>
            <button type="button" class="sched-tab" data-sched-view="classes">By class</button>
          </div>
          <div class="sched-subtabs" id="schedLayoutTabs" hidden role="tablist" aria-label="Schedule layout">
            <button type="button" class="sched-subtab is-active" data-sched-layout="table">Table view</button>
            <button type="button" class="sched-subtab" data-sched-layout="grid">Timetable</button>
          </div>
          <div id="schedGenerateFilters" class="sched-generate-filters">
            <div class="sched-field">
              <label for="schedCollege">College</label>
              <select id="schedCollege"></select>
            </div>
            <div class="sched-field" id="schedDepartmentWrap">
              <label for="schedDepartment">Program</label>
              <select id="schedDepartment"></select>
            </div>
            <div class="sched-field">
              <label for="schedYearLevel">Year level</label>
              <select id="schedYearLevel">
                <option value="">All years</option>
                <option value="1">1st year</option>
                <option value="2">2nd year</option>
                <option value="3">3rd year</option>
                <option value="4">4th year</option>
                <option value="5">5th year</option>
                <option value="6">6th year</option>
              </select>
            </div>
            <div class="sched-field">
              <label for="schedSectionStatus">Status</label>
              <select id="schedSectionStatus">
                <option value="">All</option>
                <option value="scheduled">Scheduled</option>
                <option value="unscheduled">Unscheduled</option>
              </select>
            </div>
          </div>
          <div class="sched-room-filters" id="schedRoomTableFilters" hidden>
            <div class="sched-field">
              <label for="schedRoomBuilding">Building</label>
              <select id="schedRoomBuilding"></select>
            </div>
            <div class="sched-field">
              <label for="schedRoomFloor">Floor</label>
              <select id="schedRoomFloor"></select>
            </div>
            <div class="sched-field">
              <label for="schedRoomType">Room type</label>
              <select id="schedRoomType"></select>
            </div>
          </div>
          <div class="sched-room-filters" id="schedClassFilters" hidden>
            <div class="sched-field sched-field--search">
              <label for="schedClassSearch">Search</label>
              <input id="schedClassSearch" type="search" placeholder="Course code or title" />
            </div>
            <div class="sched-field">
              <label for="schedClassCollege">College</label>
              <select id="schedClassCollege"></select>
            </div>
            <div class="sched-field">
              <label for="schedClassComponent">Component</label>
              <select id="schedClassComponent">
                <option value="">All</option>
                <option value="lecture">Lecture</option>
                <option value="laboratory">Laboratory</option>
              </select>
            </div>
            <div class="sched-field">
              <label for="schedClassStatus">Status</label>
              <select id="schedClassStatus">
                <option value="">All</option>
                <option value="scheduled">Scheduled</option>
                <option value="unscheduled">Unscheduled</option>
              </select>
            </div>
            <div class="sched-field">
              <label for="schedClassYearLevel">Year level</label>
              <select id="schedClassYearLevel">
                <option value="">All years</option>
                <option value="1">1st year</option>
                <option value="2">2nd year</option>
                <option value="3">3rd year</option>
                <option value="4">4th year</option>
                <option value="5">5th year</option>
                <option value="6">6th year</option>
              </select>
            </div>
            <div class="sched-field">
              <label for="schedClassBuilding">Building</label>
              <select id="schedClassBuilding"></select>
            </div>
            <div class="sched-field">
              <label for="schedClassMerged">Merged</label>
              <select id="schedClassMerged">
                <option value="">All</option>
                <option value="merged">Merged only</option>
                <option value="single">Not merged</option>
              </select>
            </div>
          </div>
          <div class="sched-crumb" id="schedRoomCrumb" hidden></div>
          <div id="schedSectionView"></div>
          <div id="schedRoomView" hidden></div>
          <div id="schedClassView" hidden></div>
          </div>
        </section>
      </main>
    </div>
  </div>
@endverbatim
@if ($role === 'super-admin')
@verbatim
  <div class="sched-modal" id="schedRejectModal" hidden>
    <div class="sched-modal-card">
      <h2>Send back for revisions</h2>
      <p class="sched-edit-hours">The Records Officer will see this note on Final report. If the VPAA already published an earlier copy, deans and program chairs keep that copy.</p>
      <div class="sched-field">
        <label for="schedRejectNote">What needs to change</label>
        <textarea id="schedRejectNote" rows="4" maxlength="2000"></textarea>
      </div>
      <div class="sched-modal-actions">
        <button type="button" class="sched-btn sched-btn--danger" id="schedRejectSend">Send back</button>
        <button type="button" class="sched-btn sched-btn--ghost" id="schedRejectCancel">Cancel</button>
      </div>
    </div>
  </div>
@endverbatim
@endif
@verbatim
  <script>
    (function () {
      var navToggle = document.querySelector('.nav-toggle');
      var navBar = document.querySelector('.nav-bar');
      var user = document.querySelector('.topbar-user');
      var userMenu = document.querySelector('.user-menu');
      if (navToggle && navBar) {
        navToggle.addEventListener('click', function () {
          navBar.classList.toggle('nav-bar-open');
          navToggle.classList.toggle('open', navBar.classList.contains('nav-bar-open'));
          navToggle.setAttribute('aria-expanded', navBar.classList.contains('nav-bar-open') ? 'true' : 'false');
        });
      }
      if (user && userMenu) {
        user.addEventListener('click', function (e) {
          e.stopPropagation();
          user.classList.toggle('open');
          user.setAttribute('aria-expanded', user.classList.contains('open') ? 'true' : 'false');
        });
        userMenu.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () {
          user.classList.remove('open');
          user.setAttribute('aria-expanded', 'false');
        });
      }
      var el = document.getElementById('topbar-datetime');
      if (el) {
        function tick() {
          var d = new Date();
          var opts = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
          var tOpts = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
          el.innerHTML = '<span class="topbar-datetime-date">' + d.toLocaleDateString(undefined, opts) +
            '</span><span class="topbar-datetime-time">' + d.toLocaleTimeString(undefined, tOpts) + '</span>';
        }
        tick();
        setInterval(tick, 1000);
      }
    })();
  </script>
  <div class="sched-modal sched-modal--confirm" id="schedConfirmModal" hidden>
    <div class="sched-modal-card">
      <h2 id="schedConfirmTitle">Please confirm</h2>
      <p class="sched-edit-hours is-warn" id="schedConfirmBody"></p>
      <div class="sched-modal-actions">
        <button type="button" class="sched-btn" id="schedConfirmYes">Continue</button>
        <button type="button" class="sched-btn sched-btn--ghost" id="schedConfirmNo">Cancel</button>
      </div>
    </div>
  </div>
@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="org-store" />
  <x-legacy-js name="curriculum-store" />
  <x-legacy-js name="room-types" />
  <x-legacy-js name="sections-store" />
  <x-legacy-js name="data-boot" />
  <x-legacy-js name="schedule-page" />
  <x-legacy-js name="final-report" />
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>

@endverbatim
