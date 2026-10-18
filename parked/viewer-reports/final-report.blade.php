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
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
@if ($role === 'viewing-with-approval')
  <x-legacy-css name="ui" />
  <x-legacy-css name="schedule-colleges" />
@endif
  <x-legacy-js name="page-loading" />
@verbatim
</head>
<body>
@endverbatim
@if ($role === 'viewing-program-head')
@verbatim
  <div class="app-shell" id="schedPage" data-view="sections" data-readonly="true" data-room-layout="table" data-actor="program_chair" data-scope-college-name="College of Arts and Sciences" data-scope-department-name="Computer Science, Information Technology, EMC, Emerging Media">
@endverbatim
@elseif ($role === 'viewing-with-approval')
@verbatim
  <div class="app-shell" id="schedPage" data-view="sections" data-readonly="true" data-room-layout="table" data-actor="vpaa" data-page="report">
@endverbatim
@else
@verbatim
  <div class="app-shell" id="schedPage" data-view="sections" data-readonly="true" data-room-layout="table" data-actor="dean" data-scope-college-name="College of Arts and Sciences">
@endverbatim
@endif
@verbatim
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
@endverbatim
@if ($role === 'viewing-program-head')
@verbatim
        <span class="chip">Program Chair</span>
@endverbatim
@elseif ($role === 'viewing-with-approval')
@verbatim
        <span class="chip">VPAA</span>
@endverbatim
@else
@verbatim
        <span class="chip">Dean</span>
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
@endverbatim
@if ($role === 'viewing-with-approval')
@verbatim
    <div class="topbar-tabs" aria-label="Reports">
@endverbatim
@else
@verbatim
    <div class="topbar-tabs" aria-label="Page navigation">
@endverbatim
@endif
      <x-app-nav-button />
@if ($role === 'viewing-with-approval')
@verbatim
      <button type="button" class="topbar-tab" data-ws-tab="awaiting">For your approval</button>
      <button type="button" class="topbar-tab active" data-ws-tab="final">Published timetable</button>
@endverbatim
@endif
@verbatim
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
    </div>
    </div>
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim
      <main class="shell">
        <header class="page-header">
@endverbatim
@if ($role === 'viewing-program-head')
@verbatim
          <h1 class="page-title">Final report</h1>
          <p class="page-sub">This is the VPAA-approved timetable for the departments you handle. It does not include work that is still with the Records Officer or University Registrar.</p>
@endverbatim
@elseif ($role === 'viewing-with-approval')
@verbatim
          <h1 class="page-title" id="schedPageTitle">Final report</h1>
          <p class="page-sub" id="schedPageSub">This is the timetable after you approve it. Copies waiting for your decision live on the other tab. Filters only change what you see.</p>
@endverbatim
@else
@verbatim
          <h1 class="page-title">Final report</h1>
          <p class="page-sub">This is the VPAA-approved timetable for your college. It does not include work that is still with the Records Officer or University Registrar.</p>
@endverbatim
@endif
@verbatim
        </header>
@endverbatim
@if ($role === 'viewing-with-approval')
@verbatim
        <section class="card cs-card" id="schedColleges" aria-labelledby="csTitle" hidden></section>
@endverbatim
@endif
@verbatim
        <section class="card">
          <p class="sched-term-banner" id="schedTermBanner"></p>
@endverbatim
@if ($role === 'viewing-with-approval')
@verbatim
          <p class="sched-workspace-hint" id="schedWorkspaceHint"></p>
          <div id="schedSavesPanel" hidden></div>
@endverbatim
@else
@verbatim
          <div class="sched-viewbar" id="schedOfferingBar" role="tablist" aria-label="Active or archived offerings">
            <button type="button" class="sched-seg" role="tab" id="schedSegActive" aria-selected="true">Active</button>
            <button type="button" class="sched-seg" role="tab" id="schedSegArchived" aria-selected="false">Archived</button>
          </div>
          <p class="sched-archive-hint" id="schedArchiveHint" hidden></p>
          <div id="schedArchivedList" hidden></div>
@endverbatim
@endif
@verbatim
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
@endverbatim
@if ($role === 'viewing-with-approval')
@verbatim
          <div class="sched-actions" id="schedWorkspaceActions"></div>
@endverbatim
@else
@verbatim
          <div class="sched-review" id="schedReviewBanner" hidden></div>
          <div class="sched-versions" id="schedVersionBar" hidden></div>
@endverbatim
@endif
@verbatim
          <div class="sched-actions">
@endverbatim
@if ($role === 'viewing-with-approval')
@else
@verbatim
            <button type="button" class="sched-btn sched-btn--ghost" id="schedBackArchived" hidden>Back to archived offerings</button>
@endverbatim
@endif
@verbatim
            <button type="button" class="sched-btn sched-btn--ghost" id="schedPrint">Print</button>
            <p class="sched-status" id="schedStatus"></p>
          </div>
@endverbatim
@if ($role === 'viewing-with-approval')
@verbatim
          <div class="sched-viewrow">
            <div class="sched-tabs" role="group" aria-label="Show the timetable">
              <button type="button" class="sched-tab is-active" data-sched-view="sections">By section</button>
              <button type="button" class="sched-tab" data-sched-view="rooms">By room</button>
              <button type="button" class="sched-tab" data-sched-view="classes">By class</button>
            </div>
            <label class="sched-find">
              <span class="ui-sr">Search</span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
              <input id="schedFind" type="search" placeholder="Find a section, course, or room" autocomplete="off" />
            </label>
            <button type="button" class="sched-attention" id="schedAttention" aria-pressed="false">Needs attention <span class="sched-attention-count" data-attention-count>0</span></button>
          </div>
@endverbatim
@else
@verbatim
          <div class="sched-tabs">
            <button type="button" class="sched-tab is-active" data-sched-view="sections">By section</button>
            <button type="button" class="sched-tab" data-sched-view="rooms">By room</button>
            <button type="button" class="sched-tab" data-sched-view="classes">By class</button>
          </div>
@endverbatim
@endif
@verbatim
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
@if ($role === 'viewing-with-approval')
@verbatim
  <div class="sched-modal" id="schedRejectModal" hidden>
    <div class="sched-modal-card">
      <h2>Send back for revisions</h2>
      <p class="sched-edit-hours">University Registrar will see this note on Records Officer’s submitted schedule. If you already published an earlier copy, deans and program chairs keep that copy.</p>
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
@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="org-store" />
  <x-legacy-js name="curriculum-store" />
  <x-legacy-js name="room-types" />
  <x-legacy-js name="sections-store" />
  <x-legacy-js name="data-boot" />
@if ($role === 'viewing-with-approval')
  <x-legacy-js name="schedule-colleges" />
@endif
  <x-legacy-js name="schedule-page" />
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>

@endverbatim
