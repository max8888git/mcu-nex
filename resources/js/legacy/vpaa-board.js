/**
 * The VPAA's boxes, one per college, in three places (data-mode on #vbRoot):
 * - "waiting"  (Dashboard): timetables waiting for approval; approve (one or several) or send back
 * - "approved" (Approved schedules): this term's approved colleges; retract one with a note
 * - "history"  (History): approved timetables of every term, to look at
 * Clicking a box opens the college's timetable by program and section.
 * Server: schedule.php ?colleges=1, ?college_copy=1, ?vpaa_history=1; actions college_vpaa_decide / college_vpaa_retract.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  var DAY_NAMES = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday' };
  var root;
  var mode;
  var state = { overview: null, history: null, termId: null, selected: {}, view: null, program: '', find: '' };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function time12(t) {
    var m = String(t || '').match(/^(\d{1,2}):(\d{2})/);
    if (!m) return t || '';
    var h = Number(m[1]);
    return (h % 12 || 12) + ':' + m[2] + (h < 12 ? ' AM' : ' PM');
  }

  function dateText(v) {
    if (!v) return '';
    var d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d.getTime()) ? String(v) : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function plural(n, one, many) {
    return n + ' ' + (n === 1 ? one : many);
  }

  function notice(text, tone) {
    var el = doc.createElement('div');
    el.className = 'ui-notice' + (tone === 'bad' ? ' ui-notice--bad' : '');
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  function act(action, ids, extra) {
    var t = state.overview.term;
    return global.McuApi.send('POST', 'schedule.php', Object.assign({
      action: action, colleges: ids, schoolYear: t.schoolYear, termCode: t.termCode
    }, extra || {}));
  }

  // ---------------------------------------------------------------- boxes

  function statusOf(c) {
    if (c.status === 'pending_vpaa') return { text: 'Waiting for your approval', tone: 'waiting' };
    if (c.status === 'published') return { text: 'Approved' + (c.publishedAt ? ' ' + dateText(c.publishedAt) : ''), tone: 'done' };
    if (c.lastRejectFrom === 'vpaa' && c.status === 'with_registrar') return { text: c.hasPublished ? 'You retracted it' : 'You sent it back', tone: 'back' };
    if (c.status === 'with_registrar' || c.status === 'pending_super_admin') return { text: 'With the University Registrar', tone: 'neutral' };
    return { text: 'Being prepared by the registrars', tone: 'neutral' };
  }

  function box(c, opts) {
    // History lists approved timetables only
    var st = opts.term ? { text: 'Approved', tone: 'done' } : statusOf(c);
    var count = opts.count != null ? opts.count : c.status === 'pending_vpaa' ? c.pendingClasses : c.publishedClasses;
    return '<article class="vb-box' + (opts.pick && state.selected[c.id] ? ' is-picked' : '') + '">' +
      (opts.pick ? '<label class="vb-pick" title="Select to approve several at once"><input type="checkbox" data-pick="' + esc(c.id) + '"' + (state.selected[c.id] ? ' checked' : '') + '><span class="sr-only">Select ' + esc(c.name) + '</span></label>' : '') +
      '<button type="button" class="vb-open" data-open="' + esc(c.id) + '" data-which="' + opts.which + '"' + (opts.term ? ' data-term="' + opts.term + '"' : '') + '>' +
      '<span class="vb-name">' + esc(c.name) + '</span>' +
      '<span class="ui-chip ui-chip--' + st.tone + '">' + esc(st.text) + '</span>' +
      '<span class="vb-meta">' + esc(opts.meta || (count ? plural(count, 'class', 'classes') : 'No classes')) + '</span>' +
      (c.lastRejectFrom === 'vpaa' && c.lastRejectNote && c.status !== 'pending_vpaa' && c.status !== 'published' ? '<span class="vb-note">Your note: ' + esc(c.lastRejectNote) + '</span>' : '') +
      '<span class="vb-cta">' + esc(opts.cta) + ' →</span></button></article>';
  }

  // where each college is: Records Officer → University Registrar → You (VPAA) → Approved
  var STEPS = ['Records Officer', 'University Registrar', 'You (VPAA)', 'Approved'];

  function stepOf(c) {
    if (c.status === 'published') return 3;
    if (c.status === 'pending_vpaa') return 2;
    if (c.status === 'pending_super_admin' || c.status === 'with_registrar') return 1;
    return 0;
  }

  function trackerHtml(colleges) {
    return '<h3 class="vb-sub">Where every college is</h3>' +
      '<p class="vb-lead">Each timetable goes from the Records Officer to the University Registrar, then to you.</p>' +
      '<ol class="vb-track">' + colleges.map(function (c) {
        var at = stepOf(c);
        var notes = [];
        if (c.lastRejectFrom === 'vpaa' && c.lastRejectNote && at < 2) notes.push('You sent it back: “' + c.lastRejectNote + '”');
        if (c.hasPublished && at < 3) notes.push('The last approved timetable is still in use.');
        if (at === 0 && !c.hasPublished) notes.push('Being prepared.');
        var link = at === 2 ? '<button type="button" class="vb-link" data-open="' + esc(c.id) + '" data-which="pending">Check and decide</button>'
          : c.hasPublished ? '<button type="button" class="vb-link" data-open="' + esc(c.id) + '" data-which="published">' + (at === 3 ? 'View' : 'View last approved') + '</button>' : '';
        return '<li class="vb-track-row"><div class="vb-track-name"><strong>' + esc(c.name) + '</strong>' +
          (notes.length ? '<span class="vb-track-note">' + esc(notes.join(' ')) + '</span>' : '') + '</div>' +
          '<ol class="vb-steps" aria-label="' + esc(c.name + ': ' + STEPS[at]) + '">' + STEPS.map(function (label, i) {
            var cls = i < at ? 'is-done' : i === at ? (at === 3 ? 'is-done is-current' : 'is-current') : '';
            return '<li class="vb-step ' + cls + '"' + (i === at ? ' aria-current="step"' : '') + '><span class="vb-dot" aria-hidden="true">' + (i < at || at === 3 ? '✓' : i + 1) + '</span><span class="vb-step-label">' + esc(label) + '</span></li>';
          }).join('') + '</ol>' +
          '<div class="vb-track-act">' + link + '</div></li>';
      }).join('') + '</ol>';
  }

  function render() {
    if (mode === 'history') return renderHistory();
    var o = state.overview;
    var term = o.term ? o.term.label : '';
    var html = '<div class="vb-head"><div><span class="vb-kicker">' + (mode === 'waiting' ? 'For your approval' : 'Approved schedules') + '</span>' +
      '<h2 class="vb-title">' + esc(term || 'No current offering') + '</h2></div></div>';
    if (mode === 'waiting') {
      var waiting = o.colleges.filter(function (c) { return c.status === 'pending_vpaa'; });
      var picked = waiting.filter(function (c) { return state.selected[c.id]; });
      if (waiting.length) {
        html += '<div class="vb-bar"><p>' + plural(waiting.length, 'college is', 'colleges are') + ' waiting. Click one to check its timetable, or tick several to approve together.</p>' +
          '<div class="vb-bar-actions"><button type="button" class="ui-btn" data-all>' + (picked.length === waiting.length ? 'Clear selection' : 'Select all') + '</button>' +
          '<button type="button" class="ui-btn ui-btn--primary" data-bulk' + (picked.length ? '' : ' disabled') + '>Approve ' + (picked.length ? picked.length + ' selected' : 'selected') + '</button></div></div>' +
          '<div class="vb-grid">' + waiting.map(function (c) {
            return box(c, { which: 'pending', pick: true, cta: 'Check and decide', meta: plural(c.pendingClasses, 'class', 'classes') + (c.sentAt ? ' · sent ' + dateText(c.sentAt) : '') });
          }).join('') + '</div>';
      } else {
        html += '<div class="ui-empty vb-empty"><h3>Nothing is waiting for your approval</h3><p>When the University Registrar sends a college’s timetable, it shows here as a box. You’ll get a notification.</p></div>';
      }
      html += trackerHtml(o.colleges);
    } else {
      var approved = o.colleges.filter(function (c) { return c.status === 'published'; });
      var updating = o.colleges.filter(function (c) { return c.status !== 'published' && c.hasPublished; });
      var waitingNow = o.colleges.filter(function (c) { return c.status === 'pending_vpaa'; }).length;
      var rows = approved.concat(updating);
      html += rows.length
        ? '<p class="vb-lead">Every college you approved this term. <strong>View</strong> shows its timetable; <strong>Retract</strong> sends it back to the University Registrar with your note (the approved timetable stays in use until you approve a corrected one).</p>' +
          '<div class="vb-table-wrap"><table class="vb-table"><thead><tr><th>College</th><th>Approved on</th><th class="vb-num">Classes</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>' +
          rows.map(function (c) {
            var live = c.status === 'published';
            return '<tr><td><strong>' + esc(c.name) + '</strong></td>' +
              '<td>' + esc(dateText(c.publishedAt) || '—') + '</td>' +
              '<td class="vb-num">' + (c.publishedClasses || 0) + '</td>' +
              '<td>' + (live ? '<span class="ui-chip ui-chip--done">Approved · in use</span>'
                : '<span class="ui-chip ui-chip--waiting">Being updated</span><span class="vb-cellnote">' + esc(c.status === 'pending_vpaa' ? 'A new version is waiting for you on the Dashboard.' : 'Back with the registrars; the last approved one stays in use.') + '</span>') + '</td>' +
              '<td class="vb-actions"><button type="button" class="ui-btn" data-open="' + esc(c.id) + '" data-which="published">View</button>' +
              (live ? '<button type="button" class="ui-btn ui-btn--danger" data-open="' + esc(c.id) + '" data-which="published" data-then="retract">Retract</button>' : '') + '</td></tr>';
          }).join('') + '</tbody></table></div>'
        : '<div class="ui-empty vb-empty"><h3>No college is approved for this term right now</h3><p>Colleges you approve are listed here, with <strong>View</strong> and <strong>Retract</strong>.' +
          (waitingNow ? ' ' + plural(waitingNow, 'college is', 'colleges are') + ' waiting for you on the <a href="dashboard.html">Dashboard</a>.' : '') + '</p></div>';
      html += '<p class="vb-lead vb-foot">Past terms are in <a href="history.html">History</a>.</p>';
    }
    root.innerHTML = html;
  }

  function renderHistory() {
    var terms = state.history.terms || [];
    if (!terms.length) {
      root.innerHTML = '<div class="ui-empty vb-empty"><h3>No approved timetables yet</h3><p>Every term you approve is kept here.</p></div>';
      return;
    }
    if (!terms.some(function (t) { return t.id === state.termId; })) state.termId = terms[0].id;
    var t = terms.filter(function (x) { return x.id === state.termId; })[0];
    root.innerHTML = '<div class="vb-head"><div><span class="vb-kicker">Approved timetables</span><h2 class="vb-title">' + esc(t.label) + (t.current ? ' <span class="ui-chip ui-chip--neutral">Current term</span>' : '') + '</h2></div>' +
      '<label class="vb-field"><span>Term</span><select class="ui-input" data-term-pick>' + terms.map(function (x) {
        return '<option value="' + x.id + '"' + (x.id === t.id ? ' selected' : '') + '>' + esc(x.label + (x.current ? ' (current)' : '')) + '</option>';
      }).join('') + '</select></label></div>' +
      '<p class="vb-lead">' + plural(t.colleges.length, 'college was', 'colleges were') + ' approved in this term. Click one to see its timetable.</p>' +
      '<div class="vb-grid">' + t.colleges.map(function (c) {
        return box(c, { which: 'published', term: t.id, cta: 'View timetable', count: c.classes,
          meta: plural(c.sections, 'section', 'sections') + ' · ' + plural(c.classes, 'class', 'classes') + (c.publishedAt ? ' · approved ' + dateText(c.publishedAt) : '') });
      }).join('') + '</div>';
  }

  // ---------------------------------------------------------------- the pop-up

  function kind(c) {
    return c.component === 'lab' || c.component === 'laboratory' ? 'Lab' : 'Lecture';
  }

  function rows(v) {
    var q = state.find.toLowerCase();
    var out = [];
    (v.classes || []).forEach(function (c) {
      if (state.program && c.programId !== state.program) return;
      var meetings = (c.meetings || []).length ? c.meetings : [null];
      meetings.forEach(function (m) {
        var r = {
          section: c.sectionName || '',
          code: (c.courseCode || '') + (kind(c) === 'Lab' && !/L$/.test(c.courseCode || '') ? 'L' : ''),
          title: c.courseTitle || '', kind: kind(c),
          day: m ? m.day : '', start: m ? m.start : '', end: m ? m.end : '',
          room: m ? (m.roomCode || (c.requiresCampusRoom === false ? 'Off campus' : '')) : '',
          placed: !!m
        };
        if (q && (r.section + ' ' + r.code + ' ' + r.title + ' ' + r.room).toLowerCase().indexOf(q) < 0) return;
        out.push(r);
      });
    });
    out.sort(function (a, b) {
      return a.section.localeCompare(b.section, undefined, { numeric: true }) || (a.placed === b.placed ? 0 : a.placed ? -1 : 1) ||
        DAYS.indexOf(a.day) - DAYS.indexOf(b.day) || a.start.localeCompare(b.start) || a.code.localeCompare(b.code);
    });
    return out;
  }

  function sectionsHtml(list) {
    var map = {};
    var order = [];
    list.forEach(function (r) {
      if (!map[r.section]) { map[r.section] = []; order.push(r.section); }
      map[r.section].push(r);
    });
    if (!order.length) return '<div class="ui-empty vb-empty"><h3>Nothing matches</h3><p>Try another program or search.</p></div>';
    return order.map(function (s, i) {
      return '<details class="ct-section"' + (i < 2 ? ' open' : '') + '><summary><strong>' + esc(s) + '</strong><span>' + plural(map[s].length, 'meeting', 'meetings') + '</span></summary>' +
        '<div class="ct-scroll"><table class="ct-table"><thead><tr><th>Day</th><th>Time</th><th>Code</th><th>Subject</th><th>Type</th><th>Room</th></tr></thead><tbody>' +
        map[s].map(function (r) {
          return '<tr' + (r.placed ? '' : ' class="is-unplaced"') + '><td>' + esc(r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled') + '</td>' +
            '<td class="ct-nowrap">' + (r.placed ? esc(time12(r.start) + ' – ' + time12(r.end)) : '—') + '</td>' +
            '<td class="ct-nowrap"><strong>' + esc(r.code) + '</strong></td><td>' + esc(r.title) + '</td><td>' + esc(r.kind) + '</td><td>' + esc(r.room || '—') + '</td></tr>';
        }).join('') + '</tbody></table></div></details>';
    }).join('');
  }

  function dialog() {
    var d = doc.getElementById('vbDialog');
    if (d) return d;
    d = doc.createElement('dialog');
    d.id = 'vbDialog';
    d.className = 'ui-dialog vb-dialog';
    doc.body.appendChild(d);
    d.addEventListener('click', onDialogClick);
    d.addEventListener('input', function (e) {
      if (!e.target.matches('[data-find]')) return;
      state.find = e.target.value;
      d.querySelector('#vbSections').innerHTML = sectionsHtml(rows(state.view));
      countLine();
    });
    d.addEventListener('change', function (e) {
      if (!e.target.matches('[data-program]')) return;
      state.program = e.target.value;
      d.querySelector('#vbSections').innerHTML = sectionsHtml(rows(state.view));
      countLine();
    });
    return d;
  }

  function countLine() {
    var list = rows(state.view);
    var sections = {};
    var unplaced = 0;
    list.forEach(function (r) { sections[r.section] = true; if (!r.placed) unplaced++; });
    var el = doc.getElementById('vbCount');
    if (el) el.innerHTML = plural(Object.keys(sections).length, 'section', 'sections') + ' · ' + plural(list.length, 'class meeting', 'class meetings') + (unplaced ? ' · <strong>' + unplaced + ' not scheduled</strong>' : '');
  }

  // what the VPAA can do in the pop-up: waiting → approve / send back; this term's approved → retract
  function footHtml(v) {
    if (mode === 'waiting' && v.which === 'pending' && v.status === 'pending_vpaa') {
      return '<button type="button" class="ui-btn ui-btn--danger vb-push" data-step="back">Send back</button>' +
        '<button type="button" class="ui-btn" data-close>Close</button><button type="button" class="ui-btn ui-btn--primary" data-step="approve">Approve and publish</button>';
    }
    // retract: an approved timetable of this term, from the Dashboard or Approved schedules (not History)
    if (mode !== 'history' && v.which === 'published' && v.status === 'published') {
      return '<button type="button" class="ui-btn ui-btn--danger vb-push" data-step="retract">Retract approval</button><button type="button" class="ui-btn" data-close>Close</button>';
    }
    return '<button type="button" class="ui-btn" data-close>Close</button>';
  }

  function open(id, which, term) {
    var d = dialog();
    state.program = '';
    state.find = '';
    d.innerHTML = '<div class="ui-dialog-body"><p class="vb-muted">Loading the timetable…</p></div>';
    if (!d.open) d.showModal();
    return global.McuApi.get('schedule.php?college_copy=1&college=' + encodeURIComponent(id) + '&which=' + which + (term ? '&term=' + term : '')).then(function (v) {
      state.view = v;
      var when = v.which === 'pending' ? (v.sentAt ? 'Sent by the University Registrar ' + dateText(v.sentAt) : '') : (v.publishedAt ? 'Approved ' + dateText(v.publishedAt) : '');
      d.innerHTML = '<div class="ui-dialog-head"><div><h2 class="ui-dialog-title">' + esc(v.college.name) + '</h2>' +
        '<p class="vb-dialog-sub">' + esc(v.term.label) + (when ? ' · ' + esc(when) : '') + '</p></div>' +
        '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
        '<div class="ui-dialog-body">' +
        (v.which === 'published' && v.status !== 'published' && mode !== 'history' ? '<p class="vb-info"><strong>This is the last approved timetable, still in use.</strong> ' +
          (v.status === 'pending_vpaa' ? 'A new version is waiting for your approval on the Dashboard.' : 'The registrars are already preparing a new version, so there’s nothing to retract. When they send it, you approve or send it back on the Dashboard.') + '</p>' : '') +
        (v.classes.length ? '<div class="ct-bar vb-dialog-bar">' +
          (v.programs.length > 1 ? '<label class="ct-field"><span>Program</span><select class="ui-input" data-program><option value="">All programs</option>' +
            v.programs.map(function (p) { return '<option value="' + esc(p.id) + '">' + esc(p.name) + '</option>'; }).join('') + '</select></label>' : '') +
          '<label class="ct-field ct-find"><span>Search</span><input class="ui-input" type="search" data-find placeholder="Section, subject or room"></label>' +
          (global.McuTimetablePdf ? '<div class="ct-actions"><button type="button" class="ui-btn" data-pdf>Download PDF</button></div>' : '') + '</div>' +
          '<p class="ct-count" id="vbCount"></p><div id="vbSections">' + sectionsHtml(rows(v)) + '</div>'
          : '<div class="ui-empty vb-empty"><h3>No classes in this copy</h3></div>') +
        '<div id="vbStep"></div></div>' +
        '<div class="ui-dialog-foot" id="vbFoot">' + footHtml(v) + '</div>';
      countLine();
    }).catch(function (e) {
      d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Couldn’t open it</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
        '<div class="ui-dialog-body"><p class="ui-field-error">' + esc(e.message) + '</p></div>';
    });
  }

  // confirm / note step shown in the pop-up before anything is sent
  function showStep(kindOf) {
    var v = state.view;
    var step = doc.getElementById('vbStep');
    var foot = doc.getElementById('vbFoot');
    var texts = {
      approve: { title: 'Approve and publish ' + v.college.name + '?', msg: 'Its Dean and Program Chairs will see this timetable and the college is locked.', yes: 'Yes, approve and publish', tone: 'primary' },
      back: { title: 'Send ' + v.college.name + ' back?', msg: 'It goes back to the University Registrar with your note.', note: 'What should the University Registrar fix?', yes: 'Send back', tone: 'danger' },
      retract: { title: 'Retract the approval of ' + v.college.name + '?', msg: 'It goes back to the University Registrar with your note. The approved timetable stays in use until you approve a corrected one.', note: 'Why are you retracting it, and what should be fixed?', yes: 'Retract approval', tone: 'danger' }
    }[kindOf];
    step.innerHTML = '<div class="vb-step vb-step--' + texts.tone + '"><strong>' + esc(texts.title) + '</strong><p>' + esc(texts.msg) + '</p>' +
      (texts.note ? '<label class="ui-field-label" for="vbNote">' + esc(texts.note) + '</label><textarea class="ui-textarea" id="vbNote" maxlength="2000"></textarea>' : '') +
      '<p class="ui-field-error" id="vbErr" role="alert" hidden></p></div>';
    foot.innerHTML = '<button type="button" class="ui-btn" data-step-cancel>Cancel</button><button type="button" class="ui-btn ui-btn--' + texts.tone + '" data-go="' + kindOf + '">' + esc(texts.yes) + '</button>';
    var note = doc.getElementById('vbNote');
    (note || step).scrollIntoView({ block: 'center', behavior: 'smooth' });
    if (note) note.focus();
  }

  function go(kindOf, btn) {
    var v = state.view;
    var note = doc.getElementById('vbNote');
    var err = doc.getElementById('vbErr');
    var text = note ? note.value.trim() : '';
    if (note && !text) {
      err.textContent = 'Write a short note so the University Registrar knows what to fix.';
      err.hidden = false;
      return;
    }
    btn.disabled = true;
    var call = kindOf === 'retract'
      ? act('college_vpaa_retract', [v.college.id], { note: text })
      : act('college_vpaa_decide', [v.college.id], { accept: kindOf === 'approve', note: text });
    call.then(function () {
      dialog().close();
      notice({ approve: 'Approved and published ' + v.college.name + '.', back: 'Sent ' + v.college.name + ' back.', retract: 'Retracted the approval of ' + v.college.name + '.' }[kindOf]);
      delete state.selected[v.college.id];
      return load();
    }).catch(function (e) {
      btn.disabled = false;
      err.textContent = e.message;
      err.hidden = false;
    });
  }

  function onDialogClick(e) {
    var d = dialog();
    var t = e.target;
    var el;
    if (t === d || t.closest('[data-close]')) return d.close();
    if ((el = t.closest('[data-step]'))) return showStep(el.getAttribute('data-step'));
    if (t.closest('[data-step-cancel]')) {
      doc.getElementById('vbStep').innerHTML = '';
      doc.getElementById('vbFoot').innerHTML = footHtml(state.view);
      return;
    }
    if ((el = t.closest('[data-go]'))) return go(el.getAttribute('data-go'), el);
    if (t.closest('[data-pdf]')) return pdf();
  }

  function pdf() {
    var v = state.view;
    var list = rows(v);
    var map = {};
    var order = [];
    list.forEach(function (r) {
      if (!map[r.section]) { map[r.section] = []; order.push(r.section); }
      map[r.section].push([r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled', r.placed ? time12(r.start) + ' – ' + time12(r.end) : '—', r.code, r.title, r.kind, r.room || '—']);
    });
    var prog = (v.programs.filter(function (p) { return p.id === state.program; })[0] || {}).name;
    var img = doc.querySelector('.topbar-logo img');
    global.McuTimetablePdf.download({
      logoUrl: img ? img.src : '',
      collegeName: v.college.name,
      title: (prog || v.college.name) + ' Timetable',
      subtitle: v.term.label + (v.which === 'pending' ? ' · waiting for approval' : v.publishedAt ? ' · approved ' + dateText(v.publishedAt) : ''),
      sections: order.map(function (s) { return { title: s, rows: map[s] }; }),
      footer: 'MCU-NEX · ' + v.college.name + ' · printed ' + new Date().toLocaleDateString(),
      fileName: ((prog || v.college.name) + ' timetable ' + v.term.label).replace(/[\\/:*?"<>|·]+/g, ' ').replace(/\s+/g, ' ').trim() + '.pdf'
    }).catch(function (er) { notice(er.message, 'bad'); });
  }

  // ---------------------------------------------------------------- page

  function bulkApprove() {
    var ids = Object.keys(state.selected).filter(function (id) { return state.selected[id]; });
    if (!ids.length) return;
    var names = state.overview.colleges.filter(function (c) { return state.selected[c.id]; }).map(function (c) { return c.name; });
    var d = dialog();
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Approve and publish ' + plural(ids.length, 'college', 'colleges') + '?</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p class="cs-dialog-msg">Their Deans and Program Chairs will see these timetables and the colleges are locked.</p><ul class="cs-dialog-list">' +
      names.map(function (n) { return '<li><strong>' + esc(n) + '</strong></li>'; }).join('') + '</ul><p class="ui-field-error" id="vbErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="vbBulkGo">Approve and publish</button></div>';
    if (!d.open) d.showModal();
    doc.getElementById('vbBulkGo').onclick = function () {
      var btn = this;
      btn.disabled = true;
      act('college_vpaa_decide', ids, { accept: true }).then(function () {
        d.close();
        notice('Approved and published ' + names.join(', ') + '.');
        state.selected = {};
        return load();
      }).catch(function (e) {
        btn.disabled = false;
        var err = doc.getElementById('vbErr');
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  function load() {
    var q = mode === 'history' ? 'schedule.php?vpaa_history=1' : 'schedule.php?colleges=1';
    return global.McuApi.get(q).then(function (res) {
      if (mode === 'history') state.history = res;
      else state.overview = res;
      render();
    }).catch(function (e) {
      root.innerHTML = '<div class="ui-empty vb-empty"><h3>Couldn’t load the timetables</h3><p>' + esc(e.message) + '</p></div>';
    });
  }

  function init() {
    root = doc.getElementById('vbRoot');
    if (!root || !global.McuApi) return;
    mode = root.getAttribute('data-mode') || 'waiting';
    root.addEventListener('click', function (e) {
      var t = e.target;
      var el;
      if ((el = t.closest('[data-open]'))) {
        var then = el.getAttribute('data-then');
        return open(el.getAttribute('data-open'), el.getAttribute('data-which'), el.getAttribute('data-term')).then(function () {
          if (then && doc.querySelector('#vbFoot [data-step="' + then + '"]')) showStep(then);
        });
      }
      if (t.closest('[data-bulk]')) return bulkApprove();
      if (t.closest('[data-all]')) {
        var waiting = state.overview.colleges.filter(function (c) { return c.status === 'pending_vpaa'; });
        var all = waiting.every(function (c) { return state.selected[c.id]; });
        state.selected = {};
        if (!all) waiting.forEach(function (c) { state.selected[c.id] = true; });
        return render();
      }
    });
    root.addEventListener('change', function (e) {
      var t = e.target;
      if (t.matches('[data-pick]')) {
        state.selected[t.getAttribute('data-pick')] = t.checked;
        return render();
      }
      if (t.matches('[data-term-pick]')) {
        state.termId = Number(t.value);
        return renderHistory();
      }
    });
    root.innerHTML = '<p class="vb-loading">Loading…</p>';
    load();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
