/**
 * Registrars' review of curriculums from the colleges (roles/staff/curriculums/review):
 * what's waiting (accept or send back, one or several), what the colleges are still
 * working on, and accepted ones (request a revision). Both the Records Officer and the
 * University Registrar see it; whoever decides first is enough. Server: curriculum-reviews.php.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var F = global.McuCurriculumForm;
  var esc = F.esc;
  var API = 'curriculum-reviews.php';

  var state = { tab: 'pending', data: null, selected: {}, review: null, roomTypes: [] };
  var root = null;

  function notice(text, tone) {
    var el = doc.createElement('div');
    el.className = 'ui-notice' + (tone === 'bad' ? ' ui-notice--bad' : '');
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  function when(v) {
    if (!v) return '';
    var d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function chip(status) {
    var tone = { draft: 'neutral', pending_dean: 'waiting', dean_approved: 'waiting', pending_registrar: 'waiting', accepted: 'done', superseded: 'neutral' }[status] || 'neutral';
    return '<span class="ui-chip ui-chip--' + tone + '">' + esc(status === 'pending_registrar' ? 'Waiting for review' : F.statusText(status)) + '</span>';
  }

  function load() {
    return global.McuApi.get(API + '?inbox=1').then(function (res) {
      state.data = res;
      state.roomTypes = res.roomTypes || [];
      var keep = {};
      (res.pending || []).forEach(function (v) { if (state.selected[v.id]) keep[v.id] = true; });
      state.selected = keep;
    });
  }

  function rowsFor(tab) {
    var d = state.data || {};
    return tab === 'pending' ? d.pending || [] : tab === 'progress' ? d.inProgress || [] : d.accepted || [];
  }

  function renderList() {
    state.review = null;
    var d = state.data;
    var tabs = [
      ['pending', 'Waiting for review', (d.pending || []).length],
      ['progress', 'At the colleges', (d.inProgress || []).length],
      ['accepted', 'Accepted', (d.accepted || []).length]
    ];
    var rows = rowsFor(state.tab);
    var picked = rows.filter(function (v) { return state.selected[v.id]; });
    var html = '<div class="ui-tabs cr-tabs" role="tablist">' + tabs.map(function (t) {
      return '<button type="button" class="ui-tab" role="tab" data-tab="' + t[0] + '" aria-selected="' + (state.tab === t[0]) + '">' + t[1] + ' <span class="ui-tab-count">' + t[2] + '</span></button>';
    }).join('') + '</div>';
    if (state.tab === 'pending' && rows.length) {
      html += '<div class="cs-toolbar cr-toolbar"><div class="cs-select"><span class="cs-picked">' + (picked.length ? picked.length + ' selected' : 'Tick curriculums to accept several at once') + '</span>' +
        '<button type="button" class="cs-linkbtn" data-all>Select all (' + rows.length + ')</button>' + (picked.length ? '<button type="button" class="cs-linkbtn" data-none>Clear</button>' : '') + '</div>' +
        '<div class="cs-buttons"><button type="button" class="ui-btn ui-btn--primary" data-bulk-accept' + (picked.length ? '' : ' disabled') + '>Accept' + (picked.length ? ' <span class="cs-count">' + picked.length + '</span>' : '') + '</button></div></div>';
    }
    if (!rows.length) {
      html += '<div class="ui-empty"><h3>' + (state.tab === 'pending' ? 'Nothing is waiting for review' : state.tab === 'progress' ? 'Nothing in progress' : 'No accepted curriculums from the colleges yet') + '</h3>' +
        '<p>' + (state.tab === 'pending' ? 'Curriculums the Deans send show here. You’ll get a notification too.' : 'Curriculums the colleges are working on show here.') + '</p></div>';
    } else {
      html += '<ul class="cs-list cr-list">' + rows.map(function (v) {
        var sel = !!state.selected[v.id];
        return '<li class="cs-row cr-row' + (sel ? ' is-selected' : '') + '">' +
          (state.tab === 'pending'
            ? '<label class="cs-pick"><input type="checkbox" data-pick="' + esc(v.id) + '"' + (sel ? ' checked' : '') + ' aria-label="Select ' + esc(v.programName) + '"><span class="cs-name">' + esc(v.programName) + '</span></label>'
            : '<span class="cs-pick"><span class="cs-name">' + esc(v.programName) + '</span></span>') +
          '<span class="cs-status">' + chip(v.status) + '<span class="cs-when">' + esc(v.collegeName) + '</span></span>' +
          '<span class="cs-work"><span class="cs-progress">“' + esc(v.name) + '” · Version ' + v.version + ' · ' + esc(v.academicYear) + ' · ' + v.courseCount + ' subjects, ' + v.totalUnits + ' units</span>' +
          '<span class="cs-when">' + (v.status === 'pending_registrar' ? 'Sent ' + esc(when(v.sentRegistrarAt)) + (v.deanBy ? ' by ' + esc(v.deanBy) : '')
            : v.status === 'accepted' ? 'Accepted ' + esc(when(v.registrarAt)) + (v.registrarBy ? ' by ' + esc(v.registrarBy) : '')
            : 'Updated ' + esc(when(v.updatedAt))) + '</span>' +
          '<button type="button" class="cs-linkbtn" data-open="' + esc(v.id) + '">' + (v.status === 'pending_registrar' ? 'Review' : 'View') + '</button></span>' +
          (v.lastReturnNote && v.status === 'draft' ? '<p class="cs-note cs-note--back"><strong>' + (v.lastReturnFrom === 'dean' ? 'Dean' : 'Registrar') + ':</strong> ' + esc(v.lastReturnNote) + '</p>' : '') +
          '</li>';
      }).join('') + '</ul>';
    }
    root.innerHTML = html;
  }

  function open(id) {
    return global.McuApi.get(API + '?id=' + encodeURIComponent(id)).then(function (res) {
      state.review = res.review;
      renderReview();
      global.scrollTo(0, 0);
    }).catch(function (e) { notice(e.message, 'bad'); });
  }

  function renderReview() {
    var r = state.review;
    var can = r.can || {};
    var actions = [];
    if (can.registrarDecide) {
      actions.push('<button type="button" class="ui-btn ui-btn--danger" data-reject>Send back</button>');
      actions.push('<button type="button" class="ui-btn ui-btn--primary" data-accept>Accept</button>');
    }
    if (can.requestRevision) actions.push('<button type="button" class="ui-btn" data-revise>Request a revision</button>');
    // a version that comes back after you sent it back: what changed; notes on single subjects go with a send-back
    var ret = r.returned;
    var changes = ret && can.registrarDecide ? F.diff(ret.courses || [], r.courses || []) : null;
    var noting = !!(can.registrarDecide || can.requestRevision);
    var others = (r.history || []).filter(function (h) { return h.id !== r.id && h.courseCount > 0; });
    var trail = [];
    if (r.createdBy) trail.push('Made by ' + esc(r.createdBy));
    if (r.deanBy) trail.push('approved by ' + esc(r.deanBy) + (r.deanAt ? ' on ' + esc(when(r.deanAt)) : ''));
    if (r.status === 'accepted' && r.registrarBy) trail.push('accepted by ' + esc(r.registrarBy) + ' on ' + esc(when(r.registrarAt)));
    root.innerHTML =
      '<button type="button" class="cb-back" data-back>&larr; All curriculums</button>' +
      '<div class="cb-head"><div><h2>' + esc(r.programName) + '</h2><p class="cb-draftname"><strong>' + esc(r.name) + '</strong></p><p>' + esc(r.collegeName) + ' · Version ' + r.version + ' · Effective ' + esc(r.academicYear) + '</p>' +
      (trail.length ? '<p class="cb-muted">' + trail.join(', ') + '.</p>' : '') + '</div><div class="cb-head-side">' + chip(r.status) + '</div></div>' +
      (r.notes ? '<p class="cb-notes-view"><strong>Notes from the college:</strong> ' + esc(r.notes) + '</p>' : '') +
      '<div class="cb-toolbar"><span class="cb-muted">Check it for mistakes before accepting. Problems the system finds are listed below.</span><span class="cb-spacer"></span>' +
      (others.length ? '<button type="button" class="ui-btn" data-compare>Compare…</button>' : '') +
      '<button type="button" class="ui-btn" data-csv>Download CSV</button><button type="button" class="ui-btn" data-pdf>Download PDF</button><button type="button" class="ui-btn" data-print>Print</button></div>' +
      (changes ? F.changesHtml(changes, ret, ret.from === 'registrar' ? '' : 'the Dean') : '') +
      (noting ? '<p class="cb-muted cb-notehint">Found something in one subject? Use <strong>Note</strong> on its row; the notes go with your send-back.</p>' : '') +
      F.checklistHtml(r.problems || [], true) +
      '<div id="crForm" class="cf"></div>' +
      (actions.length ? '<div class="cb-actions">' + actions.join('') + '</div>' : '');
    state.form = F.mount(doc.getElementById('crForm'), {
      courses: r.courses || [], editable: false, roomTypes: state.roomTypes,
      marks: changes ? F.diffMarks(changes) : null, removed: changes ? changes.removed : null, noting: noting
    });
  }

  function openCompare() {
    var r = state.review;
    var others = (r.history || []).filter(function (h) { return h.id !== r.id && h.courseCount > 0; });
    var active = others.filter(function (h) { return h.status === 'accepted'; })[0];
    F.compareDialog({
      title: 'Compare ' + r.programName + ' versions',
      nowLabel: 'version ' + r.version,
      courses: r.courses || [],
      choices: others.map(function (h) { return { id: h.id, label: h.name + ' · version ' + h.version + ' · ' + F.statusText(h.status), short: '“' + h.name + '”' }; }),
      pick: (active || others[0]).id,
      load: function (id) {
        return global.McuApi.get(API + '?id=' + encodeURIComponent(id)).then(function (res) { return res.review.courses || []; });
      }
    });
  }

  function dialog(id) {
    var d = doc.getElementById(id);
    if (d) return d;
    d = doc.createElement('dialog');
    d.id = id;
    d.className = 'ui-dialog cb-dialog';
    doc.body.appendChild(d);
    d.addEventListener('click', function (e) {
      if (e.target.closest('[data-close]') || e.target === d) d.close();
    });
    return d;
  }

  function ask(opts) {
    var d = dialog('crAsk');
    d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + esc(opts.title) + '</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p class="cs-dialog-msg">' + esc(opts.message) + '</p>' +
      (opts.list ? '<ul class="cs-dialog-list">' + opts.list.map(function (x) { return '<li><strong>' + esc(x) + '</strong></li>'; }).join('') + '</ul>' : '') +
      (opts.rowNotes && Object.keys(opts.rowNotes).length ? '<div class="cb-info">Your notes on ' + Object.keys(opts.rowNotes).length + (Object.keys(opts.rowNotes).length === 1 ? ' subject go' : ' subjects go') + ' with it: ' + esc(Object.keys(opts.rowNotes).join(', ')) + '.</div>' : '') +
      (opts.note ? '<label class="ui-field-label cr-note-label" for="crNote">' + esc(opts.note) + (opts.rowNotes && Object.keys(opts.rowNotes).length ? ' (optional)' : '') + '</label><textarea class="ui-textarea" id="crNote" maxlength="2000"></textarea>' : '') +
      '<p class="ui-field-error" id="crAskErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ' + (opts.danger ? 'ui-btn--danger' : 'ui-btn--primary') + '" id="crAskGo">' + esc(opts.yes) + '</button></div>';
    d.showModal();
    var noteEl = doc.getElementById('crNote');
    if (noteEl) noteEl.focus();
    doc.getElementById('crAskGo').onclick = function () {
      var btn = this;
      var err = doc.getElementById('crAskErr');
      var note = noteEl ? noteEl.value.trim() : '';
      if (opts.note && !note && !(opts.rowNotes && Object.keys(opts.rowNotes).length)) {
        err.textContent = 'Write a short note so the college knows what to change.';
        err.hidden = false;
        return;
      }
      btn.disabled = true;
      opts.go(note).then(function () { d.close(); }).catch(function (e) {
        btn.disabled = false;
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  function decide(ids, accept, note, rowNotes) {
    return global.McuApi.send('POST', API, { action: 'registrar_decide', ids: ids, accept: accept, note: note || '', rowNotes: rowNotes || {} }).then(function () {
      state.selected = {};
      return load().then(renderList);
    });
  }

  function onClick(e) {
    var t = e.target;
    var el;
    if ((el = t.closest('[data-tab]'))) {
      state.tab = el.getAttribute('data-tab');
      return renderList();
    }
    if ((el = t.closest('[data-pick]'))) {
      var id = el.getAttribute('data-pick');
      if (el.checked) state.selected[id] = true;
      else delete state.selected[id];
      return renderList();
    }
    if (t.closest('[data-all]')) {
      rowsFor('pending').forEach(function (v) { state.selected[v.id] = true; });
      return renderList();
    }
    if (t.closest('[data-none]')) {
      state.selected = {};
      return renderList();
    }
    if (t.closest('[data-bulk-accept]')) {
      var picked = rowsFor('pending').filter(function (v) { return state.selected[v.id]; });
      return ask({
        title: 'Accept ' + picked.length + (picked.length === 1 ? ' curriculum?' : ' curriculums?'),
        message: 'Each becomes the active curriculum for its program and is used for sectioning. The colleges are told.',
        list: picked.map(function (v) { return v.programName + ' · ' + v.name + ' · ' + v.academicYear + ' (version ' + v.version + ')'; }),
        yes: 'Accept',
        go: function () {
          return decide(picked.map(function (v) { return v.id; }), true).then(function () { notice('Accepted ' + picked.length + '.'); });
        }
      });
    }
    if ((el = t.closest('[data-open]'))) return open(el.getAttribute('data-open'));
    if (t.closest('[data-back]')) return load().then(renderList);
    var r = state.review;
    if (!r) return;
    if (t.closest('[data-accept]')) {
      if (F.errorsIn(r.problems || []).length) {
        return notice('This version has problems in the checklist. Send it back so the college can fix them.', 'bad');
      }
      return ask({
        title: 'Accept this curriculum?',
        message: r.programName + ' ' + r.academicYear + ' (version ' + r.version + ') becomes the active curriculum and is used for sectioning. The Dean and Program Chair are told.',
        yes: 'Accept',
        go: function () { return decide([r.id], true).then(function () { notice('Accepted.'); }); }
      });
    }
    if (t.closest('[data-reject]')) {
      return ask({
        title: 'Send back to the college?',
        message: 'The Dean and Program Chair see your note and can fix it and send it again.',
        note: 'What should they fix?',
        rowNotes: state.form ? state.form.notes() : {},
        yes: 'Send back',
        danger: true,
        go: function (note) { return decide([r.id], false, note, state.form ? state.form.notes() : {}).then(function () { notice('Sent back.'); }); }
      });
    }
    if (t.closest('[data-revise]')) {
      return ask({
        title: 'Request a revision?',
        message: 'A new version opens for the Dean and Program Chair to fix. This accepted curriculum stays active until the revision is accepted.',
        note: 'What needs to change?',
        rowNotes: state.form ? state.form.notes() : {},
        yes: 'Request revision',
        go: function (note) {
          return global.McuApi.send('POST', API, { action: 'request_revision', id: r.id, note: note, rowNotes: state.form ? state.form.notes() : {} }).then(function () {
            notice('The college was asked to revise it.');
            return load().then(renderList);
          });
        }
      });
    }
    if (t.closest('[data-compare]')) return openCompare();
    if (t.closest('[data-csv]')) return F.csv(r);
    if (t.closest('[data-print]')) return F.print(r);
    if (t.closest('[data-pdf]')) return F.pdf(r).catch(function (er) { notice(er.message, 'bad'); });
  }

  function init() {
    root = doc.getElementById('crRoot');
    if (!root || !global.McuApi) return;
    root.addEventListener('click', onClick);
    root.innerHTML = '<p class="cb-loading">Loading curriculums from the colleges…</p>';
    load()
      .then(function () {
        var want = new URLSearchParams(global.location.search).get('open');
        if (want) return open(want);
        renderList();
      })
      .catch(function (e) {
        root.innerHTML = '<div class="ui-empty"><h3>Couldn’t load curriculums</h3><p>' + esc(e.message) + '</p></div>';
      });
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
