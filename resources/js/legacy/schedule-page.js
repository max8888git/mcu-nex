/**
 * Create schedule / final report: load a term timetable and browse it
 * by section, by room, or by class. Create schedule can generate and edit;
 * final-report sets data-readonly="true" on #schedPage to keep the same
 * views and filters without changing the saved schedule.
 */
(function (global) {
  'use strict';

  var DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  var DAY_LABELS = { Mon: 'Mon', Tue: 'Tue', Wed: 'Wed', Thu: 'Thu', Fri: 'Fri', Sat: 'Sat' };
  var DAY_FULL = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday' };
  var PAIR_OF = { Mon: 'Wed', Wed: 'Mon', Tue: 'Thu', Thu: 'Tue', Fri: 'Sat', Sat: 'Fri' };
  var GRID_START = 7 * 60;
  var GRID_END = 19 * 60;
  var SLOT = 30;
  var LUNCH_START = 12 * 60;
  var LUNCH_END = 13 * 60;
  var PALETTE = ['#ede4f5', '#dbeafe', '#dcfce7', '#fef3c7', '#fce7f3', '#e0e7ff', '#ffedd5', '#e2e8f0'];
  var roomLabelById = {};

  function ready(fn) {
    if (global.MCUNexReady) return global.MCUNexReady.then(fn);
    fn();
  }

  function qs(id) {
    return document.getElementById(id);
  }

  function semesterLabel(code) {
    if (code === '2') return '2nd semester';
    if (code === 'summer') return 'Summer';
    return '1st semester';
  }

  function dayOrder(d) {
    return { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }[d] || 9;
  }

  function meetingRoomLabel(m) {
    if (!m) return '';
    if (m.roomId && roomLabelById[m.roomId]) return roomLabelById[m.roomId];
    return m.roomCode || '';
  }

  function meetingWhen(m) {
    return m.day + ' ' + formatTimeRange(m.start, m.end);
  }

  function meetingLine(m) {
    var code = meetingRoomLabel(m);
    var room = code ? ' · ' + code : ' · Off-campus';
    return meetingWhen(m) + room;
  }

  function sortedMeetings(meetings) {
    return (meetings || []).slice().sort(function (a, b) {
      var day = dayOrder(a.day) - dayOrder(b.day);
      if (day) return day;
      return String(a.start || '').localeCompare(String(b.start || ''));
    });
  }

  function meetingColumnHtml(meetings, options) {
    var withRoom = !options || options.room !== false;
    var note = options && options.note ? String(options.note) : '';
    var list = sortedMeetings(meetings);
    function stacked(fn) {
      return list
        .map(function (m) {
          return escapeHtml(fn(m));
        })
        .join('<br>');
    }
    var timeHtml = list.length
      ? stacked(function (m) {
          return formatTimeRangeLines(m.start, m.end) || '—';
        })
      : '—';
    if (note) {
      timeHtml = (list.length ? timeHtml + ' · ' : '') + escapeHtml(note);
    }
    if (!list.length && !note) {
      return withRoom ? '<td>—</td><td>—</td><td>—</td>' : '<td>—</td><td>—</td>';
    }
    var seenDay = {};
    var dayLabels = [];
    var seenRoom = {};
    var roomLabels = [];
    list.forEach(function (m) {
      var label = DAY_FULL[m.day] || m.day || '—';
      if (!seenDay[label]) {
        seenDay[label] = true;
        dayLabels.push(label);
      }
      var room = meetingRoomLabel(m) || 'Off-campus';
      if (!seenRoom[room]) {
        seenRoom[room] = true;
        roomLabels.push(room);
      }
    });
    return (
      '<td>' +
      (dayLabels.length ? dayLabels.map(escapeHtml).join('<br>') : '—') +
      '</td><td class="sched-cell-time">' +
      timeHtml +
      '</td>' +
      (withRoom
        ? '<td>' +
          (roomLabels.length ? roomLabels.map(escapeHtml).join('<br>') : '—') +
          '</td>'
        : '')
    );
  }

  function sectionLabel(name, count, cls) {
    var label = String(name || '');
    if (!label) return '';
    var n = Number(count);
    if (!isFinite(n)) return label;
    if (isPartitioned(cls)) {
      return label + ' (' + Number(cls.partitionStudents) + ' of ' + Number(cls.sectionStudentCount) + ' students)';
    }
    return label + ' (' + n + (n === 1 ? ' student' : ' students') + ')';
  }

  function isPartitioned(cls) {
    return !!(
      cls &&
      Number(cls.partitionCount) > 1 &&
      isFinite(Number(cls.partitionStudents)) &&
      isFinite(Number(cls.sectionStudentCount))
    );
  }

  function studentCountLabel(n) {
    var x = Math.round(Number(n) || 0);
    if (x < 0) x = 0;
    return x + (x === 1 ? ' student' : ' students');
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function minutesOf(hhmm) {
    var p = String(hhmm || '').split(':');
    return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
  }

  function slotIndex(hhmm) {
    return Math.round((minutesOf(hhmm) - GRID_START) / SLOT);
  }

  function slotCount() {
    return Math.round((GRID_END - GRID_START) / SLOT);
  }

  function formatClock(mins) {
    var h = Math.floor(mins / 60) % 24;
    if (h < 0) h += 24;
    var m = mins % 60;
    if (m < 0) m += 60;
    var suffix = h >= 12 ? 'pm' : 'am';
    var h12 = h % 12;
    if (!h12) h12 = 12;
    return h12 + ':' + (m < 10 ? '0' : '') + m + suffix;
  }

  function formatHhmm(hhmmStr) {
    if (hhmmStr == null || String(hhmmStr).trim() === '') return '';
    return formatClock(minutesOf(hhmmStr));
  }

  function formatTimeRange(start, end) {
    var a = formatHhmm(start);
    var b = formatHhmm(end);
    if (!a && !b) return '';
    return a + '–' + b;
  }

  function formatTimeRangeLines(start, end) {
    var a = formatHhmm(start);
    var b = formatHhmm(end);
    if (!a && !b) return '';
    if (!a) return b;
    if (!b) return a;
    return a + '\n– ' + b;
  }

  function hhmm(mins) {
    var h = Math.floor(mins / 60);
    var m = mins % 60;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
  }

  function colorFor(code) {
    var h = 0;
    String(code || '').split('').forEach(function (c) {
      h = (h * 31 + c.charCodeAt(0)) | 0;
    });
    return PALETTE[Math.abs(h) % PALETTE.length];
  }

  function emptyLine(text) {
    var p = document.createElement('p');
    p.className = 'sched-empty';
    p.textContent = text;
    return p;
  }

  ready(function () {
    var yearEl = qs('schedYear');
    var semEl = qs('schedSemester');
    var colEl = qs('schedCollege');
    var depWrap = qs('schedDepartmentWrap');
    var depEl = qs('schedDepartment');
    var ylEl = qs('schedYearLevel');
    var sectionStatusEl = qs('schedSectionStatus');
    var genSettingsWrap = qs('schedGenSettingsWrap');
    var genSettingsBtn = qs('schedGenSettingsBtn');
    var genPanel = qs('schedGenPanel');
    var genMergeEl = qs('schedGenMerge');
    var genSplitEl = qs('schedGenSplitHours');
    var genSplitLabEl = qs('schedGenSplitLabHours');
    var genLunchEl = qs('schedGenKeepLunch');
    var genPartitionEl = qs('schedGenPartitionScope');
    var genScopeEl = qs('schedGenScope');
    var genSaveBtn = qs('schedGenSave');
    var runBtn = qs('schedGenerate');
    var remainingBtn = qs('schedGenerateRemaining');
    var newSectionsBtn = qs('schedGenerateNew');
    var statusEl = qs('schedStatus');
    var sectionView = qs('schedSectionView');
    var roomView = qs('schedRoomView');
    var classView = qs('schedClassView');
    var genFilters = qs('schedGenerateFilters');
    var layoutTabs = qs('schedLayoutTabs');
    var roomTableFilters = qs('schedRoomTableFilters');
    var classFilters = qs('schedClassFilters');
    var classSearchEl = qs('schedClassSearch');
    var classCollegeEl = qs('schedClassCollege');
    var classComponentEl = qs('schedClassComponent');
    var classStatusEl = qs('schedClassStatus');
    var classYearLevelEl = qs('schedClassYearLevel');
    var classBuildingEl = qs('schedClassBuilding');
    var classMergedEl = qs('schedClassMerged');
    var roomBuildingEl = qs('schedRoomBuilding');
    var roomFloorEl = qs('schedRoomFloor');
    var roomTypeEl = qs('schedRoomType');
    var roomCrumb = qs('schedRoomCrumb');
    var modal = qs('schedMergeModal');
    var modalBody = qs('schedMergeBody');
    var modalYes = qs('schedMergeYes');
    var modalNo = qs('schedMergeNo');
    var editModal = qs('schedEditModal');
    // 'jobs' is This session / Sections. Set this to 'classic' to restore the previous single scroll.
    var editClassLayout = 'jobs';
    // 'guided' uses Merge, Partition, Remove, and Recombine as separate buttons, with Save beside Close.
    // Set this to 'menu' to restore the per-row action list and Apply button.
    var sectionActionLayout = 'guided';
    var SECTION_PARTITION_MIN = 5;
    var page = document.getElementById('schedPage');
    var shell = document.querySelector('.shell');
    var printBtn = qs('schedPrint');
    var readOnly = !!(page && page.getAttribute('data-readonly') === 'true');
    if (!yearEl || !semEl) return;
    if (!readOnly && !runBtn) return;

    var primaryView = (page && page.getAttribute('data-view')) || 'sections';
    var layout = defaultRoomLayout();
    var openSectionGrid = '';
    var openRoomTableGrid = '';
    var selectedBuilding = '';
    var selectedRoom = '';
    var runId = '';
    var classes = [];
    var rooms = [];
    var editState = null;
    var actor = (page && page.getAttribute('data-actor')) || '';
    var selectedVersion = '';
    var publication = null;
    var generatorSettings = { mergeMode: 'auto', splitHours: 3, splitLabHours: 3, keepLunch: true };
    var offeringPane = 'active';
    var currentOffering = null;
    var archivedOfferings = [];
    var offeringMeta = { canArchive: false, canUnarchive: false, viewingLive: true };
    var archivedPick = null;
    var bannerEl = qs('schedTermBanner');
    var segActive = qs('schedSegActive');
    var segArchived = qs('schedSegArchived');
    var archiveHint = qs('schedArchiveHint');
    var archivedList = qs('schedArchivedList');
    var workspaceEl = qs('schedWorkspace');
    var archiveBtn = qs('schedArchiveOffering');
    var backArchivedBtn = qs('schedBackArchived');
    var pageKind = (page && page.getAttribute('data-page')) === 'report' ? 'report' : 'create';
    var usesWorkspace = actor === 'admin' || actor === 'super_admin' || (actor === 'vpaa' && pageKind === 'report');
    var workspaceState = null;
    var pendingSaveId = '';
    var reportYear = '';
    var reportSem = '';
    var reportTerms = [];
    var savesPanel = qs('schedSavesPanel');
    var workspaceHint = qs('schedWorkspaceHint');
    var workspaceActions = qs('schedWorkspaceActions');
    // Create schedule: pick one section or room from a list instead of scrolling through all of them
    var findEl = qs('schedFind');
    var attentionBtn = qs('schedAttention');
    var masterDetail = (pageKind === 'create' || actor === 'vpaa') && !!findEl;
    var attentionOnly = false;
    var detailMode = 'grid';
    var clashIndex = null;
    var sectionsKnown = false;

    function allowedWorkspaces() {
      // Reports has the Final report only (what waits for the VPAA is in Create schedule → For approval)
      return pageKind === 'report' ? ['final'] : ['mine', 'submitted', 'saves', 'archives'];
    }

    function workspaceFromUrl() {
      try {
        var q = new URLSearchParams(location.search || '');
        var ws = String(q.get('ws') || '').toLowerCase();
        if (ws === 'shared') ws = 'mine';
        if (allowedWorkspaces().indexOf(ws) >= 0) return ws;
      } catch (err) {}
      return pageKind === 'report' ? 'final' : 'mine';
    }

    var workspace = usesWorkspace ? workspaceFromUrl() : 'mine';

    function writeWorkspaceUrl() {
      if (!usesWorkspace) return;
      try {
        var url = new URL(location.href);
        url.searchParams.set('ws', workspace);
        history.replaceState(null, '', url.pathname + url.search + url.hash);
      } catch (err) {}
    }

    function ws() {
      return workspaceState || {};
    }

    function canManageOfferings() {
      return actor === '' || actor === 'admin' || actor === 'super_admin';
    }

    function scheduleLocked() {
      if (!usesWorkspace) return readOnly || offeringPane === 'archived';
      if (pageKind === 'report') return true;
      if (workspace === 'submitted' || workspace === 'saves' || workspace === 'archives') return true;
      return offeringPane === 'archived';
    }

    function offeringTitle(year, code) {
      return (year || 'Academic year') + ' · ' + semesterLabel(code);
    }

    function csvAttr(name) {
      return String((page && page.getAttribute(name)) || '')
        .split(',')
        .map(function (s) {
          return s.trim();
        })
        .filter(Boolean);
    }

    function normName(s) {
      return String(s || '')
        .toLowerCase()
        .replace(/&/g, ' and ')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\b(the|of|and|college|department|program)\b/g, ' ')
        .replace(/s\b/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    }

    function nameMatches(a, b) {
      var x = normName(a);
      var y = normName(b);
      if (!x || !y) return false;
      return x === y || x.indexOf(y) !== -1 || y.indexOf(x) !== -1;
    }

    function scopedColleges() {
      var names = csvAttr('data-scope-college-name');
      if (!names.length || !global.OrgStore) return [];
      return global.OrgStore.colleges().filter(function (c) {
        return names.some(function (n) {
          return nameMatches(c.name, n);
        });
      });
    }

    function scopedDepartments() {
      var names = csvAttr('data-scope-department-name');
      var colleges = scopedColleges();
      if (!names.length || !global.OrgStore) return [];
      var out = [];
      (colleges.length ? colleges : global.OrgStore.colleges()).forEach(function (c) {
        (global.OrgStore.departmentsFor(c.id) || []).forEach(function (d) {
          if (
            names.some(function (n) {
              return nameMatches(d.name, n);
            })
          ) {
            out.push(d);
          }
        });
      });
      return out;
    }

    function matchesScope(c) {
      var cols = scopedColleges();
      if (cols.length) {
        var ok = cols.some(function (col) {
          return col.id === c.collegeId;
        });
        if (!ok) return false;
      }
      var deps = scopedDepartments();
      if (deps.length) {
        return deps.some(function (d) {
          return d.id === c.departmentId;
        });
      }
      return true;
    }

    function versionLabel(code) {
      if (actor === 'super_admin') {
        return (
          {
            working: 'Live timetable',
            pendingSuperAdmin:
              publication && publication.returnedFromVpaa ? 'Returned by VPAA' : 'Submitted by the Records Officer',
            pendingVpaa: 'Sent to VPAA',
            published: 'VPAA published'
          }[code] || code
        );
      }
      if (actor === 'vpaa') {
        return (
          {
            pendingVpaa: 'Ready to publish',
            published: 'Published'
          }[code] || code
        );
      }
      return (
        {
          working: 'Working copy',
          pendingSuperAdmin: 'Sent to the University Registrar',
          pendingVpaa: 'Awaiting VPAA',
          published: 'VPAA approved'
        }[code] || code
      );
    }

    function emptyScheduleText(forClasses) {
      if (offeringPane === 'active' && !currentOffering) {
        return 'No current offering yet. Set the academic year and semester on the Sections page, then return here.';
      }
      var term = (yearEl.value || '') + ' ' + semesterLabel(semEl.value);
      var vers = (publication && publication.versions) || [];
      if (actor === 'dean' || actor === 'program_chair') {
        return (
          'No published timetable for ' +
          term.trim() +
          ' yet. This page only shows the copy after the VPAA approves it, so an empty list here is expected while Records Officer is still working.'
        );
      }
      if (actor === 'vpaa' && vers.indexOf('pendingVpaa') < 0 && vers.indexOf('published') < 0) {
        return (
          'No campus package is waiting for you on ' +
          term.trim() +
          '. University Registrar has to approve Records Officer’s timetable first. This is not a database connection problem.'
        );
      }
      if (selectedVersion === 'published') {
        return 'No VPAA-approved timetable for ' + term.trim() + ' yet.';
      }
      if (selectedVersion === 'pendingVpaa') {
        return 'Nothing is waiting for VPAA approval.';
      }
      if (selectedVersion === 'pendingSuperAdmin') {
        return 'Nothing is waiting for the University Registrar’s approval.';
      }
      if (usesWorkspace && workspace === 'mine') {
        return forClasses
          ? 'No personal schedule yet. Press Generate schedule on By section, or open a save.'
          : 'No personal schedule yet. Press Generate schedule, or open a save from the Saves tab.';
      }
      if (usesWorkspace && workspace === 'submitted') {
        return actor === 'super_admin'
          ? 'Nothing is waiting for your review. Colleges the Records Officer sends you appear here.'
          : 'Nothing is waiting for the University Registrar. Send colleges from Colleges on the Working timetable tab.';
      }
      if (usesWorkspace && workspace === 'awaiting') {
        return actor === 'vpaa'
          ? 'Nothing is waiting for your approval.'
          : 'No timetable is waiting for the VPAA.';
      }
      if (usesWorkspace && workspace === 'final') {
        return actor === 'vpaa'
          ? 'You have not published a timetable for ' + term.trim() + ' yet.'
          : 'No VPAA-approved timetable for ' + term.trim() + ' yet.';
      }
      if (scheduleLocked()) return 'No saved schedule for ' + term.trim() + '.';
      if (forClasses) return 'No schedule yet. Press Generate schedule on By section to place classes.';
      return 'No schedule yet. Press Generate schedule to place classes.';
    }

    function noRoomsText() {
      return scheduleLocked()
        ? 'No rooms in Facilities yet.'
        : 'No rooms in Facilities yet. Add rooms in Data management, then generate a schedule.';
    }

    function gridEditOpts(empty) {
      var opts = { empty: empty };
      if (!scheduleLocked()) {
        opts.onEdit = function (block) {
          openEditModal(block.class, block.meeting);
        };
      }
      return opts;
    }

    function actionsCol() {
      return scheduleLocked() ? '' : '<col class="sched-col-actions">';
    }

    function actionsHead() {
      return scheduleLocked() ? '' : '<th></th>';
    }

    function editCell(offeringId, meetingId) {
      if (scheduleLocked()) return '';
      var extra =
        meetingId != null && String(meetingId) !== ''
          ? ' data-edit-meeting="' + escapeHtml(meetingId) + '"'
          : '';
      return (
        '<td class="sched-actions-cell"><button type="button" class="sched-link-btn" data-edit-offering="' +
        escapeHtml(offeringId) +
        '"' +
        extra +
        '>Edit</button></td>'
      );
    }

    function collegeLocked(collegeId) {
      var panel = global.McuScheduleColleges;
      return !!(panel && workspace === 'mine' && panel.isLocked(collegeId));
    }

    function lockedCell() {
      return '<td class="sched-actions-cell"><span class="sched-locked-tag" title="Published and locked. The University Registrar can unlock it to fix a mistake.">Locked</span></td>';
    }

    function sectionSubjectActions(c) {
      if (scheduleLocked()) return '';
      if (collegeLocked(c.collegeId)) return lockedCell();
      var html =
        '<td class="sched-actions-cell"><button type="button" class="sched-link-btn" data-edit-offering="' +
        escapeHtml(c.id) +
        '">Edit</button>';
      if ((c.meetings || []).length) {
        html +=
          '<button type="button" class="sched-link-btn" data-unschedule-offering="' +
          escapeHtml(c.id) +
          '">Unschedule</button>';
      }
      return html + '</td>';
    }

    function programFilterValue(p) {
      return p.departmentId || 'college:' + p.collegeId;
    }

    function listedPrograms() {
      if (!global.OrgStore || typeof global.OrgStore.programs !== 'function') return [];
      var collegeId = colEl ? colEl.value : '';
      var scoped = scopedDepartments();
      var list = global.OrgStore.programs();
      if (scoped.length) {
        return list.filter(function (p) {
          return scoped.some(function (d) {
            return d.id === p.departmentId;
          });
        });
      }
      if (collegeId) {
        return list.filter(function (p) {
          return p.collegeId === collegeId;
        });
      }
      return list;
    }

    function matchesSectionFilters(c) {
      var college = colEl ? colEl.value : '';
      var prog = depEl && depWrap && !depWrap.hidden ? depEl.value : '';
      var yl = ylEl ? ylEl.value : '';
      if (college && c.collegeId !== college) return false;
      if (prog) {
        if (prog.indexOf('college:') === 0) {
          if (c.collegeId !== prog.slice(8) || c.departmentId) return false;
        } else if (c.departmentId !== prog) {
          return false;
        }
      }
      if (yl && String(c.yearLevel) !== String(yl)) return false;
      var place = sectionStatusEl ? sectionStatusEl.value : '';
      if (place) {
        var scheduled = classIsScheduled(c);
        if (place === 'scheduled' && !scheduled) return false;
        if (place === 'unscheduled' && scheduled) return false;
      }
      return true;
    }

    function classIsScheduled(c) {
      return !!(c && c.meetings && c.meetings.length && c.status === 'scheduled');
    }

    function defaultRoomLayout() {
      var pref = page && page.getAttribute('data-room-layout');
      return pref === 'table' || pref === 'grid' ? pref : 'table';
    }

    function applyHash() {
      var h = (location.hash || '').replace(/^#/, '');
      if (!h) return;
      var parts = h.split('/');
      if (parts[0] === 'rooms' || parts[0] === 'sections' || parts[0] === 'classes') {
        primaryView = parts[0];
      }
      if (primaryView === 'sections') {
        if (parts[1] && parts[1] !== 'table' && parts[1] !== 'grid') {
          openSectionGrid = decodeURIComponent(parts[1]);
        }
        return;
      }
      if (primaryView === 'classes') return;
      if (parts[1] === 'grid' || parts[1] === 'table') layout = parts[1];
      else layout = defaultRoomLayout();
      if (layout === 'grid') {
        selectedBuilding = parts[2] ? decodeURIComponent(parts[2]) : '';
        selectedRoom = parts[3] ? decodeURIComponent(parts[3]) : '';
      } else if (layout === 'table' && parts[2]) {
        openRoomTableGrid = decodeURIComponent(parts[2]);
      }
    }

    function writeHash() {
      var h = '#' + primaryView;
      if (primaryView === 'sections') {
        if (openSectionGrid) h += '/' + encodeURIComponent(openSectionGrid);
      } else if (primaryView === 'classes') {
        /* list only */
      } else {
        h += '/' + layout;
        if (layout === 'grid') {
          if (selectedBuilding) h += '/' + encodeURIComponent(selectedBuilding);
          if (selectedRoom) h += '/' + encodeURIComponent(selectedRoom);
        } else if (openRoomTableGrid) {
          h += '/' + encodeURIComponent(openRoomTableGrid);
        }
      }
      if (location.hash !== h) {
        try {
          history.replaceState(null, '', h);
        } catch (e) {
          location.hash = h;
        }
      }
    }

    function syncSectionFilters() {
      if (genFilters) {
        genFilters.hidden = primaryView !== 'sections';
      }
    }

    function hideGenInfo() {
      var info = qs('schedGenInfo');
      var infoBtn = qs('schedGenInfoBtn');
      if (info) info.classList.remove('is-open');
      if (infoBtn) infoBtn.setAttribute('aria-expanded', 'false');
    }

    function hideGenPanel() {
      if (genPanel) genPanel.hidden = true;
      if (genSettingsBtn) genSettingsBtn.setAttribute('aria-expanded', 'false');
      hideGenInfo();
    }

    function placementCounts() {
      var scheduled = 0;
      classes.forEach(function (c) {
        if (c && c.meetings && c.meetings.length) scheduled += 1;
      });
      return { total: classes.length, scheduled: scheduled };
    }

    function syncGenerateChrome() {
      var blocked = scheduleLocked() || primaryView !== 'sections' || (usesWorkspace && !ws().canGenerate);
      var counts = placementCounts();
      // Generate builds from the term's sections; with none there's nothing it can place
      var noSections = masterDetail && !blocked && !!currentOffering && noSectionsForTerm();
      var counts0 = placementCounts();
      var noSectionsEl = qs('schedNoSections');
      if (noSectionsEl) {
        // only when nothing is placed yet: Schedule remaining still works without sections
        noSectionsEl.hidden = !(noSections && counts0.scheduled === 0);
        if (noSections && counts0.scheduled === 0) {
          noSectionsEl.innerHTML =
            '<strong>Generate is off:</strong> it builds the timetable from the sections for ' +
            escapeHtml(offeringTitle(currentOffering.schoolYear, currentOffering.termCode)) +
            ', and there aren’t any yet. <a href="data-management/sections.html">Add sections</a>, then come back. ' +
            'Until then you can place classes by hand with Edit.';
        }
      }
      var showGenerate = !blocked && !noSections && !!currentOffering && counts.scheduled === 0;
      var showRemaining = !blocked && counts.scheduled > 0 && counts.scheduled < counts.total;
      var fresh = masterDetail && !blocked && counts.scheduled > 0 ? newSectionsForTerm() : [];
      if (newSectionsBtn) {
        newSectionsBtn.hidden = !fresh.length;
        newSectionsBtn.textContent = 'Schedule new sections (' + fresh.length + ')';
      }
      var newHint = qs('schedGenHintNew');
      if (newHint) {
        var byCollege = {};
        fresh.forEach(function (s) {
          var n = s.collegeName || 'Other';
          byCollege[n] = (byCollege[n] || 0) + 1;
        });
        newHint.hidden = !fresh.length;
        newHint.textContent = fresh.length
          ? 'New sections aren’t in your timetable yet: ' +
            Object.keys(byCollege).map(function (n) { return n + ' (' + byCollege[n] + ')'; }).join(', ') +
            '. Schedule new sections places only them, around the classes already here, so nothing that’s placed moves.'
          : '';
      }
      var restHint = qs('schedGenHintRest');
      if (restHint) {
        restHint.textContent = noSections
          ? 'Schedule remaining classes puts each unplaced class in the first free room and time that fits its room type, size, and college. Everything already placed stays put.'
          : 'Schedule remaining classes places only the classes that don’t have a room and time yet. Everything already placed stays put.';
      }
      if (runBtn) runBtn.hidden = !showGenerate;
      if (remainingBtn) remainingBtn.hidden = !showRemaining;
      var hideSettings = blocked;
      if (genSettingsWrap) genSettingsWrap.hidden = hideSettings;
      if (hideSettings) hideGenPanel();
    }

    function showView(which, nextLayout) {
      if (which === 'sections' || which === 'rooms' || which === 'classes') primaryView = which;
      if (nextLayout === 'table' || nextLayout === 'grid') layout = nextLayout;
      if (sectionView) sectionView.hidden = primaryView !== 'sections';
      if (roomView) roomView.hidden = primaryView !== 'rooms';
      if (classView) classView.hidden = primaryView !== 'classes';
      document.querySelectorAll('[data-sched-view]').forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-sched-view') === primaryView);
      });
      document.querySelectorAll('[data-sched-layout]').forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-sched-layout') === layout);
      });
      syncSectionFilters();
      syncTermFilterChrome();
      syncGenerateChrome();
      if (masterDetail) layout = 'table';
      if (layoutTabs) layoutTabs.hidden = masterDetail || primaryView !== 'rooms';
      if (roomTableFilters) roomTableFilters.hidden = !(primaryView === 'rooms' && layout === 'table');
      if (classFilters) classFilters.hidden = primaryView !== 'classes';
      if (roomCrumb) roomCrumb.hidden = masterDetail || !(primaryView === 'rooms' && layout === 'grid');
      if (findEl) {
        findEl.placeholder =
          primaryView === 'rooms' ? 'Find a room or building' : primaryView === 'classes' ? 'Find a course code or title' : 'Find a section, course, or room';
      }
      if (shell) {
        shell.classList.toggle(
          'sched-shell--wide',
          (primaryView === 'rooms' && layout === 'grid') ||
            (primaryView === 'rooms' && layout === 'table' && !!openRoomTableGrid) ||
            (primaryView === 'sections' && !!openSectionGrid)
        );
      }
      writeHash();
      render();
    }

    function archiveHintText() {
      if (actor === 'dean' || actor === 'program_chair') {
        return 'Archived offerings are previous academic years and semesters. You still only see the VPAA-published timetable for your college or programs.';
      }
      if (actor === 'vpaa') {
        return 'Archived offerings are previous academic years and semesters. Open one to review the package that was sent to you or that you published. Approval stays on the live offering.';
      }
      return 'Archived items are whole offerings: sections and the timetable together. Unarchive restores that entire term. Delete removes it permanently. Archive the live offering first if you need to restore an older one.';
    }

    function renderArchivedList() {
      if (!archivedList) return;
      archivedList.innerHTML = '';
      if (!archivedOfferings.length) {
        archivedList.appendChild(
          emptyLine(
            actor === 'dean' || actor === 'program_chair'
              ? 'No archived VPAA-published timetables yet.'
              : 'No archived offerings yet. When a semester ends, archive it here or on the Sections page. That files the sections and timetable together.'
          )
        );
        return;
      }
      archivedOfferings.forEach(function (info) {
        var card = document.createElement('div');
        card.className = 'sched-offering-card';
        var text = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'sched-offering-card__title';
        title.textContent = offeringTitle(info.schoolYear, info.termCode);
        var meta = document.createElement('div');
        meta.className = 'sched-offering-card__meta';
        var bits = [];
        bits.push(info.sectionCount + ' section' + (info.sectionCount === 1 ? '' : 's'));
        bits.push(info.classCount + ' class' + (info.classCount === 1 ? '' : 'es'));
        if (info.published) bits.push('VPAA published');
        if (info.archivedLabel) bits.push('Archived · ' + info.archivedLabel);
        meta.textContent = bits.join(' · ');
        text.appendChild(title);
        text.appendChild(meta);
        var actions = document.createElement('div');
        actions.className = 'sched-offering-card__actions';
        var viewBtn = document.createElement('button');
        viewBtn.type = 'button';
        viewBtn.className = 'sched-btn';
        viewBtn.textContent = 'View timetable';
        viewBtn.addEventListener('click', function () {
          archivedPick = { schoolYear: info.schoolYear, termCode: info.termCode };
          selectedVersion = '';
          offeringPane = 'archived';
          fillYears();
          loadSchedule();
        });
        actions.appendChild(viewBtn);
        if (offeringMeta.canUnarchive && canManageOfferings()) {
          var un = document.createElement('button');
          un.type = 'button';
          un.className = 'sched-btn sched-btn--ghost';
          un.textContent = 'Unarchive this offering';
          un.addEventListener('click', function () {
            if (!global.SectionsStore) return;
            un.disabled = true;
            global.SectionsStore.unarchiveTerm(info.schoolYear, info.termCode).then(function (res) {
              un.disabled = false;
              if (!res.ok) {
                setStatus(res.error || 'Could not unarchive this offering.', 'bad');
                return;
              }
              offeringPane = 'active';
              archivedPick = null;
              selectedVersion = '';
              if (usesWorkspace) workspace = 'mine';
              loadSchedule();
            });
          });
          actions.appendChild(un);
          var del = document.createElement('button');
          del.type = 'button';
          del.className = 'sched-btn sched-btn--danger';
          del.textContent = 'Delete';
          del.addEventListener('click', function () {
            if (!global.SectionsStore) return;
            askConfirm({
              title: 'Delete this offering?',
              message:
                'Delete ' +
                offeringTitle(info.schoolYear, info.termCode) +
                ' permanently? This removes every section and the timetable for that academic year and semester. This cannot be undone.',
              yes: 'Delete',
              no: 'Cancel',
              yesClass: 'sched-btn--danger'
            }).then(function (ok) {
            if (!ok) return;
            del.disabled = true;
            global.SectionsStore.deleteTerm(info.schoolYear, info.termCode).then(function (res) {
              del.disabled = false;
              if (!res.ok) {
                setStatus(res.error || 'Could not delete this offering.', 'bad');
                return;
              }
              if (
                archivedPick &&
                archivedPick.schoolYear === info.schoolYear &&
                archivedPick.termCode === info.termCode
              ) {
                archivedPick = null;
              }
              selectedVersion = '';
              loadSchedule();
            });
            });
          });
          actions.appendChild(del);
        }
        card.appendChild(text);
        card.appendChild(actions);
        archivedList.appendChild(card);
      });
    }

    function syncOfferingChrome() {
      var showArchivedList = usesWorkspace
        ? workspace === 'archives' && !archivedPick
        : offeringPane === 'archived' && !archivedPick;
      if (segActive) segActive.setAttribute('aria-selected', offeringPane === 'active' ? 'true' : 'false');
      if (segArchived) segArchived.setAttribute('aria-selected', offeringPane === 'archived' ? 'true' : 'false');
      if (qs('schedOfferingBar')) qs('schedOfferingBar').hidden = usesWorkspace;
      if (archiveHint) {
        archiveHint.hidden = !showArchivedList && !(usesWorkspace && workspace === 'archives' && archivedPick);
        archiveHint.textContent = archiveHintText();
      }
      if (archivedList) archivedList.hidden = !showArchivedList;
      if (showArchivedList) renderArchivedList();
      var hideTimetable = showArchivedList || (usesWorkspace && (workspace === 'saves' || (workspace === 'archives' && !archivedPick)));
      if (workspaceEl) workspaceEl.hidden = hideTimetable && usesWorkspace ? showArchivedList || workspace === 'saves' || (workspace === 'archives' && !archivedPick) : showArchivedList;
      if (usesWorkspace && workspaceEl) {
        workspaceEl.hidden = workspace === 'saves' || (workspace === 'archives' && !archivedPick);
      }
      if (bannerEl) {
        if (usesWorkspace && workspace === 'saves') {
          bannerEl.textContent = 'Named versions of this term. Opening one replaces the timetable in Working timetable.';
        } else if (offeringPane === 'archived' && archivedPick) {
          bannerEl.innerHTML =
            'Archived offering: <strong>' +
            escapeHtml(offeringTitle(archivedPick.schoolYear, archivedPick.termCode)) +
            '</strong> — read only.';
        } else if (usesWorkspace && workspace === 'archives' && !archivedPick) {
          bannerEl.textContent =
            'Past academic years and semesters, plus named versions you moved out of Saves.';
        } else if (offeringPane === 'archived') {
          bannerEl.textContent =
            'Each archived item is one academic year and semester for the whole campus. Sections and the timetable are filed together.';
        } else if (currentOffering && masterDetail) {
          var placed = classes.filter(classIsScheduled).length;
          var open = classes.length - placed;
          bannerEl.innerHTML =
            '<strong>' +
            escapeHtml(offeringTitle(currentOffering.schoolYear, currentOffering.termCode)) +
            '</strong>' +
            (classes.length
              ? ' · ' + placed + ' of ' + classes.length + ' classes placed' +
                (open ? ' · <span class="sched-banner-warn">' + open + ' not placed</span>' : '')
              : '');
        } else if (currentOffering) {
          bannerEl.innerHTML =
            'Current offering: <strong>' +
            escapeHtml(currentOffering.schoolYear) +
            '</strong> · <strong>' +
            escapeHtml(semesterLabel(currentOffering.termCode)) +
            '</strong> — every college and year level.';
        } else {
          bannerEl.textContent =
            'No current offering yet. Set the academic year and semester on the Sections page.';
        }
      }
      if (archiveBtn) archiveBtn.hidden = true;
      if (backArchivedBtn) {
        backArchivedBtn.hidden = !(
          (usesWorkspace && workspace === 'archives' && archivedPick) ||
          (!usesWorkspace && offeringPane === 'archived' && archivedPick)
        );
      }
      syncGenerateChrome();
    }

    function syncTermFilterChrome() {
      var yearField = yearEl && yearEl.closest ? yearEl.closest('.sched-field') : null;
      var semField = semEl && semEl.closest ? semEl.closest('.sched-field') : null;
      var termUsable = !!(yearEl && semEl) && (!yearEl.disabled || !semEl.disabled);
      if (yearField) yearField.hidden = !termUsable;
      if (semField) semField.hidden = !termUsable;
      var form = yearEl && yearEl.closest ? yearEl.closest('.sched-form') : null;
      if (!form) return;
      form.hidden = !termUsable;
    }

    function setOfferingPane(next) {
      offeringPane = next === 'archived' ? 'archived' : 'active';
      archivedPick = null;
      selectedVersion = '';
      classes = [];
      fillYears();
      showView(primaryView, layout);
      loadSchedule();
    }

    function fillYears() {
      if (usesWorkspace && pageKind === 'report') {
        var terms = reportTerms.length ? reportTerms : ((ws().reportTerms) || []);
        var years = [];
        terms.forEach(function (t) {
          if (t.schoolYear && years.indexOf(t.schoolYear) < 0) years.push(t.schoolYear);
        });
        if (!years.length && currentOffering) years.push(currentOffering.schoolYear);
        var keepYear = reportYear || yearEl.value || (currentOffering && currentOffering.schoolYear) || '';
        yearEl.innerHTML = '';
        if (!years.length) {
          yearEl.appendChild(new Option('No published terms yet', ''));
          yearEl.value = '';
          yearEl.disabled = true;
        } else {
          years.forEach(function (y) {
            yearEl.appendChild(new Option(y, y));
          });
          yearEl.value = years.indexOf(keepYear) >= 0 ? keepYear : years[0];
          yearEl.disabled = years.length <= 1;
        }
        reportYear = yearEl.value;
        var semsForYear = terms.filter(function (t) {
          return t.schoolYear === reportYear;
        });
        semEl.innerHTML = '';
        ['1', '2', 'summer'].forEach(function (code) {
          var hit = semsForYear.some(function (t) {
            return t.termCode === code;
          });
          if (!hit && currentOffering && currentOffering.schoolYear === reportYear && currentOffering.termCode === code) {
            hit = true;
          }
          if (hit) semEl.appendChild(new Option(semesterLabel(code), code));
        });
        if (!semEl.options.length) {
          semEl.appendChild(new Option('1st semester', '1'));
        }
        var keepSem = reportSem || semEl.value || (currentOffering && currentOffering.termCode) || '1';
        if (Array.prototype.some.call(semEl.options, function (o) { return o.value === keepSem; })) {
          semEl.value = keepSem;
        } else {
          semEl.selectedIndex = 0;
        }
        semEl.disabled = semEl.options.length <= 1;
        reportSem = semEl.value;
        syncTermFilterChrome();
        return;
      }
      var label = '';
      var code = '';
      if (offeringPane === 'archived' && archivedPick) {
        label = archivedPick.schoolYear;
        code = archivedPick.termCode;
      } else if (currentOffering) {
        label = currentOffering.schoolYear;
        code = currentOffering.termCode;
      }
      yearEl.innerHTML = '';
      if (label) {
        yearEl.appendChild(new Option(label, label));
        yearEl.value = label;
      } else {
        yearEl.appendChild(new Option('No current offering', ''));
        yearEl.value = '';
      }
      yearEl.disabled = true;
      if (code) semEl.value = code;
      semEl.disabled = true;
      syncTermFilterChrome();
    }

    function fillColleges() {
      if (!colEl || !global.OrgStore) return;
      var keep = colEl.value;
      var scoped = scopedColleges();
      var list = scoped.length ? scoped : global.OrgStore.colleges();
      colEl.innerHTML = '';
      if (!scoped.length) colEl.appendChild(new Option('All colleges', ''));
      list.forEach(function (c) {
        colEl.appendChild(new Option(c.name, c.id));
      });
      if (scoped.length === 1) colEl.value = scoped[0].id;
      else if (keep) colEl.value = keep;
      colEl.disabled = scoped.length === 1;
      fillDepartments();
    }

    function fillDepartments() {
      if (!depEl) return;
      var collegeId = colEl ? colEl.value : '';
      var scoped = scopedDepartments();
      var list = listedPrograms();
      if (depWrap) depWrap.hidden = !list.length;
      fillSelectKeep(
        depEl,
        [{ value: '', label: 'All programs' }].concat(
          list.map(function (p) {
            var label =
              collegeId || p.representsCollege ? p.departmentName || p.collegeName : p.label;
            return { value: programFilterValue(p), label: label };
          })
        )
      );
      if (scoped.length === 1) {
        depEl.value = scoped[0].id;
        depEl.disabled = true;
      } else {
        depEl.disabled = false;
      }
    }

    function selectedProgramFilter() {
      if (!depEl || (depWrap && depWrap.hidden)) return '';
      return depEl.value || '';
    }

    function generateCollegeId() {
      var collegeId = colEl ? colEl.value : '';
      var prog = selectedProgramFilter();
      if (prog.indexOf('college:') === 0) return collegeId || prog.slice(8);
      if (collegeId || !prog || !global.OrgStore || typeof global.OrgStore.programs !== 'function') {
        return collegeId;
      }
      var hit = null;
      global.OrgStore.programs().some(function (p) {
        if (p.departmentId === prog) {
          hit = p;
          return true;
        }
        return false;
      });
      return hit ? hit.collegeId : collegeId;
    }

    function generateDepartmentId() {
      var prog = selectedProgramFilter();
      if (!prog || prog.indexOf('college:') === 0) return '';
      return prog;
    }

    function setStatus(text, tone) {
      if (!statusEl) return;
      statusEl.textContent = text || '';
      statusEl.className = 'sched-status' + (tone ? ' sched-status--' + tone : '');
    }

    function setWorkspace(next, skipLoad) {
      var allowed = allowedWorkspaces();
      workspace = allowed.indexOf(next) >= 0 ? next : allowed[0];
      archivedPick = workspace === 'archives' ? archivedPick : null;
      if (usesWorkspace) {
        offeringPane = workspace === 'archives' ? 'archived' : 'active';
        if (workspace !== 'archives') archivedPick = null;
      }
      writeWorkspaceUrl();
      syncWorkspaceTabs();
      fillYears();
      showView(primaryView, layout);
      if (!skipLoad) loadSchedule();
    }

    function syncWorkspaceTabs() {
      document.querySelectorAll('[data-ws-tab]').forEach(function (btn) {
        var id = btn.getAttribute('data-ws-tab');
        btn.classList.toggle('active', id === workspace);
      });
      var titleEl = qs('schedPageTitle');
      var subEl = qs('schedPageSub');
      if (titleEl) titleEl.textContent = workspaceTitle();
      if (subEl) subEl.textContent = workspaceSubtitle();
    }

    function workspaceTitle() {
      if (pageKind === 'report') {
        if (actor === 'vpaa' && qs('schedColleges')) return workspace === 'awaiting' ? 'For your approval' : 'Published timetable';
        return workspace === 'awaiting' ? 'Awaiting the VPAA’s approval' : 'Final report';
      }
      return (
        {
          mine: 'Working timetable',
          submitted: actor === 'super_admin' ? 'For approval' : 'Sent for approval',
          saves: 'Saves',
          archives: 'Archives'
        }[workspace] || 'Create schedule'
      );
    }

    function workspaceSubtitle() {
      if (pageKind === 'report') {
        if (actor === 'vpaa' && qs('schedColleges')) {
          return workspace === 'awaiting'
            ? 'The colleges the University Registrar sent you. Check each section below, then approve or send back each college under Colleges. Approving publishes it to deans and program chairs.'
            : 'What deans and program chairs see: every college you approved. Filters only change what you see.';
        }
        if (actor === 'vpaa') {
          return workspace === 'awaiting'
            ? 'University Registrar sent this copy. Publish it so deans and program chairs can see it, or send it back with a note.'
            : 'This is the timetable after you approve it. Copies waiting for your decision live on the other tab. Filters only change what you see.';
        }
        return workspace === 'awaiting'
          ? 'This copy was approved by the University Registrar and is waiting for the VPAA. It cannot be edited here.'
          : 'This is the timetable after the VPAA approves it. Filters only change what you see.';
      }
      return (
        {
          mine:
            pageKind === 'create'
              ? (actor === 'super_admin'
                  ? 'Your working timetable. Generate or fix what you need, then use Submit to VPAA to send the finished colleges. Lunch is 12:00–1:00 pm.'
                  : 'Your working timetable. Generate or fix what you need, then use Submit to University Registrar to send the finished colleges. Lunch is 12:00–1:00 pm.')
              : 'This is your working timetable. Named copies go on Saves; share a save if the other registrar should see it. Lunch is 12:00pm–1:00pm.',
          submitted:
            actor === 'super_admin'
              ? 'What the Records Officer sent you, read-only. Approve a college to bring it into your Working timetable (you can still edit it there), or send it back with a note.'
              : 'What you sent to the University Registrar, read-only. Under Colleges you can see where each college is, and take one back if you need to change it.',
          saves: 'Named versions of this term. Opening one replaces Working timetable.',
          archives: 'Past academic years and semesters, plus named versions moved out of Saves. Archive a VPAA-published term from Reports → Final report.'
        }[workspace] || ''
      );
    }

    function applyPayload(res) {
      publication = res.publication || null;
      if (res.version) selectedVersion = res.version;
      classes = (res.classes || []).filter(matchesScope);
      rooms = res.rooms || [];
      roomLabelById = {};
      rooms.forEach(function (r) {
        if (!r || !r.id) return;
        roomLabelById[r.id] = r.label || r.code || '';
      });
      var off = res.offering || {};
      currentOffering = off.current || null;
      archivedOfferings = off.archived || [];
      offeringMeta = {
        canArchive: !!off.canArchive,
        canUnarchive: !!off.canUnarchive,
        viewingLive: off.viewingLive !== false
      };
      if (res.workspace) {
        workspaceState = res.workspace;
        if (res.workspace.id && allowedWorkspaces().indexOf(res.workspace.id) >= 0) {
          workspace = res.workspace.id;
        }
        if (res.workspace.reportTerms) reportTerms = res.workspace.reportTerms;
      }
      if (res.reportTerms) reportTerms = res.reportTerms;
      if (res.generatorSettings) {
        generatorSettings = normalizeGenSettings(res.generatorSettings);
        fillGenSettingsForm();
      }
      fillRoomTableFilters();
      fillClassCollegeFilter();
      fillClassBuildingFilter();
      syncPublicationUi();
      syncWorkspaceUi();
      syncOfferingChrome();
      try {
        document.dispatchEvent(
          new CustomEvent('mcu:schedule-loaded', {
            detail: {
              actor: actor,
              page: pageKind,
              workspace: workspace,
              offering: currentOffering,
              year: yearEl ? yearEl.value : '',
              sem: semEl ? semEl.value : '',
              archived: offeringPane === 'archived' || !!archivedPick
            }
          })
        );
      } catch (err) {}
    }

    function fillClassCollegeFilter() {
      if (!classCollegeEl) return;
      var seen = {};
      var scoped = scopedColleges();
      var opts = scoped.length ? [] : [{ value: '', label: 'All colleges' }];
      (scoped.length ? scoped : global.OrgStore ? global.OrgStore.colleges() : []).forEach(function (c) {
        seen[c.id] = true;
        opts.push({ value: c.id, label: c.name });
      });
      classes.forEach(function (c) {
        if (!c.collegeId || seen[c.collegeId]) return;
        seen[c.collegeId] = true;
        opts.push({ value: c.collegeId, label: c.collegeName || c.collegeId });
      });
      fillSelectKeep(classCollegeEl, opts);
      if (scoped.length === 1) classCollegeEl.value = scoped[0].id;
      classCollegeEl.disabled = scoped.length === 1;
    }

    function fillClassBuildingFilter() {
      if (!classBuildingEl) return;
      var seen = {};
      var opts = [{ value: '', label: 'All buildings' }];
      roomsByBuilding().forEach(function (b) {
        seen[b.id] = true;
        opts.push({ value: b.id, label: b.name });
      });
      classes.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (!m.buildingId || seen[m.buildingId]) return;
          seen[m.buildingId] = true;
          opts.push({ value: m.buildingId, label: m.buildingName || m.buildingId });
        });
      });
      opts.push({ value: 'off', label: 'Off-campus' });
      fillSelectKeep(classBuildingEl, opts);
    }


    function fillSelectKeep(sel, options, selected) {
      if (!sel) return;
      var keep = selected != null ? String(selected) : sel.value;
      sel.innerHTML = '';
      options.forEach(function (o) {
        sel.appendChild(new Option(o.label, o.value));
      });
      if (keep && Array.prototype.some.call(sel.options, function (o) { return o.value === keep; })) {
        sel.value = keep;
      } else if (sel.options.length) {
        sel.selectedIndex = 0;
      }
    }

    function fillRoomTableFilters() {
      var buildings = roomsByBuilding();
      fillSelectKeep(
        roomBuildingEl,
        [{ value: '', label: 'All buildings' }].concat(
          buildings.map(function (b) {
            return { value: b.id, label: b.name };
          })
        )
      );
      var types = {};
      rooms.forEach(function (r) {
        if (r.typeName) types[r.typeName] = true;
      });
      fillSelectKeep(
        roomTypeEl,
        [{ value: '', label: 'All room types' }].concat(
          Object.keys(types)
            .sort()
            .map(function (t) {
              return { value: t, label: t };
            })
        )
      );
      fillRoomFloorFilter();
    }

    function fillRoomFloorFilter() {
      var buildingId = roomBuildingEl ? roomBuildingEl.value : '';
      var floors = {};
      var hasNone = false;
      rooms.forEach(function (r) {
        if (buildingId && r.buildingId !== buildingId) return;
        if (r.floorNumber == null) hasNone = true;
        else floors[r.floorNumber] = true;
      });
      var opts = [{ value: '', label: 'All floors' }].concat(
        Object.keys(floors)
          .map(function (n) {
            return parseInt(n, 10);
          })
          .sort(function (a, b) {
            return a - b;
          })
          .map(function (n) {
            return { value: String(n), label: 'Floor ' + n };
          })
      );
      if (hasNone) opts.push({ value: 'none', label: 'No floor' });
      fillSelectKeep(roomFloorEl, opts);
    }

    function filteredTableRooms() {
      var buildingId = roomBuildingEl ? roomBuildingEl.value : '';
      var floorVal = roomFloorEl ? roomFloorEl.value : '';
      var typeVal = roomTypeEl ? roomTypeEl.value : '';
      return rooms
        .filter(function (r) {
          if (buildingId && r.buildingId !== buildingId) return false;
          if (typeVal && r.typeName !== typeVal) return false;
          if (floorVal === 'none') return r.floorNumber == null;
          if (floorVal !== '' && String(r.floorNumber) !== floorVal) return false;
          return true;
        })
        .slice()
        .sort(function (a, b) {
          return (
            String(a.buildingName || '').localeCompare(String(b.buildingName || '')) ||
            (a.floorNumber || 0) - (b.floorNumber || 0) ||
            String(a.code || '').localeCompare(String(b.code || ''))
          );
        });
    }

    function loadSchedule() {
      fillYears();
      syncWorkspaceTabs();
      if (!global.McuApi) {
        syncOfferingChrome();
        syncWorkspaceUi();
        return Promise.resolve();
      }
      var q = 'schedule.php?actor=' + encodeURIComponent(actor || 'admin');
      if (usesWorkspace) {
        q += '&page=' + encodeURIComponent(pageKind);
        q += '&workspace=' + encodeURIComponent(workspace);
      }
      if ((offeringPane === 'archived' && archivedPick) || (usesWorkspace && workspace === 'archives' && archivedPick)) {
        q +=
          '&year=' +
          encodeURIComponent(archivedPick.schoolYear) +
          '&sem=' +
          encodeURIComponent(archivedPick.termCode);
      } else if (usesWorkspace && pageKind === 'report' && yearEl.value) {
        q += '&year=' + encodeURIComponent(yearEl.value) + '&sem=' + encodeURIComponent(semEl.value);
      } else {
        q += '&live=1';
      }
      if (selectedVersion && !usesWorkspace) q += '&version=' + encodeURIComponent(selectedVersion);
      return global.McuApi.get(q)
        .then(function (res) {
          applyPayload(res);
          fillYears();
          if ((offeringPane === 'archived' && !archivedPick) || (usesWorkspace && workspace === 'archives' && !archivedPick) || (usesWorkspace && workspace === 'saves')) {
            setStatus('', '');
            render();
            return;
          }
          var s = res.summary || {};
          if (!classes.length) {
            // the empty view already says it
            setStatus(pageKind === 'create' ? '' : emptyScheduleText(false), 'muted');
          } else if (masterDetail) {
            setStatus('', ''); // the line above the buttons shows the count
            if (pageKind === 'create' && workspace === 'mine') {
              var plan = takeResolvePlan();
              if (plan) setTimeout(function () { resolveApprovalClashes(plan); }, 0);
            }
          } else {
            var ver = usesWorkspace ? ' · ' + workspaceTitle() : selectedVersion ? ' · ' + versionLabel(selectedVersion) : '';
            setStatus(
              s.scheduled +
                ' of ' +
                s.offerings +
                ' classes placed for ' +
                yearEl.value +
                ' ' +
                semesterLabel(semEl.value) +
                ver +
                (s.unscheduled ? ' · ' + s.unscheduled + ' still unscheduled' : ''),
              s.unscheduled ? 'warn' : 'ok'
            );
          }
          render();
        })
        .catch(function (err) {
          setStatus(err.message || String(err), 'bad');
        });
    }

    function canReviewPackage() {
      var pub = publication || {};
      if (pub.canApprove || pub.canReject) return true;
      if (actor === 'super_admin' && pub.status === 'pending_super_admin') return true;
      if (actor === 'vpaa' && pub.status === 'pending_vpaa') return true;
      return false;
    }

    function canSendLivePackage() {
      var pub = publication || {};
      if (actor !== 'super_admin' || !pub.canSendLive) return false;
      if (offeringPane === 'archived') return false;
      if (pub.status === 'pending_super_admin' && !pub.workingDiffersFromSubmitted) return false;
      return true;
    }

    function syncPublicationUi() {
      var bar = qs('schedVersionBar');
      var banner = qs('schedReviewBanner');
      var readyBtn = qs('schedReady');
      var approveBtn = qs('schedApprove');
      var rejectBtn = qs('schedReject');
      var sendLiveBtn = qs('schedSendLive');
      var pub = publication || { versions: [], canReady: false, canApprove: false, canReject: false };
      if (usesWorkspace) {
        if (bar) bar.hidden = true;
        if (banner) banner.hidden = true;
        if (readyBtn) readyBtn.hidden = true;
        if (approveBtn) approveBtn.hidden = true;
        if (rejectBtn) rejectBtn.hidden = true;
        if (sendLiveBtn) sendLiveBtn.hidden = true;
        return;
      }
      if (bar) {
        bar.innerHTML = '';
        var versions = pub.versions || [];
        bar.hidden = versions.length < 2;
        versions.forEach(function (code) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'sched-version' + (code === selectedVersion ? ' is-active' : '');
          btn.setAttribute('data-sched-version', code);
          btn.textContent = versionLabel(code);
          btn.addEventListener('click', function () {
            selectedVersion = code;
            loadSchedule();
          });
          bar.appendChild(btn);
        });
      }
      if (banner) {
        var bits = [];
        var vers = pub.versions || [];
        if (actor === 'vpaa') {
          if (pub.lastReject && pub.lastReject.from === 'vpaa' && vers.indexOf('pendingVpaa') < 0) {
            bits.push('You sent this term back to the University Registrar.');
          }
          if (vers.indexOf('pendingVpaa') < 0 && vers.indexOf('published') < 0) {
            bits.push(
              'Database is connected. There is no package for you to review until University Registrar sends a campus timetable.'
            );
          } else if (pub.status === 'pending_vpaa') {
            bits.push('This package was sent by the University Registrar. Approve it to publish, or send it back to the University Registrar.');
          }
        } else if (actor === 'dean' || actor === 'program_chair') {
          if (!vers.length) {
            bits.push(
              'Database is connected. This role only sees a timetable after the VPAA publishes it.'
            );
          }
        } else if (actor !== 'dean' && actor !== 'program_chair') {
          if (pub.lastReject && pub.lastReject.note && actor === 'super_admin') {
            var who = pub.lastReject.from === 'vpaa' ? 'The VPAA' : 'University Registrar';
            bits.push(
              '<strong>' +
                who +
                ' sent this term back.</strong> ' +
                escapeHtml(pub.lastReject.note)
            );
          } else if (pub.lastReject && pub.lastReject.note && actor === 'admin' && !pub.returnedFromVpaa) {
            bits.push(
              '<strong>' +
                (pub.lastReject.from === 'vpaa' ? 'The VPAA' : 'University Registrar') +
                ' sent this term back.</strong> ' +
                escapeHtml(pub.lastReject.note)
            );
          }
          if (pub.workingDiffersFromSubmitted && actor === 'admin') {
            bits.push('Create schedule has changed since this package was sent to the University Registrar. Submit again to replace it.');
          } else if (pub.workingDiffersFromSubmitted && actor === 'super_admin') {
            bits.push(
              pub.returnedFromVpaa
                ? 'The live timetable has changed since this package was sent to the VPAA. Use the version tabs to compare, then send the returned package, send the live copy, or send it back to the Records Officer' +
                  (readOnly ? '.' : ' from Final report.')
                : 'The live timetable has changed since Records Officer submitted. Use the version tabs to compare, then send Records Officer’s snapshot or the live copy' +
                  (readOnly ? '.' : ' from Final report.')
            );
          }
          if (pub.status === 'pending_super_admin') {
            if (actor === 'super_admin') {
              if (pub.returnedFromVpaa) {
                if (!pub.workingDiffersFromSubmitted) {
                  bits.push(
                    'Send this package back to the Records Officer for changes, or send a timetable to the VPAA again.'
                  );
                }
              } else if (!pub.workingDiffersFromSubmitted) {
                bits.push(
                  'Records Officer submitted this campus timetable. Approve it to send it to the VPAA, or send it back to the Records Officer with a note.'
                );
              }
            } else {
              bits.push('This campus timetable is waiting for the University Registrar.');
            }
          } else if (pub.status === 'pending_vpaa') {
            bits.push('University Registrar sent this package. It is waiting for the VPAA.');
          } else if (pub.status === 'published' && actor !== 'vpaa') {
            bits.push('The VPAA has published this term. Deans and program chairs can see that approved copy.');
          }
          if (actor === 'super_admin' && canSendLivePackage() && pub.status !== 'pending_super_admin') {
            bits.push(
              readOnly
                ? 'You can send the live timetable to the VPAA without waiting for the Records Officer to submit.'
                : 'You can send the live timetable to the VPAA from Final report without waiting for the Records Officer to submit.'
            );
          }
        }
        banner.innerHTML = bits.join('<br />');
        banner.hidden = !bits.length;
      }
      if (readyBtn) {
        readyBtn.hidden = !(actor === 'admin' && pub.canReady);
        readyBtn.disabled = readyBtn.hidden;
      }
      if (approveBtn) {
        approveBtn.hidden = !canReviewPackage();
        approveBtn.disabled = approveBtn.hidden;
        if (actor === 'super_admin' && !approveBtn.hidden) {
          if (pub.returnedFromVpaa) {
            approveBtn.textContent = pub.workingDiffersFromSubmitted
              ? 'Send returned package to VPAA'
              : 'Send to VPAA again';
          } else {
            approveBtn.textContent = pub.workingDiffersFromSubmitted
              ? 'Send Records Officer’s snapshot to VPAA'
              : 'Approve and send to VPAA';
          }
        }
      }
      var sendLiveBtn = qs('schedSendLive');
      if (sendLiveBtn) {
        sendLiveBtn.hidden = !canSendLivePackage();
        sendLiveBtn.disabled = sendLiveBtn.hidden;
      }
      if (rejectBtn) {
        rejectBtn.hidden = !canReviewPackage();
        rejectBtn.disabled = rejectBtn.hidden;
        if (actor === 'super_admin' && !rejectBtn.hidden) {
          rejectBtn.textContent = 'Send back to the Records Officer';
        }
      }
    }

    function formatStamp(value) {
      if (!value) return '';
      var d = new Date(String(value).replace(' ', 'T'));
      if (isNaN(d.getTime())) return String(value);
      return d.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
      });
    }

    function addActionButton(parent, label, className, onClick) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = className || 'sched-btn';
      btn.textContent = label;
      btn.addEventListener('click', onClick);
      parent.appendChild(btn);
      return btn;
    }

    function summaryLine(summary) {
      var s = summary || {};
      if (!s.offerings) return 'Empty timetable';
      return (s.scheduled || 0) + ' of ' + s.offerings + ' classes placed';
    }

    function renderSaveCards(list, kind, opts) {
      opts = opts || {};
      var wrap = document.createElement('div');
      wrap.className = 'sched-saves-grid';
      if (!list || !list.length) {
        var empty = document.createElement('p');
        empty.className = 'sched-empty-panel';
        empty.textContent =
          kind === 'archived_save'
            ? 'No named versions have been moved to Archives for this term.'
            : opts.shared
              ? 'No saves have been shared with you yet.'
              : 'No named saves yet. Use Save as on Working timetable.';
        wrap.appendChild(empty);
        return wrap;
      }
      list.forEach(function (item) {
        var card = document.createElement('div');
        card.className = 'sched-save-card';
        var text = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'sched-save-card__title';
        title.textContent = item.title || 'Untitled save';
        var meta = document.createElement('div');
        meta.className = 'sched-save-card__meta';
        var bits = [summaryLine(item.summary)];
        if (item.updatedAt) bits.push(formatStamp(item.updatedAt));
        if (opts.shared) bits.push('From ' + (item.ownerLabel || 'the other registrar'));
        else if (item.isShared) bits.push('Shared with ' + (actor === 'super_admin' ? 'Records Officer' : 'University Registrar'));
        meta.textContent = bits.join(' · ');
        text.appendChild(title);
        text.appendChild(meta);
        var actions = document.createElement('div');
        actions.className = 'sched-save-card__actions';
        addActionButton(actions, 'Open…', 'sched-btn', function () {
          openSavePicker(item.id, item.title);
        });
        if (kind !== 'archived_save' && item.canManage) {
          addActionButton(
            actions,
            item.isShared ? 'Stop sharing' : 'Share with ' + (actor === 'super_admin' ? 'Records Officer' : 'University Registrar'),
            'sched-btn sched-btn--ghost',
            function () {
              var sharing = !item.isShared;
              askConfirm({
                title: sharing ? 'Share this save?' : 'Stop sharing?',
                message: sharing
                  ? 'The other registrar will see “' + (item.title || 'Untitled save') + '” on Saves and can open it.'
                  : '“' + (item.title || 'Untitled save') + '” will leave the other registrar’s Saves list.',
                yes: sharing ? 'Share' : 'Stop sharing',
                no: 'Cancel'
              }).then(function (ok) {
                if (ok) runPublication('share_save', { saveId: item.id, share: sharing });
              });
            }
          );
          addActionButton(actions, 'Move to Archives', 'sched-btn sched-btn--archive', function () {
            askConfirm({
              title: 'Move this save to Archives?',
              message: '“' + (item.title || 'Untitled save') + '” will leave Saves and appear on Archives.',
              yes: 'Move to Archives',
              no: 'Cancel',
              yesClass: 'sched-btn--archive'
            }).then(function (ok) {
              if (ok) runPublication('archive_save', { saveId: item.id });
            });
          });
        }
        if (item.canManage !== false) {
          addActionButton(actions, 'Delete', 'sched-btn sched-btn--danger', function () {
            askConfirm({
              title: 'Delete this save?',
              message: 'Delete “' + (item.title || 'Untitled save') + '”? This cannot be undone.',
              yes: 'Delete',
              no: 'Cancel',
              yesClass: 'sched-btn--danger'
            }).then(function (ok) {
              if (ok) runPublication('delete_save', { saveId: item.id });
            });
          });
        }
        card.appendChild(text);
        card.appendChild(actions);
        wrap.appendChild(card);
      });
      return wrap;
    }

    function syncWorkspaceUi() {
      syncWorkspaceTabs();
      writeWorkspaceUrl();
      if (workspaceHint) {
        var st = ws();
        var banner = st.tabStatus || {};
        // on Create schedule the Colleges panel shows where each college is
        var panelShowsIt =
          (pageKind === 'create' && (workspace === 'mine' || banner.code === 'empty')) ||
          (actor === 'vpaa' && !!qs('schedColleges')) ||
          (pageKind === 'report' && workspace === 'final' && !!qs('frColleges')); // Final report: final-report.js, per college
        if (banner.label && !panelShowsIt) {
          workspaceHint.className = 'sched-status-banner' + (banner.tone ? ' is-' + banner.tone : '');
          var hintLabel = banner.label;
          if (actor === 'super_admin' && banner.code === 'waiting_super_admin') hintLabel = 'Waiting for your review';
          workspaceHint.innerHTML = '<strong>' + escapeHtml(hintLabel) + '</strong>' +
            (banner.detail ? ' — ' + escapeHtml(banner.detail) : '');
        } else {
          workspaceHint.className = 'sched-workspace-hint';
          workspaceHint.textContent = '';
        }
      }
      if (savesPanel) {
        var showSaves = usesWorkspace && (workspace === 'saves' || (workspace === 'archives' && !archivedPick));
        savesPanel.hidden = !showSaves;
        savesPanel.innerHTML = '';
        if (showSaves && workspace === 'saves') {
          var mineHead = document.createElement('h3');
          mineHead.className = 'sched-save-card__title';
          mineHead.textContent = 'My saves';
          savesPanel.appendChild(mineHead);
          savesPanel.appendChild(renderSaveCards(ws().saves || [], 'save'));
          var shareHead = document.createElement('h3');
          shareHead.className = 'sched-save-card__title';
          shareHead.textContent = 'Shared with you';
          savesPanel.appendChild(shareHead);
          savesPanel.appendChild(renderSaveCards(ws().sharedSaves || [], 'save', { shared: true }));
        } else if (showSaves && workspace === 'archives') {
          var archivedHeading = document.createElement('h3');
          archivedHeading.className = 'sched-save-card__title';
          archivedHeading.textContent = 'Filed drafts (not past official reports)';
          savesPanel.appendChild(archivedHeading);
          savesPanel.appendChild(renderSaveCards(ws().archivedSaves || [], 'archived_save'));
        }
      }
      if (workspaceActions) {
        workspaceActions.innerHTML = '';
        if (usesWorkspace && pageKind === 'create' && workspace === 'mine') {
          // sending for approval is per college now, in the Colleges panel (schedule-colleges.js)
          addActionButton(workspaceActions, 'Save as…', 'sched-btn sched-btn--ghost', openSaveAsModal);
          addActionButton(workspaceActions, 'Clear schedule', 'sched-btn sched-btn--ghost sched-btn--danger', clearActiveSchedule).classList.add('sched-actions-end');
        }
        if (usesWorkspace && pageKind === 'report' && workspace === 'final' && ws().canArchivePublished) {
          addActionButton(workspaceActions, 'Archive this offering', 'sched-btn sched-btn--archive', archivePublishedOffering);
        }
        if (usesWorkspace && pageKind !== 'create' && workspace === 'submitted' && actor === 'admin' && ws().canRetractSubmitted) {
          addActionButton(workspaceActions, 'Retract submission', 'sched-btn sched-btn--ghost', function () {
            askConfirm({
              title: 'Retract this submission?',
              message: 'University Registrar will no longer see this pending copy. Working timetable stays as it is.',
              yes: 'Retract',
              no: 'Cancel'
            }).then(function (ok) {
              if (ok) runPublication('retract_admin_submission');
            });
          });
        }
        if (usesWorkspace && pageKind !== 'create' && workspace === 'submitted' && actor === 'super_admin' && ws().canApproveAdminSubmit) {
          addActionButton(workspaceActions, 'Approve and send to VPAA', 'sched-btn', function () {
            var incomplete = vpaaIncompleteMessage();
            if (incomplete) {
              setStatus(incomplete, 'bad');
              return;
            }
            if (ws().hasVpaaPending) {
              setStatus(
                'A timetable is already waiting for the VPAA. Retract that copy on Reports first if you want to send a different one.',
                'bad'
              );
              return;
            }
            askConfirm({
              title: 'Send Records Officer’s submission to the VPAA?',
              message: 'This copy will appear on Reports → Awaiting the VPAA’s approval. Records Officer will still see this snapshot here until the VPAA decides.',
              yes: 'Send to VPAA',
              no: 'Cancel'
            }).then(function (ok) {
              if (ok) runPublication('approve_admin_submission');
            });
          });
          addActionButton(workspaceActions, 'Send back to the Records Officer', 'sched-btn sched-btn--danger', openRejectModal);
        }
        if (usesWorkspace && workspace === 'submitted' && actor === 'super_admin' && ws().canOpenReturned) {
          addActionButton(workspaceActions, 'Open in Working timetable', 'sched-btn', function () {
            askConfirm({
              title: 'Replace Working timetable?',
              message: 'This loads the VPAA-returned copy into Working timetable and overwrites whatever is there now.',
              yes: 'Open in Working timetable',
              no: 'Cancel'
            }).then(function (ok) {
              if (ok) runPublication('open_returned', { target: 'mine' });
            });
          });
        }
        if (usesWorkspace && workspace === 'submitted' && actor === 'super_admin' && ws().canSendReturnedToAdmin) {
          addActionButton(workspaceActions, 'Send back to the Records Officer', 'sched-btn sched-btn--danger', openRejectModal);
        }
        if (usesWorkspace && workspace === 'awaiting' && actor === 'vpaa' && ws().canDecideVpaa && !qs('schedColleges')) {
          addActionButton(workspaceActions, 'Publish timetable', 'sched-btn', function () {
            publishVpaaPackage();
          });
          addActionButton(workspaceActions, 'Send back', 'sched-btn sched-btn--danger', openRejectModal);
        }
        if (usesWorkspace && workspace === 'awaiting' && actor === 'super_admin' && ws().canRetractAwaiting) {
          addActionButton(workspaceActions, 'Retract from the VPAA', 'sched-btn sched-btn--ghost', function () {
            askConfirm({
              title: 'Retract this copy?',
              message: 'The VPAA will no longer see this pending timetable. You can submit a different copy from Create schedule.',
              yes: 'Retract',
              no: 'Cancel'
            }).then(function (ok) {
              if (ok) runPublication('retract_vpaa_pending');
            });
          });
        }
        if (!workspaceActions.classList.contains('sched-actions-slot')) {
          workspaceActions.hidden = !workspaceActions.childNodes.length;
        }
      }
      syncSectionFilters();
      syncTermFilterChrome();
    }

    function archivePublishedOffering() {
      if (!ws().canArchivePublished) {
        setStatus(
          'Archive only a VPAA-published timetable on Final report, and only while that term is still the live offering.',
          'bad'
        );
        return;
      }
      askConfirm({
        title: 'Archive this published term?',
        message:
          'Archive ' +
          offeringTitle(yearEl.value, semEl.value) +
          '? This closes the live offering. The VPAA-published timetable stays on Final report. Sections for this academic year and semester are filed with it. Create schedule will be empty until you start a new offering on the Sections page.',
        yes: 'Archive',
        no: 'Cancel',
        yesClass: 'sched-btn--archive'
      }).then(function (ok) {
        if (!ok) return;
        runPublication('archive_published_offering');
      });
    }

    function publishVpaaPackage() {
      if (!ws().canDecideVpaa) {
        setStatus('There is no timetable waiting for your approval.', 'bad');
        return;
      }
      askConfirm({
        title: 'Publish this timetable?',
        message: 'Deans and program chairs will see this copy. University Registrar and Records Officer will see it on Final report.',
        yes: 'Publish timetable',
        no: 'Cancel'
      }).then(function (ok) {
        if (!ok) return;
        runPublication('vpaa_decide', { accept: true });
      });
    }

    function submitCurrentToSuperAdmin() {
      if (ws().hasAdminSubmitted) {
        setStatus(
          ws().adminSubmittedForwarded || ws().hasVpaaPending
            ? 'A copy of this term is still with the University Registrar or the VPAA. Wait for that decision, or ask the University Registrar to send it back, before submitting another.'
            : 'A timetable is already waiting for the University Registrar. Retract that submission first if you want to send a different copy.',
          'bad'
        );
        return;
      }
      askConfirm({
        title: 'Submit to the University Registrar?',
        message:
          'A copy of Working timetable will appear on Sent for approval. This tab stays as it is.',
        yes: 'Submit',
        no: 'Cancel'
      }).then(function (ok) {
        if (!ok) return;
        runPublication('submit_to_super_admin', { from: 'mine' });
      });
    }

    function vpaaIncompleteMessage() {
      var unscheduled = 0;
      (classes || []).forEach(function (c) {
        if (!(c && classIsScheduled(c))) unscheduled++;
      });
      if (!classes.length) {
        return 'There are no classes to send. Generate a timetable and place every class before submitting it to the VPAA.';
      }
      if (!unscheduled) return '';
      if (unscheduled === 1) {
        return '1 class is still unscheduled. Place every class before submitting this timetable to the VPAA.';
      }
      return unscheduled + ' classes are still unscheduled. Place every class before submitting this timetable to the VPAA.';
    }

    function submitCurrentToVpaa() {
      var incomplete = vpaaIncompleteMessage();
      if (incomplete) {
        setStatus(incomplete, 'bad');
        return;
      }
      if (ws().hasVpaaPending) {
        setStatus(
          'A timetable is already waiting for the VPAA. Retract that copy on Reports first if you want to send a different one.',
          'bad'
        );
        return;
      }
      askConfirm({
        title: 'Submit to the VPAA?',
        message:
          'A copy of Working timetable will appear on Reports → Awaiting the VPAA’s approval. This tab stays as it is.',
        yes: 'Submit to the VPAA',
        no: 'Cancel'
      }).then(function (ok) {
        if (!ok) return;
        runPublication('submit_to_vpaa', { from: 'mine' });
      });
    }

    function openSaveAsModal() {
      var modalEl = qs('schedSaveAsModal');
      var nameEl = qs('schedSaveAsName');
      var errEl = qs('schedSaveAsError');
      if (!modalEl) return;
      if (errEl) errEl.textContent = '';
      if (nameEl) nameEl.value = '';
      modalEl.hidden = false;
      if (nameEl) nameEl.focus();
    }

    function closeSaveAsModal() {
      var modalEl = qs('schedSaveAsModal');
      if (modalEl) modalEl.hidden = true;
    }

    function confirmSaveAs() {
      var nameEl = qs('schedSaveAsName');
      var errEl = qs('schedSaveAsError');
      var title = nameEl ? String(nameEl.value || '').trim() : '';
      if (!title) {
        if (errEl) errEl.textContent = 'Give this save a name.';
        return;
      }
      closeSaveAsModal();
      runPublication('save_as', { title: title, from: 'mine' });
    }

    function clearActiveSchedule() {
      askConfirm({
        title: 'Clear Working timetable?',
        message:
          'This removes your working timetable from Working timetable. Sections, named saves, submissions, and Reports copies stay as they are.',
        yes: 'Clear schedule',
        no: 'Cancel',
        yesClass: 'sched-btn--danger'
      }).then(function (ok) {
        if (!ok) return;
        runPublication('clear_schedule', { from: 'mine' });
      });
    }

    function openSavePicker(saveId, title) {
      pendingSaveId = saveId;
      askConfirm({
        title: 'Replace Working timetable?',
        message:
          'Opening “' +
          (title || 'this save') +
          '” replaces the timetable currently in Working timetable. Save as first if you still need it.',
        yes: 'Open in Working timetable',
        no: 'Cancel'
      }).then(function (ok) {
        if (!ok) {
          pendingSaveId = '';
          return;
        }
        runPublication('open_save', { saveId: saveId, target: 'mine' });
        pendingSaveId = '';
      });
    }

    function closeOpenSaveModal() {
      var modalEl = qs('schedOpenSaveModal');
      if (modalEl) modalEl.hidden = true;
      pendingSaveId = '';
    }

    function openSaveInto() {
      var saveId = pendingSaveId;
      if (!saveId) return;
      closeOpenSaveModal();
      runPublication('open_save', { saveId: saveId, target: 'mine' });
    }

    function publicationBody(extra) {
      var body = {
        actor: actor,
        schoolYear: yearEl.value,
        termCode: semEl.value,
        year: yearEl.value,
        sem: semEl.value,
        workspace: workspace,
        page: pageKind,
        from: 'mine'
      };
      extra = extra || {};
      Object.keys(extra).forEach(function (k) {
        body[k] = extra[k];
      });
      return body;
    }

    function runPublication(action, extra) {
      if (!global.McuApi) return;
      setStatus('Saving…', 'muted');
      var body = publicationBody(extra);
      body.action = action;
      global.McuApi.send('POST', 'schedule.php', body)
        .then(function (res) {
          selectedVersion = res.version || '';
          applyPayload(res);
          var s = res.summary || {};
          if (usesWorkspace && (workspace === 'saves' || (workspace === 'archives' && !archivedPick))) {
            setStatus('', '');
          } else if (!classes.length) {
            setStatus(emptyScheduleText(false), 'muted');
          } else if (masterDetail) {
            setStatus('Saved.', 'ok');
          } else {
            setStatus(
              s.scheduled +
                ' of ' +
                s.offerings +
                ' classes · ' +
                (usesWorkspace ? workspaceTitle() : versionLabel(selectedVersion || 'working')),
              'ok'
            );
          }
          render();
        })
        .catch(function (err) {
          var msg = err && err.message ? String(err.message) : String(err);
          setStatus(msg, 'bad');
        });
    }

    function openRejectModal() {
      var modalEl = qs('schedRejectModal');
      var noteEl = qs('schedRejectNote');
      var hintEl = modalEl ? modalEl.querySelector('.sched-edit-hours') : null;
      var pub = publication || {};
      if (!modalEl) return;
      if (hintEl) {
        hintEl.textContent =
          actor === 'vpaa'
            ? 'University Registrar will see this note on Final report. If you already published an earlier copy, deans and program chairs keep that copy.'
            : 'The Records Officer will see this note on Final report. If the VPAA already published an earlier copy, deans and program chairs keep that copy.';
      }
      if (noteEl) {
        noteEl.value =
          actor === 'super_admin' && pub.returnedFromVpaa && pub.lastReject && pub.lastReject.note
            ? pub.lastReject.note
            : '';
      }
      modalEl.hidden = false;
      if (noteEl) noteEl.focus();
    }

    function closeRejectModal() {
      var modalEl = qs('schedRejectModal');
      if (modalEl) modalEl.hidden = true;
    }

    function membersOf(cls) {
      if (!cls) return [];
      if (!cls.mergedClassId) return [cls];
      return classes.filter(function (c) {
        return c.mergedClassId === cls.mergedClassId;
      });
    }

    function render() {
      syncGenerateChrome();
      syncFiltersButton();
      renderRoomCrumb();
      if (primaryView === 'sections') renderSections();
      else if (primaryView === 'rooms') renderRooms();
      else renderClasses();
    }

    function classGroups() {
      var seen = {};
      var out = [];
      classes.forEach(function (c) {
        var key = c.mergedClassId || c.id;
        if (seen[key]) {
          seen[key].members.push(c);
          return;
        }
        seen[key] = {
          id: c.id,
          courseCode: c.courseCode,
          courseTitle: c.courseTitle,
          component: c.component,
          hoursPerWeek: c.hoursPerWeek,
          roomTypeName: c.roomTypeName || '',
          requiresCampusRoom: c.requiresCampusRoom !== false,
          meetings: c.meetings || [],
          status: c.status,
          notes: c.notes,
          merged: !!c.mergedClassId,
          members: [c]
        };
        out.push(seen[key]);
      });
      out.sort(function (a, b) {
        return (
          String(a.courseCode).localeCompare(String(b.courseCode)) ||
          String(a.component).localeCompare(String(b.component))
        );
      });
      return out;
    }

    function filteredClassGroups() {
      var q = masterDetail ? findQuery() : classSearchEl ? String(classSearchEl.value || '').trim().toLowerCase() : '';
      var college = classCollegeEl ? classCollegeEl.value : '';
      var comp = classComponentEl ? classComponentEl.value : '';
      var status = classStatusEl ? classStatusEl.value : '';
      var yearLevel = classYearLevelEl ? classYearLevelEl.value : '';
      var building = classBuildingEl ? classBuildingEl.value : '';
      var merged = classMergedEl ? classMergedEl.value : '';
      if (masterDetail) clashIndex = null;
      return classGroups().filter(function (g) {
        if (masterDetail && attentionOnly && classIsScheduled(g) && !clashesFor(g.members).length) return false;
        if (q) {
          var hay = (g.courseCode + ' ' + g.courseTitle).toLowerCase();
          if (hay.indexOf(q) < 0) return false;
        }
        if (comp && g.component !== comp) return false;
        if (status === 'scheduled' && !classIsScheduled(g)) return false;
        if (status === 'unscheduled' && classIsScheduled(g)) return false;
        if (merged === 'merged' && !g.merged) return false;
        if (merged === 'single' && g.merged) return false;
        if (college) {
          var collegeHit = g.members.some(function (m) {
            return m.collegeId === college;
          });
          if (!collegeHit) return false;
        }
        if (yearLevel) {
          var yearHit = g.members.some(function (m) {
            return String(m.yearLevel) === yearLevel;
          });
          if (!yearHit) return false;
        }
        if (building === 'off') {
          var onCampus = (g.meetings || []).some(function (m) {
            return m.roomId || m.buildingId;
          });
          if (onCampus) return false;
        } else if (building) {
          var buildingHit = (g.meetings || []).some(function (m) {
            return m.buildingId === building;
          });
          if (!buildingHit) return false;
        }
        return true;
      });
    }

    function roomById(id) {
      if (!id) return null;
      for (var i = 0; i < rooms.length; i++) {
        if (rooms[i].id === id) return rooms[i];
      }
      return null;
    }

    // "Computer Lab" and "Computer Laboratory" are the same room type
    function roomTypeKey(s) {
      return String(s || '').replace(/\s+/g, ' ').trim().toLowerCase().replace(/\blaboratory\b/g, 'lab');
    }

    function sameRoomType(a, b) {
      return roomTypeKey(a) === roomTypeKey(b);
    }

    function subjectNeedsCampusRoom(cls) {
      if (!cls) return true;
      if (cls.requiresCampusRoom === false) return false;
      var name = String(cls.roomTypeName || '')
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
      if (name === 'no room' || name === 'noroom' || name === 'off campus') return false;
      if (global.RoomTypes && cls.roomTypeName) {
        var t = global.RoomTypes.findByName(cls.roomTypeName);
        if (t && t.requiresCampusRoom === false) return false;
      }
      return true;
    }

    function assignedRoomTypeMismatch(cls, roomId) {
      if (!subjectNeedsCampusRoom(cls) || !roomId) return false;
      var room = roomById(roomId);
      if (!room || !room.typeName || !cls.roomTypeName) return false;
      return !sameRoomType(room.typeName, cls.roomTypeName);
    }

    function classTypeMismatch(cls) {
      if (!subjectNeedsCampusRoom(cls)) return false;
      return (cls.meetings || []).some(function (m) {
        return assignedRoomTypeMismatch(cls, m.roomId);
      });
    }

    function typeCellHtml(cls, roomId) {
      var label = cls.roomTypeName || '—';
      var mismatch = arguments.length > 1
        ? assignedRoomTypeMismatch(cls, roomId)
        : classTypeMismatch(cls);
      var extra = mismatch
        ? ' <span class="sched-badge sched-badge--warn">Mismatch</span>'
        : '';
      return (
        '<span class="sched-type-label" title="' +
        escapeHtml(label) +
        '">' +
        escapeHtml(label) +
        '</span>' +
        extra
      );
    }

    function roomOpenToCollege(room, collegeId) {
      var list = (room && room.colleges) || [];
      return !list.length || !collegeId || list.indexOf(collegeId) >= 0;
    }

    function collegeNameById(id) {
      var c = global.OrgStore && global.OrgStore.colleges
        ? global.OrgStore.colleges().filter(function (x) { return x.id === id; })[0]
        : null;
      return c ? c.name : id;
    }

    // Facilities can set a room, floor, or building aside for some colleges
    function roomCollegeWarning(cls, roomId) {
      if (!cls || !roomId) return '';
      var room = roomById(roomId);
      if (!room || roomOpenToCollege(room, cls.collegeId)) return '';
      return (
        (room.label || room.code) +
        ' is set aside for ' +
        room.colleges.map(collegeNameById).join(', ') +
        ' in Facilities. ' +
        (cls.collegeName || 'This class') +
        ' would be using it.'
      );
    }

    function roomCapacityWarning(cls, roomId) {
      if (!cls || !roomId) return '';
      var room = roomById(roomId);
      if (!room || !(Number(room.capacity) > 0)) return '';
      var count = classStudentCount(cls);
      if (!(count > Number(room.capacity))) return '';
      return (
        (room.label || room.code) +
        ' holds ' +
        room.capacity +
        ' students. This class has ' +
        count +
        '.'
      );
    }

    function roomTypeWarning(cls, roomId) {
      if (!cls) return '';
      var need = cls.roomTypeName || 'a campus';
      if (!subjectNeedsCampusRoom(cls)) {
        if (!roomId) return '';
        var used = roomById(roomId);
        return (
          'This subject is ' +
          (cls.roomTypeName || 'Off-campus') +
          ' and does not need a campus room. ' +
          ((used && (used.label || used.code)) || 'That room') +
          ' would still be booked.'
        );
      }
      if (!roomId) {
        return 'This subject needs a ' + need + ' room. You are saving it without a campus room.';
      }
      var room = roomById(roomId);
      if (!room || !room.typeName || sameRoomType(room.typeName, cls.roomTypeName)) return '';
      return (
        'This subject needs a ' +
        need +
        ' room. ' +
        (room.label || room.code) +
        ' is ' +
        room.typeName +
        '.'
      );
    }

    function roomsOccupied(meetings) {
      var seen = {};
      var labels = [];
      (meetings || []).forEach(function (m) {
        var label = meetingRoomLabel(m);
        if (!label || seen[label]) return;
        seen[label] = true;
        labels.push(label);
      });
      if (!meetings || !meetings.length) return '—';
      return labels.length ? labels.join('; ') : 'Off-campus';
    }

    function renderClasses() {
      if (!classView) return;
      classView.innerHTML = '';
      if (!classes.length) {
        classView.appendChild(emptyLine(emptyScheduleText(true)));
        return;
      }
      var groups = filteredClassGroups();
      if (masterDetail) {
        syncAttention(
          classGroups().filter(function (g) {
            return !classIsScheduled(g) || clashesFor(g.members).length;
          }).length
        );
      }
      if (!groups.length) {
        classView.appendChild(emptyLine('No classes match these filters.'));
        return;
      }
      var table = document.createElement('table');
      table.className = 'sched-table';
      table.innerHTML =
        '<colgroup>' +
        '<col class="sched-col-class-code">' +
        '<col class="sched-col-class-title">' +
        '<col class="sched-col-class-comp">' +
        '<col class="sched-col-class-type">' +
        '<col class="sched-col-class-hours">' +
        '<col class="sched-col-class-day">' +
        '<col class="sched-col-class-time">' +
        '<col class="sched-col-class-secs">' +
        '<col class="sched-col-class-students">' +
        '<col class="sched-col-class-rooms">' +
        actionsCol() +
        '</colgroup>' +
        '<thead><tr>' +
        '<th>Course Code</th><th>Subject Title</th><th>Component</th><th>Room type</th><th>Hours</th>' +
        '<th>Day</th><th>Time</th><th>Sections</th><th>Students</th><th>Room / building</th>' +
        actionsHead() +
        '</tr></thead>';
      var tb = document.createElement('tbody');
      groups.forEach(function (g) {
        var sectionText = g.members
          .map(function (m) {
            return sectionLabel(m.sectionName, m.studentCount, m);
          })
          .join('; ');
        var studentTotal = g.members.reduce(function (n, m) {
          return n + (Number(m.studentCount) || 0);
        }, 0);
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="sched-cell-nowrap">' +
          escapeHtml(g.courseCode) +
          '</td><td>' +
          escapeHtml(g.courseTitle) +
          (g.merged ? ' <span class="sched-badge">Merged</span>' : '') +
          '</td><td>' +
          escapeHtml(g.component) +
          '</td><td class="sched-cell-type">' +
          typeCellHtml(g) +
          '</td><td class="sched-cell-nowrap">' +
          g.hoursPerWeek +
          '</td>' +
          meetingColumnHtml(g.meetings, {
            room: false,
            note: g.status && g.status !== 'scheduled' ? g.status : ''
          }) +
          '<td>' +
          escapeHtml(sectionText) +
          '</td><td class="sched-cell-nowrap">' +
          studentTotal +
          '</td><td>' +
          escapeHtml(roomsOccupied(g.meetings)) +
          '</td>' +
          editCell(g.id);
        tb.appendChild(tr);
      });
      table.appendChild(tb);
      classView.appendChild(scrollable(table));
    }









    function renderSections() {
      if (!sectionView) return;
      sectionView.innerHTML = '';
      if (!classes.length) {
        sectionView.appendChild(emptyLine(emptyScheduleText(false)));
        return;
      }
      var bySec = {};
      classes.forEach(function (c) {
        if (!matchesSectionFilters(c)) return;
        if (!bySec[c.sectionName]) bySec[c.sectionName] = [];
        bySec[c.sectionName].push(c);
      });
      if (!Object.keys(bySec).length) {
        sectionView.appendChild(emptyLine('No sections match these filters.'));
        return;
      }
      if (masterDetail) {
        renderSectionPicker(bySec);
        return;
      }
      if (openSectionGrid && !sectionGridStillVisible(bySec, openSectionGrid)) {
        openSectionGrid = '';
        writeHash();
      }
      Object.keys(bySec)
        .sort()
        .forEach(function (name) {
          sectionView.appendChild(sectionBlock(name, bySec[name], false));
        });
    }

    function sectionBlock(name, rows, picked) {
          var wantGrid = picked ? detailMode === 'grid' : openSectionGrid === name;
          var shared = [];
          var parts = [];
          rows.forEach(function (c) {
            if (isPartitioned(c)) parts.push(c);
            else shared.push(c);
          });
          parts.sort(function (a, b) {
            var code = String(a.courseCode).localeCompare(String(b.courseCode));
            if (code) return code;
            var comp = String(a.component).localeCompare(String(b.component));
            if (comp) return comp;
            return (Number(a.partitionIndex) || 0) - (Number(b.partitionIndex) || 0);
          });
          var block = document.createElement('div');
          block.className = 'sched-block';
          var head = document.createElement('div');
          head.className = 'sched-block-head';
          var h = document.createElement('h3');
          var first = rows[0];
          var whole = 0;
          rows.forEach(function (c) {
            var n = Number(c.sectionStudentCount);
            if (isFinite(n) && n > whole) whole = n;
          });
          if (!whole) whole = Number(first && first.studentCount) || 0;
          h.textContent = sectionLabel(name, whole);
          if (first && first.isRetake) {
            var badge = document.createElement('span');
            badge.className = 'sched-badge';
            badge.textContent = 'Retake';
            h.appendChild(document.createTextNode(' '));
            h.appendChild(badge);
          }
          head.appendChild(h);
          if (picked) head.appendChild(detailModeSwitch());
          else if (shared.length) head.appendChild(sectionTimetableButton(name));
          block.appendChild(head);
          block.setAttribute('data-section-grid', name);
          if (picked) block.appendChild(issueSummary(rows));
          if (shared.length) {
            if (picked && wantGrid) appendSectionGrid(block, name, shared);
            else block.appendChild(scrollable(sectionSubjectTable(shared)));
            if (!picked && wantGrid) appendSectionGrid(block, name, shared);
          }
          var groups = partitionGroups(parts);
          var sizeCounts = {};
          groups.forEach(function (group) {
            var sizeKey = group.part + '/' + group.whole;
            sizeCounts[sizeKey] = (sizeCounts[sizeKey] || 0) + 1;
          });
          var set = null;
          var setProfile = '';
          groups.forEach(function (group) {
            if (group.profile !== setProfile) {
              setProfile = group.profile;
              set = document.createElement('div');
              set.className = 'sched-partition-set';
              block.appendChild(set);
            }
            var slice = document.createElement('div');
            slice.className = 'sched-partition';
            var sliceHead = document.createElement('div');
            sliceHead.className = 'sched-block-head';
            var sliceTitle = document.createElement('h3');
            var sizeKey = group.part + '/' + group.whole;
            sliceTitle.textContent = partitionGroupHeading(group, sizeCounts[sizeKey] > 1);
            var sliceKey = partitionGroupKey(name, group);
            sliceHead.appendChild(sliceTitle);
            if (!picked) sliceHead.appendChild(sectionTimetableButton(sliceKey));
            slice.appendChild(sliceHead);
            slice.setAttribute('data-section-grid', sliceKey);
            if (picked && wantGrid) appendSectionGrid(slice, name, group.rows);
            else slice.appendChild(scrollable(sectionSubjectTable(group.rows)));
            if (!picked && openSectionGrid === sliceKey) appendSectionGrid(slice, name, group.rows);
            set.appendChild(slice);
          });
          return block;
    }

    // ----- pick-one layout (Create schedule) -----

    function findQuery() {
      return findEl ? String(findEl.value || '').trim().toLowerCase() : '';
    }

    function meetingRoomText(m) {
      return roomLabelById[m.roomId] || m.roomCode || m.roomLabel || '';
    }

    // room double-bookings and section overlaps in the timetable being shown
    function buildClashIndex() {
      var byClass = {};
      var list = [];
      classes.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (DAYS.indexOf(m.day) < 0) return;
          list.push({ c: c, m: m, s: minutesOf(m.start), e: minutesOf(m.end) });
        });
      });
      function note(c, text) {
        if (!byClass[c.id]) byClass[c.id] = [];
        if (byClass[c.id].indexOf(text) < 0) byClass[c.id].push(text);
      }
      for (var i = 0; i < list.length; i++) {
        for (var j = i + 1; j < list.length; j++) {
          var a = list[i];
          var b = list[j];
          if (a.m.day !== b.m.day || a.c.id === b.c.id) continue;
          if (a.c.mergedClassId && a.c.mergedClassId === b.c.mergedClassId) continue;
          if (!rangesOverlapMin(a.s, a.e, b.s, b.e)) continue;
          var sameRoom = !!a.m.roomId && a.m.roomId === b.m.roomId;
          var sameSection =
            !!a.c.sectionId && a.c.sectionId === b.c.sectionId && partitionsShareStudents(a.c, b.c);
          if (!sameRoom && !sameSection) continue;
          var why = sameRoom ? 'same room as ' : 'same students as ';
          note(a.c, a.c.courseCode + ' ' + a.c.component + ': ' + why + conflictClassLabel(b.c, b.m));
          note(b.c, b.c.courseCode + ' ' + b.c.component + ': ' + why + conflictClassLabel(a.c, a.m));
        }
      }
      return byClass;
    }

    function clashesFor(rows) {
      if (!clashIndex) clashIndex = buildClashIndex();
      var out = [];
      rows.forEach(function (c) {
        (clashIndex[c.id] || []).forEach(function (t) {
          if (out.indexOf(t) < 0) out.push(t);
        });
      });
      return out;
    }

    function issuesFor(rows) {
      var unplaced = rows.filter(function (c) {
        return !classIsScheduled(c);
      });
      return { unplaced: unplaced, clashes: clashesFor(rows) };
    }

    function countTag(n, one, many, tone) {
      return '<span class="sched-tag sched-tag--' + tone + '">' + n + ' ' + (n === 1 ? one : many) + '</span>';
    }

    function issueTagHtml(issues, total) {
      if (issues.clashes.length) return countTag(issues.clashes.length, 'clash', 'clashes', 'bad');
      if (issues.unplaced.length) return countTag(issues.unplaced.length, 'not placed', 'not placed', 'warn');
      return '<span class="sched-tag sched-tag--ok">' + total + '/' + total + '</span>';
    }

    function issueSummary(rows) {
      var issues = issuesFor(rows);
      var box = document.createElement('div');
      if (!issues.unplaced.length && !issues.clashes.length) {
        box.className = 'sched-issues is-ok';
        box.textContent =
          (rows.length === 1 ? 'Its 1 class is' : 'All ' + rows.length + ' classes are') + ' placed, with no clashes.';
        return box;
      }
      box.className = 'sched-issues';
      var html = '';
      if (issues.unplaced.length) {
        html +=
          '<p><strong>' +
          issues.unplaced.length +
          ' not placed yet:</strong> ' +
          issues.unplaced
            .map(function (c) {
              return scheduleLocked()
                ? escapeHtml(c.courseCode + ' ' + c.component)
                : '<button type="button" class="sched-issue-link" data-edit-offering="' +
                    escapeHtml(c.id) +
                    '">' +
                    escapeHtml(c.courseCode + ' ' + c.component) +
                    '</button>';
            })
            .join(', ') +
          '</p>';
      }
      if (issues.clashes.length) {
        html +=
          '<p><strong>Clashes:</strong></p><ul>' +
          issues.clashes
            .map(function (x) {
              return '<li>' + escapeHtml(x) + '</li>';
            })
            .join('') +
          '</ul>';
      }
      box.innerHTML = html;
      return box;
    }

    function detailModeSwitch() {
      var wrap = document.createElement('div');
      wrap.className = 'sched-mode';
      wrap.setAttribute('role', 'group');
      wrap.setAttribute('aria-label', 'Show as');
      [
        ['grid', 'Timetable'],
        ['list', 'List']
      ].forEach(function (opt) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'sched-mode-btn';
        b.textContent = opt[1];
        b.setAttribute('aria-pressed', detailMode === opt[0] ? 'true' : 'false');
        b.addEventListener('click', function () {
          detailMode = opt[0];
          render();
        });
        wrap.appendChild(b);
      });
      return wrap;
    }

    function pickerShell(host, title) {
      var md = document.createElement('div');
      md.className = 'sched-md';
      var side = document.createElement('div');
      side.className = 'sched-md-list';
      side.setAttribute('aria-label', title);
      var detail = document.createElement('div');
      detail.className = 'sched-md-detail';
      md.appendChild(side);
      md.appendChild(detail);
      host.appendChild(md);
      return { side: side, detail: detail };
    }

    function pickerItem(side, label, sub, tagHtml, picked, onPick) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'sched-md-item' + (picked ? ' is-picked' : '');
      b.setAttribute('aria-current', picked ? 'true' : 'false');
      b.innerHTML =
        '<span class="sched-md-name">' +
        escapeHtml(label) +
        (sub ? '<span class="sched-md-sub">' + escapeHtml(sub) + '</span>' : '') +
        '</span>' +
        (tagHtml || '');
      b.addEventListener('click', onPick);
      side.appendChild(b);
      return b;
    }

    function pickerGroup(side, label) {
      var g = document.createElement('div');
      g.className = 'sched-md-group';
      g.textContent = label;
      side.appendChild(g);
    }

    function keepPickedInView(side) {
      var el = side.querySelector('.is-picked');
      if (!el) return;
      var top = el.offsetTop - side.offsetTop;
      if (top < side.scrollTop || top + el.offsetHeight > side.scrollTop + side.clientHeight) {
        side.scrollTop = Math.max(0, top - 40);
      }
    }

    function showPickedDetail(host) {
      var d = host.querySelector('.sched-md-detail');
      if (!d || !d.scrollIntoView) return;
      // one column on small screens: the schedule is below the list
      var stacked = global.matchMedia && global.matchMedia('(max-width: 860px)').matches;
      if (stacked || d.getBoundingClientRect().top < 100) d.scrollIntoView({ block: 'start', behavior: stacked ? 'smooth' : 'auto' });
    }

    function sectionMatchesFind(name, rows, q) {
      if (!q) return true;
      if (String(name).toLowerCase().indexOf(q) >= 0) return true;
      return rows.some(function (c) {
        var hay = (c.courseCode + ' ' + c.courseTitle + ' ' + (c.collegeName || '')).toLowerCase();
        if (hay.indexOf(q) >= 0) return true;
        return (c.meetings || []).some(function (m) {
          return meetingRoomText(m).toLowerCase().indexOf(q) >= 0;
        });
      });
    }

    function renderSectionPicker(bySec) {
      clashIndex = null;
      var q = findQuery();
      var names = Object.keys(bySec).sort();
      var flagged = 0;
      var shown = names.filter(function (name) {
        var iss = issuesFor(bySec[name]);
        var bad = iss.unplaced.length || iss.clashes.length;
        if (bad) flagged++;
        if (attentionOnly && !bad) return false;
        return sectionMatchesFind(name, bySec[name], q);
      });
      syncAttention(flagged);
      if (!shown.length) {
        sectionView.appendChild(
          emptyLine(
            attentionOnly && !q
              ? 'Nothing needs attention. Every class is placed, with no clashes.'
              : 'No sections match your search.'
          )
        );
        return;
      }
      var byCollege = {};
      shown.forEach(function (name) {
        var col = (bySec[name][0] && bySec[name][0].collegeName) || 'Other';
        if (!byCollege[col]) byCollege[col] = [];
        byCollege[col].push(name);
      });
      var colleges = Object.keys(byCollege).sort(function (a, b) {
        return a.localeCompare(b, undefined, { sensitivity: 'base' });
      });
      if (shown.indexOf(openSectionGrid) < 0) {
        openSectionGrid = byCollege[colleges[0]][0];
        writeHash();
      }
      var ui = pickerShell(sectionView, 'Sections');
      colleges.forEach(function (col) {
          pickerGroup(ui.side, col);
          byCollege[col].forEach(function (name) {
            var rows = bySec[name];
            pickerItem(
              ui.side,
              String(name).replace(col, '').trim() || name,
              rows.length === 1 ? '1 class' : rows.length + ' classes',
              issueTagHtml(issuesFor(rows), rows.length),
              name === openSectionGrid,
              function () {
                openSectionGrid = name;
                writeHash();
                render();
                showPickedDetail(sectionView);
              }
            );
          });
        });
      ui.detail.appendChild(sectionBlock(openSectionGrid, bySec[openSectionGrid], true));
      keepPickedInView(ui.side);
    }

    function activeFilterCount() {
      var box = primaryView === 'rooms' ? roomTableFilters : primaryView === 'classes' ? classFilters : genFilters;
      if (!box) return 0;
      return Array.prototype.filter.call(box.querySelectorAll('select'), function (s) {
        return !s.disabled && s.value !== '' && !s.closest('[hidden]');
      }).length;
    }

    function syncFiltersButton() {
      var btn = qs('schedFiltersBtn');
      if (!btn) return;
      var n = activeFilterCount();
      var c = btn.querySelector('[data-filter-count]');
      if (c) {
        c.textContent = String(n);
        c.hidden = !n;
      }
    }

    function syncAttention(count) {
      if (!attentionBtn) return;
      attentionBtn.setAttribute('aria-pressed', attentionOnly ? 'true' : 'false');
      var n = attentionBtn.querySelector('[data-attention-count]');
      if (n) n.textContent = String(count);
      attentionBtn.classList.toggle('has-items', count > 0);
    }

    function renderRoomPicker(host) {
      clashIndex = null;
      var q = findQuery();
      var flagged = 0;
      var entries = filteredTableRooms()
        .filter(function (r) {
          if (!q) return true;
          var hay = String(r.label || '') + ' ' + (r.code || '') + ' ' + (r.buildingName || '') + ' ' + (r.typeName || '');
          return hay.toLowerCase().indexOf(q) >= 0;
        })
        .map(function (r) {
          var rows = classes.filter(function (c) {
            return (c.meetings || []).some(function (m) {
              return m.roomId === r.id;
            });
          });
          var clashes = clashesFor(rows).filter(function (x) {
            return x.indexOf('same room as') >= 0;
          });
          if (clashes.length) flagged++;
          return { room: r, used: rows.length, clashes: clashes };
        });
      var offRows = tableRowsForRoom('');
      var filtersOn = (roomBuildingEl && roomBuildingEl.value) || (roomFloorEl && roomFloorEl.value) || (roomTypeEl && roomTypeEl.value);
      if (offRows.length && !filtersOn && (!q || 'off-campus'.indexOf(q) >= 0)) {
        entries.push({ room: { id: 'off-campus', label: 'Off-campus', code: 'Off-campus', buildingName: '' }, used: offRows.length, clashes: [] });
      }
      if (attentionOnly) {
        entries = entries.filter(function (e) {
          return e.clashes.length;
        });
      }
      syncAttention(flagged);
      if (!entries.length) {
        host.appendChild(
          emptyLine(attentionOnly && !q ? 'No room is double-booked.' : rooms.length ? 'No rooms match your search.' : noRoomsText())
        );
        return;
      }
      if (
        !entries.some(function (e) {
          return e.room.id === openRoomTableGrid;
        })
      ) {
        openRoomTableGrid = entries[0].room.id;
        writeHash();
      }
      var ui = pickerShell(host, 'Rooms');
      var lastBuilding = null;
      var pick = null;
      entries.forEach(function (e) {
        var b = e.room.buildingName || (e.room.id === 'off-campus' ? 'Not on campus' : 'Other');
        if (b !== lastBuilding) {
          pickerGroup(ui.side, b);
          lastBuilding = b;
        }
        if (e.room.id === openRoomTableGrid) pick = e;
        var tag = e.clashes.length
          ? countTag(e.clashes.length, 'clash', 'clashes', 'bad')
          : e.used
            ? countTag(e.used, 'class', 'classes', 'muted')
            : '<span class="sched-tag sched-tag--ok">Free</span>';
        pickerItem(
          ui.side,
          e.room.code || e.room.label || 'Room',
          [e.room.typeName, e.room.capacity ? 'cap ' + e.room.capacity : ''].filter(Boolean).join(' · '),
          tag,
          e.room.id === openRoomTableGrid,
          function () {
            openRoomTableGrid = e.room.id;
            writeHash();
            render();
            showPickedDetail(host);
          }
        );
      });
      ui.detail.appendChild(roomDetail(pick));
      keepPickedInView(ui.side);
    }

    function roomDetail(entry) {
      var room = entry.room;
      var off = room.id === 'off-campus';
      var block = document.createElement('div');
      block.className = 'sched-block';
      var head = document.createElement('div');
      head.className = 'sched-block-head';
      var titleWrap = document.createElement('div');
      var h = document.createElement('h3');
      h.textContent = room.label || room.code || 'Room';
      titleWrap.appendChild(h);
      var metaParts = [];
      if (room.floorNumber != null) metaParts.push('Floor ' + room.floorNumber);
      if (room.typeName) metaParts.push(room.typeName);
      if (room.capacity) metaParts.push('cap ' + room.capacity);
      if (!off) {
        var fill = roomFillStatus(room.id);
        metaParts.push(fill === 'empty' ? 'Free all week' : fill === 'full' ? 'Fully booked' : 'Partly booked');
      }
      var meta = document.createElement('div');
      meta.className = 'sched-block-meta';
      meta.textContent = metaParts.join(' · ');
      titleWrap.appendChild(meta);
      head.appendChild(titleWrap);
      head.appendChild(detailModeSwitch());
      block.appendChild(head);
      if (entry.clashes.length) {
        var box = document.createElement('div');
        box.className = 'sched-issues';
        box.innerHTML =
          '<p><strong>Double-booked:</strong></p><ul>' +
          entry.clashes
            .map(function (x) {
              return '<li>' + escapeHtml(x) + '</li>';
            })
            .join('') +
          '</ul>';
        block.appendChild(box);
      }
      var rid = off ? '' : room.id;
      if (detailMode === 'grid') {
        var wrap = document.createElement('div');
        wrap.className = 'sched-section-grid';
        wrap.appendChild(buildWeeklyGrid(blocksForRoom(rid), gridEditOpts('No classes in this room yet. It’s free all week.')));
        block.appendChild(wrap);
      } else {
        block.appendChild(scrollable(roomOccupancyTable(tableRowsForRoom(rid))));
      }
      return block;
    }

    function sectionGridStillVisible(bySec, key) {
      if (String(key).indexOf('partition:') === 0) {
        return Object.keys(bySec).some(function (name) {
          return partitionGroups(
            bySec[name].filter(function (c) {
              return isPartitioned(c);
            })
          ).some(function (group) {
            return partitionGroupKey(name, group) === key;
          });
        });
      }
      return !!bySec[key];
    }

    function partitionStudentCount(c) {
      var n = Number(c.partitionStudents);
      if (!isFinite(n)) n = Number(c.studentCount) || 0;
      return n;
    }

    function partitionGroups(parts) {
      var subjects = {};
      (parts || []).forEach(function (c) {
        var key = String(c.courseCode) + '\0' + String(c.component || '');
        if (!subjects[key]) subjects[key] = [];
        subjects[key].push(c);
      });
      var profiles = {};
      Object.keys(subjects).forEach(function (key) {
        var slices = subjects[key].slice().sort(function (a, b) {
          var size = partitionStudentCount(a) - partitionStudentCount(b);
          if (size) return size;
          return (Number(a.partitionIndex) || 0) - (Number(b.partitionIndex) || 0);
        });
        var whole = Number(slices[0].sectionStudentCount);
        if (!isFinite(whole)) whole = 0;
        var profile =
          slices.length + ':' + whole + ':' + slices.map(partitionStudentCount).join(',');
        if (!profiles[profile]) profiles[profile] = [];
        profiles[profile].push(slices);
      });
      var groups = [];
      Object.keys(profiles)
        .sort(function (a, b) {
          var as = a.split(':').pop().split(',').map(Number);
          var bs = b.split(':').pop().split(',').map(Number);
          var n = Math.max(as.length, bs.length);
          for (var i = 0; i < n; i++) {
            var diff = (as[i] || 0) - (bs[i] || 0);
            if (diff) return diff;
          }
          return a.localeCompare(b);
        })
        .forEach(function (profile) {
          var subjectSlices = profiles[profile];
          var slotCount = subjectSlices[0].length;
          for (var i = 0; i < slotCount; i++) {
            var rows = subjectSlices.map(function (slices) {
              return slices[i];
            });
            groups.push({
              profile: profile,
              slot: i,
              rows: rows,
              part: partitionStudentCount(rows[0]),
              whole: Number(rows[0].sectionStudentCount) || partitionStudentCount(rows[0])
            });
          }
        });
      return groups;
    }

    function partitionGroupKey(sectionName, group) {
      return 'partition:' + sectionName + '\u0001' + group.profile + '\u0001' + group.slot;
    }

    function partitionGroupHeading(group, distinguish) {
      var text = group.part + ' of ' + group.whole + ' students';
      if (distinguish) text += ' · partition ' + (group.slot + 1);
      return text;
    }

    function sectionTimetableButton(gridKey) {
      var gridBtn = document.createElement('button');
      gridBtn.type = 'button';
      gridBtn.className = 'sched-link-btn';
      var open = openSectionGrid === gridKey;
      gridBtn.textContent = open ? 'Hide timetable' : 'Timetable';
      gridBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      gridBtn.addEventListener('click', function () {
        openSectionGrid = openSectionGrid === gridKey ? '' : gridKey;
        showView('sections');
        if (openSectionGrid) {
          var esc =
            global.CSS && CSS.escape
              ? CSS.escape(openSectionGrid)
              : openSectionGrid.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
          var el = sectionView.querySelector('[data-section-grid="' + esc + '"]');
          if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
      return gridBtn;
    }

    function statusTagHtml(c) {
      return classIsScheduled(c)
        ? '<span class="sched-tag sched-tag--ok">Placed</span>'
        : '<span class="sched-tag sched-tag--warn">Not placed</span>';
    }

    // wide tables scroll sideways on small screens instead of squeezing every column
    function scrollable(el) {
      if (!el || el.tagName !== 'TABLE') return el;
      var wrap = document.createElement('div');
      wrap.className = 'sched-table-scroll';
      wrap.appendChild(el);
      return wrap;
    }

    function sectionSubjectTable(rows) {
      var table = document.createElement('table');
      table.className = 'sched-table';
      table.innerHTML =
        '<colgroup>' +
        '<col class="sched-col-code">' +
        '<col class="sched-col-title">' +
        '<col class="sched-col-component">' +
        '<col class="sched-col-type">' +
        '<col class="sched-col-hours">' +
        '<col class="sched-col-day">' +
        '<col class="sched-col-time">' +
        '<col class="sched-col-room">' +
        '<col class="sched-col-status">' +
        actionsCol() +
        '</colgroup>' +
        '<thead><tr><th>Course Code</th><th>Subject Title</th><th>Component</th><th>Room type</th><th>Hours</th><th>Day</th><th>Time</th><th>Room</th><th>Status</th>' +
        actionsHead() +
        '</tr></thead>';
      var tb = document.createElement('tbody');
      rows
        .slice()
        .sort(function (a, b) {
          return String(a.courseCode).localeCompare(String(b.courseCode));
        })
        .forEach(function (c) {
          var tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="sched-cell-nowrap">' +
            escapeHtml(c.courseCode) +
            '</td><td>' +
            escapeHtml(c.courseTitle) +
            (c.mergedClassId ? ' <span class="sched-badge">Merged</span>' : '') +
            '</td><td>' +
            escapeHtml(c.component) +
            '</td><td class="sched-cell-type">' +
            typeCellHtml(c) +
            '</td><td class="sched-cell-nowrap">' +
            c.hoursPerWeek +
            '</td>' +
            meetingColumnHtml(c.meetings) +
            '<td>' +
            statusTagHtml(c) +
            (c.notes ? ' <span class="sched-status-note">' + escapeHtml(c.notes) + '</span>' : '') +
            '</td>' +
            sectionSubjectActions(c);
          tb.appendChild(tr);
        });
      table.appendChild(tb);
      return table;
    }

    function appendSectionGrid(host, name, rows) {
      var wrap = document.createElement('div');
      wrap.className = 'sched-section-grid';
      var blocks = [];
      var leftover = [];
      (rows || []).forEach(function (c) {
        if (!(c.meetings || []).length) leftover.push(c);
        (c.meetings || []).forEach(function (m) {
          blocks.push({
            class: c,
            meeting: m,
            sections: [sectionLabel(c.sectionName, c.studentCount, c)],
            studentCount: Number(c.studentCount) || 0
          });
        });
      });
      wrap.appendChild(
        buildWeeklyGrid(blocks, gridEditOpts('No placed meetings for this section.'))
      );
      if (leftover.length) {
        var p = document.createElement('p');
        p.className = 'sched-unscheduled';
        p.textContent =
          'Unscheduled: ' +
          leftover
            .map(function (c) {
              return c.courseCode + ' ' + c.component;
            })
            .join(', ');
        wrap.appendChild(p);
      }
      host.appendChild(wrap);
    }

    function roomsByBuilding() {
      var map = {};
      rooms.forEach(function (r) {
        var bid = r.buildingId || 'b-none';
        if (!map[bid]) {
          map[bid] = { id: bid, name: r.buildingName || 'Unassigned', rooms: [] };
        }
        map[bid].rooms.push(r);
      });
      return Object.keys(map)
        .sort(function (a, b) {
          return map[a].name.localeCompare(map[b].name);
        })
        .map(function (k) {
          return map[k];
        });
    }

    function roomIsAvailable(r) {
      return !!(r && r.status === 'active' && r.buildingActive !== false);
    }

    function occupancyForRoom(roomId) {
      var n = 0;
      var seen = {};
      classes.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (m.roomId !== roomId) return;
          var key = (c.mergedClassId || c.id) + '|' + m.day + '|' + m.start;
          if (seen[key]) return;
          seen[key] = true;
          n += 1;
        });
      });
      return n;
    }

    function isLunchSlot(mins) {
      return mins >= LUNCH_START && mins < LUNCH_END;
    }

    function bookableSlotTotal() {
      var n = 0;
      var rows = slotCount();
      var skipLunch = lunchKeepOn();
      for (var r = 0; r < rows; r++) {
        if (skipLunch && isLunchSlot(GRID_START + r * SLOT)) continue;
        n += 1;
      }
      return n * DAYS.length;
    }

    function occupiedSlotCount(roomId) {
      var occupied = {};
      var rows = slotCount();
      classes.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (m.roomId !== roomId) return;
          if (DAYS.indexOf(m.day) < 0) return;
          var start = slotIndex(m.start);
          var end = slotIndex(m.end);
          if (start < 0) start = 0;
          if (end > rows) end = rows;
          for (var i = start; i < end; i++) {
            if (lunchKeepOn() && isLunchSlot(GRID_START + i * SLOT)) continue;
            occupied[m.day + '|' + i] = true;
          }
        });
      });
      return Object.keys(occupied).length;
    }

    function roomFillStatus(roomId) {
      var used = occupiedSlotCount(roomId);
      if (!used) return 'empty';
      if (used >= bookableSlotTotal()) return 'full';
      return 'partial';
    }

    function roomFillBadgeHtml(roomId) {
      var status = roomFillStatus(roomId);
      var label =
        status === 'full' ? 'Fully occupied' : status === 'partial' ? 'Partially occupied' : 'Empty';
      return (
        '<span class="sched-room-fill sched-room-fill--' +
        status +
        '">' +
        escapeHtml(label) +
        '</span>'
      );
    }

    function weeklyBlockHtml(b, opts) {
      opts = opts || {};
      var cls = b.class || {};
      var meets =
        b.meetings && b.meetings.length
          ? b.meetings
          : b.meeting
            ? [b.meeting]
            : [];
      var timeHtml = meets
        .map(function (meet) {
          var time = formatTimeRange(meet.start, meet.end);
          if (opts.showDay) {
            time = (DAY_FULL[meet.day] || meet.day || '') + ' · ' + time;
          }
          return '<div class="wb-time">' + escapeHtml(time) + '</div>';
        })
        .join('');
      if (!timeHtml) {
        timeHtml = '<div class="wb-time"></div>';
      }
      var n = Number(b.studentCount);
      if (!isFinite(n)) n = classStudentCount(cls);
      var countText = studentCountLabel(n);
      return (
        '<div class="wb-count">' +
        escapeHtml(countText) +
        '</div>' +
        timeHtml +
        '<div class="wb-subj">' +
        escapeHtml(cls.courseTitle || cls.courseCode) +
        '</div><div class="wb-code">' +
        escapeHtml(cls.courseCode) +
        ' · ' +
        escapeHtml(cls.component) +
        (cls.mergedClassId ? ' · merged' : '') +
        '</div><div class="wb-section">' +
        escapeHtml((b.sections || [sectionLabel(cls.sectionName, cls.studentCount, cls)]).join(', ')) +
        '</div>'
      );
    }

    function groupBlocksForRoomHover(roomId) {
      var grouped = {};
      var out = [];
      blocksForRoom(roomId).forEach(function (b) {
        var cls = b.class || {};
        var key = String(cls.mergedClassId || cls.id || '');
        if (!grouped[key]) {
          grouped[key] = {
            class: cls,
            meeting: b.meeting,
            meetings: [],
            sections: (b.sections || []).slice(),
            studentCount: isFinite(Number(b.studentCount))
              ? Number(b.studentCount)
              : classStudentCount(cls)
          };
          out.push(grouped[key]);
        }
        var rec = grouped[key];
        if (b.meeting) rec.meetings.push(b.meeting);
        (b.sections || []).forEach(function (s) {
          if (rec.sections.indexOf(s) < 0) rec.sections.push(s);
        });
      });
      out.forEach(function (g) {
        g.meetings.sort(function (a, b) {
          var da = dayOrder(a.day || '') - dayOrder(b.day || '');
          if (da) return da;
          return String(a.start || '').localeCompare(String(b.start || ''));
        });
        g.meeting = g.meetings[0] || g.meeting;
      });
      out.sort(function (a, b) {
        var ma = a.meetings[0] || {};
        var mb = b.meetings[0] || {};
        var da = dayOrder(ma.day || '') - dayOrder(mb.day || '');
        if (da) return da;
        return String(ma.start || '').localeCompare(String(mb.start || ''));
      });
      return out;
    }

    function roomHoverPanel() {
      var el = qs('schedRoomHover');
      if (el) return el;
      el = document.createElement('div');
      el.id = 'schedRoomHover';
      el.className = 'sched-room-hover';
      el.hidden = true;
      el.setAttribute('role', 'region');
      el.setAttribute('aria-label', 'Scheduled classes in this room');
      var inner = document.createElement('div');
      inner.className = 'sched-room-hover-inner';
      el.appendChild(inner);
      el.addEventListener('mouseenter', cancelRoomHoverHide);
      el.addEventListener('mouseleave', function (e) {
        if (roomHoverRelated(e.relatedTarget)) return;
        scheduleRoomHoverHide();
      });
      document.body.appendChild(el);
      return el;
    }

    var roomHoverHideTimer = null;
    var roomHoverAnchor = null;

    function roomHoverRelated(target) {
      if (!target || !target.closest) return false;
      if (target.closest('#schedRoomHover')) return true;
      return !!(roomHoverAnchor && (target === roomHoverAnchor || roomHoverAnchor.contains(target)));
    }

    function cancelRoomHoverHide() {
      if (roomHoverHideTimer) {
        clearTimeout(roomHoverHideTimer);
        roomHoverHideTimer = null;
      }
    }

    function scheduleRoomHoverHide() {
      cancelRoomHoverHide();
      roomHoverHideTimer = setTimeout(function () {
        roomHoverHideTimer = null;
        hideRoomHover();
      }, 280);
    }

    function hideRoomHover() {
      cancelRoomHoverHide();
      roomHoverAnchor = null;
      var el = qs('schedRoomHover');
      if (el) el.hidden = true;
    }

    function fillRoomHover(room) {
      var panel = roomHoverPanel();
      var inner = panel.querySelector('.sched-room-hover-inner') || panel;
      var blocks = groupBlocksForRoomHover(room.id);
      inner.innerHTML = '';
      var title = document.createElement('div');
      title.className = 'sched-room-hover-title';
      title.textContent = (room.label || room.code || 'Room') + ' — scheduled classes';
      inner.appendChild(title);
      if (!blocks.length) {
        inner.appendChild(emptyLine('No classes booked in this room for the selected term.'));
        return panel;
      }
      blocks.forEach(function (b) {
        var block = document.createElement('div');
        block.className = 'weekly-schedule-block';
        block.style.background = colorFor(b.class && b.class.courseCode);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'wb-inner';
        btn.innerHTML = weeklyBlockHtml(b, { showDay: true });
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          hideRoomHover();
          if (!scheduleLocked()) {
            openEditModal(b.class, b.meeting);
            return;
          }
          selectedBuilding = room.buildingId;
          selectedRoom = room.id;
          showView('rooms', 'grid');
        });
        block.appendChild(btn);
        inner.appendChild(block);
      });
      return panel;
    }

    function showRoomHover(btn, room) {
      cancelRoomHoverHide();
      roomHoverAnchor = btn;
      var panel = fillRoomHover(room);
      var inner = panel.querySelector('.sched-room-hover-inner');
      if (inner) inner.style.maxHeight = '';
      panel.hidden = false;
      var rect = btn.getBoundingClientRect();
      var bridge = 12;
      var left = rect.left;
      var top = rect.bottom - bridge;
      var pw = panel.offsetWidth;
      if (left + pw > window.innerWidth - 12) left = Math.max(12, window.innerWidth - pw - 12);
      var roomBelow = window.innerHeight - rect.bottom - 12;
      if (inner && roomBelow > 80) {
        inner.style.maxHeight = roomBelow + 'px';
      }
      panel.style.left = left + 'px';
      panel.style.top = top + 'px';
    }

    function bindRoomCardHover(btn, room) {
      btn.addEventListener('mouseenter', function () {
        showRoomHover(btn, room);
      });
      btn.addEventListener('mouseleave', function (e) {
        if (roomHoverRelated(e.relatedTarget)) return;
        scheduleRoomHoverHide();
      });
      btn.addEventListener('focus', function () {
        showRoomHover(btn, room);
      });
      btn.addEventListener('blur', scheduleRoomHoverHide);
    }

    function renderRoomCrumb() {
      if (!roomCrumb || primaryView !== 'rooms' || layout !== 'grid') return;
      roomCrumb.innerHTML = '';
      var bld = roomsByBuilding().filter(function (b) {
        return b.id === selectedBuilding;
      })[0];
      var room = rooms.filter(function (r) {
        return r.id === selectedRoom;
      })[0];
      function addBtn(label, fn) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = label;
        btn.addEventListener('click', fn);
        roomCrumb.appendChild(btn);
      }
      function addSep() {
        var sep = document.createElement('span');
        sep.className = 'sched-crumb-sep';
        sep.textContent = '/';
        roomCrumb.appendChild(sep);
      }
      addBtn('Buildings', function () {
        selectedBuilding = '';
        selectedRoom = '';
        showView('rooms', 'grid');
      });
      if (bld) {
        addSep();
        addBtn(bld.name, function () {
          selectedRoom = '';
          showView('rooms', 'grid');
        });
      }
      if (room) {
        addSep();
        var cur = document.createElement('span');
        cur.textContent = room.label || room.code;
        roomCrumb.appendChild(cur);
      }
    }

    function renderRooms() {
      hideRoomHover();
      if (!roomView) return;
      roomView.innerHTML = '';
      if (masterDetail) {
        renderRoomPicker(roomView);
        return;
      }
      if (layout === 'grid') {
        renderRoomBrowser(roomView);
        return;
      }
      renderRoomTable(roomView);
    }

    function tableRowsForRoom(roomId) {
      var rows = [];
      classes.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if ((m.roomId || '') !== (roomId || '')) return;
          rows.push({
            sectionName: c.sectionName,
            studentCount: c.studentCount,
            partitionCount: c.partitionCount,
            partitionStudents: c.partitionStudents,
            sectionStudentCount: c.sectionStudentCount,
            courseCode: c.courseCode,
            courseTitle: c.courseTitle,
            component: c.component,
            roomTypeName: c.roomTypeName || '',
            requiresCampusRoom: c.requiresCampusRoom !== false,
            day: m.day,
            start: m.start,
            end: m.end,
            merged: !!c.mergedClassId,
            offeringId: c.id,
            meetingId: m.id,
            roomId: m.roomId || ''
          });
        });
      });
      return rows;
    }

    function renderRoomTable(host) {
      var list = filteredTableRooms();
      var offRows = tableRowsForRoom('');
      var buildingId = roomBuildingEl ? roomBuildingEl.value : '';
      var floorVal = roomFloorEl ? roomFloorEl.value : '';
      var typeVal = roomTypeEl ? roomTypeEl.value : '';
      var showOff = !buildingId && !floorVal && !typeVal && offRows.length;
      var ids = {};
      list.forEach(function (r) {
        ids[r.id] = true;
      });
      if (openRoomTableGrid && openRoomTableGrid !== 'off-campus' && !ids[openRoomTableGrid]) {
        openRoomTableGrid = '';
        writeHash();
      }
      if (!list.length && !showOff) {
        host.appendChild(
          emptyLine(
            rooms.length
              ? 'No rooms match these filters.'
              : noRoomsText()
          )
        );
        return;
      }
      list.forEach(function (room) {
        host.appendChild(roomTableBlock(room, tableRowsForRoom(room.id)));
      });
      if (showOff) {
        host.appendChild(
          roomTableBlock(
            { id: 'off-campus', label: 'Off-campus', buildingName: '', floorNumber: null, typeName: '' },
            offRows
          )
        );
      }
    }

    function roomTableBlock(room, rows) {
      var block = document.createElement('div');
      block.className = 'sched-block';
      block.setAttribute('data-room-grid', room.id);
      var head = document.createElement('div');
      head.className = 'sched-block-head';
      var titleWrap = document.createElement('div');
      var h = document.createElement('h3');
      h.textContent = room.label || room.code || 'Room';
      titleWrap.appendChild(h);
      var metaParts = [];
      if (room.buildingName) metaParts.push(room.buildingName);
      if (room.floorNumber != null) metaParts.push('Floor ' + room.floorNumber);
      if (room.typeName) metaParts.push(room.typeName);
      if (room.capacity) metaParts.push('cap ' + room.capacity);
      if (metaParts.length) {
        var meta = document.createElement('div');
        meta.className = 'sched-block-meta';
        meta.textContent = metaParts.join(' · ');
        titleWrap.appendChild(meta);
      }
      var open = openRoomTableGrid === room.id;
      var gridBtn = document.createElement('button');
      gridBtn.type = 'button';
      gridBtn.className = 'sched-link-btn';
      gridBtn.textContent = open ? 'Hide timetable' : 'Timetable';
      gridBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      gridBtn.addEventListener('click', function () {
        openRoomTableGrid = openRoomTableGrid === room.id ? '' : room.id;
        showView('rooms', 'table');
        if (openRoomTableGrid) {
          var esc =
            global.CSS && CSS.escape
              ? CSS.escape(openRoomTableGrid)
              : openRoomTableGrid.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
          var el = roomView.querySelector('[data-room-grid="' + esc + '"]');
          if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
      head.appendChild(titleWrap);
      head.appendChild(gridBtn);
      block.appendChild(head);
      block.appendChild(scrollable(roomOccupancyTable(rows)));
      if (open) {
        var wrap = document.createElement('div');
        wrap.className = 'sched-section-grid';
        wrap.appendChild(
          buildWeeklyGrid(room.id === 'off-campus' ? blocksForRoom('') : blocksForRoom(room.id), gridEditOpts('This room has no classes in the saved schedule.'))
        );
        block.appendChild(wrap);
      }
      return block;
    }

    function roomOccupancyTable(rows) {
      if (!rows.length) {
        return emptyLine('No classes booked in this room for the selected term.');
      }
      var table = document.createElement('table');
      table.className = 'sched-table';
      table.innerHTML =
        '<colgroup>' +
        '<col class="sched-col-day">' +
        '<col class="sched-col-time">' +
        '<col class="sched-col-section">' +
        '<col class="sched-col-room-code">' +
        '<col class="sched-col-type">' +
        '<col class="sched-col-room-title">' +
        actionsCol() +
        '</colgroup>' +
        '<thead><tr><th>Day</th><th>Time</th><th>Section</th><th>Course Code</th><th>Required type</th><th>Subject Title</th>' +
        actionsHead() +
        '</tr></thead>';
      var tb = document.createElement('tbody');
      rows
        .slice()
        .sort(function (a, b) {
          return dayOrder(a.day) - dayOrder(b.day) || String(a.start).localeCompare(String(b.start));
        })
        .forEach(function (r) {
          var tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="sched-cell-nowrap">' +
            escapeHtml(r.day) +
            '</td><td class="sched-cell-nowrap">' +
            escapeHtml(formatTimeRange(r.start, r.end)) +
            '</td><td>' +
            escapeHtml(sectionLabel(r.sectionName, r.studentCount, r)) +
            '</td><td class="sched-cell-nowrap">' +
            escapeHtml(r.courseCode) +
            '</td><td class="sched-cell-type">' +
            typeCellHtml(r, r.roomId) +
            '</td><td>' +
            escapeHtml(r.courseTitle) +
            ' (' +
            escapeHtml(r.component) +
            ')' +
            (r.merged ? ' <span class="sched-badge">Merged</span>' : '') +
            '</td>' +
            editCell(r.offeringId, r.meetingId || '');
          tb.appendChild(tr);
        });
      table.appendChild(tb);
      return table;
    }

    function renderRoomBrowser(host) {
      var buildings = roomsByBuilding();
      if (!buildings.length) {
        host.appendChild(emptyLine(noRoomsText()));
        return;
      }
      if (!selectedBuilding) {
        var grid = document.createElement('div');
        grid.className = 'sched-building-grid';
        buildings.forEach(function (b) {
          var used = b.rooms.reduce(function (n, r) {
            return n + occupancyForRoom(r.id);
          }, 0);
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'sched-building-card';
          var bldgOff = (b.rooms || []).every(function (r) {
            return r.buildingActive === false;
          });
          btn.innerHTML =
            '<span class="sched-building-name">' +
            escapeHtml(b.name) +
            '</span><span class="sched-building-meta">' +
            (bldgOff ? 'Unavailable · ' : '') +
            b.rooms.length +
            ' rooms · ' +
            used +
            ' booked period' +
            (used === 1 ? '' : 's') +
            '</span>';
          btn.addEventListener('click', function () {
            selectedBuilding = b.id;
            selectedRoom = '';
            showView('rooms', 'grid');
          });
          grid.appendChild(btn);
        });
        host.appendChild(grid);
        return;
      }
      var bld = buildings.filter(function (b) {
        return b.id === selectedBuilding;
      })[0];
      if (!bld) {
        selectedBuilding = '';
        renderRoomBrowser(host);
        return;
      }
      if (!selectedRoom) {
        var list = document.createElement('div');
        list.className = 'sched-room-list';
        bld.rooms.forEach(function (r) {
          var n = occupancyForRoom(r.id);
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'sched-room-card';
          btn.innerHTML =
            '<span class="sched-room-card-head">' +
            '<span class="sched-room-name">' +
            escapeHtml(r.label || r.code) +
            '</span>' +
            roomFillBadgeHtml(r.id) +
            '</span><span class="sched-room-meta">' +
            (r.buildingActive === false
              ? 'Building unavailable'
              : r.status === 'active'
                ? 'Active'
                : 'Under maintenance') +
            (r.capacity ? ' · cap ' + r.capacity : '') +
            (r.typeName ? ' · ' + escapeHtml(r.typeName) : '') +
            ' · ' +
            n +
            ' period' +
            (n === 1 ? '' : 's') +
            '</span>';
          btn.addEventListener('click', function () {
            hideRoomHover();
            selectedRoom = r.id;
            showView('rooms', 'grid');
          });
          bindRoomCardHover(btn, r);
          list.appendChild(btn);
        });
        host.appendChild(list);
        var spacer = document.createElement('div');
        spacer.className = 'sched-room-hover-spacer';
        spacer.setAttribute('aria-hidden', 'true');
        host.appendChild(spacer);
        return;
      }
      var room = bld.rooms.filter(function (r) {
        return r.id === selectedRoom;
      })[0];
      if (!room) {
        selectedRoom = '';
        renderRoomBrowser(host);
        return;
      }
      var heading = document.createElement('h3');
      heading.textContent = room.label || room.code;
      host.appendChild(heading);
      if (!scheduleLocked()) {
        var hint = document.createElement('p');
        hint.className = 'page-sub';
        hint.textContent = 'Click a class block to change its day, time, or room, or to move a section to another class.';
        host.appendChild(hint);
      }
      host.appendChild(
        buildWeeklyGrid(blocksForRoom(room.id), gridEditOpts('This room has no classes in the saved schedule.'))
      );
    }

    function blocksForRoom(roomId) {
      var seen = {};
      var out = [];
      classes.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if ((m.roomId || '') !== roomId) return;
          var key = (c.mergedClassId || c.id) + '|' + m.day + '|' + m.start;
          if (seen[key]) {
            seen[key].sections.push(sectionLabel(c.sectionName, c.studentCount, c));
            seen[key].studentCount += Number(c.studentCount) || 0;
            return;
          }
          var rec = {
            class: c,
            meeting: m,
            sections: [sectionLabel(c.sectionName, c.studentCount, c)],
            studentCount: Number(c.studentCount) || 0
          };
          seen[key] = rec;
          out.push(rec);
        });
      });
      return out;
    }

    function buildWeeklyGrid(blocks, opts) {
      opts = opts || {};
      var wrap = document.createElement('div');
      wrap.className = 'weekly-schedule-wrap';
      var rows = slotCount();
      var placed = {};
      DAYS.forEach(function (d) {
        placed[d] = {};
      });
      (blocks || []).forEach(function (b) {
        var m = b.meeting;
        if (!m || !placed[m.day]) return;
        var start = slotIndex(m.start);
        var end = slotIndex(m.end);
        if (start < 0) start = 0;
        if (end > rows) end = rows;
        if (end <= start) return;
        placed[m.day][start] = { block: b, span: end - start };
        for (var i = start + 1; i < end; i++) placed[m.day][i] = { skip: true };
      });

      var table = document.createElement('table');
      table.className = 'weekly-schedule-table';
      var thead = document.createElement('thead');
      var hr = document.createElement('tr');
      hr.appendChild(document.createElement('th')).textContent = 'Time';
      DAYS.forEach(function (d) {
        var th = document.createElement('th');
        th.textContent = DAY_FULL[d] || d;
        hr.appendChild(th);
      });
      thead.appendChild(hr);
      table.appendChild(thead);
      var tb = document.createElement('tbody');
      var any = false;
      for (var r = 0; r < rows; r++) {
        var mins = GRID_START + r * SLOT;
        var tr = document.createElement('tr');
        var timeTd = document.createElement('td');
        timeTd.className = 'weekly-schedule-time-col';
        var slotPad = document.createElement('span');
        slotPad.className = 'weekly-schedule-time-slot';
        slotPad.setAttribute('aria-hidden', 'true');
        timeTd.appendChild(slotPad);
        var startLabel = document.createElement('span');
        startLabel.className = 'weekly-schedule-time-label';
        startLabel.textContent = formatClock(mins);
        timeTd.appendChild(startLabel);
        if (r === rows - 1) {
          var endLabel = document.createElement('span');
          endLabel.className = 'weekly-schedule-time-label weekly-schedule-time-label--end';
          endLabel.textContent = formatClock(GRID_END);
          timeTd.appendChild(endLabel);
        }
        tr.appendChild(timeTd);
        DAYS.forEach(function (d) {
          var cell = placed[d][r];
          if (cell && cell.skip) return;
          var td = document.createElement('td');
          if (cell && cell.block) {
            any = true;
            td.className = 'weekly-schedule-block';
            td.rowSpan = cell.span;
            td.style.background = colorFor(cell.block.class.courseCode);
            var inner = document.createElement(opts.onEdit ? 'button' : 'div');
            if (opts.onEdit) inner.type = 'button';
            else inner.className = 'wb-inner';
            inner.innerHTML = weeklyBlockHtml(cell.block);
            if (opts.onEdit) {
              inner.addEventListener('click', function () {
                opts.onEdit(cell.block);
              });
            }
            if (opts.highlightKey) {
              var meet = cell.block.meeting || {};
              var blockKey =
                ((cell.block.class && (cell.block.class.mergedClassId || cell.block.class.id)) || '') +
                '|' +
                (meet.day || '') +
                '|' +
                (meet.start || '');
              if (blockKey === opts.highlightKey) {
                td.classList.add('weekly-schedule-block--current');
              }
            }
            td.appendChild(inner);
          } else {
            td.className =
              mins >= LUNCH_START && mins < LUNCH_END ? 'weekly-schedule-lunch' : 'weekly-schedule-empty';
            td.innerHTML = '<span class="weekly-schedule-time-slot" aria-hidden="true"></span>';
          }
          tr.appendChild(td);
        });
        tb.appendChild(tr);
      }
      table.appendChild(tb);
      wrap.appendChild(table);
      if (!any && opts.empty) {
        wrap.appendChild(emptyLine(opts.empty));
      }
      return wrap;
    }

    function classById(id) {
      for (var i = 0; i < classes.length; i++) {
        if (classes[i].id === id) return classes[i];
      }
      return null;
    }

    function meetingById(cls, meetingId) {
      if (!cls || !meetingId) return (cls && cls.meetings && cls.meetings[0]) || null;
      for (var i = 0; i < (cls.meetings || []).length; i++) {
        if (cls.meetings[i].id === meetingId) return cls.meetings[i];
      }
      return cls.meetings[0] || null;
    }

    function assignedMeetingRoomId(cls, meeting) {
      if (!meeting) return '';
      if (meeting.roomId) return meeting.roomId;
      var found = '';
      membersOf(cls).forEach(function (member) {
        if (found) return;
        (member.meetings || []).forEach(function (other) {
          if (found || !other.roomId) return;
          if (other.day === meeting.day && String(other.start || '') === String(meeting.start || '')) {
            found = other.roomId;
          }
        });
      });
      if (found) return found;
      var code = String(meeting.roomCode || '').trim();
      if (!code) return '';
      for (var r = 0; r < rooms.length; r++) {
        var label = String(rooms[r].label || rooms[r].code || '').trim();
        if (label && label === code) return rooms[r].id;
      }
      return '';
    }

    function fillTimeSelect(sel, selected, opts) {
      opts = opts || {};
      var min = opts.min != null ? opts.min : GRID_START;
      var max = opts.max != null ? opts.max : GRID_END;
      sel.innerHTML = '';
      for (var m = min; m <= max; m += SLOT) {
        sel.appendChild(new Option(formatClock(m), hhmm(m)));
      }
      if (selected && Array.prototype.some.call(sel.options, function (o) { return o.value === selected; })) {
        sel.value = selected;
      }
    }

    function roomOptionLabel(r) {
      var bits = [r.label || r.code || r.id];
      if (r.typeName) bits.push(r.typeName);
      if (r.capacity) bits.push('cap ' + r.capacity);
      if (r.colleges && r.colleges.length && editState && editState.cls && !roomOpenToCollege(r, editState.cls.collegeId)) {
        bits.push('set aside for other colleges');
      }
      return bits.join(' · ');
    }

    function roomPickerEls(which) {
      if (which === 'planner') {
        return {
          building: qs('schedEditPlannerRoomBuilding'),
          type: qs('schedEditPlannerRoomTypeFilter'),
          room: qs('schedEditPlannerRoom')
        };
      }
      return {
        building: qs('schedEditRoomBuilding'),
        type: qs('schedEditRoomTypeFilter'),
        room: qs('schedEditRoom')
      };
    }

    function fillRoomSelect(sel, selected, filters) {
      if (!sel) return;
      filters = filters || {};
      var list = rooms.filter(function (r) {
        if (!roomIsAvailable(r)) return false;
        if (filters.building && r.buildingId !== filters.building) return false;
        if (filters.type && r.typeName !== filters.type) return false;
        return true;
      });
      sel.innerHTML = '';
      sel.appendChild(new Option('Off-campus', ''));
      var seen = {};
      list.forEach(function (r) {
        seen[r.id] = true;
        sel.appendChild(new Option(roomOptionLabel(r), r.id));
      });
      if (selected && !seen[selected]) {
        var extra = roomById(selected);
        if (extra) sel.appendChild(new Option(roomOptionLabel(extra) + ' (outside filter)', extra.id));
      }
      if (selected && Array.prototype.some.call(sel.options, function (o) { return o.value === selected; })) {
        sel.value = selected;
      }
    }

    function refillRoomPicker(which, keepSelected) {
      var els = roomPickerEls(which);
      var selected = keepSelected != null ? keepSelected : (els.room && els.room.value) || '';
      fillRoomSelect(els.room, selected, {
        building: els.building && els.building.value,
        type: els.type && els.type.value
      });
      if (which !== 'planner') {
        updateRoomLink(els.room && els.room.value);
        refreshEditRoomHint();
      } else {
        refreshPlanner();
      }
    }

    function setupRoomPicker(which, cls, selectedRoomId) {
      var els = roomPickerEls(which);
      var current = roomById(selectedRoomId);
      var buildings = [{ value: '', label: 'All buildings' }].concat(
        roomsByBuilding()
          .filter(function (b) {
            if (current && current.buildingId === b.id) return true;
            return (b.rooms || []).some(roomIsAvailable);
          })
          .map(function (b) {
            return { value: b.id, label: b.name };
          })
      );
      var types = {};
      rooms.forEach(function (r) {
        if (roomIsAvailable(r) && r.typeName) types[r.typeName] = true;
      });
      var typeOpts = [{ value: '', label: 'All types' }].concat(
        Object.keys(types)
          .sort()
          .map(function (t) {
            return { value: t, label: t };
          })
      );
      var preferType = '';
      if (cls && subjectNeedsCampusRoom(cls) && cls.roomTypeName && types[cls.roomTypeName]) {
        preferType = cls.roomTypeName;
      } else if (current && current.typeName) {
        preferType = current.typeName;
      }
      fillSelectKeep(els.building, buildings, current && current.buildingId);
      fillSelectKeep(els.type, typeOpts, preferType);
      fillRoomSelect(els.room, selectedRoomId || '', {
        building: els.building && els.building.value,
        type: els.type && els.type.value
      });
    }

    function targetOptionsFor(cls) {
      if (!cls) return [];
      var seen = {};
      var out = [];
      classes.forEach(function (c) {
        if (c.id === cls.id) return;
        if (cls.mergedClassId && c.mergedClassId === cls.mergedClassId) return;
        if (!(c.meetings && c.meetings.length)) return;
        if (c.courseCode !== cls.courseCode || c.component !== cls.component) return;
        var key = c.mergedClassId || c.id;
        if (seen[key]) return;
        seen[key] = true;
        var members = membersOf(c)
          .map(function (m) {
            return sectionLabel(m.sectionName, m.studentCount, m);
          })
          .join(', ');
        var when = sortedMeetings(c.meetings)
          .map(function (m) {
            return (DAY_LABELS[m.day] || m.day) + ' ' + formatTimeRange(m.start, m.end);
          })
          .join('; ');
        var room = roomsOccupied(c.meetings);
        out.push({
          id: c.id,
          label:
            (c.courseCode || '') +
            ' ' +
            (c.component || '') +
            ' · ' +
            members +
            (c.mergedClassId ? ' (merged)' : '') +
            (when ? ' · ' + when : '') +
            (room && room !== '—' ? ' · ' + room : '')
        });
      });
      out.sort(function (a, b) {
        return String(a.label).localeCompare(String(b.label));
      });
      return out;
    }

    function formatHoursPhrase(hours) {
      var n = Math.round((Number(hours) || 0) * 100) / 100;
      var label = Math.abs(n - Math.round(n)) < 0.001 ? String(Math.round(n)) : String(n);
      return label + (n === 1 ? ' hour' : ' hours');
    }

    function proposedWeeklyMinutes(cls, meetingId, start, end) {
      var total = 0;
      (cls.meetings || []).forEach(function (m) {
        if (m.id === meetingId) {
          total += minutesOf(end) - minutesOf(start);
        } else {
          total += minutesOf(m.end) - minutesOf(m.start);
        }
      });
      return total;
    }

    function hoursMismatchMessage(cls, meetingId, start, end) {
      if (!cls || !meetingId) return '';
      var required = Math.round((Number(cls.hoursPerWeek) || 0) * 60);
      var got = proposedWeeklyMinutes(cls, meetingId, start, end);
      if (got === required) return '';
      return (
        'This class is set for ' +
        formatHoursPhrase(required / 60) +
        ' a week. The new times add up to ' +
        formatHoursPhrase(got / 60) +
        '. Keep the total exact.'
      );
    }

    function hoursMessageForTotal(cls, gotMins) {
      var required = Math.round((Number(cls.hoursPerWeek) || 0) * 60);
      if (gotMins === required) {
        return 'This class needs ' + formatHoursPhrase(cls.hoursPerWeek) + ' a week.';
      }
      return (
        'This class is set for ' +
        formatHoursPhrase(required / 60) +
        ' a week. The new times add up to ' +
        formatHoursPhrase(gotMins / 60) +
        '. Keep the total exact.'
      );
    }

    function refreshEditHoursHint() {
      var hint = qs('schedEditHours');
      if (!editState || !editState.cls || !editState.meetingId) {
        if (hint) hint.textContent = '';
        return;
      }
      var start = qs('schedEditStart') && qs('schedEditStart').value;
      var end = qs('schedEditEnd') && qs('schedEditEnd').value;
      var msg = hoursMismatchMessage(editState.cls, editState.meetingId, start, end);
      if (hint) {
        hint.textContent = msg ||
          'This class needs ' + formatHoursPhrase(editState.cls.hoursPerWeek) + ' a week.';
        hint.classList.toggle('is-warn', !!msg);
      }
      refreshEditRoomHint();
    }

    // checked while editing, before Save: same section or same room at that time
    function editClashMessage(roomId) {
      if (!editState || !editState.meetingId || editState.planner) return '';
      var meeting = meetingById(editState.cls, editState.meetingId);
      var day = qs('schedEditDay') && qs('schedEditDay').value;
      var start = qs('schedEditStart') && qs('schedEditStart').value;
      var end = qs('schedEditEnd') && qs('schedEditEnd').value;
      if (!meeting || !day || !start || !end) return '';
      return windowBlocked(
        day,
        start,
        end,
        groupSectionIds(editState.cls),
        roomId,
        siblingMeetingIds(editState.cls, meeting),
        [],
        groupLanes(editState.cls)
      );
    }

    function refreshEditRoomHint() {
      var hint = qs('schedEditRoomHint');
      if (!hint || !editState || !editState.cls) {
        if (hint) hint.textContent = '';
        return;
      }
      var roomId = qs('schedEditRoom') ? qs('schedEditRoom').value : '';
      var msg = [editClashMessage(roomId), roomTypeWarning(editState.cls, roomId), roomCollegeWarning(editState.cls, roomId), roomCapacityWarning(editState.cls, roomId)]
        .filter(Boolean)
        .join(' ');
      if (msg) {
        hint.textContent = msg;
        hint.classList.add('is-warn');
      } else if (!subjectNeedsCampusRoom(editState.cls)) {
        hint.textContent = 'This subject is ' + (editState.cls.roomTypeName || 'Off-campus') + ' and does not need a campus room.';
        hint.classList.remove('is-warn');
      } else {
        hint.textContent = editState.cls.roomTypeName
          ? 'This subject needs a ' + editState.cls.roomTypeName + ' room.'
          : '';
        hint.classList.remove('is-warn');
      }
    }

    var confirmWaiter = null;

    function closeConfirmModal(answer) {
      var modal = qs('schedConfirmModal');
      if (modal) modal.hidden = true;
      var done = confirmWaiter;
      confirmWaiter = null;
      if (done) done(!!answer);
    }

    function askConfirm(opts) {
      opts = opts || {};
      var message = typeof opts === 'string' ? opts : String(opts.message || '');
      var modal = qs('schedConfirmModal');
      var body = qs('schedConfirmBody');
      var title = qs('schedConfirmTitle');
      var yes = qs('schedConfirmYes');
      var no = qs('schedConfirmNo');
      if (!modal || !body) return Promise.resolve(window.confirm(message));
      if (title) title.textContent = opts.title || 'Please confirm';
      body.textContent = message;
      if (yes) {
        yes.textContent = opts.yes || 'Continue';
        yes.className = 'sched-btn' + (opts.yesClass ? ' ' + opts.yesClass : '');
      }
      if (no) no.textContent = opts.no || 'Cancel';
      modal.hidden = false;
      if (no && no.focus) no.focus();
      return new Promise(function (resolve) {
        confirmWaiter = resolve;
      });
    }

    function askRoomConfirm(message) {
      return askConfirm({
        title: 'Use this room anyway?',
        message: message,
        yes: 'Save anyway',
        no: 'Go back'
      });
    }

    function withRoomConfirm(cls, roomId, next) {
      var bits = [];
      var typeWarn = roomTypeWarning(cls, roomId);
      var collegeWarn = roomCollegeWarning(cls, roomId);
      var capWarn = roomCapacityWarning(cls, roomId);
      if (typeWarn) bits.push(typeWarn);
      if (collegeWarn) bits.push(collegeWarn);
      if (capWarn) bits.push(capWarn);
      if (!bits.length) {
        next();
        return;
      }
      askRoomConfirm(bits.join('\n\n') + '\n\nSave anyway?').then(function (ok) {
        if (ok) next();
      });
    }

    function lunchKeepOn() {
      return !!(generatorSettings && generatorSettings.keepLunch !== false);
    }

    function usesLunchHour(start, end) {
      return minutesOf(start) < LUNCH_END && minutesOf(end) > LUNCH_START;
    }

    function withLunchConfirm(start, end, next) {
      if (!lunchKeepOn() || !usesLunchHour(start, end)) {
        next();
        return;
      }
      askConfirm({
        title: 'Use lunch hour (12:00–1:00)?',
        message:
          'Generate schedule keeps 12:00–1:00 vacant. This class would use that hour. Continue only if it should meet during lunch.',
        yes: 'Use 12:00–1:00',
        no: 'Go back'
      }).then(function (ok) {
        if (ok) next();
      });
    }

    function normalizeGenSettings(raw) {
      raw = raw || {};
      var mode = String(raw.mergeMode || raw.merge_mode || 'auto');
      if (['auto', 'manual', 'off'].indexOf(mode) < 0) mode = 'auto';
      function clampSplitHours(value, fallback) {
        var hours = Number(value);
        if (!isFinite(hours)) hours = fallback;
        if (hours < 1) hours = 1;
        if (hours > 6) hours = 6;
        return Math.round(hours * 2) / 2;
      }
      var hours = clampSplitHours(raw.splitHours != null ? raw.splitHours : raw.split_hours, 3);
      var labRaw = raw.splitLabHours != null ? raw.splitLabHours : raw.split_lab_hours;
      var labHours = labRaw == null ? hours : clampSplitHours(labRaw, hours);
      var keep = raw.keepLunch;
      if (keep == null) keep = raw.keep_lunch;
      var scope = String(raw.partitionScope != null ? raw.partitionScope : raw.partition_scope || 'small');
      if (['off', 'small', 'medium', 'any'].indexOf(scope) < 0) scope = 'small';
      return {
        mergeMode: mode,
        splitHours: hours,
        splitLabHours: labHours,
        keepLunch: keep !== false && keep !== 0 && keep !== '0',
        partitionScope: scope
      };
    }

    function genSettingsScopeText() {
      if (actor === 'super_admin') {
        return 'These settings apply only to the University Registrar’s Working timetable.';
      }
      return 'These settings apply only to the Records Officer’s Working timetable.';
    }

    // Partitions are merges, so turning merges off turns this off too. The
    // stored choice is kept so it comes back when merges are turned on again.
    function syncPartitionToggle() {
      if (!genPartitionEl) return;
      var merging = !genMergeEl || genMergeEl.value !== 'off';
      var scope = merging ? generatorSettings.partitionScope : 'off';
      // Create schedule only offers On (any) / Off; older saved choices count as On
      var offered = Array.prototype.some.call(genPartitionEl.options, function (o) { return o.value === scope; });
      genPartitionEl.value = offered ? scope : scope === 'off' ? 'off' : 'any';
      genPartitionEl.disabled = !merging;
    }

    function fillGenSettingsForm() {
      var s = normalizeGenSettings(generatorSettings);
      generatorSettings = s;
      if (genMergeEl) genMergeEl.value = s.mergeMode;
      if (genSplitEl) genSplitEl.value = String(s.splitHours);
      if (genSplitLabEl) genSplitLabEl.value = String(s.splitLabHours);
      if (genLunchEl) genLunchEl.checked = !!s.keepLunch;
      syncPartitionToggle();
      if (genScopeEl) genScopeEl.textContent = genSettingsScopeText();
    }

    function readGenSettingsFromForm() {
      var hours = genSplitEl ? Number(genSplitEl.value) : generatorSettings.splitHours;
      var labHours = genSplitLabEl ? Number(genSplitLabEl.value) : generatorSettings.splitLabHours;
      var s = normalizeGenSettings({
        mergeMode: genMergeEl ? genMergeEl.value : generatorSettings.mergeMode,
        splitHours: hours,
        splitLabHours: labHours,
        keepLunch: genLunchEl ? genLunchEl.checked : generatorSettings.keepLunch,
        partitionScope: genPartitionEl && !genPartitionEl.disabled
          ? genPartitionEl.value
          : generatorSettings.partitionScope
      });
      generatorSettings = s;
      return s;
    }

    function toggleGenPanel() {
      if (!genPanel) return;
      var open = !!genPanel.hidden;
      genPanel.hidden = !open;
      if (genSettingsBtn) genSettingsBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        fillGenSettingsForm();
      }
    }

    function saveGenSettings() {
      if (!global.McuApi || !currentOffering) return;
      var settings = readGenSettingsFromForm();
      if (genSaveBtn) genSaveBtn.disabled = true;
      global.McuApi.send('POST', 'schedule.php', {
        action: 'save_generator_settings',
        actor: actor,
        workspace: workspace,
        page: pageKind,
        schoolYear: currentOffering.schoolYear,
        termCode: currentOffering.termCode,
        mergeMode: settings.mergeMode,
        splitHours: settings.splitHours,
        splitLabHours: settings.splitLabHours,
        keepLunch: settings.keepLunch,
        partitionScope: settings.partitionScope
      })
        .then(function (res) {
          if (genSaveBtn) genSaveBtn.disabled = false;
          applyPayload(res);
          render();
          setStatus('Generate settings saved.', 'ok');
          hideGenPanel();
        })
        .catch(function (err) {
          if (genSaveBtn) genSaveBtn.disabled = false;
          setStatus(err.message || String(err), 'bad');
        });
    }

    function classStudentCount(cls) {
      return membersOf(cls).reduce(function (n, m) {
        return n + (Number(m.studentCount) || 0);
      }, 0);
    }

    function groupSectionIds(cls) {
      return membersOf(cls).map(function (m) {
        return m.sectionId;
      });
    }

    // Each partition is its own group of students. The same group cannot take
    // two classes at once. A different group of the same section can.
    function laneOf(cls) {
      if (!isPartitioned(cls)) return '';
      return 'p:' + cls.sectionId + ':' + (Number(cls.partitionIndex) || 1);
    }

    function partitionsShareStudents(a, b) {
      var left = laneOf(a);
      var right = laneOf(b);
      if (!left || !right) return true;
      return left === right;
    }

    function keyedLane(cls) {
      var lanes = {};
      var lane = laneOf(cls);
      if (lane) lanes[cls.sectionId] = lane;
      return lanes;
    }

    function groupLanes(cls) {
      var lanes = {};
      membersOf(cls).forEach(function (m) {
        var lane = laneOf(m);
        if (lane) lanes[m.sectionId] = lane;
      });
      return lanes;
    }

    function groupMeetingIds(cls) {
      var ids = [];
      membersOf(cls).forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          ids.push(m.id);
        });
      });
      return ids;
    }

    function siblingMeetingIds(cls, meeting) {
      var ids = [];
      if (!meeting) return ids;
      membersOf(cls).forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (m.day === meeting.day && m.start === meeting.start) ids.push(m.id);
        });
      });
      return ids;
    }

    function classHoursMinutes(cls) {
      var total = 0;
      (cls.meetings || []).forEach(function (m) {
        total += minutesOf(m.end) - minutesOf(m.start);
      });
      return total;
    }

    function remainingHourMinutes(cls) {
      var req = Math.round((Number(cls.hoursPerWeek) || 0) * 60);
      return Math.max(0, req - classHoursMinutes(cls));
    }

    function rangesOverlapMin(a0, a1, b0, b1) {
      return a0 < b1 && b0 < a1;
    }

    function conflictClassLabel(cls, meet) {
      var bits = [
        (cls && cls.courseCode) || 'Class',
        (cls && cls.component) || '',
        '·',
        (cls && cls.sectionName) || 'section'
      ];
      if (meet) {
        bits.push('·', (meet.day || '') + ' ' + formatTimeRange(meet.start, meet.end));
        var roomBit = meetingRoomLabel(meet);
        if (roomBit) bits.push('·', roomBit);
      }
      return bits.filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    }

    function windowBlocked(day, start, end, sectionIds, roomId, ignoreIds, extraBusy, lanes) {
      var s = minutesOf(start);
      var e = minutesOf(end);
      if (e <= s) return 'End time must be after start time.';
      if (s < GRID_START || e > GRID_END) return 'Meetings must stay between 7:00am and 7:00pm.';
      var ignore = {};
      (ignoreIds || []).forEach(function (id) {
        ignore[id] = true;
      });
      var secs = {};
      (sectionIds || []).forEach(function (id) {
        secs[id] = true;
      });
      var extras = extraBusy || [];
      for (var i = 0; i < extras.length; i++) {
        var x = extras[i];
        if (x.day !== day) continue;
        if (rangesOverlapMin(s, e, minutesOf(x.start), minutesOf(x.end))) {
          return (
            'That time overlaps another meeting for this class. Conflict with: ' +
            (x.day || day) +
            ' ' +
            formatTimeRange(x.start, x.end)
          );
        }
      }
      for (var c = 0; c < classes.length; c++) {
        var other = classes[c];
        var sameSection = !!secs[other.sectionId];
        var meets = other.meetings || [];
        for (var m = 0; m < meets.length; m++) {
          var meet = meets[m];
          if (ignore[meet.id] || meet.day !== day) continue;
          if (!rangesOverlapMin(s, e, minutesOf(meet.start), minutesOf(meet.end))) continue;
          if (sameSection) {
            var lane = (lanes || {})[other.sectionId] || '';
            var otherLane = laneOf(other);
            if (!lane || !otherLane || lane === otherLane) {
              return (
                'That time overlaps another class for this section. Conflict with: ' +
                conflictClassLabel(other, meet)
              );
            }
          }
          if (roomId && meet.roomId === roomId) {
            return (
              'That room is already booked at this time. Conflict with: ' +
              conflictClassLabel(other, meet)
            );
          }
        }
      }
      return '';
    }

    function openWindowsFor(cls, roomId, durationMins, ignoreIds, extraBusy, preferDay) {
      var sectionIds = groupSectionIds(cls);
      var lanes = groupLanes(cls);
      var out = [];
      DAYS.forEach(function (day) {
        for (var start = GRID_START; start + durationMins <= GRID_END; start += SLOT) {
          var end = start + durationMins;
          if (
            !windowBlocked(day, hhmm(start), hhmm(end), sectionIds, roomId || '', ignoreIds, extraBusy, lanes)
          ) {
            out.push({ day: day, start: hhmm(start), end: hhmm(end) });
          }
        }
      });
      out.sort(function (a, b) {
        var pa = a.day === preferDay ? 0 : 1;
        var pb = b.day === preferDay ? 0 : 1;
        if (pa !== pb) return pa - pb;
        return dayOrder(a.day) - dayOrder(b.day) || minutesOf(a.start) - minutesOf(b.start);
      });
      return out;
    }

    function roomAlternatives(cls, durationMins, ignoreIds, extraBusy, excludeRoomId, preferDay) {
      if (!subjectNeedsCampusRoom(cls)) return [];
      var need = classStudentCount(cls);
      var typeName = cls.roomTypeName || '';
      var excludeBld = '';
      var current = roomById(excludeRoomId);
      if (current) excludeBld = current.buildingId;
      var cands = rooms.filter(function (r) {
        if (!roomIsAvailable(r)) return false;
        if (r.id === excludeRoomId) return false;
        if ((r.capacity || 0) < need) return false;
        if (typeName && r.typeName !== typeName) return false;
        return openWindowsFor(cls, r.id, durationMins, ignoreIds, extraBusy, preferDay).length > 0;
      });
      cands.sort(function (a, b) {
        var sa = a.buildingId === excludeBld ? 0 : 1;
        var sb = b.buildingId === excludeBld ? 0 : 1;
        if (sa !== sb) return sa - sb;
        return (a.capacity || 0) - (b.capacity || 0);
      });
      return cands.slice(0, 6).map(function (r) {
        return {
          room: r,
          sameBuilding: !!excludeBld && r.buildingId === excludeBld,
          windows: openWindowsFor(cls, r.id, durationMins, ignoreIds, extraBusy, preferDay).slice(0, 3)
        };
      });
    }

    function fillDurationSelect(sel, selected, maxMins, minMins) {
      if (!sel) return;
      minMins = minMins || SLOT;
      maxMins = maxMins || 6 * 60;
      if (maxMins < minMins) maxMins = minMins;
      sel.innerHTML = '';
      for (var m = minMins; m <= maxMins; m += SLOT) {
        sel.appendChild(new Option(formatHoursPhrase(m / 60), String(m)));
      }
      if (selected && Array.prototype.some.call(sel.options, function (o) { return o.value === String(selected); })) {
        sel.value = String(selected);
      } else if (sel.options.length) {
        sel.selectedIndex = 0;
      }
    }

    function closeRoomPreview() {
      var modal = qs('schedRoomPreviewModal');
      var grid = qs('schedRoomPreviewGrid');
      if (modal) modal.hidden = true;
      if (grid) grid.innerHTML = '';
    }

    function openRoomPreview(roomId) {
      var modal = qs('schedRoomPreviewModal');
      var grid = qs('schedRoomPreviewGrid');
      var title = qs('schedRoomPreviewTitle');
      var sub = qs('schedRoomPreviewSub');
      var room = roomById(roomId);
      if (!modal || !grid || !room) return;
      var label = room.label || room.code || 'Room';
      if (title) title.textContent = label;
      if (sub) {
        sub.textContent =
          (room.buildingName ? room.buildingName + ' · ' : '') +
          'Saved timetable for this room. Close it to keep editing the class.';
      }
      var highlightKey = '';
      if (editState && editState.cls) {
        var meet = meetingById(editState.cls, editState.meetingId);
        if (meet && (meet.roomId || '') === (room.id || '')) {
          highlightKey =
            (editState.cls.mergedClassId || editState.cls.id || '') +
            '|' +
            (meet.day || '') +
            '|' +
            (meet.start || '');
        }
      }
      grid.innerHTML = '';
      grid.appendChild(
        buildWeeklyGrid(blocksForRoom(room.id), {
          empty: 'This room has no classes in the saved schedule.',
          highlightKey: highlightKey
        })
      );
      modal.hidden = false;
    }

    function updateRoomLink(roomId) {
      var a = qs('schedEditRoomLink');
      if (!a) return;
      var room = roomById(roomId);
      if (!room || !room.buildingId) {
        a.hidden = true;
        a.onclick = null;
        return;
      }
      a.hidden = false;
      a.onclick = function (e) {
        e.preventDefault();
        openRoomPreview(room.id);
      };
    }

    function renderMeetingList(cls, selectedId) {
      var box = qs('schedEditMeetingList');
      if (!box) return;
      box.innerHTML = '';
      (cls.meetings || []).forEach(function (m) {
        var row = document.createElement('button');
        row.type = 'button';
        row.className = 'sched-meet-row' + (m.id === selectedId ? ' is-active' : '');
        row.innerHTML = '<span>' + escapeHtml(meetingLine(m)) + '</span>';
        if (m.id === selectedId) {
          row.disabled = true;
        } else {
          row.addEventListener('click', function () {
            openEditModal(cls, m);
          });
        }
        box.appendChild(row);
      });
      if (!(cls.meetings || []).length) {
        box.appendChild(emptyLine('No meetings yet. Add one to place this class.'));
      }
      var n = (cls.meetings || []).length;
      var selected = meetingById(cls, selectedId);
      var selectedMins = selected ? minutesOf(selected.end) - minutesOf(selected.start) : 0;
      if (qs('schedEditAdd')) qs('schedEditAdd').hidden = false;
      if (qs('schedEditCombine')) qs('schedEditCombine').hidden = n < 2;
      if (qs('schedEditRemove')) qs('schedEditRemove').hidden = n < 2;
      if (qs('schedEditSplit')) qs('schedEditSplit').hidden = !selected || selectedMins < 60;
    }

    function plannerIgnoreIds() {
      if (!editState || !editState.planner) return [];
      if (editState.planner.mode === 'combine') return groupMeetingIds(editState.cls);
      if (editState.planner.mode === 'split') {
        return siblingMeetingIds(editState.cls, meetingById(editState.cls, editState.meetingId));
      }
      return [];
    }

    function plannerExtraBusy() {
      if (!editState || !editState.planner || editState.planner.mode !== 'split') return [];
      var meeting = meetingById(editState.cls, editState.meetingId);
      var durEl = qs('schedEditPlannerDuration');
      if (!meeting || !durEl) return [];
      var dur = parseInt(durEl.value, 10) || 0;
      var orig = minutesOf(meeting.end) - minutesOf(meeting.start);
      var keep = orig - dur;
      if (keep < SLOT) return [];
      return [{ day: meeting.day, start: meeting.start, end: hhmm(minutesOf(meeting.start) + keep) }];
    }

    function preferDayForPlanner() {
      var meeting = editState && meetingById(editState.cls, editState.meetingId);
      if (!meeting) return 'Wed';
      if (editState.planner && editState.planner.mode === 'combine') return meeting.day;
      return PAIR_OF[meeting.day] || meeting.day;
    }

    function renderSlotButtons(host, windows, roomId) {
      host.innerHTML = '';
      var byDay = {};
      windows.forEach(function (w) {
        (byDay[w.day] = byDay[w.day] || []).push(w);
      });
      DAYS.forEach(function (day) {
        var list = byDay[day];
        if (!list) return;
        var wrap = document.createElement('div');
        wrap.className = 'sched-slot-day';
        wrap.innerHTML = '<strong>' + (DAY_FULL[day] || day) + '</strong>';
        var btns = document.createElement('div');
        btns.className = 'sched-slot-btns';
        list.forEach(function (w) {
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = formatClock(minutesOf(w.start)) + '–' + formatClock(minutesOf(w.end));
          b.addEventListener('click', function () {
            applyPlannerSlot(w, roomId);
          });
          btns.appendChild(b);
        });
        wrap.appendChild(btns);
        host.appendChild(wrap);
      });
    }

    function refreshPlanner() {
      if (!editState || !editState.planner) return;
      var cls = editState.cls;
      var mode = editState.planner.mode;
      var duration = parseInt(qs('schedEditPlannerDuration').value, 10) || SLOT;
      var roomId = qs('schedEditPlannerRoom').value || '';
      var ignore = plannerIgnoreIds();
      var extra = plannerExtraBusy();
      var prefer = preferDayForPlanner();
      var keep = qs('schedEditPlannerKeep');
      var status = qs('schedEditPlannerStatus');
      var slotHost = qs('schedEditPlannerSlots');
      var altHost = qs('schedEditPlannerAlts');
      if (keep) {
        if (mode === 'split') {
          var meeting = meetingById(cls, editState.meetingId);
          var orig = minutesOf(meeting.end) - minutesOf(meeting.start);
          var firstEnd = hhmm(minutesOf(meeting.start) + (orig - duration));
          keep.hidden = false;
          keep.textContent =
            'The first session will be ' +
            (DAY_FULL[meeting.day] || meeting.day) +
            ' ' +
            formatClock(minutesOf(meeting.start)) +
            '–' +
            formatClock(minutesOf(firstEnd)) +
            '. Pick a window for the other ' +
            formatHoursPhrase(duration / 60) +
            '.';
        } else {
          keep.hidden = true;
          keep.textContent = '';
        }
      }
      var got = classHoursMinutes(cls) + (mode === 'add' ? duration : 0);
      var hoursNote = hoursMessageForTotal(cls, got);
      var typeWarn = roomTypeWarning(cls, roomId);
      var capWarn = roomCapacityWarning(cls, roomId);
      var roomWarn = [typeWarn, capWarn].filter(Boolean).join(' ');
      var windows = openWindowsFor(cls, roomId, duration, ignore, extra, prefer);
      slotHost.innerHTML = '';
      altHost.innerHTML = '';
      if (windows.length) {
        if (status) {
          status.textContent = roomWarn ? hoursNote + ' ' + roomWarn : hoursNote;
          status.classList.toggle('is-warn', /add up to/.test(hoursNote) || !!roomWarn);
        }
        renderSlotButtons(slotHost, windows, roomId);
        return;
      }
      var room = roomById(roomId);
      var roomLabel = room ? room.label || room.code : 'Off-campus';
      if (status) {
        status.textContent =
          roomLabel +
          ' has no free ' +
          formatHoursPhrase(duration / 60) +
          ' window for these sections. ' +
          hoursNote;
        status.classList.add('is-warn');
      }
      var alts = roomAlternatives(cls, duration, ignore, extra, roomId, prefer);
      if (!alts.length) {
        var empty = document.createElement('p');
        empty.className = 'sched-edit-hours is-warn';
        empty.textContent = 'No other room of the right type has an open window either.';
        altHost.appendChild(empty);
        return;
      }
      var heading = document.createElement('p');
      heading.className = 'sched-edit-hours';
      heading.textContent = 'Other rooms with an open window:';
      altHost.appendChild(heading);
      alts.forEach(function (alt) {
        var card = document.createElement('div');
        card.className = 'sched-alt-card';
        var meta = alt.sameBuilding ? 'Same building' : alt.room.buildingName || '';
        if (alt.room.capacity) meta += (meta ? ' · ' : '') + 'cap ' + alt.room.capacity;
        card.innerHTML =
          '<strong>' +
          escapeHtml(alt.room.label || alt.room.code) +
          '</strong><em>' +
          escapeHtml(meta) +
          '</em>';
        var inner = document.createElement('div');
        inner.className = 'sched-slot-days';
        renderSlotButtons(inner, alt.windows, alt.room.id);
        card.appendChild(inner);
        altHost.appendChild(card);
      });
    }

    function openPlanner(mode) {
      if (!editState || !editState.cls) return;
      var cls = editState.cls;
      var meeting = meetingById(cls, editState.meetingId);
      var planner = qs('schedEditPlanner');
      var form = qs('schedEditMeetingForm');
      if (!planner) return;
      if (form) form.hidden = true;
      planner.hidden = false;
      if (qs('schedEditAdd')) qs('schedEditAdd').hidden = true;
      if (qs('schedEditCombine')) qs('schedEditCombine').hidden = true;
      editState.planner = { mode: mode };
      var titles = editClassLayout === 'jobs'
        ? { add: 'Add session', split: 'Split this session', combine: 'Combine sessions' }
        : { add: 'Add meeting', split: 'Split this meeting', combine: 'Combine meetings' };
      qs('schedEditPlannerTitle').textContent = titles[mode] || 'Place meeting';
      var dur = 60;
      var min = SLOT;
      var max = 6 * 60;
      if (mode === 'split' && meeting) {
        var orig = minutesOf(meeting.end) - minutesOf(meeting.start);
        max = orig - SLOT;
        dur = Math.max(SLOT, Math.floor(orig / (2 * SLOT)) * SLOT);
        if (dur > max) dur = max;
      } else if (mode === 'add') {
        var rem = remainingHourMinutes(cls);
        dur = rem >= SLOT ? rem : 60;
        if (dur > max) dur = max;
      } else if (mode === 'combine') {
        dur = classHoursMinutes(cls);
        min = dur;
        max = dur;
      }
      fillDurationSelect(qs('schedEditPlannerDuration'), dur, max, min);
      qs('schedEditPlannerDuration').disabled = mode === 'combine';
      setupRoomPicker('planner', cls, assignedMeetingRoomId(cls, meeting));
      setEditError('');
      refreshPlanner();
      syncEditPane('session');
      if (planner.scrollIntoView) {
        planner.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    }

    function closePlanner() {
      if (editState) editState.planner = null;
      if (qs('schedEditPlanner')) qs('schedEditPlanner').hidden = true;
      if (qs('schedEditMeetingForm')) qs('schedEditMeetingForm').hidden = !(editState && editState.meetingId);
      if (editState && editState.cls) renderMeetingList(editState.cls, editState.meetingId);
      syncEditPane('session');
    }

    function applyPlannerSlot(win, roomId) {
      if (!editState || !editState.planner || !global.McuApi) return;
      var mode = editState.planner.mode;
      var meeting = meetingById(editState.cls, editState.meetingId);
      withRoomConfirm(editState.cls, roomId || '', function () {
        withLunchConfirm(win.start, win.end, function () {
        if (mode === 'add') {
          postEdit('add_meeting', {
            offeringId: editState.offeringId,
            day: win.day,
            start: win.start,
            end: win.end,
            roomId: roomId || ''
          });
          return;
        }
        if (mode === 'split' && meeting) {
          var orig = minutesOf(meeting.end) - minutesOf(meeting.start);
          var dur = parseInt(qs('schedEditPlannerDuration').value, 10) || 0;
          postEdit('split_meeting', {
            meetingId: editState.meetingId,
            firstEnd: hhmm(minutesOf(meeting.start) + (orig - dur)),
            day: win.day,
            start: win.start,
            end: win.end,
            roomId: roomId || ''
          });
          return;
        }
        postEdit('combine_meetings', {
          offeringId: editState.offeringId,
          day: win.day,
          start: win.start,
          end: win.end,
          roomId: roomId || ''
        });
        });
      });
    }

    function postEdit(action, extra) {
      if (!global.McuApi) return;
      setEditError('');
      var body = {
        action: action,
        schoolYear: yearEl.value,
        termCode: semEl.value,
        actor: actor,
        workspace: workspace,
        page: pageKind,
        from: 'mine'
      };
      Object.keys(extra || {}).forEach(function (k) {
        body[k] = extra[k];
      });
      return global.McuApi.send('POST', 'schedule.php', body)
        .then(function (res) {
          applyPayload(res);
          render();
          if (action === 'unschedule_class') {
            showEditSaved('Unscheduled');
            closeEditModalSoon();
            setStatus('Class unscheduled.', 'ok');
            return res;
          }
          var oid = extra.offeringId || (editState && editState.offeringId);
          var cls = classById(oid);
          var mid = res.meetingId || extra.meetingId;
          if (cls) openEditModal(cls, meetingById(cls, mid));
          showEditSaved(editSavedLabel(action));
          setStatus('Schedule updated.', 'ok');
          return res;
        })
        .catch(function (err) {
          setEditError(err.message || String(err));
        });
    }

    function startAddMeeting() {
      openPlanner('add');
    }

    function startSplitMeeting() {
      var meeting = editState && meetingById(editState.cls, editState.meetingId);
      if (!meeting) return;
      if (minutesOf(meeting.end) - minutesOf(meeting.start) < 60) {
        setEditError('A meeting needs to be at least 1 hour before it can be split.');
        return;
      }
      openPlanner('split');
    }

    function startCombineMeetings() {
      if (!editState || !editState.cls) return;
      var cls = editState.cls;
      var meeting = meetingById(cls, editState.meetingId);
      if (!meeting || (cls.meetings || []).length < 2) {
        setEditError('There is only one meeting to keep.');
        return;
      }
      var total = classHoursMinutes(cls);
      var start = minutesOf(meeting.start);
      var end = start + total;
      var blocked =
        end > GRID_END ||
        windowBlocked(
          meeting.day,
          meeting.start,
          hhmm(end),
          groupSectionIds(cls),
          meeting.roomId || '',
          groupMeetingIds(cls),
          [],
          groupLanes(cls)
        );
      if (!blocked) {
        withRoomConfirm(cls, meeting.roomId || '', function () {
          withLunchConfirm(meeting.start, hhmm(end), function () {
          postEdit('combine_meetings', {
            offeringId: cls.id,
            day: meeting.day,
            start: meeting.start,
            end: hhmm(end),
            roomId: meeting.roomId || ''
          });
          });
        });
        return;
      }
      openPlanner('combine');
    }

    function removeSelectedMeeting() {
      if (!editState || !editState.meetingId) return;
      if ((editState.cls.meetings || []).length < 2) {
        setEditError('Keep at least one meeting. Combine or change times instead.');
        return;
      }
      postEdit('remove_meeting', { meetingId: editState.meetingId, offeringId: editState.offeringId });
    }

    function sectionStudentTotal(cls) {
      return Number(cls && (cls.sectionStudentCount || cls.studentCount)) || 0;
    }

    function sectionActionReady() {
      var action = editState && editState.sectionAction;
      if (!action) return false;
      if (action.kind === 'merge') return !!(qs('schedEditTarget') && qs('schedEditTarget').value);
      if (action.kind === 'partition') {
        var src = classById(action.offeringId);
        return partitionSizesValid(action.sizes, sectionStudentTotal(src));
      }
      return action.kind === 'remove' || action.kind === 'recombine';
    }

    function partitionSizesValid(sizes, total) {
      if (!sizes || sizes.length < 2 || sizes.length > 3) return false;
      var sum = 0;
      for (var i = 0; i < sizes.length; i++) {
        var n = Number(sizes[i]);
        if (!isFinite(n) || n < SECTION_PARTITION_MIN) return false;
        sum += n;
      }
      return sum === total;
    }

    function livePartitionSizes(sizes, index, value, total) {
      var next = sizes.slice();
      next[index] = value;
      var used = 0;
      for (var i = 0; i < next.length - 1; i++) used += Number(next[i]) || 0;
      next[next.length - 1] = total - used;
      return next;
    }

    function chooseSectionAction(m, kind) {
      if (!editState) return;
      var current = editState.sectionAction;
      if (current && current.offeringId === m.id && current.kind === kind) {
        editState.sectionAction = null;
      } else {
        editState.sectionAction = {
          offeringId: m.id,
          kind: kind,
          sizes: kind === 'partition' ? partitionSizes(sectionStudentTotal(m), 2) : null
        };
      }
      paintSectionAction();
      renderSectionEditors();
    }

    function paintSectionAction() {
      var action = editState && editState.sectionAction;
      document.querySelectorAll('#schedEditMemberList [data-section-action]').forEach(function (btn) {
        var on = !!(
          action &&
          btn.getAttribute('data-offering') === action.offeringId &&
          btn.getAttribute('data-section-action') === action.kind
        );
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    function renderSectionEditors() {
      var action = editState && editState.sectionAction;
      var targetWrap = qs('schedEditTargetWrap');
      var partWrap = qs('schedEditPartitionWrap');
      if (sectionActionLayout !== 'guided') {
        if (partWrap) partWrap.hidden = true;
        syncEditSave();
        return;
      }
      if (targetWrap) targetWrap.hidden = !(action && action.kind === 'merge');
      if (partWrap) partWrap.hidden = !(action && action.kind === 'partition');
      if (action && action.kind === 'partition') renderPartitionEditor();
      syncEditSave();
    }

    function renderPartitionEditor() {
      var action = editState && editState.sectionAction;
      var src = action && classById(action.offeringId);
      if (!src) return;
      var total = sectionStudentTotal(src);
      if (!action.sizes || action.sizes.length < 2) action.sizes = partitionSizes(total, 2);
      var hint = qs('schedEditPartitionHint');
      var valid = partitionSizesValid(action.sizes, total);
      if (hint) {
        hint.textContent =
          total +
          ' students. The first group keeps this timetable. Each group needs at least ' +
          SECTION_PARTITION_MIN +
          ' students, up to ' +
          partitionMaxParts(src) +
          ' groups.';
        hint.classList.toggle('is-warn', !valid);
      }
      var bar = qs('schedEditPartitionBar');
      if (bar) {
        bar.innerHTML = '';
        action.sizes.forEach(function (n, i) {
          var seg = document.createElement('span');
          seg.style.flex = String(Math.max(Number(n) || 0, 1));
          seg.textContent = String(n);
          seg.className = i === 0 ? 'is-stay' : i === 1 ? 'is-leave' : 'is-leave-2';
          bar.appendChild(seg);
        });
      }
      var host = qs('schedEditPartitionSizes');
      if (host) {
        host.innerHTML = '';
        action.sizes.forEach(function (n, i) {
          var row = document.createElement('div');
          row.className = 'sched-partition-size';
          var label = document.createElement('label');
          var inputId = 'schedPartSize' + i;
          label.setAttribute('for', inputId);
          label.textContent = i === 0 ? 'Stays in this class' : i === action.sizes.length - 1 && action.sizes.length > 2 ? 'Left over, leaves unscheduled' : 'Leaves unscheduled';
          var input = document.createElement('input');
          input.id = inputId;
          input.type = 'number';
          input.min = String(SECTION_PARTITION_MIN);
          input.step = '1';
          input.value = String(n);
          input.setAttribute('data-part-index', String(i));
          if (i === action.sizes.length - 1) input.readOnly = true;
          input.addEventListener('input', function () {
            var value = parseInt(input.value, 10);
            if (!isFinite(value)) return;
            action.sizes = livePartitionSizes(action.sizes, i, value, total);
            refreshPartitionLive();
          });
          row.appendChild(label);
          row.appendChild(input);
          host.appendChild(row);
        });
      }
      refreshPartitionControls();
    }

    function refreshPartitionLive() {
      var action = editState && editState.sectionAction;
      var src = action && classById(action.offeringId);
      if (!action || !src) return;
      var total = sectionStudentTotal(src);
      var hint = qs('schedEditPartitionHint');
      var valid = partitionSizesValid(action.sizes, total);
      if (hint) {
        hint.classList.toggle('is-warn', !valid);
        hint.textContent = valid
          ? total +
            ' students. The first group keeps this timetable. Each group needs at least ' +
            SECTION_PARTITION_MIN +
            ' students, up to ' +
            partitionMaxParts(src) +
            ' groups.'
          : 'Each group needs at least ' +
            SECTION_PARTITION_MIN +
            ' students, and together they must equal ' +
            total +
            '.';
      }
      var bar = qs('schedEditPartitionBar');
      if (bar) {
        Array.prototype.forEach.call(bar.children, function (seg, i) {
          var n = action.sizes[i];
          seg.style.flex = String(Math.max(Number(n) || 0, 1));
          seg.textContent = String(n);
        });
      }
      var last = qs('schedEditPartitionSizes') && qs('schedEditPartitionSizes').querySelector('[data-part-index="' + (action.sizes.length - 1) + '"]');
      if (last) last.value = String(action.sizes[action.sizes.length - 1]);
      refreshPartitionControls();
      syncEditSave();
    }

    function refreshPartitionControls() {
      var action = editState && editState.sectionAction;
      var src = action && classById(action.offeringId);
      var add = qs('schedEditPartitionAdd');
      var remove = qs('schedEditPartitionRemove');
      if (!action || !src) {
        if (add) add.hidden = true;
        if (remove) remove.hidden = true;
        return;
      }
      var last = Number(action.sizes[action.sizes.length - 1]) || 0;
      if (add) add.hidden = action.sizes.length >= partitionMaxParts(src) || last < SECTION_PARTITION_MIN * 2;
      if (remove) remove.hidden = action.sizes.length < 3;
    }

    function addPartitionSlice() {
      var action = editState && editState.sectionAction;
      var src = action && classById(action.offeringId);
      if (!action || !src || action.sizes.length >= partitionMaxParts(src)) return;
      var last = Number(action.sizes[action.sizes.length - 1]) || 0;
      if (last < SECTION_PARTITION_MIN * 2) return;
      action.sizes[action.sizes.length - 1] = last - SECTION_PARTITION_MIN;
      action.sizes.push(SECTION_PARTITION_MIN);
      renderPartitionEditor();
      syncEditSave();
    }

    function removePartitionSlice() {
      var action = editState && editState.sectionAction;
      if (!action || action.sizes.length < 3) return;
      var removed = action.sizes.pop();
      action.sizes[action.sizes.length - 1] = Number(action.sizes[action.sizes.length - 1]) + Number(removed);
      renderPartitionEditor();
      syncEditSave();
    }

    function saveSectionAction() {
      if (!sectionActionReady() || !editState || !editState.sectionAction) return;
      var action = editState.sectionAction;
      var src = classById(action.offeringId);
      if (!src) return;
      if (action.kind === 'merge') {
        confirmMergeSection(src, qs('schedEditTarget').value);
        return;
      }
      if (action.kind === 'remove') {
        confirmRemoveSection(src);
        return;
      }
      if (action.kind === 'recombine') {
        confirmRecombineSection(src);
        return;
      }
      if (action.kind === 'partition') confirmPartitionSection(src, action.sizes.slice());
    }

    function fillGuidedMemberRow(row, m, members, targets) {
      var actions = document.createElement('div');
      actions.className = 'sched-section-actions';
      function addBtn(kind, label) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'sched-btn sched-btn--ghost';
        btn.textContent = label;
        btn.setAttribute('data-section-action', kind);
        btn.setAttribute('data-offering', m.id);
        btn.setAttribute('aria-pressed', 'false');
        btn.addEventListener('click', function () {
          chooseSectionAction(m, kind);
        });
        actions.appendChild(btn);
      }
      if (members.length > 1) addBtn('remove', 'Remove');
      if (targets.length) addBtn('merge', 'Merge');
      if (isPartitioned(m)) addBtn('recombine', 'Recombine');
      else if (partitionMaxParts(m) >= 2) addBtn('partition', 'Partition');
      if (!actions.childNodes.length) return;
      row.appendChild(actions);
    }

    function fillMemberRow(row, m, members, targets) {
      if (editClassLayout !== 'jobs') {
        if (members.length > 1) {
          var unmerge = document.createElement('button');
          unmerge.type = 'button';
          unmerge.className = 'sched-link-btn';
          unmerge.textContent = 'Remove from class';
          unmerge.addEventListener('click', function () {
            reassignSection(m.id, '');
          });
          row.appendChild(unmerge);
        }
        if (targets.length) {
          var move = document.createElement('button');
          move.type = 'button';
          move.className = 'sched-link-btn';
          move.textContent = 'Merge into…';
          move.addEventListener('click', function () {
            var dest = qs('schedEditTarget').value;
            if (!dest) {
              setEditError('Choose a class to merge this section into.');
              return;
            }
            confirmMergeSection(m, dest);
          });
          row.appendChild(move);
        }
        if (isPartitioned(m)) {
          var rejoin = document.createElement('button');
          rejoin.type = 'button';
          rejoin.className = 'sched-link-btn';
          rejoin.textContent = 'Recombine';
          rejoin.addEventListener('click', function () {
            confirmRecombineSection(m);
          });
          row.appendChild(rejoin);
        } else if (targets.length) {
          for (var p = 2; p <= partitionMaxParts(m); p++) {
            row.appendChild(partitionButton(m, p));
          }
        }
        return;
      }
      if (sectionActionLayout === 'guided') {
        fillGuidedMemberRow(row, m, members, targets);
        return;
      }
      var actions = [];
      if (members.length > 1) actions.push({ value: 'remove', label: 'Remove from this class' });
      if (targets.length) actions.push({ value: 'merge', label: 'Merge into the class above' });
      if (isPartitioned(m)) actions.push({ value: 'recombine', label: 'Recombine' });
      else if (targets.length) {
        for (var part = 2; part <= partitionMaxParts(m); part++) {
          actions.push({ value: 'partition:' + part, label: 'Partition into ' + part });
        }
      }
      if (!actions.length) return;
      var sel = document.createElement('select');
      sel.className = 'sched-member-action';
      sel.setAttribute('aria-label', 'Action for ' + sectionLabel(m.sectionName, m.studentCount, m));
      sel.appendChild(new Option('Choose an action', ''));
      actions.forEach(function (action) {
        sel.appendChild(new Option(action.label, action.value));
      });
      var apply = document.createElement('button');
      apply.type = 'button';
      apply.className = 'sched-btn sched-btn--ghost';
      apply.textContent = 'Apply';
      apply.addEventListener('click', function () {
        var choice = sel.value;
        if (!choice) {
          setEditError('Choose an action for this section.');
          return;
        }
        if (choice === 'remove') {
          confirmRemoveSection(m);
          return;
        }
        if (choice === 'merge') {
          var dest = qs('schedEditTarget').value;
          if (!dest) {
            setEditError('Choose a class to merge this section into.');
            return;
          }
          confirmMergeSection(m, dest);
          return;
        }
        if (choice === 'recombine') {
          confirmRecombineSection(m);
          return;
        }
        if (choice.indexOf('partition:') === 0) {
          confirmPartitionSection(m, Number(choice.slice('partition:'.length)));
        }
      });
      row.appendChild(sel);
      row.appendChild(apply);
    }

    function syncEditPane(pane) {
      if (!editModal) return;
      if (editClassLayout !== 'jobs') {
        editModal.classList.remove('is-sections', 'is-planning');
        syncEditSave();
        return;
      }
      var planning = !!(editState && editState.planner);
      var showSections = pane === 'sections' && !planning && !!(editState && editState.hasSectionTools);
      editModal.classList.toggle('is-planning', planning);
      editModal.classList.toggle('is-sections', showSections);
      var views = qs('schedEditViews');
      if (views) views.hidden = planning || !(editState && editState.hasSectionTools);
      var sessionBtn = qs('schedEditViewSession');
      var sectionBtn = qs('schedEditViewSections');
      if (sessionBtn) {
        sessionBtn.classList.toggle('is-active', !showSections);
        sessionBtn.setAttribute('aria-pressed', !showSections ? 'true' : 'false');
      }
      if (sectionBtn) {
        sectionBtn.classList.toggle('is-active', showSections);
        sectionBtn.setAttribute('aria-pressed', showSections ? 'true' : 'false');
      }
      syncEditSave();
    }

    function syncEditSave() {
      var btn = qs('schedEditSave');
      var form = qs('schedEditMeetingForm');
      if (!btn) return;
      var planning = !!(editState && editState.planner);
      var sections = editClassLayout === 'jobs' && editModal && editModal.classList.contains('is-sections');
      if (sections && sectionActionLayout === 'guided') {
        btn.hidden = false;
        btn.disabled = !sectionActionReady();
        btn.title = btn.disabled ? 'Choose a section action first.' : '';
        return;
      }
      var formOpen = !!(form && !form.hidden);
      btn.hidden = !formOpen || planning || sections;
      if (!btn.hidden) {
        btn.disabled = false;
        btn.title = '';
      }
    }

    function mountEditLayout() {
      if (!editModal) return;
      editModal.classList.add(editClassLayout === 'jobs' ? 'sched-edit--jobs' : 'sched-edit--classic');
      if (editClassLayout !== 'jobs') return;
      var extra = qs('schedEditJobsExtra');
      if (!extra) return;
      ['schedEditAdd', 'schedEditSplit', 'schedEditCombine', 'schedEditRemove'].forEach(function (id) {
        var el = qs(id);
        if (el) extra.appendChild(el);
      });
      if (qs('schedEditAdd')) qs('schedEditAdd').textContent = 'Add session';
      if (qs('schedEditSplit')) qs('schedEditSplit').textContent = 'Split';
      if (qs('schedEditCombine')) qs('schedEditCombine').textContent = 'Combine';
      if (qs('schedEditRemove')) qs('schedEditRemove').textContent = 'Remove';
      if (qs('schedEditSave')) qs('schedEditSave').textContent = 'Save';
      var hint = qs('schedEditSectionsHint');
      if (hint && sectionActionLayout === 'menu') {
        hint.textContent = 'Choose one action for a section. Removing a section unschedules it.';
      }
    }

    function editMetaHtml(cls) {
      var kind = String(cls.component || '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
      if (kind) kind = kind.charAt(0).toUpperCase() + kind.slice(1);
      var sections = membersOf(cls).map(function (m) {
        return '<span>' + escapeHtml(sectionLabel(m.sectionName, m.studentCount, m)) + '</span>';
      }).join('');
      var facts = '';
      if (sections) {
        facts += '<dt>Sections</dt><dd class="sched-edit-meta-sections">' + sections + '</dd>';
      }
      facts += '<dt>In this class</dt><dd>' + escapeHtml(studentCountLabel(classStudentCount(cls))) + '</dd>';
      if (cls.roomTypeName) {
        facts += '<dt>Room type</dt><dd>' + escapeHtml(cls.roomTypeName) + '</dd>';
      }
      if (!subjectNeedsCampusRoom(cls)) {
        facts += '<dt>Campus room</dt><dd>Not used</dd>';
      }
      return (
        '<div class="sched-edit-meta-head"><strong>' +
        escapeHtml(cls.courseCode) +
        '</strong>' +
        (kind ? '<span class="sched-edit-meta-kind">' + escapeHtml(kind) + '</span>' : '') +
        (cls.mergedClassId ? '<span class="sched-edit-meta-badge">Merged</span>' : '') +
        '</div><p class="sched-edit-meta-title">' +
        escapeHtml(cls.courseTitle) +
        '</p><dl class="sched-edit-meta-facts">' +
        facts +
        '</dl>'
      );
    }

    function openEditModal(cls, meeting) {
      if (scheduleLocked() || !editModal || !cls) return;
      if (collegeLocked(cls.collegeId)) {
        setStatus(
          (cls.collegeName || 'This college') +
            ' is published and locked, so its classes can’t be edited. ' +
            (actor === 'super_admin' ? 'Unlock it in Colleges to fix a mistake.' : 'Ask the University Registrar to unlock it.'),
          'bad'
        );
        return;
      }
      editState = { offeringId: cls.id, meetingId: meeting && meeting.id, cls: cls, planner: null };
      var members = membersOf(cls);
      var targets = targetOptionsFor(cls);
      qs('schedEditTitle').textContent = 'Edit class';
      qs('schedEditMeta').innerHTML = editMetaHtml(cls);
      var meetBox = qs('schedEditMeeting');
      if (meetBox) meetBox.hidden = false;
      renderMeetingList(cls, meeting && meeting.id);
      var form = qs('schedEditMeetingForm');
      var planner = qs('schedEditPlanner');
      if (planner) planner.hidden = true;
      if (meeting) {
        if (form) form.hidden = false;
        qs('schedEditDay').value = meeting.day;
        fillTimeSelect(qs('schedEditStart'), meeting.start, { max: GRID_END - SLOT });
        fillTimeSelect(qs('schedEditEnd'), meeting.end, { min: GRID_START + SLOT });
        var assignedRoom = assignedMeetingRoomId(cls, meeting);
        setupRoomPicker('edit', cls, assignedRoom);
        updateRoomLink(assignedRoom);
      } else if (form) {
        form.hidden = true;
      }
      var secBox = qs('schedEditSections');
      var list = qs('schedEditMemberList');
      list.innerHTML = '';
      var canPartition = members.some(function (m) {
        return !isPartitioned(m) && partitionMaxParts(m) >= 2;
      });
      if (members.length > 1 || targets.length || isPartitioned(cls) || canPartition) {
        secBox.hidden = false;
        members.forEach(function (m) {
          var row = document.createElement('div');
          row.className = 'sched-member-row';
          row.innerHTML = '<span>' + escapeHtml(sectionLabel(m.sectionName, m.studentCount, m)) + '</span>';
          fillMemberRow(row, m, members, targets);
          list.appendChild(row);
        });
        var tgt = qs('schedEditTarget');
        tgt.innerHTML = '';
        tgt.appendChild(new Option('Choose a scheduled class…', ''));
        targets.forEach(function (t) {
          tgt.appendChild(new Option(t.label, t.id));
        });
        if (editClassLayout === 'jobs' && sectionActionLayout === 'guided') {
          qs('schedEditTargetWrap').hidden = true;
          if (qs('schedEditPartitionWrap')) qs('schedEditPartitionWrap').hidden = true;
        } else {
          qs('schedEditTargetWrap').hidden = !targets.length;
        }
      } else {
        secBox.hidden = true;
      }
      editState.hasSectionTools = !secBox.hidden;
      setEditError('');
      hideEditSaved();
      refreshEditHoursHint();
      syncEditPane('session');
      editModal.hidden = false;
    }






    function closeEditModal() {
      if (editCloseTimer) {
        clearTimeout(editCloseTimer);
        editCloseTimer = null;
      }
      hideEditSaved();
      closeRoomPreview();
      closeConfirmModal(false);
      if (editModal) {
        editModal.classList.remove('is-sections', 'is-planning');
        editModal.hidden = true;
      }
      editState = null;
    }

    var editSavedTimer = null;
    var editCloseTimer = null;

    function hideEditSaved() {
      var el = qs('schedEditSaved');
      if (el) el.classList.remove('is-on');
      if (editSavedTimer) {
        clearTimeout(editSavedTimer);
        editSavedTimer = null;
      }
    }

    function editSavedLabel(action) {
      if (action === 'add_meeting') return 'Session added';
      if (action === 'split_meeting') return 'Session split';
      if (action === 'combine_meetings') return 'Sessions combined';
      if (action === 'remove_meeting') return 'Session removed';
      if (action === 'partition_section') return 'Section partitioned';
      if (action === 'recombine_section') return 'Section recombined';
      return 'Saved';
    }

    function showEditSaved(message) {
      var el = qs('schedEditSaved');
      var text = qs('schedEditSavedText');
      if (!el) return;
      if (text) text.textContent = message || 'Saved';
      el.classList.remove('is-on');
      void el.offsetWidth;
      el.classList.add('is-on');
      if (editSavedTimer) clearTimeout(editSavedTimer);
      editSavedTimer = setTimeout(hideEditSaved, 1700);
    }

    function closeEditModalSoon() {
      if (editCloseTimer) clearTimeout(editCloseTimer);
      editCloseTimer = setTimeout(function () {
        editCloseTimer = null;
        closeEditModal();
      }, 1200);
    }

    function setEditError(text) {
      var el = qs('schedEditError');
      if (!el) return;
      el.textContent = text || '';
      if (text && el.scrollIntoView) {
        el.scrollIntoView({ block: 'nearest' });
      }
    }

    function saveMeeting() {
      if (!editState || !editState.meetingId || !global.McuApi) return;
      var start = qs('schedEditStart').value;
      var end = qs('schedEditEnd').value;
      var roomId = qs('schedEditRoom').value;
      setEditError('');
      refreshEditRoomHint();
      withRoomConfirm(editState.cls, roomId, function () {
      withLunchConfirm(start, end, function () {
      qs('schedEditSave').disabled = true;
      global.McuApi.send('POST', 'schedule.php', {
        action: 'move_meeting',
        actor: actor,
        workspace: workspace,
        page: pageKind,
        schoolYear: yearEl.value,
        termCode: semEl.value,
        meetingId: editState.meetingId,
        day: qs('schedEditDay').value,
        start: start,
        end: end,
        roomId: roomId
      })
        .then(function (res) {
          applyPayload(res);
          qs('schedEditSave').disabled = false;
          var cls = classById(editState.offeringId);
          setStatus('Meeting updated.', 'ok');
          render();
          if (cls) openEditModal(cls, meetingById(cls, editState.meetingId));
          showEditSaved('Saved');
        })
        .catch(function (err) {
          qs('schedEditSave').disabled = false;
          setEditError(err.message || String(err));
        });
      });
      });
    }

    function destRoomId(cls) {
      var rid = '';
      membersOf(cls).forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (!rid && m.roomId) rid = m.roomId;
        });
      });
      return rid;
    }

    function mergeIgnoreIds(src, dest) {
      var ids = [];
      membersOf(src).concat(membersOf(dest)).forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (m.id) ids.push(m.id);
        });
      });
      return ids;
    }

    function confirmMergeSection(src, destId) {
      var dest = classById(destId);
      if (!src || !dest) {
        setEditError('That class is no longer on the timetable.');
        return;
      }
      if (!(dest.meetings && dest.meetings.length)) {
        setEditError('Choose a class that is already scheduled.');
        return;
      }
      if (src.courseCode !== dest.courseCode || src.component !== dest.component) {
        setEditError('Only classes with the same course code and component can be merged.');
        return;
      }
      var destMembers = membersOf(dest);
      var combined = destMembers.reduce(function (n, c) {
        return n + (Number(c.studentCount) || 0);
      }, 0) + (Number(src.studentCount) || 0);
      var roomId = destRoomId(dest);
      var room = roomById(roomId);
      var overRooms = [];
      var seenRooms = {};
      destMembers.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (!m.roomId || seenRooms[m.roomId]) return;
          seenRooms[m.roomId] = true;
          var meetRoom = roomById(m.roomId);
          if (meetRoom && Number(meetRoom.capacity) > 0 && combined > Number(meetRoom.capacity)) {
            overRooms.push(meetRoom);
          }
        });
      });
      var when = sortedMeetings(dest.meetings)
        .map(function (m) {
          return (DAY_FULL[m.day] || m.day) + ' ' + formatTimeRange(m.start, m.end);
        })
        .join('; ');
      var roomLabel = room ? room.label || room.code : '';
      var destPeople = destMembers
        .map(function (c) {
          return sectionLabel(c.sectionName, c.studentCount, c);
        })
        .join(', ');
      var lines = [
        sectionLabel(src.sectionName, src.studentCount, src) +
          ' will join ' +
          destPeople +
          '.',
        src.courseCode +
          ' ' +
          src.component +
          ' will follow this class: ' +
          dest.courseCode +
          ' ' +
          dest.component +
          (when ? ', ' + when : '') +
          (roomLabel ? ' in ' + roomLabel : '') +
          '.',
        'Combined class: ' + combined + ' students' + (room && room.capacity ? ' (room holds ' + room.capacity + ')' : '') + '.'
      ];
      overRooms.forEach(function (meetRoom) {
        lines.push(
          'Warning: ' +
            (meetRoom.label || meetRoom.code) +
            ' holds ' +
            meetRoom.capacity +
            ' students. This merge would put ' +
            combined +
            ' students in that room.'
        );
      });
      var ignore = mergeIgnoreIds(src, dest);
      var clashes = [];
      dest.meetings.forEach(function (m) {
        var hit = windowBlocked(
          m.day,
          m.start,
          m.end,
          [src.sectionId],
          '',
          ignore,
          [],
          keyedLane(src)
        );
        if (hit && clashes.indexOf(hit) < 0) clashes.push(hit);
      });
      clashes.forEach(function (hit) {
        lines.push('Warning: ' + hit);
      });
      askConfirm({
        title: overRooms.length ? 'Merge into a room that is too small?' : 'Merge into this class?',
        message: lines.join('\n\n'),
        yes: overRooms.length ? 'Merge anyway' : 'Merge',
        no: 'Cancel'
      }).then(function (ok) {
        if (ok) reassignSection(src.id, dest.id);
      });
    }

    function partitionMaxParts(cls) {
      var total = Number(cls && (cls.sectionStudentCount || cls.studentCount)) || 0;
      return Math.min(3, Math.floor(total / SECTION_PARTITION_MIN));
    }

    function partitionSizes(total, parts) {
      var base = Math.floor(total / parts);
      var extra = total % parts;
      var sizes = [];
      for (var i = 0; i < parts; i++) {
        sizes.push(base + (i < extra ? 1 : 0));
      }
      return sizes;
    }

    function partitionButton(src, parts) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sched-link-btn';
      btn.textContent = 'Split in ' + parts;
      btn.addEventListener('click', function () {
        confirmPartitionSection(src, parts);
      });
      return btn;
    }

    function confirmPartitionSection(src, partsOrSizes) {
      var total = Number(src.sectionStudentCount || src.studentCount) || 0;
      var sizes = Array.isArray(partsOrSizes) ? partsOrSizes.slice() : partitionSizes(total, partsOrSizes);
      var parts = sizes.length;
      askConfirm({
        title: 'Partition this section?',
        message: [
          sizes[0] +
            ' students from ' +
            src.sectionName +
            ' stay in this ' +
            src.courseCode +
            ' ' +
            src.component +
            ' class and keep its timetable. ' +
            sizes.slice(1).join(' and ') +
            ' students leave unscheduled.',
          'Each group needs at least 5 students. One group can meet while another group of this section is in a different class. The same group cannot take two classes at once.'
        ].join('\n\n'),
        yes: 'Partition',
        no: 'Cancel'
      }).then(function (ok) {
        if (ok) {
          postPartitionChange(
            'partition_section',
            { offeringId: src.id, parts: parts, sizes: sizes },
            'Section partitioned.'
          );
        }
      });
    }

    function confirmRecombineSection(src) {
      var total = Number(src.sectionStudentCount || src.studentCount) || 0;
      var others = membersOf(src).reduce(function (n, m) {
        if (m.id === src.id) return n;
        return n + (Number(m.studentCount) || 0);
      }, 0);
      var seats = others + total;
      var room = roomById(destRoomId(src));
      var over = room && Number(room.capacity) > 0 && seats > Number(room.capacity);
      var lines = [
        src.sectionName +
          ' goes back to one class of ' +
          total +
          ' students for ' +
          src.courseCode +
          ' ' +
          src.component +
          '.',
        'The other partitions are removed and their students join this one.'
      ];
      if (over) {
        lines.push(
          'Warning: ' +
            (room.label || room.code) +
            ' holds ' +
            room.capacity +
            ' students. This class would have ' +
            seats +
            '.'
        );
      }
      askConfirm({
        title: over ? 'Recombine into a room that is too small?' : 'Recombine this section?',
        message: lines.join('\n\n'),
        yes: over ? 'Recombine anyway' : 'Recombine',
        no: 'Cancel'
      }).then(function (ok) {
        if (ok) postPartitionChange('recombine_section', { offeringId: src.id }, 'Section recombined.');
      });
    }

    function postPartitionChange(action, extra, okText) {
      if (!global.McuApi) return;
      setEditError('');
      var body = {
        action: action,
        actor: actor,
        workspace: workspace,
        page: pageKind,
        schoolYear: yearEl.value,
        termCode: semEl.value
      };
      Object.keys(extra).forEach(function (k) {
        body[k] = extra[k];
      });
      global.McuApi.send('POST', 'schedule.php', body)
        .then(function (res) {
          applyPayload(res);
          render();
          showEditSaved(okText.replace(/\.$/, ''));
          closeEditModalSoon();
          setStatus(okText, 'ok');
        })
        .catch(function (err) {
          setEditError(err.message || String(err));
        });
    }

    function confirmRemoveSection(src) {
      var label = sectionLabel(src.sectionName, src.studentCount, src);
      askConfirm({
        title: 'Remove this section?',
        message:
          label +
          ' will leave the merged class and become unscheduled. Its meetings are removed. Place it later with Add session, or merge it into another class.',
        yes: 'Remove',
        no: 'Cancel'
      }).then(function (ok) {
        if (ok) reassignSection(src.id, '');
      });
    }

    function reassignSection(offeringId, targetOfferingId) {
      if (!global.McuApi) return;
      setEditError('');
      global.McuApi.send('POST', 'schedule.php', {
        action: 'reassign_section',
        actor: actor,
        workspace: workspace,
        page: pageKind,
        schoolYear: yearEl.value,
        termCode: semEl.value,
        offeringId: offeringId,
        targetOfferingId: targetOfferingId || ''
      })
        .then(function (res) {
          applyPayload(res);
          render();
          showEditSaved(targetOfferingId ? 'Section merged' : 'Section removed');
          closeEditModalSoon();
          setStatus(
            targetOfferingId ? 'Section merged into the other class.' : 'Section removed from the merged class.',
            'ok'
          );
        })
        .catch(function (err) {
          setEditError(err.message || String(err));
        });
    }

    function showProposal(res) {
      runId = res.runId;
      if (!modal || !modalBody) return;
      var p = res.proposal || {};
      var lines = (p.members || [])
        .map(function (m) {
          return sectionLabel(m.name, m.studentCount) + ' · ' + m.collegeName;
        })
        .join('\n');
      modalBody.innerHTML =
        '<p>Merge <strong>' +
        escapeHtml(p.courseCode) +
        '</strong> — ' +
        escapeHtml(p.courseTitle) +
        ' <em>' +
        escapeHtml(p.component) +
        '</em>?</p>' +
        '<p>' +
        p.combinedCount +
        ' students' +
        (p.roomCode ? ' in ' + escapeHtml(p.roomCode) + ' (cap ' + p.roomCapacity + ')' : '') +
        (p.sameCollege ? '. Same college.' : '. Includes another college.') +
        '</p><pre class="sched-merge-list">' +
        escapeHtml(lines) +
        '</pre>' +
        '<p class="page-sub">' +
        (res.remaining || 1) +
        ' merge decision' +
        ((res.remaining || 1) === 1 ? '' : 's') +
        ' remaining.</p>';
      modal.hidden = false;
    }

    function handleGenerateResult(res) {
      if (runBtn) runBtn.disabled = false;
      if (remainingBtn) remainingBtn.disabled = false;
      if (res.status === 'needs_merge') {
        setStatus('Confirm this merge, then scheduling continues.', 'warn');
        showProposal(res);
        return;
      }
      if (modal) modal.hidden = true;
      applyPayload(res);
      if (res.remainingOnly && masterDetail && classes.some(function (c) { return !classIsScheduled(c) && !collegeLocked(c.collegeId); })) {
        // e.g. classes whose section isn't on the Sections page: the server has nothing to build them from
        placeRemainingInPage(null, 'Placed');
        return;
      }
      var s = res.summary || {};
      var msg = res.remainingOnly
        ? 'Scheduled remaining classes where rooms were free. ' +
          s.scheduled +
          ' of ' +
          s.offerings +
          ' classes are placed.'
        : 'Placed ' + s.scheduled + ' of ' + s.offerings + ' classes.';
      setStatus(msg, s.unscheduled ? 'warn' : 'ok');
      render();
    }

    function newSectionsForTerm() {
      if (!global.SectionsStore || !(sectionsKnown || global.SectionsStore.currentTerm())) return [];
      var ids = {};
      var names = {};
      classes.forEach(function (c) {
        if (c.sectionId) ids[c.sectionId] = true;
        if (c.sectionName) names[c.sectionName] = true;
      });
      return global.SectionsStore.list().filter(function (s) {
        return !ids[s.id] && !names[s.name] && !collegeLocked(s.collegeId);
      });
    }

    function noSectionsForTerm() {
      return (
        !!global.SectionsStore &&
        (sectionsKnown || !!global.SectionsStore.currentTerm()) &&
        !global.SectionsStore.list().length
      );
    }

    function lunchOverlap(start, end) {
      return lunchKeepOn() && rangesOverlapMin(minutesOf(start), minutesOf(end), 12 * 60, 13 * 60);
    }

    // first free day and time in a room the class may use (type, size, college); lunch stays free
    function findSpotFor(cls, durationMins, avoidDay) {
      var candidates = subjectNeedsCampusRoom(cls)
        ? rooms
            .filter(function (r) {
              if (!roomIsAvailable(r)) return false;
              if ((r.capacity || 0) < classStudentCount(cls)) return false;
              if (cls.roomTypeName && !sameRoomType(r.typeName, cls.roomTypeName)) return false;
              return roomOpenToCollege(r, cls.collegeId);
            })
            .sort(function (a, b) {
              return (a.capacity || 0) - (b.capacity || 0);
            })
        : [{ id: '' }];
      for (var i = 0; i < candidates.length; i++) {
        var wins = openWindowsFor(cls, candidates[i].id, durationMins, [], [], '').filter(function (w) {
          return w.day !== avoidDay && !lunchOverlap(w.start, w.end);
        });
        if (wins.length) return { roomId: candidates[i].id, win: wins[0] };
      }
      return null;
    }

    // Schedule remaining without sections: the server can't build its job list, so the
    // page puts each unplaced class in the first free spot, one at a time
    function placeRemainingInPage(onlyIds, donePrefix, onDone) {
      var todo = [];
      var seen = {};
      classes.forEach(function (c) {
        if (classIsScheduled(c) || collegeLocked(c.collegeId)) return;
        if (onlyIds && onlyIds.indexOf(c.id) < 0) return;
        var key = c.mergedClassId || c.id;
        if (seen[key]) return;
        seen[key] = true;
        todo.push(c.id);
      });
      if (!todo.length) {
        setStatus('There are no unscheduled classes to place.', 'muted');
        if (onDone) onDone(0, []);
        return;
      }
      if (remainingBtn) remainingBtn.disabled = true;
      setStatus('Scheduling remaining classes…');
      var placedCount = 0;
      var stuck = [];
      function splitMinutesFor(cls) {
        var hours = cls && String(cls.component) === 'laboratory'
          ? generatorSettings && generatorSettings.splitLabHours
          : generatorSettings && generatorSettings.splitHours;
        return (Number(hours) || 3) * 60;
      }
      function post(offeringId, spot) {
        return global.McuApi.send('POST', 'schedule.php', {
          action: 'add_meeting',
          schoolYear: yearEl.value,
          termCode: semEl.value,
          actor: actor,
          workspace: workspace,
          page: pageKind,
          from: 'mine',
          offeringId: offeringId,
          day: spot.win.day,
          start: spot.win.start,
          end: spot.win.end,
          roomId: spot.roomId || ''
        }).then(applyPayload);
      }
      function next(i) {
        if (i >= todo.length) {
          if (remainingBtn) remainingBtn.disabled = false;
          render();
          var msg = placedCount
            ? (donePrefix || 'Placed') + ' ' + placedCount + (placedCount === 1 ? ' class' : ' classes') + ' in free rooms.'
            : 'No class could be placed.';
          if (stuck.length) {
            msg += ' No free room and time for: ' + stuck.join(', ') + '. Place ' + (stuck.length === 1 ? 'it' : 'them') + ' with Edit.';
          }
          setStatus(msg, stuck.length ? 'warn' : 'ok');
          if (onDone) onDone(placedCount, stuck);
          return;
        }
        var cls = classById(todo[i]);
        if (!cls) return next(i + 1);
        var need = remainingHourMinutes(cls) || Math.round((Number(cls.hoursPerWeek) || 0) * 60);
        var label = cls.courseCode + ' ' + cls.component + ' (' + (cls.sectionName || '') + ')';
        if (!need) return next(i + 1);
        // long subjects go in two sessions on different days, like Generate does
        var half = Math.ceil(need / 2 / SLOT) * SLOT;
        var parts = need > splitMinutesFor(cls) ? [half, need - half] : [need];
        var first = findSpotFor(cls, parts[0], '');
        if (!first) {
          stuck.push(label);
          return next(i + 1);
        }
        post(cls.id, first)
          .then(function () {
            if (parts.length < 2) return;
            var again = classById(todo[i]);
            var second = again && findSpotFor(again, parts[1], first.win.day);
            if (second) return post(cls.id, second);
            stuck.push(label + ', second session');
          })
          .then(function () {
            placedCount++;
            next(i + 1);
          })
          .catch(function (err) {
            stuck.push(label + ' (' + ((err && err.message) || 'error') + ')');
            next(i + 1);
          });
      }
      next(0);
    }

    function clashPairs() {
      var list = [];
      classes.forEach(function (c) {
        (c.meetings || []).forEach(function (m) {
          if (DAYS.indexOf(m.day) >= 0) list.push({ c: c, m: m, s: minutesOf(m.start), e: minutesOf(m.end) });
        });
      });
      var out = [];
      for (var i = 0; i < list.length; i++) {
        for (var j = i + 1; j < list.length; j++) {
          var a = list[i];
          var b = list[j];
          if (a.m.day !== b.m.day || a.c.id === b.c.id) continue;
          if (a.c.mergedClassId && a.c.mergedClassId === b.c.mergedClassId) continue;
          if (!rangesOverlapMin(a.s, a.e, b.s, b.e)) continue;
          var sameRoom = !!a.m.roomId && a.m.roomId === b.m.roomId;
          var sameSection =
            !!a.c.sectionId && a.c.sectionId === b.c.sectionId && partitionsShareStudents(a.c, b.c);
          if (sameRoom || sameSection) out.push([a.c, b.c]);
        }
      }
      return out;
    }

    // plan from the Colleges panel: { move: 'mine' | 'theirs', colleges: [ids just approved] }
    function resolveApprovalClashes(plan, round, movedSoFar) {
      round = round || 1;
      movedSoFar = movedSoFar || 0;
      var approved = plan.colleges || [];
      var movers = {};
      clashPairs().forEach(function (pair) {
        var aIn = approved.indexOf(pair[0].collegeId) >= 0;
        var bIn = approved.indexOf(pair[1].collegeId) >= 0;
        if (aIn === bIn) return;
        var incoming = aIn ? pair[0] : pair[1];
        var other = aIn ? pair[1] : pair[0];
        var mover = plan.move === 'theirs' ? incoming : other;
        if (collegeLocked(mover.collegeId)) mover = mover === incoming ? other : incoming;
        if (!collegeLocked(mover.collegeId)) movers[mover.id] = true;
      });
      var ids = Object.keys(movers);
      if (!ids.length) {
        setStatus(movedSoFar ? 'Moved ' + movedSoFar + (movedSoFar === 1 ? ' class' : ' classes') + ' to free rooms. No clashes left.' : 'No clashes to fix.', 'ok');
        return;
      }
      if (round > 3) {
        setStatus('Moved ' + movedSoFar + ' classes. ' + ids.length + ' still clash; fix them with Needs attention.', 'warn');
        return;
      }
      setStatus('Moving ' + ids.length + (ids.length === 1 ? ' class' : ' classes') + ' that clashed…');
      var i = 0;
      (function unplaceNext() {
        if (i >= ids.length) {
          // check again after placing: a class can only land in a free spot, but the
          // first pass may not have seen every pair
          placeRemainingInPage(ids, 'Moved', function (placed) {
            resolveApprovalClashes(plan, round + 1, movedSoFar + placed);
          });
          return;
        }
        var id = ids[i++];
        global.McuApi.send('POST', 'schedule.php', {
          action: 'unschedule_class',
          schoolYear: yearEl.value,
          termCode: semEl.value,
          actor: actor,
          workspace: 'mine',
          page: pageKind,
          from: 'mine',
          offeringId: id
        })
          .then(applyPayload)
          .catch(function () {})
          .then(unplaceNext);
      })();
    }

    function takeResolvePlan() {
      try {
        var raw = global.sessionStorage.getItem('mcu.resolveClashes');
        if (!raw) return null;
        global.sessionStorage.removeItem('mcu.resolveClashes');
        return JSON.parse(raw);
      } catch (err) {
        return null;
      }
    }

    function generate(onlyUnscheduled) {
      if (scheduleLocked() || !currentOffering) return;
      if (!global.McuApi) {
        setStatus('The database is not connected.', 'bad');
        return;
      }
      if (workspace !== 'mine') {
        setStatus('Generate only runs on Working timetable.', 'bad');
        return;
      }
      var remaining = !!onlyUnscheduled;
      if (remaining) {
        var hasUnscheduled = classes.some(function (c) {
          return !(c.meetings && c.meetings.length);
        });
        if (!hasUnscheduled && !(masterDetail && newSectionsForTerm().length)) {
          setStatus('There are no unscheduled classes to place.', 'muted');
          return;
        }
      }
      if (remaining && masterDetail && noSectionsForTerm()) {
        placeRemainingInPage();
        return;
      }
      var settings = readGenSettingsFromForm();
      var start = function () {
        hideGenPanel();
        if (runBtn) runBtn.disabled = true;
        if (remainingBtn) remainingBtn.disabled = true;
        setStatus(remaining ? 'Scheduling remaining classes…' : 'Generating schedule…');
        var payload = {
          action: 'generate',
          actor: actor,
          workspace: workspace,
          page: pageKind,
          schoolYear: currentOffering.schoolYear,
          termCode: currentOffering.termCode,
          mergeMode: settings.mergeMode,
          splitHours: settings.splitHours,
          splitLabHours: settings.splitLabHours,
          keepLunch: settings.keepLunch,
          partitionScope: settings.partitionScope,
          collegeId: generateCollegeId(),
          departmentId: generateDepartmentId(),
          yearLevel: ylEl ? ylEl.value : ''
        };
        if (remaining) payload.onlyUnscheduled = true;
        global.McuApi.send('POST', 'schedule.php', payload)
          .then(handleGenerateResult)
          .catch(function (err) {
            if (runBtn) runBtn.disabled = false;
            if (remainingBtn) remainingBtn.disabled = false;
            var msg = err.message || String(err);
            if (/no sections found/i.test(msg)) {
              msg =
                'There are no sections for ' +
                offeringTitle(currentOffering.schoolYear, currentOffering.termCode) +
                ' yet, so there’s nothing to schedule. Add the sections in Data management → Sections, then try again.';
            }
            setStatus(msg, 'bad');
          });
      };
      start();
    }

    function confirmUnscheduleClass(cls) {
      if (!cls || scheduleLocked()) return;
      var label =
        (cls.courseCode || 'Class') +
        ' ' +
        (cls.component || '') +
        ' · ' +
        sectionLabel(cls.sectionName, cls.studentCount, cls);
      askConfirm({
        title: 'Unschedule?',
        message:
          label +
          ' will become unscheduled. Other sections in a merged class keep their timetable.' +
          (cls.mergedClassId ? ' This section leaves the merge.' : ''),
        yes: 'Unschedule',
        no: 'Cancel'
      }).then(function (ok) {
        if (!ok) return;
        postEdit('unschedule_class', { offeringId: cls.id });
      });
    }

    function decide(accept) {
      if (!runId || !global.McuApi) return;
      modalYes.disabled = true;
      modalNo.disabled = true;
      global.McuApi.send('POST', 'schedule.php', {
        action: 'decide',
        actor: actor,
        workspace: workspace,
        page: pageKind,
        schoolYear: yearEl.value,
        termCode: semEl.value,
        runId: runId,
        accept: accept
      })
        .then(function (res) {
          modalYes.disabled = false;
          modalNo.disabled = false;
          handleGenerateResult(res);
        })
        .catch(function (err) {
          modalYes.disabled = false;
          modalNo.disabled = false;
          setStatus(err.message || String(err), 'bad');
        });
    }

    document.querySelectorAll('[data-sched-view]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = btn.getAttribute('data-sched-view');
        if (next === 'rooms' && primaryView !== 'rooms') {
          selectedBuilding = '';
          selectedRoom = '';
          showView('rooms', defaultRoomLayout());
          return;
        }
        showView(next, layout);
      });
    });
    document.querySelectorAll('[data-sched-layout]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.getAttribute('data-sched-layout') === 'table') {
          selectedBuilding = '';
          selectedRoom = '';
        }
        showView(primaryView, btn.getAttribute('data-sched-layout'));
      });
    });
    if (colEl) {
      colEl.addEventListener('change', function () {
        fillDepartments();
        if (primaryView === 'sections') render();
      });
    }
    if (depEl) {
      depEl.addEventListener('change', function () {
        if (primaryView === 'sections') render();
      });
    }
    if (ylEl) {
      ylEl.addEventListener('change', function () {
        if (primaryView === 'sections') render();
      });
    }
    if (sectionStatusEl) {
      sectionStatusEl.addEventListener('change', function () {
        if (primaryView === 'sections') render();
      });
    }
    if (roomBuildingEl) {
      roomBuildingEl.addEventListener('change', function () {
        fillRoomFloorFilter();
        showView('rooms', 'table');
      });
    }
    if (roomFloorEl) {
      roomFloorEl.addEventListener('change', function () {
        showView('rooms', 'table');
      });
    }
    if (roomTypeEl) {
      roomTypeEl.addEventListener('change', function () {
        showView('rooms', 'table');
      });
    }
    if (classSearchEl) {
      classSearchEl.addEventListener('input', function () {
        if (primaryView === 'classes') renderClasses();
      });
    }
    if (classCollegeEl) {
      classCollegeEl.addEventListener('change', function () {
        if (primaryView === 'classes') renderClasses();
      });
    }
    if (classComponentEl) {
      classComponentEl.addEventListener('change', function () {
        if (primaryView === 'classes') renderClasses();
      });
    }
    if (classStatusEl) {
      classStatusEl.addEventListener('change', function () {
        if (primaryView === 'classes') renderClasses();
      });
    }
    if (classYearLevelEl) {
      classYearLevelEl.addEventListener('change', function () {
        if (primaryView === 'classes') renderClasses();
      });
    }
    if (classBuildingEl) {
      classBuildingEl.addEventListener('change', function () {
        if (primaryView === 'classes') renderClasses();
      });
    }
    if (classMergedEl) {
      classMergedEl.addEventListener('change', function () {
        if (primaryView === 'classes') renderClasses();
      });
    }
    if (segActive) {
      segActive.addEventListener('click', function () {
        setOfferingPane('active');
      });
    }
    if (segArchived) {
      segArchived.addEventListener('click', function () {
        setOfferingPane('archived');
      });
    }
    document.querySelectorAll('[data-ws-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setWorkspace(btn.getAttribute('data-ws-tab'));
      });
    });
    if (qs('schedSaveAsConfirm')) qs('schedSaveAsConfirm').addEventListener('click', confirmSaveAs);
    if (qs('schedSaveAsCancel')) qs('schedSaveAsCancel').addEventListener('click', closeSaveAsModal);
    if (qs('schedSaveAsModal')) {
      qs('schedSaveAsModal').addEventListener('click', function (e) {
        if (e.target === qs('schedSaveAsModal')) closeSaveAsModal();
      });
    }
    if (qs('schedSaveAsName')) {
      qs('schedSaveAsName').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          confirmSaveAs();
        }
      });
    }
    if (qs('schedOpenSaveMine')) {
      qs('schedOpenSaveMine').addEventListener('click', function () {
        openSaveInto();
      });
    }
    if (qs('schedOpenSaveCancel')) qs('schedOpenSaveCancel').addEventListener('click', closeOpenSaveModal);
    if (qs('schedOpenSaveModal')) {
      qs('schedOpenSaveModal').addEventListener('click', function (e) {
        if (e.target === qs('schedOpenSaveModal')) closeOpenSaveModal();
      });
    }
    if (backArchivedBtn) {
      backArchivedBtn.addEventListener('click', function () {
        archivedPick = null;
        selectedVersion = '';
        fillYears();
        showView(primaryView, layout);
        loadSchedule();
      });
    }
    if (archiveBtn) {
      archiveBtn.addEventListener('click', function () {
        if (!currentOffering || !global.SectionsStore) return;
        var title = offeringTitle(currentOffering.schoolYear, currentOffering.termCode);
        askConfirm({
          title: 'Archive this offering?',
          message:
            'Archive ' +
            title +
            '? This files the sections and timetable together. They stay under Archived and can be restored later.',
          yes: 'Archive',
          no: 'Cancel',
          yesClass: 'sched-btn--archive'
        }).then(function (ok) {
          if (!ok) return;
          archiveBtn.disabled = true;
          global.SectionsStore.archiveTerm(currentOffering.schoolYear, currentOffering.termCode).then(function (res) {
            archiveBtn.disabled = false;
            if (!res.ok) {
              setStatus(res.error || 'Could not archive this offering.', 'bad');
              return;
            }
            currentOffering = null;
            classes = [];
            offeringPane = 'archived';
            archivedPick = null;
            selectedVersion = '';
            if (usesWorkspace) workspace = 'archives';
            loadSchedule();
          });
        });
      });
    }
    // the Colleges panel (schedule-colleges.js) changed approvals or locks
    document.addEventListener('mcu:schedule-reload', function () {
      loadSchedule();
    });
    document.addEventListener('mcu:colleges-changed', function () {
      render();
    });
    yearEl.addEventListener('change', function () {
      selectedVersion = '';
      if (pageKind === 'report') reportYear = yearEl.value;
      loadSchedule();
    });
    global.addEventListener('scroll', function (e) {
      var panel = qs('schedRoomHover');
      if (panel && e.target && (e.target === panel || (panel.contains && panel.contains(e.target)))) {
        return;
      }
      hideRoomHover();
    }, true);
    global.addEventListener('resize', hideRoomHover);
    semEl.addEventListener('change', function () {
      selectedVersion = '';
      if (pageKind === 'report') reportSem = semEl.value;
      loadSchedule();
    });
    if (qs('schedReady')) {
      qs('schedReady').addEventListener('click', function () {
        if (actor !== 'admin') return;
        var pub = publication || {};
        var msg =
          pub.status === 'pending_super_admin'
            ? 'Replace the package University Registrar is reviewing with the current Create schedule timetable for this whole campus term?'
            : 'Send the current Create schedule timetable for this whole campus term to the University Registrar?';
        askConfirm({ title: 'Send to the University Registrar?', message: msg, yes: 'Send', no: 'Cancel' }).then(function (ok) {
          if (ok) runPublication('ready_for_super_admin');
        });
      });
    }
    if (qs('schedApprove')) {
      qs('schedApprove').addEventListener('click', function () {
        if (actor === 'vpaa') {
          askConfirm({
            title: 'Publish this timetable?',
            message: 'Publish this campus timetable for deans and program chairs?',
            yes: 'Publish',
            no: 'Cancel'
          }).then(function (ok) {
            if (ok) runPublication('vpaa_decide', { accept: true });
          });
          return;
        }
        var pub = publication || {};
        var msg;
        if (pub.returnedFromVpaa) {
          msg = pub.workingDiffersFromSubmitted
            ? 'Send the package the VPAA returned, not the live timetable?'
            : 'Send this package to the VPAA again?';
        } else {
          msg = pub.workingDiffersFromSubmitted
            ? 'Send Records Officer’s submitted snapshot to the VPAA? The live timetable has changed since they submitted.'
            : 'Approve the package Records Officer submitted and send it to the VPAA?';
        }
        askConfirm({
          title: 'Send to VPAA?',
          message: msg,
          yes: 'Send to VPAA',
          no: 'Cancel'
        }).then(function (ok) {
          if (!ok) return;
          runPublication('super_admin_decide', { accept: true });
        });
      });
    }
    if (qs('schedSendLive')) {
      qs('schedSendLive').addEventListener('click', function () {
        if (actor !== 'super_admin') return;
        var pub = publication || {};
        var differs = !!pub.workingDiffersFromSubmitted && pub.status === 'pending_super_admin';
        var msg;
        if (pub.returnedFromVpaa && differs) {
          msg = 'Send the live timetable to the VPAA instead of the package they returned? This freezes Create schedule as it is now.';
        } else if (differs) {
          msg = 'Send the live timetable to the VPAA instead of Records Officer’s submitted snapshot? This freezes Create schedule as it is now.';
        } else {
          msg = 'Send the current live timetable to the VPAA? Records Officer does not need to submit first. This freezes Create schedule as it is now.';
        }
        askConfirm({
          title: 'Send live timetable to VPAA?',
          message: msg,
          yes: 'Send live timetable',
          no: 'Cancel'
        }).then(function (ok) {
          if (!ok) return;
          runPublication('super_admin_send_live');
        });
      });
    }
    if (qs('schedReject')) {
      qs('schedReject').addEventListener('click', openRejectModal);
    }
    if (qs('schedRejectCancel')) {
      qs('schedRejectCancel').addEventListener('click', closeRejectModal);
    }
    if (qs('schedRejectSend')) {
      qs('schedRejectSend').addEventListener('click', function () {
        var note = qs('schedRejectNote') ? String(qs('schedRejectNote').value || '').trim() : '';
        if (!note) {
          setStatus(
            actor === 'vpaa' ? 'Say what University Registrar should revise.' : 'Say what the Records Officer should revise.',
            'bad'
          );
          return;
        }
        closeRejectModal();
        runPublication(actor === 'vpaa' ? 'vpaa_decide' : 'super_admin_decide', { accept: false, note: note });
      });
    }
    if (qs('schedRejectModal')) {
      qs('schedRejectModal').addEventListener('click', function (e) {
        if (e.target === qs('schedRejectModal')) closeRejectModal();
      });
    }
    if (runBtn) runBtn.addEventListener('click', function () {
      generate(false);
    });
    if (remainingBtn) {
      remainingBtn.addEventListener('click', function () {
        generate(true);
      });
    }
    if (newSectionsBtn) {
      newSectionsBtn.addEventListener('click', function () {
        generate(true);
      });
    }
    if (genSettingsBtn) genSettingsBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      toggleGenPanel();
    });
    if (genSaveBtn) genSaveBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      saveGenSettings();
    });
    if (genMergeEl) {
      genMergeEl.addEventListener('change', syncPartitionToggle);
    }
    if (genPartitionEl) {
      genPartitionEl.addEventListener('change', function () {
        if (!genPartitionEl.disabled) {
          generatorSettings.partitionScope = genPartitionEl.value;
        }
      });
    }
    var genInfo = qs('schedGenInfo');
    var genInfoBtn = qs('schedGenInfoBtn');
    var genInfoPop = qs('schedGenInfoPop');
    if (genInfoBtn && genInfo) {
      genInfoBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = !genInfo.classList.contains('is-open');
        genInfo.classList.toggle('is-open', open);
        genInfoBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    if (genInfoPop) {
      genInfoPop.addEventListener('mousedown', function (e) {
        e.preventDefault();
      });
    }
    document.querySelectorAll('[data-gen-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        hideGenPanel();
        if (genSettingsBtn) genSettingsBtn.focus();
      });
    });
    if (genPanel) {
      genPanel.addEventListener('click', function (e) {
        e.stopPropagation();
        if (genInfo && !genInfo.contains(e.target)) hideGenInfo();
      });
    }
    document.addEventListener('click', function (e) {
      if (!genPanel || genPanel.hidden || !genSettingsWrap) return;
      if (genSettingsWrap.contains(e.target)) return;
      hideGenPanel();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') hideGenPanel();
    });
    if (printBtn) {
      printBtn.addEventListener('click', function () {
        window.print();
      });
    }
    if (modalYes) modalYes.addEventListener('click', function () { decide(true); });
    if (modalNo) modalNo.addEventListener('click', function () { decide(false); });
    if (qs('schedEditSave')) {
      qs('schedEditSave').addEventListener('click', function () {
        if (
          sectionActionLayout === 'guided' &&
          editModal &&
          editModal.classList.contains('is-sections')
        ) {
          saveSectionAction();
          return;
        }
        saveMeeting();
      });
    }
    if (qs('schedEditTarget')) qs('schedEditTarget').addEventListener('change', syncEditSave);
    if (qs('schedEditPartitionAdd')) qs('schedEditPartitionAdd').addEventListener('click', addPartitionSlice);
    if (qs('schedEditPartitionRemove')) qs('schedEditPartitionRemove').addEventListener('click', removePartitionSlice);
    if (qs('schedEditCancel')) qs('schedEditCancel').addEventListener('click', closeEditModal);
    if (qs('schedEditAdd')) qs('schedEditAdd').addEventListener('click', startAddMeeting);
    if (qs('schedEditSplit')) qs('schedEditSplit').addEventListener('click', startSplitMeeting);
    if (qs('schedEditCombine')) qs('schedEditCombine').addEventListener('click', startCombineMeetings);
    if (qs('schedEditRemove')) qs('schedEditRemove').addEventListener('click', removeSelectedMeeting);
    if (qs('schedEditPlannerCancel')) qs('schedEditPlannerCancel').addEventListener('click', closePlanner);
    if (qs('schedEditViewSession')) {
      qs('schedEditViewSession').addEventListener('click', function () {
        syncEditPane('session');
      });
    }
    if (qs('schedEditViewSections')) {
      qs('schedEditViewSections').addEventListener('click', function () {
        syncEditPane('sections');
      });
    }
    mountEditLayout();
    ['schedEditDay', 'schedEditStart', 'schedEditEnd'].forEach(function (id) {
      if (qs(id)) qs(id).addEventListener('change', refreshEditHoursHint);
    });
    if (qs('schedEditRoom')) {
      qs('schedEditRoom').addEventListener('change', function () {
        updateRoomLink(qs('schedEditRoom').value);
        refreshEditRoomHint();
      });
    }
    ['schedEditRoomBuilding', 'schedEditRoomTypeFilter'].forEach(function (id) {
      var el = qs(id);
      if (!el) return;
      el.addEventListener('change', function () {
        refillRoomPicker('edit');
      });
    });
    ['schedEditPlannerRoomBuilding', 'schedEditPlannerRoomTypeFilter'].forEach(function (id) {
      var el = qs(id);
      if (!el) return;
      el.addEventListener('change', function () {
        refillRoomPicker('planner');
      });
    });
    if (qs('schedConfirmYes')) qs('schedConfirmYes').addEventListener('click', function () { closeConfirmModal(true); });
    if (qs('schedConfirmNo')) qs('schedConfirmNo').addEventListener('click', function () { closeConfirmModal(false); });
    if (qs('schedConfirmModal')) {
      qs('schedConfirmModal').addEventListener('click', function (e) {
        if (e.target === qs('schedConfirmModal')) closeConfirmModal(false);
      });
    }
    if (qs('schedEditPlannerDuration')) qs('schedEditPlannerDuration').addEventListener('change', refreshPlanner);
    if (qs('schedEditPlannerRoom')) qs('schedEditPlannerRoom').addEventListener('change', refreshPlanner);
    if (qs('schedRoomPreviewClose')) qs('schedRoomPreviewClose').addEventListener('click', closeRoomPreview);
    if (qs('schedRoomPreviewModal')) {
      qs('schedRoomPreviewModal').addEventListener('click', function (e) {
        if (e.target === qs('schedRoomPreviewModal')) closeRoomPreview();
      });
    }
    if (editModal) {
      editModal.addEventListener('click', function (e) {
        if (e.target === editModal) closeEditModal();
      });
    }
    if (findEl) {
      var findTimer = null;
      findEl.addEventListener('input', function () {
        clearTimeout(findTimer);
        findTimer = setTimeout(render, 150);
      });
    }
    if (qs('schedFiltersBtn') && workspaceEl) {
      qs('schedFiltersBtn').addEventListener('click', function () {
        var open = !workspaceEl.classList.contains('is-filters-open');
        workspaceEl.classList.toggle('is-filters-open', open);
        this.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    if (attentionBtn) {
      attentionBtn.addEventListener('click', function () {
        attentionOnly = !attentionOnly;
        render();
      });
    }
    document.addEventListener('click', function (e) {
      if (scheduleLocked()) return;
      var unscheduleBtn = e.target.closest && e.target.closest('[data-unschedule-offering]');
      if (unscheduleBtn) {
        var drop = classById(unscheduleBtn.getAttribute('data-unschedule-offering'));
        if (drop) confirmUnscheduleClass(drop);
        return;
      }
      var btn = e.target.closest && e.target.closest('[data-edit-offering]');
      if (!btn) return;
      var cls = classById(btn.getAttribute('data-edit-offering'));
      if (!cls) return;
      openEditModal(cls, meetingById(cls, btn.getAttribute('data-edit-meeting')));
    });

    applyHash();
    fillYears();
    fillColleges();
    syncWorkspaceTabs();
    showView(primaryView, layout);
    loadSchedule();
    if (global.OrgStore) {
      global.OrgStore.onChange(function () {
        fillColleges();
        fillClassCollegeFilter();
      });
    }
    if (global.SectionsStore) {
      global.SectionsStore.onChange(function () {
        sectionsKnown = true;
        loadSchedule();
      });
    }
  });
})(typeof window !== 'undefined' ? window : this);
