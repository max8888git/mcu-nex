/**
 * Reports → Finished curriculums (roles/staff/finished-curriculums): the curriculums that
 * don't run any more, each with its subjects and the approved schedules of every term its
 * sections were scheduled in. The Records Officer and University Registrar archive and restore;
 * only the University Registrar deletes an unused one.
 * Server: curricula.php ?finished=1, ?finished_id=cur-N (curriculum-archive.php); PATCH status.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var F = global.McuCurriculumForm;
  var DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  var DAY_NAMES = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday' };
  var root;
  var state = { data: null, find: '' };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function dateText(v) {
    var d = new Date(String(v || '').replace(' ', 'T'));
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function time12(t) {
    var m = String(t || '').match(/^(\d{1,2}):(\d{2})/);
    if (!m) return t || '';
    var h = Number(m[1]);
    return (h % 12 || 12) + ':' + m[2] + (h < 12 ? ' AM' : ' PM');
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

  // ---------------------------------------------------------------- the list

  function matches(c) {
    var q = state.find.toLowerCase();
    return !q || (c.programName + ' ' + c.collegeName + ' ' + c.name + ' ' + c.academicYear).toLowerCase().indexOf(q) >= 0;
  }

  // Delete is only for the University Registrar, and only for an archived curriculum nothing ever used.
  // The server decides (deleteBlocked says why not) and checks again when the delete is sent.
  function deleteHtml(c) {
    if (!state.data.canDelete) return '';
    if (c.deleteBlocked) return '<span class="fc-keep" title="' + esc(c.deleteBlocked) + '">Kept for history</span>';
    return '<button type="button" class="ui-btn ui-btn--danger" data-delete="' + esc(c.id) + '">Delete</button>';
  }

  function rowHtml(c, kind) {
    var d = state.data;
    var meta = [esc(c.collegeName), 'effective ' + esc(c.academicYear), plural(c.courseCount, 'subject', 'subjects'),
      c.termsUsed ? 'scheduled in ' + plural(c.termsUsed, 'term', 'terms') : 'never scheduled'];
    if (kind === 'archived') meta.push('archived ' + esc(dateText(c.updatedAt)));
    return '<li class="fc-row">' +
      '<div class="fc-row-main"><strong class="fc-name">' + esc(c.programName) + '</strong><span class="fc-curname">' + esc(c.name) + '</span>' +
      '<span class="fc-meta">' + meta.join(' · ') + '</span></div>' +
      '<div class="fc-row-actions"><button type="button" class="ui-btn" data-view="' + esc(c.id) + '">View</button>' +
      (d.canArchive ? (kind === 'archived'
        ? '<button type="button" class="ui-btn" data-restore="' + esc(c.id) + '">Restore</button>' + deleteHtml(c)
        : '<button type="button" class="ui-btn ui-btn--primary" data-archive="' + esc(c.id) + '">Archive</button>') : '') +
      '</div></li>';
  }

  function render() {
    var d = state.data;
    var archived = d.archived.filter(matches);
    var notInUse = d.notInUse.filter(matches);
    var html = '<div class="fc-bar"><label class="ct-field ct-find"><span>Search</span><input class="ui-input" type="search" data-find placeholder="Program, college or school year" value="' + esc(state.find) + '"></label></div>';
    html += '<section class="fc-group"><h2 class="fc-title">Finished curriculums <span class="fc-count">' + d.archived.length + '</span></h2>' +
      '<p class="fc-lead">Curriculums that don’t run any more, archived here' +
      '. Open one to see its subjects and the schedules of every term it was used in.' +
      (d.canDelete ? ' One that nothing ever used (a wrong upload, a duplicate) can be deleted; the rest are kept as history.' : '') + '</p>' +
      (archived.length ? '<ul class="fc-list">' + archived.map(function (c) { return rowHtml(c, 'archived'); }).join('') + '</ul>'
        : '<p class="fc-empty">' + (d.archived.length ? 'Nothing matches.' : 'No finished curriculums yet.') + '</p>') + '</section>';
    html += '<section class="fc-group fc-group--quiet"><h2 class="fc-title">Not used this term <span class="fc-count">' + d.notInUse.length + '</span></h2>' +
      '<p class="fc-lead">Active curriculums that no section follows' + (d.term ? ' in ' + esc(d.term.label) : '') + '. ' +
      (d.canArchive ? 'If one won’t run again (its last batch has graduated), archive it.' : 'The University Registrar archives the ones that won’t run again.') +
      ' ' + plural(d.inUse, 'curriculum is', 'curriculums are') + ' in use this term.</p>' +
      (notInUse.length ? '<ul class="fc-list">' + notInUse.map(function (c) { return rowHtml(c, 'active'); }).join('') + '</ul>'
        : '<p class="fc-empty">' + (d.notInUse.length ? 'Nothing matches.' : 'Every active curriculum is in use.') + '</p>') + '</section>';
    root.innerHTML = html;
  }

  // ---------------------------------------------------------------- one curriculum

  function dialog() {
    var d = doc.getElementById('fcDialog');
    if (d) return d;
    d = doc.createElement('dialog');
    d.id = 'fcDialog';
    d.className = 'ui-dialog fc-dialog';
    doc.body.appendChild(d);
    d.addEventListener('click', function (e) {
      if (e.target === d || e.target.closest('[data-close]')) d.close();
      var tab = e.target.closest('[data-fc-tab]');
      if (tab) showTab(tab.getAttribute('data-fc-tab'));
    });
    return d;
  }

  function showTab(which) {
    var d = dialog();
    d.querySelectorAll('[data-fc-tab]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-fc-tab') === which); });
    d.querySelectorAll('[data-fc-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-fc-pane') !== which; });
  }

  function kind(c) {
    return c.component === 'lab' || c.component === 'laboratory' ? 'Lab' : 'Lecture';
  }

  function termHtml(t, open) {
    var rows = [];
    t.classes.forEach(function (c) {
      var meetings = (c.meetings || []).length ? c.meetings : [null];
      meetings.forEach(function (m) {
        rows.push({
          section: c.sectionName || '', code: (c.courseCode || '') + (kind(c) === 'Lab' && !/L$/.test(c.courseCode || '') ? 'L' : ''),
          title: c.courseTitle || '', kind: kind(c), day: m ? m.day : '', start: m ? m.start : '', end: m ? m.end : '',
          room: m ? (m.roomCode || '') : '', placed: !!m
        });
      });
    });
    rows.sort(function (a, b) {
      return a.section.localeCompare(b.section, undefined, { numeric: true }) || DAYS.indexOf(a.day) - DAYS.indexOf(b.day) || a.start.localeCompare(b.start);
    });
    var sections = {};
    rows.forEach(function (r) { (sections[r.section] = sections[r.section] || []).push(r); });
    return '<details class="fc-term"' + (open ? ' open' : '') + '><summary><strong>' + esc(t.label) + '</strong>' + (t.current ? ' <span class="ui-chip ui-chip--neutral">current term</span>' : '') +
      '<span class="fc-meta">' + plural(Object.keys(sections).length, 'section', 'sections') + ' · ' + plural(t.classes.length, 'class', 'classes') + '</span></summary>' +
      Object.keys(sections).map(function (s) {
        return '<h4 class="fc-section">' + esc(s) + '</h4><div class="ct-scroll"><table class="ct-table"><thead><tr><th>Day</th><th>Time</th><th>Code</th><th>Subject</th><th>Type</th><th>Room</th></tr></thead><tbody>' +
          sections[s].map(function (r) {
            return '<tr><td>' + esc(r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled') + '</td><td class="ct-nowrap">' + (r.placed ? esc(time12(r.start) + ' – ' + time12(r.end)) : '—') + '</td>' +
              '<td class="ct-nowrap"><strong>' + esc(r.code) + '</strong></td><td>' + esc(r.title) + '</td><td>' + esc(r.kind) + '</td><td>' + esc(r.room || '—') + '</td></tr>';
          }).join('') + '</tbody></table></div>';
      }).join('') + '</details>';
  }

  function open(id) {
    var d = dialog();
    d.innerHTML = '<div class="ui-dialog-body"><p class="fc-meta">Loading…</p></div>';
    if (!d.open) d.showModal();
    global.McuApi.get('curricula.php?finished_id=' + encodeURIComponent(id)).then(function (res) {
      var cur = res.curriculum;
      d.innerHTML = '<div class="ui-dialog-head"><div><h2 class="ui-dialog-title">' + esc(cur.departmentName) + '</h2>' +
        '<p class="fc-meta">' + esc(cur.collegeName) + ' · ' + esc(cur.name) + ' · effective ' + esc(cur.academicYear) + ' · ' + (cur.status === 'archived' ? 'finished' : 'active') + '</p></div>' +
        '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
        '<div class="ui-dialog-body">' +
        '<div class="fc-tabs" role="tablist"><button type="button" class="fc-tab is-active" data-fc-tab="schedules">Schedules by term (' + res.terms.length + ')</button>' +
        '<button type="button" class="fc-tab" data-fc-tab="subjects">Subjects (' + cur.courses.length + ')</button></div>' +
        '<div data-fc-pane="schedules">' + (res.terms.length ? res.terms.map(function (t, i) { return termHtml(t, i === 0); }).join('')
          : '<p class="fc-empty">No approved schedule uses this curriculum. (Schedules approved before Oct 17, 2026 are matched by section; a term without its sections can’t be shown.)</p>') + '</div>' +
        '<div data-fc-pane="subjects" hidden><div id="fcSubjects" class="cf"></div></div></div>' +
        '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Close</button></div>';
      F.mount(doc.getElementById('fcSubjects'), { courses: cur.courses.map(F.fromSheet), editable: false, roomTypes: [] });
    }).catch(function (e) {
      d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Couldn’t open it</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
        '<div class="ui-dialog-body"><p class="ui-field-error">' + esc(e.message) + '</p></div>';
    });
  }

  function confirmStatus(id, status) {
    var d = state.data;
    var c = d.archived.concat(d.notInUse).filter(function (x) { return x.id === id; })[0];
    if (!c) return;
    var dlg = dialog();
    var archive = status === 'archived';
    dlg.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + (archive ? 'Archive' : 'Restore') + ' ' + esc(c.programName) + ' (effective ' + esc(c.academicYear) + ')?</h2>' +
      '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p class="cs-dialog-msg">' + (archive
        ? 'It moves to Finished curriculums: it can’t be picked for new sections, and its subjects and schedules stay here to look up. You can restore it later.'
        : 'It becomes active again and can be used for sections.') + ' This is kept in the Transaction log.</p><p class="ui-field-error" id="fcErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="fcGo">' + (archive ? 'Archive' : 'Restore') + '</button></div>';
    if (!dlg.open) dlg.showModal();
    doc.getElementById('fcGo').onclick = function () {
      var btn = this;
      btn.disabled = true;
      global.McuApi.send('PATCH', 'curricula.php', { id: id, status: status }).then(function () {
        dlg.close();
        notice(archive ? 'Archived. It’s in Finished curriculums.' : 'Restored. It’s active again.');
        return load();
      }).catch(function (e) {
        btn.disabled = false;
        var err = doc.getElementById('fcErr');
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  function confirmDelete(id) {
    var c = state.data.archived.filter(function (x) { return x.id === id; })[0];
    if (!c) return;
    var dlg = dialog();
    dlg.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Delete ' + esc(c.programName) + ' (effective ' + esc(c.academicYear) + ')?</h2>' +
      '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><p class="cs-dialog-msg"><strong>' + esc(c.name) + '</strong> and its ' + plural(c.courseCount, 'subject', 'subjects') +
      ' will be permanently removed. No section or approved timetable ever used it. This can\u2019t be undone, and it is kept in the Transaction log.</p>' +
      '<p class="cs-dialog-msg">If you may need it again, cancel and keep it archived instead.</p>' +
      '<p class="ui-field-error" id="fcErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--danger" id="fcGo">Delete curriculum</button></div>';
    if (!dlg.open) dlg.showModal();
    doc.getElementById('fcGo').onclick = function () {
      var btn = this;
      btn.disabled = true;
      global.McuApi.send('DELETE', 'curricula.php?id=' + encodeURIComponent(id)).then(function () {
        dlg.close();
        notice('Deleted ' + c.programName + ' (effective ' + c.academicYear + ').');
        return load();
      }).catch(function (e) {
        btn.disabled = false;
        var err = doc.getElementById('fcErr');
        err.textContent = e.message;
        err.hidden = false;
      });
    };
  }

  function load() {
    return global.McuApi.get('curricula.php?finished=1').then(function (res) {
      state.data = res;
      render();
    }).catch(function (e) {
      root.innerHTML = '<div class="ui-empty"><h3>Couldn’t load Finished curriculums</h3><p>' + esc(e.message) + '</p></div>';
    });
  }

  function init() {
    root = doc.getElementById('fcRoot');
    if (!root || !global.McuApi || !F) return;
    root.addEventListener('click', function (e) {
      var el;
      if ((el = e.target.closest('[data-view]'))) return open(el.getAttribute('data-view'));
      if ((el = e.target.closest('[data-archive]'))) return confirmStatus(el.getAttribute('data-archive'), 'archived');
      if ((el = e.target.closest('[data-restore]'))) return confirmStatus(el.getAttribute('data-restore'), 'active');
      if ((el = e.target.closest('[data-delete]'))) return confirmDelete(el.getAttribute('data-delete'));
    });
    root.addEventListener('input', function (e) {
      if (!e.target.matches('[data-find]')) return;
      state.find = e.target.value;
      var pos = e.target.selectionStart;
      render();
      var el = root.querySelector('[data-find]');
      if (el) { el.focus(); el.setSelectionRange(pos, pos); }
    });
    root.innerHTML = '<p class="fc-meta">Loading…</p>';
    load();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
