@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | Create schedule</title>
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
  <x-legacy-js name="page-loading" />
@verbatim
</head>
<body>
@endverbatim
@if ($role === 'super-admin')
@verbatim
  <div class="app-shell" id="schedPage" data-view="sections" data-actor="super_admin" data-page="create" data-room-layout="table">
@endverbatim
@else
@verbatim
  <div class="app-shell" id="schedPage" data-view="sections" data-actor="admin" data-page="create" data-room-layout="table">
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
    <div class="topbar-tabs" aria-label="Schedule workspaces">
@endverbatim
      <x-app-nav-button />
@verbatim
      <button type="button" class="topbar-tab active" data-ws-tab="mine">Working timetable</button>
@endverbatim
@if ($role === 'super-admin')
@verbatim
      <button type="button" class="topbar-tab" data-ws-tab="submitted">For approval</button>
@endverbatim
@else
@verbatim
      <button type="button" class="topbar-tab" data-ws-tab="submitted">Sent for approval</button>
@endverbatim
@endif
@verbatim
      <button type="button" class="topbar-tab" data-ws-tab="saves">Saves</button>
      <button type="button" class="topbar-tab" data-ws-tab="archives">Archives</button>
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
    </div>
    </div>
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim
      <main class="shell">
        <header class="page-header">
          <h1 class="page-title" id="schedPageTitle">Create schedule</h1>
@endverbatim
@if ($role === 'super-admin')
@verbatim
          <p class="page-sub" id="schedPageSub">This is your working timetable. Named copies go on Saves; share a save if Records Officer should see it. Lunch is 12:00pm–1:00pm.</p>
@endverbatim
@else
@verbatim
          <p class="page-sub" id="schedPageSub">This is your working timetable. Named copies go on Saves; share a save if University Registrar should see it. Lunch is 12:00pm–1:00pm.</p>
@endverbatim
@endif
@verbatim
        </header>
        <section class="card cs-card" id="schedColleges" aria-labelledby="csTitle" hidden></section>
        <section class="cs-working" id="schedWorkingNotes" aria-live="polite" hidden></section>
        <section class="card">
          <p class="sched-term-banner" id="schedTermBanner"></p>
          <p class="sched-workspace-hint" id="schedWorkspaceHint"></p>
          <p class="sched-archive-hint" id="schedArchiveHint" hidden></p>
          <div id="schedArchivedList" hidden></div>
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
          <div class="sched-actions">
            <button type="button" class="sched-btn sched-btn--primary" id="schedGenerate">Generate schedule</button>
            <button type="button" class="sched-btn sched-btn--primary" id="schedGenerateRemaining">Schedule remaining classes</button>
            <button type="button" class="sched-btn sched-btn--primary" id="schedGenerateNew" hidden>Schedule new sections</button>
            <div class="sched-gen-settings" id="schedGenSettingsWrap" hidden>
              <button type="button" class="sched-btn sched-btn--ghost" id="schedGenSettingsBtn" aria-expanded="false" aria-controls="schedGenPanel">Generate settings</button>
              <div class="sched-gen-panel" id="schedGenPanel" role="dialog" aria-labelledby="schedGenTitle" hidden>
                <div class="sched-gen-head">
                  <h2 id="schedGenTitle">Generate settings</h2>
                  <button type="button" class="sched-gen-close" data-gen-close aria-label="Close settings">&times;</button>
                </div>
                <p class="sched-gen-scope" id="schedGenScope"></p>
                <label class="sched-field">
                  <span>Automatic section merges</span>
                  <select id="schedGenMerge">
                    <option value="auto">Automatic</option>
                    <option value="manual">Confirm each merge</option>
                    <option value="off">Off</option>
                  </select>
                </label>
                <label class="sched-field">
                  <span>Split a lecture into two sessions when hours/week exceed</span>
                  <input id="schedGenSplitHours" type="number" min="1" max="6" step="0.5" value="3">
                </label>
                <label class="sched-field">
                  <span>Split a laboratory into two sessions when hours/week exceed</span>
                  <input id="schedGenSplitLabHours" type="number" min="1" max="6" step="0.5" value="3">
                </label>
                <label class="sched-check">
                  <input id="schedGenKeepLunch" type="checkbox" checked>
                  <span>Keep 12:00–1:00 vacant when generating</span>
                </label>
                <label class="sched-field">
                  <span>Split sections to save a class</span>
                  <select id="schedGenPartitionScope">
                    <option value="any">On</option>
                    <option value="off">Off</option>
                  </select>
                </label>
                <div class="sched-gen-info" id="schedGenInfo">
                  <button type="button" class="sched-gen-info-btn sched-gen-info-btn--text" id="schedGenInfoBtn" aria-expanded="false" aria-controls="schedGenInfoPop">How merging and the lunch break work</button>
                  <div class="sched-gen-info-pop" id="schedGenInfoPop" role="region" aria-label="Section merging and lunch break">
                    <p class="sched-gen-info-title">Section Merging</p>
                    <p>Small sections can be split and merged with other classes of the same subject to reduce the number of classes. Partitions must have at least 5 students, with up to 3 partitions per subject, and are only created when they eliminate a class.</p>
                    <p><strong>On:</strong> a section is split whenever that removes a class. <strong>Off:</strong> sections are never split.</p>
                    <p>Automatic section merging must be on.</p>
                    <p class="sched-gen-info-title">Lunch Break</p>
                    <p>Generate skips lunch by default. You can still schedule a class during lunch by editing its times, with a confirmation prompt. 12:00–1:00 remains greyed out for visibility.</p>
                  </div>
                </div>
                <button type="button" class="sched-btn sched-btn--primary" id="schedGenSave">Save settings</button>
              </div>
            </div>
            <button type="button" class="sched-btn sched-btn--submit" id="schedSubmitColleges" hidden></button>
            <span class="sched-actions-slot" id="schedWorkspaceActions"></span>
            <button type="button" class="sched-btn sched-btn--ghost" id="schedBackArchived" hidden>Back to archived offerings</button>
            <p class="sched-gen-hint sched-gen-hint--all">Generate places every class in a room and time for you. Published, locked colleges are kept exactly as they are.</p>
            <p class="sched-gen-hint sched-gen-hint--rest" id="schedGenHintRest">Schedule remaining classes places only the classes that don’t have a room and time yet. Everything already placed stays put.</p>
            <p class="sched-gen-hint sched-gen-hint--new" id="schedGenHintNew" hidden></p>
            <p class="sched-nosections" id="schedNoSections" hidden></p>
            <p class="sched-status" id="schedStatus" role="status"></p>
          </div>
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
            <button type="button" class="sched-filters-btn" id="schedFiltersBtn" aria-expanded="false">Filters <span class="sched-attention-count" data-filter-count hidden>0</span></button>
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
  <div class="sched-modal" id="schedMergeModal" hidden>
    <div class="sched-modal-card">
      <h2>Merge these sections?</h2>
      <div id="schedMergeBody"></div>
      <div class="sched-modal-actions">
        <button type="button" class="sched-btn" id="schedMergeYes">Merge</button>
        <button type="button" class="sched-btn sched-btn--ghost" id="schedMergeNo">Keep separate</button>
      </div>
    </div>
  </div>
  <div class="sched-modal" id="schedEditModal" hidden>
    <div class="sched-modal-card sched-modal-card--wide">
      <h2 id="schedEditTitle">Edit class</h2>
      <div class="sched-edit-saved" id="schedEditSaved" aria-live="polite">
        <span class="sched-edit-saved-mark" aria-hidden="true">&#10003;</span>
        <span id="schedEditSavedText">Saved</span>
      </div>
      <p class="sched-err" id="schedEditError" role="alert"></p>
      <div class="sched-edit-body">
      <div class="sched-edit-views" id="schedEditViews" hidden>
        <button type="button" class="sched-edit-view is-active" id="schedEditViewSession" aria-pressed="true">This session</button>
        <button type="button" class="sched-edit-view" id="schedEditViewSections" aria-pressed="false">Sections</button>
      </div>
      <div class="sched-edit-meta" id="schedEditMeta"></div>
      <div id="schedEditMeeting">
        <div class="sched-edit-section sched-edit-section--tight" id="schedEditMeetingListWrap">
          <h3 class="sched-edit-classic-only">Meetings</h3>
          <div id="schedEditMeetingList"></div>
          <div class="sched-modal-actions" id="schedEditClassicListActions">
            <button type="button" class="sched-btn sched-btn--ghost" id="schedEditAdd">Add meeting</button>
            <button type="button" class="sched-btn sched-btn--ghost" id="schedEditCombine">Combine meetings</button>
          </div>
        </div>
        <div id="schedEditMeetingForm">
          <div class="sched-edit-grid sched-edit-grid--times">
            <div class="sched-field">
              <label for="schedEditDay">Day</label>
              <select id="schedEditDay">
                <option value="Mon">Monday</option>
                <option value="Tue">Tuesday</option>
                <option value="Wed">Wednesday</option>
                <option value="Thu">Thursday</option>
                <option value="Fri">Friday</option>
                <option value="Sat">Saturday</option>
              </select>
            </div>
            <div class="sched-field">
              <label for="schedEditStart">Start</label>
              <select id="schedEditStart"></select>
            </div>
            <div class="sched-field">
              <label for="schedEditEnd">End</label>
              <select id="schedEditEnd"></select>
            </div>
            <div class="sched-field sched-field--full">
              <label for="schedEditRoom">Room</label>
              <div class="sched-room-pick">
                <select id="schedEditRoomBuilding" aria-label="Filter by building"></select>
                <select id="schedEditRoomTypeFilter" aria-label="Filter by room type"></select>
                <select id="schedEditRoom"></select>
              </div>
              <button type="button" class="sched-room-link" id="schedEditRoomLink" hidden>View this room’s timetable</button>
              <p class="sched-edit-hours" id="schedEditRoomHint"></p>
            </div>
          </div>
          <p class="sched-edit-hours" id="schedEditHours"></p>
          <div class="sched-modal-actions" id="schedEditFormActions">
            <button type="button" class="sched-btn sched-btn--ghost" id="schedEditSplit">Split this meeting</button>
            <button type="button" class="sched-btn sched-btn--ghost" id="schedEditRemove">Remove meeting</button>
          </div>
        </div>
        <div class="sched-modal-actions" id="schedEditJobsExtra"></div>
        <div class="sched-planner" id="schedEditPlanner" hidden>
          <h3 id="schedEditPlannerTitle">Add meeting</h3>
          <p class="sched-edit-hours" id="schedEditPlannerKeep"></p>
          <div class="sched-edit-grid">
            <div class="sched-field">
              <label for="schedEditPlannerDuration">Duration</label>
              <select id="schedEditPlannerDuration"></select>
            </div>
            <div class="sched-field sched-field--full">
              <label for="schedEditPlannerRoom">Room</label>
              <div class="sched-room-pick">
                <select id="schedEditPlannerRoomBuilding" aria-label="Filter by building"></select>
                <select id="schedEditPlannerRoomTypeFilter" aria-label="Filter by room type"></select>
                <select id="schedEditPlannerRoom"></select>
              </div>
            </div>
          </div>
          <p class="sched-edit-hours" id="schedEditPlannerStatus"></p>
          <div class="sched-planner-windows">
            <div id="schedEditPlannerSlots" class="sched-slot-days"></div>
            <div id="schedEditPlannerAlts" class="sched-alt-list"></div>
          </div>
          <div class="sched-modal-actions">
            <button type="button" class="sched-btn sched-btn--ghost" id="schedEditPlannerCancel">Cancel</button>
          </div>
        </div>
      </div>
      <div class="sched-edit-section" id="schedEditSections" hidden>
        <h3 class="sched-edit-classic-only">Sections in this class</h3>
        <p class="sched-edit-hours sched-edit-jobs-only" id="schedEditSectionsHint">Choose Merge, Partition, Remove, or Recombine, then Save. Removing a section unschedules it.</p>
        <div id="schedEditMemberList"></div>
        <div class="sched-field" id="schedEditTargetWrap" hidden>
          <label for="schedEditTarget">Merge this section into</label>
          <select id="schedEditTarget"></select>
        </div>
        <div class="sched-field" id="schedEditPartitionWrap" hidden>
          <p class="sched-edit-hours" id="schedEditPartitionHint"></p>
          <div id="schedEditPartitionBar" class="sched-partition-bar"></div>
          <div id="schedEditPartitionSizes"></div>
          <div class="sched-modal-actions">
            <button type="button" class="sched-btn sched-btn--ghost" id="schedEditPartitionAdd">Add a partition</button>
            <button type="button" class="sched-btn sched-btn--ghost" id="schedEditPartitionRemove">Remove last partition</button>
          </div>
        </div>
      </div>
      </div>
      <div class="sched-modal-actions" id="schedEditFooter">
        <button type="button" class="sched-btn" id="schedEditSave">Save meeting</button>
        <button type="button" class="sched-btn sched-btn--ghost" id="schedEditCancel">Close</button>
      </div>
    </div>
  </div>
@endverbatim
@if ($role === 'super-admin')
@verbatim
  <div class="sched-modal" id="schedRejectModal" hidden>
    <div class="sched-modal-card">
      <h2>Send back for revisions</h2>
      <p class="sched-edit-hours">The Records Officer will see this note on Create schedule. If the VPAA already published an earlier copy, deans and program chairs keep that copy.</p>
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
  <div class="sched-modal" id="schedSaveAsModal" hidden>
    <div class="sched-modal-card">
      <h2>Save as</h2>
      <p class="sched-edit-hours">Name this version. The timetable you are working on stays active; the named copy appears on Saves.</p>
      <div class="sched-field">
        <label for="schedSaveAsName">Save name</label>
        <input id="schedSaveAsName" type="text" maxlength="120" placeholder="e.g. Draft after room swap" />
      </div>
      <p class="sched-err" id="schedSaveAsError" role="alert"></p>
      <div class="sched-modal-actions">
        <button type="button" class="sched-btn" id="schedSaveAsConfirm">Save</button>
        <button type="button" class="sched-btn sched-btn--ghost" id="schedSaveAsCancel">Cancel</button>
      </div>
    </div>
  </div>
  <div class="sched-modal" id="schedOpenSaveModal" hidden>
    <div class="sched-modal-card">
      <h2>Open this save</h2>
      <p class="sched-edit-hours" id="schedOpenSaveBody">Opening a save replaces the timetable currently in Working timetable. Save as first if you still need it.</p>
      <div class="sched-modal-actions">
        <button type="button" class="sched-btn" id="schedOpenSaveMine">Open in Working timetable</button>
        <button type="button" class="sched-btn sched-btn--ghost" id="schedOpenSaveCancel">Cancel</button>
      </div>
    </div>
  </div>
  <div class="sched-modal sched-modal--room-preview" id="schedRoomPreviewModal" hidden>
    <div class="sched-modal-card sched-modal-card--timetable">
      <h2 id="schedRoomPreviewTitle">Room timetable</h2>
      <p class="sched-edit-hours" id="schedRoomPreviewSub">This is the saved timetable for this room. Close it to keep editing the class.</p>
      <div class="sched-room-preview-grid" id="schedRoomPreviewGrid"></div>
      <div class="sched-modal-actions">
        <button type="button" class="sched-btn sched-btn--ghost" id="schedRoomPreviewClose">Close</button>
      </div>
    </div>
  </div>
  <div class="sched-modal sched-modal--confirm" id="schedConfirmModal" hidden>
    <div class="sched-modal-card">
      <h2 id="schedConfirmTitle">Use this room anyway?</h2>
      <p class="sched-edit-hours is-warn" id="schedConfirmBody"></p>
      <div class="sched-modal-actions">
        <button type="button" class="sched-btn" id="schedConfirmYes">Save anyway</button>
        <button type="button" class="sched-btn sched-btn--ghost" id="schedConfirmNo">Go back</button>
      </div>
    </div>
  </div>
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
  <x-legacy-js name="schedule-colleges" />
  <x-legacy-js name="schedule-page" />
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>

@endverbatim
