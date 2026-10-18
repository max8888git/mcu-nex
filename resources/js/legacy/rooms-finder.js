/**
 * Rooms available: find a free room for a day and time.
 *
 * Rooms (building, floor, type, capacity, status) come from Facilities
 * (FacilitiesStore). Bookings are the active term's class meetings from
 * GET api/room-availability.php (Create schedule's timetable). A room is free
 * when it isn't under maintenance and has no class overlapping the chosen time.
 *
 * Filters live in the URL (?day=tue&start=13:00&end=15:00&bldg=lrc&type=...&seats=40)
 * so a search can be bookmarked or shared.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var root = doc.getElementById('rfPage');
  if (!root) return;

  var DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
  var DAY_LONG = { mon: 'Monday', tue: 'Tuesday', wed: 'Wednesday', thu: 'Thursday', fri: 'Friday', sat: 'Saturday' };
  var DAY_API = { mon: 'Mon', tue: 'Tue', wed: 'Wed', thu: 'Thu', fri: 'Fri', sat: 'Sat' };
  var OPEN = 7 * 60;   // timeline 7:00 AM
  var CLOSE = 21 * 60; // to 9:00 PM

  var rooms = [];
  var bookingsByRoom = {};
  var term = null;
  var published = null;
  var bookingsLoaded = false;
  var bookingsFailed = false;
  // `state` is what the list shows; `draft` is what the filters say until Show is pressed
  var state = { day: '', start: '', end: '', bldg: '', type: '', seats: '', booked: false, repair: false };
  var draft = {};

  function $(id) { return doc.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function toMin(hhmm) {
    var m = /^(\d{1,2}):(\d{2})$/.exec(String(hhmm || ''));
    return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null;
  }

  function fmt(min) {
    var h = Math.floor(min / 60);
    var m = min % 60;
    var ap = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12 || 12;
    return h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
  }

  function fmtRange(a, b) {
    return fmt(a) + '–' + fmt(b);
  }

  function hhmm(min) {
    var h = Math.floor(min / 60);
    var m = min % 60;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
  }

  // older links used ?day=monday and ?start=7:00 AM
  function normDay(v) {
    v = String(v || '').toLowerCase().slice(0, 3);
    return DAYS.indexOf(v) !== -1 ? v : '';
  }

  function normTime(v) {
    v = String(v || '').trim();
    if (/^\d{1,2}:\d{2}$/.test(v)) return hhmm(toMin(v));
    var m = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec(v);
    if (!m) return '';
    var h = parseInt(m[1], 10) % 12 + (/pm/i.test(m[3]) ? 12 : 0);
    return hhmm(h * 60 + parseInt(m[2], 10));
  }

  function today() {
    var d = new Date().getDay(); // 0 Sun
    return d >= 1 && d <= 6 ? DAYS[d - 1] : 'mon';
  }

  function naturalCompare(a, b) {
    return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
  }

  // ----- data -----

  function loadRooms() {
    var F = global.FacilitiesStore;
    var snap = F ? F.snapshot() : null;
    rooms = [];
    if (!snap) return;
    (snap.buildingOrder || []).forEach(function (bkey, bIndex) {
      if (snap.buildingAvailable && snap.buildingAvailable[bkey] === false) return;
      (snap.roomsByBuilding[bkey] || []).forEach(function (rid) {
        var alloc = F.roomGet(snap.roomAllocation, bkey, rid) || {};
        var status = String(F.roomGet(snap.roomStatus, bkey, rid) || 'active');
        var cap = parseInt(alloc.capacity, 10);
        var college = '';
        if (alloc.college && global.OrgStore && global.OrgStore.collegeName) college = global.OrgStore.collegeName(alloc.college) || '';
        rooms.push({
          key: (bkey + '::' + rid).toLowerCase(),
          bkey: bkey,
          bIndex: bIndex,
          code: rid,
          building: (snap.buildingNames || {})[bkey] || bkey,
          floor: snap.buildingUsesFloors && snap.buildingUsesFloors[bkey] === false ? null : F.roomGet(snap.roomFloorById, bkey, rid),
          type: alloc.type || '',
          capacity: isNaN(cap) ? null : cap,
          repair: status === 'under_maintenance',
          college: college,
        });
      });
    });
  }

  function loadBookings() {
    if (!global.McuApi) return Promise.resolve();
    return global.McuApi.get('room-availability.php').then(function (res) {
      term = res.term || null;
      published = res.published || null;
      bookingsByRoom = {};
      (res.bookings || []).forEach(function (b) {
        var key = (b.building + '::' + b.room).toLowerCase();
        var day = String(b.day || '').toLowerCase().slice(0, 3);
        var s = toMin(b.start);
        var e = toMin(b.end);
        if (s == null || e == null || e <= s) return;
        (bookingsByRoom[key] = bookingsByRoom[key] || {});
        (bookingsByRoom[key][day] = bookingsByRoom[key][day] || []).push({
          start: s, end: e, course: b.course, title: b.title, section: b.section, component: b.component, students: b.students,
        });
      });
      Object.keys(bookingsByRoom).forEach(function (k) {
        Object.keys(bookingsByRoom[k]).forEach(function (d) {
          bookingsByRoom[k][d].sort(function (a, b) { return a.start - b.start; });
        });
      });
      bookingsLoaded = true;
    }, function () {
      bookingsFailed = true;
    });
  }

  function dayBookings(room, day) {
    return (bookingsByRoom[room.key] || {})[day] || [];
  }

  function freeGaps(list) {
    var gaps = [];
    var at = OPEN;
    list.forEach(function (b) {
      if (b.start > at) gaps.push([at, Math.min(b.start, CLOSE)]);
      at = Math.max(at, b.end);
    });
    if (at < CLOSE) gaps.push([at, CLOSE]);
    return gaps.filter(function (g) { return g[1] - g[0] >= 30; });
  }

  // ----- filters -----

  function readUrl() {
    var p = new URLSearchParams(global.location.search);
    state.day = normDay(p.get('day')) || today();
    state.start = normTime(p.get('start'));
    state.end = normTime(p.get('end'));
    state.bldg = p.get('bldg') && p.get('bldg') !== 'all' ? p.get('bldg') : '';
    state.type = p.get('type') || '';
    state.seats = /^\d+$/.test(p.get('seats') || '') ? p.get('seats') : '';
    state.booked = p.get('booked') === '1';
    state.repair = p.get('repair') === '1';
  }

  function writeUrl() {
    var p = new URLSearchParams();
    p.set('day', state.day);
    if (state.start) p.set('start', state.start);
    if (state.end) p.set('end', state.end);
    if (state.bldg) p.set('bldg', state.bldg);
    if (state.type) p.set('type', state.type);
    if (state.seats) p.set('seats', state.seats);
    if (state.booked) p.set('booked', '1');
    if (state.repair) p.set('repair', '1');
    global.history.replaceState(null, '', global.location.pathname + '?' + p.toString());
  }

  function fillControls() {
    draft = Object.assign({}, state);
    Array.prototype.forEach.call(doc.querySelectorAll('[data-rf-day]'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-rf-day') === state.day ? 'true' : 'false');
    });
    var times = '<option value="">Any time</option>';
    for (var m = OPEN; m <= CLOSE; m += 30) times += '<option value="' + hhmm(m) + '">' + fmt(m) + '</option>';
    $('rfStart').innerHTML = times;
    $('rfEnd').innerHTML = times;
    $('rfStart').value = state.start;
    $('rfEnd').value = state.end;

    var bsel = $('rfBldg');
    var seen = {};
    bsel.innerHTML = '<option value="">All buildings</option>' + rooms.filter(function (r) {
      if (seen[r.bkey]) return false;
      seen[r.bkey] = true;
      return true;
    }).map(function (r) { return '<option value="' + esc(r.bkey) + '">' + esc(r.building) + '</option>'; }).join('');
    bsel.value = state.bldg;

    var types = {};
    rooms.forEach(function (r) { if (r.type) types[r.type] = true; });
    $('rfType').innerHTML = '<option value="">All room types</option>' + Object.keys(types).sort().map(function (t) {
      return '<option value="' + esc(t) + '">' + esc(t) + '</option>';
    }).join('');
    $('rfType').value = state.type;
    $('rfSeats').value = state.seats;
    $('rfShowBooked').checked = state.booked;
    $('rfShowRepair').checked = state.repair;
  }

  function window_(f) {
    f = f || state;
    var s = toMin(f.start);
    var e = toMin(f.end);
    if (s == null && e == null) return null;
    if (s == null) s = OPEN;
    if (e == null) e = CLOSE;
    return { start: s, end: e };
  }

  // "Admin Building 101" (several buildings have a 101); a room already named after its building keeps its name
  function roomName(r) {
    var code = String(r.code || '');
    var bldg = String(r.building || '');
    return bldg && code.toLowerCase().indexOf(bldg.toLowerCase()) !== 0 ? bldg + ' ' + code : code;
  }

  // ----- render -----

  function timeline(room, win) {
    var span = CLOSE - OPEN;
    var blocks = dayBookings(room, state.day).map(function (b) {
      var l = (Math.max(b.start, OPEN) - OPEN) / span * 100;
      var w = (Math.min(b.end, CLOSE) - Math.max(b.start, OPEN)) / span * 100;
      return '<span class="rf-tl-busy" style="left:' + l + '%;width:' + w + '%" title="' + esc(fmtRange(b.start, b.end) + ' · ' + b.course) + '"></span>';
    }).join('');
    var sel = win
      ? '<span class="rf-tl-want" style="left:' + ((win.start - OPEN) / span * 100) + '%;width:' + ((win.end - win.start) / span * 100) + '%"></span>'
      : '';
    return '<div class="rf-tl" aria-hidden="true">' + blocks + sel + '</div>' +
      '<div class="rf-tl-scale" aria-hidden="true"><span>7 AM</span><span>12 PM</span><span>5 PM</span><span>9 PM</span></div>';
  }

  function evaluate(room, win) {
    var list = dayBookings(room, state.day);
    if (room.repair) return { kind: 'repair', list: list };
    if (win) {
      var clash = list.filter(function (b) { return b.start < win.end && b.end > win.start; });
      return { kind: clash.length ? 'booked' : 'free', clash: clash, list: list };
    }
    return { kind: list.length ? 'partly' : 'free', list: list };
  }

  function describe(ev, win) {
    if (ev.kind === 'repair') return 'Under maintenance, can’t be booked';
    if (ev.kind === 'booked') {
      var b = ev.clash[0];
      return 'Booked ' + fmtRange(b.start, b.end) + ' · ' + b.course + (b.section ? ' (' + b.section + ')' : '') +
        (ev.clash.length > 1 ? ' and ' + (ev.clash.length - 1) + ' more' : '');
    }
    if (!ev.list.length) return 'Free all day';
    if (win) return 'Free for this time · ' + ev.list.length + (ev.list.length === 1 ? ' class' : ' classes') + ' at other times';
    return 'Free ' + freeGaps(ev.list).map(function (g) { return fmtRange(g[0], g[1]); }).join(', ');
  }

  function render() {
    var win = window_();
    var err = $('rfTimeError');
    var bad = win && win.end <= win.start;
    err.hidden = !bad;
    $('rfEnd').toggleAttribute('aria-invalid', !!bad);
    var list = $('rfResults');
    list.removeAttribute('aria-busy');
    if (bad) {
      list.innerHTML = '';
      $('rfSummary').textContent = '';
      return;
    }

    var seats = parseInt(state.seats, 10) || 0;
    var matches = rooms.filter(function (r) {
      if (state.bldg && r.bkey !== state.bldg) return false;
      if (state.type && r.type !== state.type) return false;
      if (seats && (r.capacity == null || r.capacity < seats)) return false;
      return true;
    }).map(function (r) { return { room: r, ev: evaluate(r, win) }; });

    // with no time chosen, "free" means no classes at all that day
    var free = matches.filter(function (m) { return m.ev.kind === 'free'; });
    var shown = matches.filter(function (m) {
      if (m.ev.kind === 'repair') return state.repair;
      if (m.ev.kind === 'booked') return state.booked;
      return true;
    });
    var rank = { free: 0, partly: 1, booked: 2, repair: 3 };
    shown.sort(function (a, b) {
      return rank[a.ev.kind] - rank[b.ev.kind] || a.room.bIndex - b.room.bIndex || naturalCompare(a.room.code, b.room.code);
    });

    var when = DAY_LONG[state.day] + (win ? ', ' + fmtRange(win.start, win.end) : '');
    $('rfSummary').innerHTML = '<strong>' + free.length + '</strong> of ' + matches.length +
      (matches.length === 1 ? ' room' : ' rooms') + (win ? ' free on ' : ' free all day on ') + esc(when) +
      (seats ? ' with at least ' + seats + ' seats' : '');

    if (!rooms.length) {
      list.innerHTML = '<div class="ui-empty"><h3>No rooms yet</h3><p>Add buildings and rooms in Data management, Facilities. They’ll show up here.</p></div>';
      return;
    }
    if (!shown.length) {
      list.innerHTML = '<div class="ui-empty"><h3>No free rooms match</h3><p>Try another time, fewer seats, or a different building. You can also turn on “Show booked rooms”.</p></div>';
      return;
    }

    list.innerHTML = shown.map(function (m) {
      var r = m.room;
      var ev = m.ev;
      var chip = { free: ['ui-chip--done', 'Free'], partly: ['ui-chip--done', 'Free now'], booked: ['ui-chip--waiting', 'Booked'], repair: ['ui-chip--back', 'Maintenance'] }[ev.kind];
      if (ev.kind === 'partly') chip = ['ui-chip--neutral', ev.list.length + (ev.list.length === 1 ? ' class' : ' classes')];
      var meta = [r.building, r.floor != null ? 'Floor ' + r.floor : '', r.type, r.capacity != null ? r.capacity + ' seats' : 'Seats not set'].filter(Boolean);
      return '<li class="rf-card' + (ev.kind === 'booked' || ev.kind === 'repair' ? ' is-muted' : '') + '">' +
        '<div class="rf-card-head"><h3 class="rf-room">' + esc(roomName(r)) + '</h3><span class="ui-chip ' + chip[0] + '">' + esc(chip[1]) + '</span></div>' +
        '<p class="rf-meta">' + meta.slice(1).map(esc).join(' · ') + '</p>' +
        (r.college ? '<p class="rf-college">Reserved for ' + esc(r.college) + '</p>' : '') +
        timeline(r, win) +
        '<p class="rf-status rf-status--' + ev.kind + '">' + esc(describe(ev, win)) + '</p>' +
        '<button type="button" class="ui-btn rf-view" data-rf-room="' + esc(r.key) + '">View week<span class="ui-sr"> for ' + esc(roomName(r)) + '</span></button>' +
        '</li>';
    }).join('');
  }

  function renderTermNote() {
    var note = $('rfTermNote');
    if (bookingsFailed) {
      note.hidden = false;
      note.className = 'rf-note rf-note--warn';
      note.textContent = 'Class schedules couldn’t load, so rooms show only their Facilities status. Refresh to try again.';
      return;
    }
    if (!bookingsLoaded) return;
    $('rfTerm').textContent = term ? term.label : '';
    var any = Object.keys(bookingsByRoom).length > 0;
    note.hidden = any;
    if (!any) {
      note.className = 'rf-note';
      note.textContent = !term
        ? 'There’s no active term yet, so no class bookings are shown.'
        : !published
          ? 'No timetable has been published for ' + term.label + ' yet, so every room shows as free. Rooms fill up once the VPAA approves a timetable.'
          : 'The published timetable for ' + term.label + ' has no classes with rooms, so every room shows as free.';
    }
  }

  // ----- week dialog -----

  function openWeek(key) {
    var room = rooms.filter(function (r) { return r.key === key; })[0];
    if (!room) return;
    var d = $('rfDialog');
    $('rfDialogTitle').textContent = roomName(room);
    $('rfDialogMeta').textContent = [room.floor != null ? 'Floor ' + room.floor : '', room.type, room.capacity != null ? room.capacity + ' seats' : '',
      room.college ? 'Reserved for ' + room.college : '', room.repair ? 'Under maintenance' : ''].filter(Boolean).join(' · ');
    $('rfWeek').innerHTML = DAYS.map(function (day) {
      var list = dayBookings(room, day);
      return '<div class="rf-day' + (day === state.day ? ' is-current' : '') + '"><h3 class="rf-day-name">' + DAY_LONG[day] + '</h3>' +
        (list.length
          ? '<ul class="rf-day-list">' + list.map(function (b) {
              return '<li><span class="rf-day-time">' + esc(fmtRange(b.start, b.end)) + '</span><span><strong>' + esc(b.course) + '</strong> ' +
                esc(b.title || '') + (b.section ? ' · ' + esc(b.section) : '') + (b.students ? ' · ' + b.students + ' students' : '') + '</span></li>';
            }).join('') + '</ul>' +
            '<p class="rf-day-free">Free: ' + (freeGaps(list).map(function (g) { return esc(fmtRange(g[0], g[1])); }).join(', ') || 'none') + '</p>'
          : '<p class="rf-day-free">Free all day</p>') +
        '</div>';
    }).join('');
    d.showModal();
  }

  // ----- wiring -----

  function changed() {
    var keys = ['day', 'start', 'end', 'bldg', 'type', 'seats', 'booked', 'repair'];
    return keys.some(function (k) { return String(draft[k]) !== String(state[k]); });
  }

  // the filters changed but the list doesn't show them yet
  function markPending() {
    var pending = changed();
    $('rfShow').classList.toggle('is-pending', pending);
    $('rfShow').textContent = pending ? 'Show results' : 'Show';
    var win = window_(draft);
    var bad = win && win.end <= win.start;
    $('rfTimeError').hidden = !bad;
    $('rfEnd').toggleAttribute('aria-invalid', !!bad);
  }

  function setDraft(patch) {
    for (var k in patch) if (Object.prototype.hasOwnProperty.call(patch, k)) draft[k] = patch[k];
    $('rfNowHint').hidden = true;
    markPending();
  }

  function show() {
    var win = window_(draft);
    if (win && win.end <= win.start) {
      markPending();
      $('rfEnd').focus();
      return;
    }
    state = Object.assign({}, draft);
    writeUrl();
    render();
    markPending();
  }

  Array.prototype.forEach.call(doc.querySelectorAll('[data-rf-day]'), function (b) {
    b.addEventListener('click', function () {
      Array.prototype.forEach.call(doc.querySelectorAll('[data-rf-day]'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      setDraft({ day: b.getAttribute('data-rf-day') });
    });
  });
  $('rfStart').addEventListener('change', function (e) { setDraft({ start: e.target.value }); });
  $('rfEnd').addEventListener('change', function (e) { setDraft({ end: e.target.value }); });
  $('rfBldg').addEventListener('change', function (e) { setDraft({ bldg: e.target.value }); });
  $('rfType').addEventListener('change', function (e) { setDraft({ type: e.target.value }); });
  $('rfSeats').addEventListener('input', function (e) {
    var clean = e.target.value.replace(/\D/g, '');
    if (clean !== e.target.value) e.target.value = clean;
    setDraft({ seats: clean });
  });
  $('rfSeats').addEventListener('keydown', function (e) { if (e.key === 'Enter') show(); });
  $('rfShowBooked').addEventListener('change', function (e) { setDraft({ booked: e.target.checked }); });
  $('rfShowRepair').addEventListener('change', function (e) { setDraft({ repair: e.target.checked }); });
  $('rfShow').addEventListener('click', show);
  // fills in today and the current hour (press Show to see it); outside class hours it says so instead
  $('rfNow').addEventListener('click', function () {
    var d = new Date();
    var hint = $('rfNowHint');
    var nowMin = d.getHours() * 60 + d.getMinutes();
    if (d.getDay() === 0 || nowMin < OPEN || nowMin >= CLOSE) {
      hint.textContent = 'It’s outside class hours now (Monday to Saturday, ' + fmt(OPEN) + ' to ' + fmt(CLOSE) + '). Pick a day and time instead.';
      hint.hidden = false;
      return;
    }
    var start = Math.floor(nowMin / 30) * 30;
    var end = Math.min(CLOSE, start + 60);
    var day = today();
    Array.prototype.forEach.call(doc.querySelectorAll('[data-rf-day]'), function (x) { x.setAttribute('aria-pressed', x.getAttribute('data-rf-day') === day ? 'true' : 'false'); });
    $('rfStart').value = hhmm(start);
    $('rfEnd').value = hhmm(end);
    setDraft({ day: day, start: hhmm(start), end: hhmm(end) });
    hint.textContent = 'Filled in ' + DAY_LONG[day] + ', ' + fmtRange(start, end) + '. Press Show to see the free rooms.';
    hint.hidden = false;
  });
  // start over: every filter back to the default, shown right away
  $('rfClear').addEventListener('click', function () {
    state = Object.assign({}, state, { start: '', end: '', bldg: '', type: '', seats: '', booked: false, repair: false });
    writeUrl();
    fillControls();
    render();
    $('rfNowHint').hidden = true;
    markPending();
  });
  $('rfResults').addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-rf-room]');
    if (b) openWeek(b.getAttribute('data-rf-room'));
  });
  Array.prototype.forEach.call(doc.querySelectorAll('[data-rf-close]'), function (b) {
    b.addEventListener('click', function () { $('rfDialog').close(); });
  });
  $('rfDialog').addEventListener('click', function (e) { if (e.target === e.currentTarget) e.currentTarget.close(); });

  readUrl();
  (global.MCUNexReady || Promise.resolve()).then(function () {
    loadRooms();
    fillControls();
    render();
    markPending();
    return loadBookings();
  }).then(function () {
    renderTermNote();
    render();
  });
})(typeof window !== 'undefined' ? window : this);
