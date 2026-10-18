/**
 * Curriculum page for Deans and Program Chairs (roles/viewer/curriculum-builder):
 * the programs they handle, and the form to make a curriculum version, check it,
 * and pass it on (Program Chair → Dean → Registrar). Server: curriculum-reviews.php.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var F = global.McuCurriculumForm;
  var esc = F.esc;
  var API = 'curriculum-reviews.php';

  var state = {
    role: '',
    programs: [],
    roomTypes: [],
    review: null, // the open version (full)
    form: null,
    editing: false,
    dirty: false,
    problems: [],
    saveTimer: null,
    autoTimer: null,
    autosaving: false,
    changes: 0
  };

  var root = null;

  function api(body) {
    return global.McuApi.send('POST', API, body);
  }

  function notice(text, tone) {
    var el = doc.createElement('div');
    el.className = 'ui-notice' + (tone === 'bad' ? ' ui-notice--bad' : '');
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  function chip(status) {
    var tone = { draft: 'neutral', pending_dean: 'waiting', dean_approved: 'waiting', pending_registrar: 'waiting', accepted: 'done', superseded: 'neutral' }[status] || 'neutral';
    var text = F.statusText(status);
    if (state.role === 'dean' && status === 'pending_dean') text = 'Waiting for your approval';
    return '<span class="ui-chip ui-chip--' + tone + '">' + esc(text) + '</span>';
  }

  function when(v) {
    if (!v) return '';
    var d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function returnNote(v) {
    if (!v || !v.lastReturnFrom || !v.lastReturnNote) return '';
    var who = v.lastReturnFrom === 'dean' ? 'The Dean' : 'The Registrar';
    var what = v.revisionOf && v.lastReturnFrom === 'registrar' ? ' asked for a revision' : ' sent this back';
    return '<div class="cb-return"><strong>' + who + what + (v.lastReturnAt ? ' on ' + esc(when(v.lastReturnAt)) : '') + ':</strong> ' + esc(v.lastReturnNote) + '</div>';
  }

  function nextYear(program) {
    var y = new Date().getFullYear();
    var latest = (program.live || [])[0];
    if (latest && /^\d{4}-\d{4}$/.test(latest.academicYear)) y = Math.max(y, Number(latest.academicYear.slice(0, 4)) + 1);
    return y + '-' + (y + 1);
  }

  // ---------------------------------------------------------------- list

  function load() {
    return global.McuApi.get(API + '?mine=1').then(function (res) {
      state.role = res.role;
      state.programs = res.programs || [];
      state.roomTypes = res.roomTypes || [];
    });
  }

  var OPEN = ['draft', 'pending_dean', 'dean_approved', 'pending_registrar'];
  var ON_THE_WAY = ['pending_dean', 'dean_approved', 'pending_registrar'];

  // the one version already sent on (to the Dean, approved, or with the Registrar)
  function onTheWayOf(p) {
    return (p.versions || []).filter(function (v) { return ON_THE_WAY.indexOf(v.status) >= 0; })[0] || null;
  }

  // the drafts this person works on: a Program Chair's own; a Dean's own, plus ones the Registrar sent back or asked to revise
  function draftsOf(p) {
    return (p.versions || []).filter(function (v) {
      if (v.status !== 'draft') return false;
      return state.role !== 'dean' || v.madeByDean || !p.hasDepartments || v.lastReturnFrom === 'registrar' || !!v.revisionOf;
    });
  }

  function onTheWay(v) {
    return v && ON_THE_WAY.indexOf(v.status) >= 0;
  }

  function versionLine(v) {
    return '<div class="cb-current">' + chip(v.status) + '<span><strong>' + esc(v.name) + '</strong> · Version ' + v.version + ' · ' + esc(v.academicYear) + ' · ' + v.courseCount + (v.courseCount === 1 ? ' subject' : ' subjects') +
      (v.updatedAt ? ' · updated ' + esc(when(v.updatedAt)) : '') + '</span></div>';
  }

  function draftRow(p, v, way) {
    var dean = state.role === 'dean';
    var where = { pending_dean: dean ? 'waiting for your approval' : 'with the Dean', dean_approved: 'approved and ready for the Registrar', pending_registrar: 'with the Registrar' };
    var why = way ? '“' + way.name + '” is ' + (where[way.status] || 'on its way') + '. Only one draft goes at a time.'
      : !v.courseCount ? 'Add subjects first.' : '';
    return '<li class="cb-draft">' +
      '<div class="cb-draft-head"><strong class="cb-draft-name">' + esc(v.name) + '</strong>' +
      (v.lastReturnFrom ? '<span class="ui-chip ui-chip--back">Sent back</span>' : '') + '</div>' +
      '<span class="cb-muted">Version ' + v.version + ' · ' + esc(v.academicYear) + ' · ' + v.courseCount + (v.courseCount === 1 ? ' subject' : ' subjects') + (v.updatedAt ? ' · saved ' + esc(when(v.updatedAt)) : '') + '</span>' +
      returnNote(v) +
      '<div class="cb-draft-actions">' +
      '<button type="button" class="ui-btn" data-open="' + esc(v.id) + '">Open</button>' +
      '<button type="button" class="ui-btn" data-rename="' + esc(v.id) + '">Rename</button>' +
      '<button type="button" class="ui-btn ui-btn--primary" data-send-draft="' + esc(v.id) + '"' + (why ? ' disabled title="' + esc(why) + '"' : '') + '>' +
      (dean ? 'Send to Registrar' : 'Send to Dean') + '</button>' +
      '</div>' + (why && way ? '<p class="cb-hint">' + esc(why) + '</p>' : '') + '</li>';
  }

  function findVersion(id) {
    var out = null;
    state.programs.forEach(function (p) {
      (p.versions || []).forEach(function (v) { if (v.id === id) out = { p: p, v: v }; });
    });
    return out;
  }

  function renderList() {
    state.review = null;
    state.form = null;
    var dean = state.role === 'dean';
    var waiting = [];
    var sendable = 0;
    state.programs.forEach(function (p) {
      var way = onTheWayOf(p);
      if (dean && way && way.status === 'pending_dean') waiting.push(way);
      if (dean && way && way.status !== 'pending_registrar') sendable++;
      if (dean && !way && draftsOf(p).length) sendable++;
    });
    var html = '';
    var withChairs = state.programs.some(function (p) { return p.hasDepartments; });
    if (dean && withChairs) {
      html += '<div class="cb-strip"><div><strong>' + (waiting.length ? waiting.length + ' waiting for your approval' : 'Nothing waiting for your approval') + '</strong>' +
        '<span>Approve or send back what your Program Chairs send, or make your own drafts. Then send one to the Registrar.</span></div>' +
        (sendable ? '<button type="button" class="ui-btn ui-btn--primary" data-bulk-send>Send to Registrar…</button>' : '') + '</div>';
    } else if (dean && sendable) {
      html += '<div class="cb-strip"><div><strong>Ready to send</strong><span>Pick the draft to send for each program, one at a time or several together.</span></div>' +
        '<button type="button" class="ui-btn ui-btn--primary" data-bulk-send>Send to Registrar…</button></div>';
    }
    var sub = doc.querySelector('.ui-page-sub');
    if (sub && dean && !withChairs) sub.textContent = 'Make drafts of each program’s curriculum, then pick the one to send to the Registrar. Once accepted it’s used for sectioning.';
    if (!state.programs.length) {
      html += '<div class="ui-empty"><h3>No programs to show</h3><p>' + (state.role === 'program_chair'
        ? 'Your account isn’t linked to a department with programs yet. Ask the University Registrar to set it in Account management.'
        : 'Your college has no active programs yet.') + '</p></div>';
    }
    html += '<div class="cb-grid">' + state.programs.map(function (p) {
      var way = onTheWayOf(p);
      var drafts = draftsOf(p);
      var accepted = (p.versions || []).filter(function (x) { return x.status === 'accepted'; })[0];
      var canStart = dean || p.hasDepartments;
      return '<article class="cb-card">' +
        '<div class="cb-card-head"><h3>' + esc(p.name) + '</h3><span class="cb-college">' + esc(p.collegeName) + '</span></div>' +
        '<p class="cb-live">' + ((p.live || []).length
          ? 'Active curriculum: ' + p.live.map(function (l) { return '<span class="ui-tag">' + esc(l.academicYear) + '</span>'; }).join(' ')
          : 'No active curriculum yet') + '</p>' +
        (way ? '<div class="cb-way"><p class="cb-label">' + (dean && p.hasDepartments && !way.madeByDean ? 'From the Program Chair' : 'Sent') + '</p>' + versionLine(way) + returnNote(way) +
          '<div class="cb-card-actions cb-main-actions"><button type="button" class="ui-btn ui-btn--primary" data-open="' + esc(way.id) + '">' + (dean && way.status === 'pending_dean' ? 'Check it' : 'Open') + '</button></div>' +
          (dean && way.status === 'pending_dean' && drafts.length ? '<p class="cb-hint">If you approve it, that one goes to the Registrar. Your drafts stay saved.</p>' : '') + '</div>' : '') +
        (drafts.length ? '<div class="cb-drafts"><p class="cb-label">' + (dean && p.hasDepartments ? 'Your drafts' : 'Drafts') + ' (' + drafts.length + ')</p><ul>' +
          drafts.map(function (v) { return draftRow(p, v, way); }).join('') + '</ul></div>' : '') +
        (state.role === 'program_chair' && !p.hasDepartments ? '<p class="cb-hint">In this college the Dean makes the curriculum.</p>' : '') +
        '<div class="cb-card-actions">' +
        (canStart ? '<button type="button" class="ui-btn' + (drafts.length || way ? '' : ' ui-btn--primary') + '" data-start="' + esc(p.id) + '">' +
          (drafts.length || way ? '+ New draft' : accepted || (p.live || []).length ? 'Make a new version' : 'Start a curriculum') + '</button>' : '') +
        (accepted ? '<button type="button" class="ui-btn" data-open="' + esc(accepted.id) + '">View accepted</button>' : '') +
        '</div></article>';
    }).join('') + '</div>';
    root.innerHTML = html;
  }

  function renameDialog(id, current, done) {
    var d = ensureDialog('cbRename', 'cb-dialog');
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Rename draft</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><label class="ui-field-label" for="cbRenameText">Name</label>' +
      '<input class="ui-input" id="cbRenameText" maxlength="80" value="' + esc(current) + '">' +
      '<p class="ui-field-hint">Something that tells your drafts apart, like “With new electives”.</p><p class="ui-field-error" id="cbRenameErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="cbRenameGo">Save name</button></div>';
    d.showModal();
    var input = doc.getElementById('cbRenameText');
    input.select();
    var go = function () {
      var err = doc.getElementById('cbRenameErr');
      api({ action: 'rename', id: id, name: input.value.trim() }).then(function (res) {
        d.close();
        notice('Renamed.');
        done(res.review);
      }).catch(function (e) {
        err.textContent = e.message;
        err.hidden = false;
      });
    };
    doc.getElementById('cbRenameGo').onclick = go;
    input.onkeydown = function (e) { if (e.key === 'Enter') go(); };
  }

  // send one draft on from the program list
  function sendDraft(id) {
    var found = findVersion(id);
    if (!found) return;
    var v = found.v;
    var dean = state.role === 'dean';
    confirmBox(dean ? 'Send to the Registrar?' : 'Send to the Dean?',
      '“' + v.name + '” (version ' + v.version + ') of ' + found.p.name + ' goes to ' + (dean ? 'the Records Officer and the University Registrar' : 'the Dean') +
      '. Your other drafts stay saved. You can’t edit this one while it’s with them.', dean ? 'Send to Registrar' : 'Send to Dean', function () {
        return api(dean ? { action: 'send_to_registrar', ids: [id] } : { action: 'send_to_dean', id: id }).then(function () {
          notice(dean ? 'Sent to the Registrar.' : 'Sent to the Dean.');
          return load().then(renderList);
        });
      });
  }

  // ---------------------------------------------------------------- start a version

  function ensureDialog(id, cls) {
    var d = doc.getElementById(id);
    if (d) return d;
    d = doc.createElement('dialog');
    d.id = id;
    d.className = 'ui-dialog ' + (cls || '');
    doc.body.appendChild(d);
    d.addEventListener('click', function (e) {
      if (e.target.closest('[data-csv-template]')) return F.csvTemplate(state.roomTypes);
      if (e.target.closest('[data-xlsx-template]')) return excelTemplate();
      if (e.target.closest('[data-close]') || e.target === d) d.close();
    });
    return d;
  }

  function excelTemplate() {
    return F.excelTemplate(state.roomTypes).catch(function (e) { notice(e.message, 'bad'); });
  }

  // the layout the import reads, with a filled-in template to start from
  function csvHelpHtml() {
    return '<details class="cb-csvhelp"><summary>What should the file look like?</summary>' +
      '<p class="cb-templates"><button type="button" class="ui-btn" data-xlsx-template>Excel template</button><button type="button" class="ui-btn" data-csv-template>CSV template</button></p>' +
      '<p>The Excel template has the column widths set and dropdowns for Year Level, Semester, room types and Elective.</p>' +
      '<p>One row per subject, with these columns (first row = headings):</p><ul>' +
      '<li><strong>Course Code</strong>, <strong>Course Title</strong>, <strong>Year Level</strong> (1–8) and <strong>Semester</strong> (1, 2 or Summer) are required.</li>' +
      '<li><strong>Lecture Units</strong> and <strong>Lab Units</strong>. Put a subject’s lab on the same row; it gets the “L” code (CCS 101L) by itself.</li>' +
      '<li><strong>Lecture Room Type</strong> and <strong>Lab Room Type</strong>: ' + (state.roomTypes.length ? esc(state.roomTypes.join(', ')) : 'one of the room types in Data management') + '.</li>' +
      '<li><strong>Prerequisite 1–3</strong>: a course code from an earlier term, a year standing (“3rd Year Standing”), or blank for none.</li>' +
      '<li><strong>Elective</strong>: Yes, or blank. Total Units and Hours are worked out for you.</li>' +
      '</ul><p>The Download CSV button on any curriculum gives this same layout, so you can edit that file and import it again.</p></details>';
  }

  function openStart(programId) {
    var p = state.programs.filter(function (x) { return x.id === programId; })[0];
    if (!p) return;
    var d = ensureDialog('cbStart', 'cb-dialog');
    var copies = (p.versions || []).filter(function (v) { return v.courseCount > 0; });
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + ((p.live || []).length ? 'Make a new version: ' : 'Start a curriculum: ') + esc(p.name) + '</h2>' +
      '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body">' +
      '<label class="ui-field-label" for="cbYear">Effective school year</label>' +
      '<input class="ui-input" id="cbYear" value="' + esc(nextYear(p)) + '" placeholder="2027-2028" inputmode="numeric">' +
      '<p class="ui-field-hint">The first school year students follow this curriculum.</p>' +
      '<label class="ui-field-label" for="cbName">Name of this draft</label>' +
      '<input class="ui-input" id="cbName" maxlength="80" placeholder="Draft ' + (((p.versions || [])[0] || { version: 0 }).version + 1) + '">' +
      '<p class="ui-field-hint">So you can tell your drafts apart, like “With new electives”. You can rename it later.</p>' +
      '<fieldset class="cb-from"><legend class="ui-field-label">Start from</legend>' +
      '<label><input type="radio" name="cbFrom" value="blank" checked> A blank form</label>' +
      ((p.live || []).length ? '<label><input type="radio" name="cbFrom" value="live"> A copy of the active curriculum <select class="ui-input cb-inline" id="cbLive">' +
        p.live.map(function (l) { return '<option value="' + esc(l.id) + '">' + esc(l.academicYear) + '</option>'; }).join('') + '</select></label>' : '') +
      (copies.length ? '<label><input type="radio" name="cbFrom" value="version"> A copy of <select class="ui-input cb-inline" id="cbVersion">' +
        copies.map(function (v) { return '<option value="' + esc(v.id) + '">' + esc(v.name + ' · version ' + v.version + ' · ' + F.statusText(v.status)) + '</option>'; }).join('') + '</select></label>' : '') +
      '<label><input type="radio" name="cbFrom" value="import"> A file (Excel or CSV) <input type="file" id="cbFile" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"></label>' +
      '</fieldset>' + csvHelpHtml() + '<p class="ui-field-error" id="cbStartErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="cbStartGo">Start</button></div>';
    d.showModal();
    doc.getElementById('cbStartGo').onclick = function () {
      var err = doc.getElementById('cbStartErr');
      var from = (d.querySelector('input[name="cbFrom"]:checked') || {}).value || 'blank';
      var body = { action: 'create', programId: p.id, academicYear: doc.getElementById('cbYear').value.trim(), from: from, name: doc.getElementById('cbName').value.trim() };
      var go = function () {
        api(body).then(function (res) {
          d.close();
          load().then(function () { openReview(res.review, true); });
        }).catch(function (e) {
          err.textContent = e.message;
          err.hidden = false;
        });
      };
      if (from === 'live') body.sourceId = doc.getElementById('cbLive').value;
      if (from === 'version') body.sourceId = doc.getElementById('cbVersion').value;
      if (from === 'import') {
        var file = doc.getElementById('cbFile').files[0];
        if (!file) {
          err.textContent = 'Choose a file first.';
          err.hidden = false;
          return;
        }
        var excel = /\.xlsx$/i.test(file.name);
        if (excel && !global.McuCurriculumExcel) {
          err.textContent = 'The Excel reader didn’t load. Refresh the page and try again.';
          err.hidden = false;
          return;
        }
        (excel
          ? global.McuCurriculumExcel.read(file).then(function (rows) { return global.CurriculumStore.parseGrid(rows); })
          : file.text().then(function (text) { return global.CurriculumStore && global.CurriculumStore.parse(text); })
        ).then(function (parsed) {
          if (!parsed || !parsed.ok) {
            err.textContent = (parsed && parsed.error) || 'That file couldn’t be read.';
            err.hidden = false;
            return;
          }
          body.courses = parsed.rows.map(function (r) { return F.fromSheet(r.course || {}); });
          go();
        }).catch(function (e) {
          err.textContent = e.message;
          err.hidden = false;
        });
        return;
      }
      go();
    };
  }

  // ---------------------------------------------------------------- one version

  function localKey(id) {
    return 'mcu.curriculum.draft.' + id;
  }

  function openReview(review, startEditing) {
    state.review = review;
    state.editing = !!(startEditing && review.can.edit);
    state.dirty = false;
    renderReview();
    global.scrollTo(0, 0);
  }

  function fetchReview(id, startEditing) {
    return global.McuApi.get(API + '?id=' + encodeURIComponent(id)).then(function (res) {
      openReview(res.review, startEditing);
    });
  }

  function renderReview() {
    var r = state.review;
    var can = r.can || {};
    var local = null;
    try {
      local = JSON.parse(global.localStorage.getItem(localKey(r.id)) || 'null');
    } catch (e) {}
    var restore = local && can.edit && local.savedAt && (!r.updatedAt || new Date(local.savedAt) > new Date(String(r.updatedAt).replace(' ', 'T')));
    var actions = [];
    if (can.sendToDean) actions.push('<button type="button" class="ui-btn ui-btn--primary" data-do="send_to_dean">Send to Dean</button>');
    if (can.deanDecide) {
      actions.push('<button type="button" class="ui-btn ui-btn--danger" data-do="dean_reject">Send back to Program Chair</button>');
      actions.push('<button type="button" class="ui-btn ui-btn--primary" data-do="dean_approve">Approve</button>');
    }
    if (can.sendToRegistrar) actions.push('<button type="button" class="ui-btn ui-btn--primary" data-do="send_to_registrar">' + (r.status === 'pending_dean' ? 'Approve and send to Registrar' : 'Send to Registrar') + '</button>');
    if (can.takeBack) actions.push('<button type="button" class="ui-btn" data-do="take_back">Take back from Registrar</button>');
    if (can.discard) actions.push('<button type="button" class="ui-btn ui-btn--danger cb-push" data-do="discard">Delete this draft</button>');
    // the Dean checking a version that comes back after a send-back: what changed, and notes on single subjects
    var ret = r.returned;
    var reviewing = !!can.deanDecide && !state.editing;
    var changes = ret && reviewing ? F.diff(ret.courses || [], r.courses || []) : null;
    var others = otherVersions(r);
    root.innerHTML =
      '<button type="button" class="cb-back" data-back>&larr; All programs</button>' +
      '<div class="cb-head"><div><h2>' + esc(r.programName) + '</h2>' +
      '<p class="cb-draftname"><strong>' + esc(r.name) + '</strong>' + (can.rename ? ' <button type="button" class="cs-linkbtn" data-rename="' + esc(r.id) + '">Rename</button>' : '') + '</p>' +
      '<p>' + esc(r.collegeName) + ' · Version ' + r.version + ' · ' +
      (state.editing ? '<label class="cb-year">Effective <input class="ui-input" id="cbYearEdit" value="' + esc(r.academicYear) + '"></label>' : esc(r.academicYear)) + '</p></div>' +
      '<div class="cb-head-side">' + chip(r.status) + '</div></div>' +
      returnNote(r) + ownershipNote(r) +
      (restore ? '<div class="cb-restore">You have unsaved changes from ' + esc(new Date(local.savedAt).toLocaleString()) + '. <button type="button" class="cs-linkbtn" data-restore>Restore them</button> · <button type="button" class="cs-linkbtn" data-forget>Discard</button></div>' : '') +
      '<div class="cb-toolbar">' +
      (can.edit && !state.editing ? '<button type="button" class="ui-btn ui-btn--primary" data-edit>Edit</button>' : '') +
      (state.editing ? '<button type="button" class="ui-btn ui-btn--primary" data-save>Save</button><button type="button" class="ui-btn" data-reset>Reset</button><button type="button" class="ui-btn" data-done>Done editing</button><span class="cb-saved" id="cbSaved"></span>' : '') +
      (state.editing && others.length ? '<button type="button" class="ui-btn" data-copy-from>Copy subjects…</button>' : '') +
      '<span class="cb-spacer"></span>' + nextStepHtml(can, r) +
      (others.length ? '<button type="button" class="ui-btn" data-compare>Compare…</button>' : '') +
      '<button type="button" class="ui-btn" data-csv>Download CSV</button><button type="button" class="ui-btn" data-pdf>Download PDF</button><button type="button" class="ui-btn" data-print>Print</button>' +
      '</div>' +
      (changes ? F.changesHtml(changes, ret, ret.from === 'dean' ? 'you' : 'the Registrar') : '') +
      (reviewing ? '<p class="cb-muted cb-notehint">Found something in one subject? Use <strong>Note</strong> on its row; the notes go with your send-back.</p>' : '') +
      '<div id="cbChecklist"></div>' +
      (state.editing ? '<label class="ui-field-label" for="cbNotes">Notes for the reviewer (optional)</label><textarea class="ui-textarea cb-notes" id="cbNotes" maxlength="2000">' + esc(r.notes || '') + '</textarea>' : (r.notes ? '<p class="cb-notes-view"><strong>Notes:</strong> ' + esc(r.notes) + '</p>' : '')) +
      '<div id="cbForm" class="cf"></div>' +
      (actions.length ? '<div class="cb-actions">' + actions.join('') + '</div>' : '') +
      historyHtml(r);
    state.form = F.mount(doc.getElementById('cbForm'), {
      courses: r.courses || [],
      editable: state.editing,
      roomTypes: state.roomTypes,
      marks: changes ? F.diffMarks(changes) : null,
      removed: changes ? changes.removed : null,
      // the one fixing it sees the notes; the Dean checking it writes new ones
      rowNotes: ret && !reviewing ? ret.notes : null,
      noting: reviewing,
      onProblems: function (problems) {
        state.problems = problems;
        doc.getElementById('cbChecklist').innerHTML = F.checklistHtml(problems);
      },
      onChange: function (courses) {
        state.dirty = true;
        state.changes++;
        markSaved('Unsaved changes');
        clearTimeout(state.saveTimer);
        state.saveTimer = setTimeout(function () {
          try {
            global.localStorage.setItem(localKey(r.id), JSON.stringify({ savedAt: new Date().toISOString(), courses: courses }));
          } catch (e) {}
        }, 800);
        clearTimeout(state.autoTimer);
        state.autoTimer = setTimeout(autosave, 3000);
      }
    });
  }

  // other versions of this program that have subjects (for Compare and Copy subjects)
  function otherVersions(r) {
    return (r.history || []).filter(function (h) { return h.id !== r.id && h.courseCount > 0; });
  }

  function versionLabel(h) {
    return h.name + ' · version ' + h.version + ' · ' + F.statusText(h.status) + (h.deanDraft && state.role === 'dean' ? ' (your draft)' : '');
  }

  function versionCourses(id) {
    return global.McuApi.get(API + '?id=' + encodeURIComponent(id)).then(function (res) { return res.review.courses || []; });
  }

  // best one to compare with: the Program Chair's ↔ the Dean's own draft, else the newest other
  function defaultOther(r, others) {
    var pick = others.filter(function (h) { return r.deanDraft ? (!h.deanDraft && OPEN.indexOf(h.status) >= 0) : h.deanDraft; })[0];
    return (pick || others[0] || {}).id;
  }

  function openCompare(withId) {
    var r = state.review;
    var others = otherVersions(r);
    if (!others.length) return;
    F.compareDialog({
      title: 'Compare ' + r.programName + ' versions',
      nowLabel: '“' + r.name + '”',
      courses: state.form ? state.form.courses() : r.courses,
      choices: others.map(function (h) { return { id: h.id, label: versionLabel(h), short: '“' + h.name + '”' }; }),
      pick: withId || defaultOther(r, others),
      load: versionCourses
    });
  }

  function openCopy() {
    var r = state.review;
    var others = otherVersions(r);
    F.copyDialog({
      courses: state.form.courses(),
      choices: others.map(function (h) { return { id: h.id, label: versionLabel(h) }; }),
      pick: defaultOther(r, others),
      load: versionCourses,
      onAdd: function (list) {
        var res = state.form.merge(list);
        var n = res.added + res.replaced;
        notice('Copied ' + n + (n === 1 ? ' subject' : ' subjects') + (res.replaced ? ' (' + res.replaced + ' replaced yours)' : '') + '. Check them; it saves automatically.');
      }
    });
  }

  function ownershipNote(r) {
    if (state.role !== 'dean' || !r.hasDepartments) return '';
    var own = (r.history || []).filter(function (h) { return h.deanDraft && h.id !== r.id; })[0];
    if (r.deanDraft) {
      var main = (r.history || []).filter(function (h) { return ON_THE_WAY.indexOf(h.status) >= 0; })[0];
      return '<p class="cb-info">This is your own draft. It’s saved as you work.' +
        (main ? ' Version ' + main.version + ' (' + esc(main.status === 'pending_dean' ? 'waiting for your approval' : F.statusText(main.status).toLowerCase()) + ') is already on its way; only one version goes to the Registrar at a time.' : '') + '</p>';
    }
    if (own && r.status === 'pending_dean') {
      return '<p class="cb-info">If you approve this, it’s the version you send to the Registrar. Your drafts stay saved. ' +
        '<button type="button" class="cs-linkbtn" data-compare="' + esc(own.id) + '">Compare with “' + esc(own.name) + '”</button></p>';
    }
    return '';
  }

  // the main thing to do next, shown in the top bar too
  function nextStepHtml(can, r) {
    if (can.sendToRegistrar) {
      return '<button type="button" class="ui-btn ui-btn--primary" data-do="send_to_registrar">' + (r.status === 'pending_dean' ? 'Approve and send to Registrar' : 'Send to Registrar') + '</button>';
    }
    if (can.sendToDean) return '<button type="button" class="ui-btn ui-btn--primary" data-do="send_to_dean">Send to Dean</button>';
    return '';
  }

  function historyHtml(r) {
    var list = (r.history || []).filter(function (h) { return h.id !== r.id; });
    if (!list.length) return '';
    return '<details class="cb-history"><summary>Other versions (' + list.length + ')</summary><ul>' + list.map(function (h) {
      return '<li><button type="button" class="cs-linkbtn" data-open="' + esc(h.id) + '">Version ' + h.version + ' · ' + esc(h.academicYear) + '</button> ' + chip(h.status) +
        (h.registrarBy && h.status !== 'draft' ? ' <span class="cb-muted">accepted by ' + esc(h.registrarBy) + ' ' + esc(when(h.registrarAt)) + '</span>' : '') + '</li>';
    }).join('') + '</ul></details>';
  }

  function markSaved(text) {
    var el = doc.getElementById('cbSaved');
    if (el) el.textContent = text || '';
  }

  function currentBody() {
    var yearEl = doc.getElementById('cbYearEdit');
    var notesEl = doc.getElementById('cbNotes');
    return {
      action: 'save',
      id: state.review.id,
      academicYear: yearEl ? yearEl.value.trim() : state.review.academicYear,
      notes: notesEl ? notesEl.value : state.review.notes,
      courses: state.form.courses()
    };
  }

  // quiet save while editing: no redraw, so typing isn't interrupted
  function autosave() {
    if (!state.editing || !state.dirty || !state.review || state.autosaving) return;
    var body = currentBody();
    body.courses = body.courses.filter(function (c) { return !F.isBlankRow(c); });
    if (F.rowsWithoutCode(body.courses).length) {
      markSaved('Not saved yet: a subject needs its course code');
      return;
    }
    var seq = state.changes;
    var id = state.review.id;
    state.autosaving = true;
    api(body).then(function (res) {
      state.autosaving = false;
      if (!state.review || state.review.id !== id) return;
      state.review = res.review;
      if (seq === state.changes) {
        state.dirty = false;
        try { global.localStorage.removeItem(localKey(id)); } catch (e) {}
        markSaved('Saved automatically ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
      }
    }).catch(function () {
      state.autosaving = false;
      markSaved('Couldn’t save automatically. Your changes are kept on this computer.');
    });
  }

  function save() {
    clearTimeout(state.autoTimer);
    var body = currentBody();
    // rows left untouched after "+ Add subject" are dropped; a row with anything in it needs a code
    body.courses = body.courses.filter(function (c) { return !F.isBlankRow(c); });
    var noCode = F.rowsWithoutCode(body.courses);
    if (noCode.length) {
      var first = body.courses[noCode[0]];
      var msg = (noCode.length === 1 ? 'A subject has' : noCode.length + ' subjects have') + ' no course code' +
        (first.title ? ' (' + first.title + (noCode.length > 1 ? ', …' : '') + ')' : '') + '. Add the code before saving.';
      notice(msg, 'bad');
      if (state.form) state.form.focusRow(state.form.courses().indexOf(first));
      return Promise.reject(new Error(msg));
    }
    markSaved('Saving…');
    return api(body).then(function (res) {
      try { global.localStorage.removeItem(localKey(state.review.id)); } catch (e) {}
      state.dirty = false;
      state.review = res.review;
      var editing = state.editing;
      renderReview();
      state.editing = editing;
      markSaved('Saved ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
      return res.review;
    }).catch(function (e) {
      markSaved('');
      notice(e.message, 'bad');
      throw e;
    });
  }

  function askNote(title, label, hint, yes, onSend, rowNotes) {
    var d = ensureDialog('cbNote', 'cb-dialog');
    var noted = Object.keys(rowNotes || {});
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + esc(title) + '</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body">' +
      (noted.length ? '<div class="cb-info">Your notes on ' + noted.length + (noted.length === 1 ? ' subject go' : ' subjects go') + ' with it: ' + esc(noted.join(', ')) + '.</div>' : '') +
      '<label class="ui-field-label" for="cbNoteText">' + esc(label) + (noted.length ? ' (optional)' : '') + '</label><textarea class="ui-textarea" id="cbNoteText" maxlength="2000"></textarea>' +
      '<p class="ui-field-hint">' + esc(hint) + '</p><p class="ui-field-error" id="cbNoteErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--danger" id="cbNoteGo">' + esc(yes) + '</button></div>';
    d.showModal();
    doc.getElementById('cbNoteText').focus();
    doc.getElementById('cbNoteGo').onclick = function () {
      var note = doc.getElementById('cbNoteText').value.trim();
      var err = doc.getElementById('cbNoteErr');
      if (!note && !noted.length) {
        err.textContent = 'Write a short note so they know what to fix.';
        err.hidden = false;
        return;
      }
      onSend(note).then(function () { d.close(); }).catch(function (e) {
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  function confirmBox(title, message, yes, onYes) {
    var d = ensureDialog('cbConfirm', 'cb-dialog');
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + esc(title) + '</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p>' + esc(message) + '</p><p class="ui-field-error" id="cbConfirmErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="cbConfirmGo">' + esc(yes) + '</button></div>';
    d.showModal();
    doc.getElementById('cbConfirmGo').onclick = function () {
      var btn = this;
      btn.disabled = true;
      onYes().then(function () { d.close(); }).catch(function (e) {
        btn.disabled = false;
        var err = doc.getElementById('cbConfirmErr');
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  function step(action, extra, done) {
    var go = function () {
      return api(Object.assign({ action: action, id: state.review.id }, extra || {})).then(function (res) {
        var rv = res.review || (res.reviews && res.reviews[0]);
        notice(done);
        return load().then(function () {
          if (rv && rv.can) openReview(rv, false);
          else if (rv && rv.id) return fetchReview(rv.id, false);
          else renderList();
        });
      });
    };
    // send what's on screen first
    return state.dirty && state.editing ? save().then(go) : go();
  }

  function onAction(what) {
    var r = state.review;
    var blocked = F.errorsIn(state.problems).length;
    if ((what === 'send_to_dean' || what === 'send_to_registrar' || what === 'dean_approve') && blocked) {
      notice('Fix the ' + blocked + (blocked === 1 ? ' problem' : ' problems') + ' in the checklist first.', 'bad');
      doc.getElementById('cbChecklist').scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }
    if (what === 'send_to_dean') {
      confirmBox('Send to the Dean?', 'The Dean will check ' + r.programName + ' (version ' + r.version + ') and approve it or send it back. You can’t edit it while the Dean has it.', 'Send to Dean',
        function () { return step('send_to_dean', {}, 'Sent to the Dean.'); });
    } else if (what === 'dean_approve') {
      confirmBox('Approve this curriculum?', 'You can then send it to the Registrar, together with other approved curriculums if you like.', 'Approve',
        function () { return step('dean_decide', { accept: true }, 'Approved.'); });
    } else if (what === 'dean_reject') {
      var rowNotes = state.form ? state.form.notes() : {};
      askNote('Send back to the Program Chair?', 'What should the Program Chair fix?', 'They’ll see this on their Curriculum page, and each subject note on its row.', 'Send back', function (note) {
        return step('dean_decide', { accept: false, note: note, rowNotes: rowNotes }, 'Sent back to the Program Chair.');
      }, rowNotes);
    } else if (what === 'send_to_registrar') {
      confirmBox('Send to the Registrar?', 'The Records Officer and the University Registrar will review ' + r.programName + ' (version ' + r.version + '). Once accepted it becomes the active curriculum used for sectioning.', 'Send to Registrar',
        function () { return step('send_to_registrar', { ids: [r.id] }, 'Sent to the Registrar.'); });
    } else if (what === 'take_back') {
      confirmBox('Take it back?', 'The registrars will no longer see this version. You can edit it and send it again.', 'Take back',
        function () { return step('take_back', {}, 'Taken back.'); });
    } else if (what === 'discard') {
      confirmBox('Delete this draft?', 'Version ' + r.version + ' and its subjects will be deleted. This can’t be undone.', 'Delete draft', function () {
        return api({ action: 'discard', id: r.id }).then(function () {
          try { global.localStorage.removeItem(localKey(r.id)); } catch (e) {}
          notice('Draft deleted.');
          return load().then(renderList);
        });
      });
    }
  }

  function openBulkSend() {
    var items = [];
    state.programs.forEach(function (p) {
      var way = onTheWayOf(p);
      if (way && way.status !== 'pending_registrar') items.push({ p: p, v: way });
      else if (!way) draftsOf(p).forEach(function (v) { if (v.courseCount) items.push({ p: p, v: v }); });
    });
    var d = ensureDialog('cbBulk', 'cb-dialog');
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Send to the Registrar</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p class="cs-dialog-msg">Tick what to send, one per program. Sending one that’s still waiting for you also approves it.</p><ul class="cs-submit-list">' +
      items.map(function (x) {
        return '<li><label><input type="checkbox" data-bulk="' + esc(x.v.id) + '" data-prog="' + esc(x.p.id) + '"' + (x.v.status === 'dean_approved' ? ' checked' : '') + '><span class="cs-submit-name"><strong>' + esc(x.p.name) + ' · ' + esc(x.v.name) + '</strong><span>Version ' + x.v.version + ' · ' + esc(x.v.academicYear) + ' · ' + esc(F.statusText(x.v.status)) + '</span></span></label></li>';
      }).join('') + '</ul><p class="ui-field-error" id="cbBulkErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="cbBulkGo">Send to Registrar</button></div>';
    d.showModal();
    // one per program: ticking a draft unticks the program's others
    d.onchange = function (e) {
      var b = e.target;
      if (!b.matches('[data-bulk]') || !b.checked) return;
      d.querySelectorAll('[data-prog="' + b.getAttribute('data-prog') + '"]').forEach(function (o) { if (o !== b) o.checked = false; });
    };
    doc.getElementById('cbBulkGo').onclick = function () {
      var ids = Array.prototype.filter.call(d.querySelectorAll('[data-bulk]'), function (b) { return b.checked; }).map(function (b) { return b.getAttribute('data-bulk'); });
      var err = doc.getElementById('cbBulkErr');
      if (!ids.length) {
        err.textContent = 'Tick at least one.';
        err.hidden = false;
        return;
      }
      api({ action: 'send_to_registrar', ids: ids }).then(function () {
        d.close();
        notice('Sent ' + ids.length + (ids.length === 1 ? ' curriculum' : ' curriculums') + ' to the Registrar.');
        return load().then(renderList);
      }).catch(function (e) {
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  // ---------------------------------------------------------------- events

  function onClick(e) {
    var t = e.target;
    var el;
    if ((el = t.closest('[data-start]'))) return openStart(el.getAttribute('data-start'));
    if ((el = t.closest('[data-open]'))) {
      if (state.dirty && !global.confirmLeave) {
        // keep their work: it's in local storage and offered again
      }
      return fetchReview(el.getAttribute('data-open'), false).catch(function (er) { notice(er.message, 'bad'); });
    }
    if (t.closest('[data-bulk-send]')) return openBulkSend();
    if (t.closest('[data-back]')) {
      if (state.dirty) {
        return confirmBox('Leave without saving?', 'Your unsaved changes stay on this computer and are offered again when you open this version.', 'Leave', function () {
          state.dirty = false;
          return load().then(renderList);
        });
      }
      return load().then(renderList);
    }
    if (t.closest('[data-edit]')) {
      state.editing = true;
      return renderReview();
    }
    if (t.closest('[data-save]')) return save().catch(function () {});
    if (t.closest('[data-reset]')) {
      return confirmBox('Reset to the last save?', 'Changes since you last saved will be undone.', 'Reset', function () {
        try { global.localStorage.removeItem(localKey(state.review.id)); } catch (er) {}
        state.dirty = false;
        renderReview();
        return Promise.resolve();
      });
    }
    if (t.closest('[data-done]')) {
      var finish = function () {
        state.editing = false;
        renderReview();
      };
      return state.dirty ? save().then(finish).catch(function () {}) : finish();
    }
    if (t.closest('[data-restore]')) {
      try {
        var local = JSON.parse(global.localStorage.getItem(localKey(state.review.id)));
        state.review.courses = local.courses;
        state.editing = true;
        renderReview();
        state.dirty = true;
        markSaved('Restored, not saved yet');
      } catch (er) {}
      return;
    }
    if (t.closest('[data-forget]')) {
      try { global.localStorage.removeItem(localKey(state.review.id)); } catch (er) {}
      return renderReview();
    }
    if ((el = t.closest('[data-goto]'))) return state.form && state.form.focusRow(Number(el.getAttribute('data-goto')));
    if ((el = t.closest('[data-compare]'))) return openCompare(el.getAttribute('data-compare'));
    if ((el = t.closest('[data-send-draft]'))) return sendDraft(el.getAttribute('data-send-draft'));
    if ((el = t.closest('[data-rename]'))) {
      var rid = el.getAttribute('data-rename');
      var cur = state.review && state.review.id === rid ? state.review : (findVersion(rid) || {}).v;
      return renameDialog(rid, cur ? cur.name : '', function (fresh) {
        if (state.review && state.review.id === rid) {
          state.review.name = fresh.name;
          var nameEl = root.querySelector('.cb-draftname strong');
          if (nameEl) nameEl.textContent = fresh.name;
          return;
        }
        load().then(renderList);
      });
    }
    if (t.closest('[data-copy-from]')) return openCopy();
    if ((el = t.closest('[data-do]'))) return onAction(el.getAttribute('data-do'));
    var copy = function () {
      return Object.assign({}, state.review, { courses: state.form ? state.form.courses() : state.review.courses });
    };
    if (t.closest('[data-csv]')) return F.csv(copy());
    if (t.closest('[data-print]')) return F.print(copy());
    if (t.closest('[data-pdf]')) {
      return F.pdf(copy()).catch(function (er) { notice(er.message, 'bad'); });
    }
  }

  function init() {
    root = doc.getElementById('cbRoot');
    if (!root || !global.McuApi) return;
    root.addEventListener('click', onClick);
    var template = doc.getElementById('cbTemplate');
    if (template) template.addEventListener('click', function () { F.csvTemplate(state.roomTypes); });
    var xlsx = doc.getElementById('cbTemplateXlsx');
    if (xlsx) xlsx.addEventListener('click', excelTemplate);
    global.addEventListener('beforeunload', function (e) {
      if (state.dirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
    root.innerHTML = '<p class="cb-loading">Loading your programs…</p>';
    load()
      .then(function () {
        var want = new URLSearchParams(global.location.search).get('open');
        if (want) return fetchReview(want, false);
        renderList();
      })
      .catch(function (e) {
        root.innerHTML = '<div class="ui-empty"><h3>Curriculums aren’t available</h3><p>' + esc(e.message) + '</p></div>';
      });
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
