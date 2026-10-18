/**
 * Reports → Final report: where each college's approved timetable stands, above the
 * campus-wide view that schedule-page.js draws (by section / by room / by class).
 * Clicking a college sets the College filter; colleges with nothing approved are marked
 * in the filter. Server: schedule.php?colleges=1 (college_status_overview).
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var root = doc.getElementById('frColleges');
  var yearEl = doc.getElementById('schedYear');
  var semEl = doc.getElementById('schedSemester');
  var colEl = doc.getElementById('schedCollege');
  if (!root || !global.McuApi) return;

  var colleges = [];
  var loadedFor = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function dateText(s) {
    var m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return '';
    return new Date(+m[1], +m[2] - 1, +m[3]).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function stateOf(c) {
    if (c.status === 'published') return { tone: 'ok', text: 'Approved ' + dateText(c.publishedAt) };
    if (c.hasPublished) {
      return {
        tone: 'warn',
        text: 'Approved ' + dateText(c.publishedAt),
        note: c.status === 'pending_vpaa' ? 'A new version is waiting for the VPAA; this one stays in use until then.' : 'A new version is with the registrars; this one stays in use until the VPAA approves it.'
      };
    }
    return { tone: 'none', text: 'Not approved yet' };
  }

  function picked() {
    return colEl ? colEl.value : '';
  }

  function render() {
    if (!colleges.length) {
      root.innerHTML = '';
      return;
    }
    var approved = colleges.filter(function (c) { return c.hasPublished; }).length;
    var pick = picked();
    var pickedCollege = colleges.filter(function (c) { return c.id === pick; })[0];
    root.innerHTML =
      '<div class="fr-head"><strong>Approved timetables</strong> <span class="fr-sub">' + approved + ' of ' + colleges.length + ' colleges · click one to show only that college</span></div>' +
      '<div class="fr-list">' + colleges.map(function (c) {
        var st = stateOf(c);
        return '<button type="button" class="fr-college fr-college--' + st.tone + (c.id === pick ? ' is-picked' : '') + '" data-college="' + esc(c.id) + '"' +
          (st.note ? ' title="' + esc(st.note) + '"' : '') + ' aria-pressed="' + (c.id === pick ? 'true' : 'false') + '">' +
          '<span class="fr-name">' + esc(c.name) + '</span>' +
          '<span class="fr-meta">' + esc(st.text) + (c.hasPublished ? ' · ' + (c.publishedClasses || 0) + ' classes' : '') + '</span>' +
          (st.note ? '<span class="fr-note">' + esc(st.note) + '</span>' : '') + '</button>';
      }).join('') + '</div>' +
      (pickedCollege && !pickedCollege.hasPublished
        ? '<p class="fr-empty">' + esc(pickedCollege.name) + ' has no approved timetable for this term yet. It shows here once the VPAA approves it.</p>'
        : '');
    markOptions();
  }

  // the College filter: mark colleges with nothing approved (schedule-page.js refills it, so redo it then)
  function markOptions() {
    if (!colEl) return;
    var by = {};
    colleges.forEach(function (c) { by[c.id] = c; });
    Array.prototype.forEach.call(colEl.options, function (o) {
      var c = by[o.value];
      var base = o.getAttribute('data-name') || o.textContent;
      o.setAttribute('data-name', base);
      var text = c && !c.hasPublished ? base + ' (not approved yet)' : base;
      if (o.textContent !== text) o.textContent = text;
    });
  }

  function load() {
    var year = yearEl ? yearEl.value : '';
    var sem = semEl ? semEl.value : '1';
    var key = year + '|' + sem;
    if (key === loadedFor) return;
    loadedFor = key;
    global.McuApi.get('schedule.php?colleges=1' + (year ? '&year=' + encodeURIComponent(year) + '&sem=' + encodeURIComponent(sem) : ''))
      .then(function (res) {
        if (loadedFor !== key) return;
        colleges = (res && res.colleges) || [];
        render();
      })
      .catch(function () {
        colleges = [];
        render();
      });
  }

  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-college]');
    if (!btn || !colEl) return;
    var id = btn.getAttribute('data-college');
    colEl.value = colEl.value === id ? '' : id;
    colEl.dispatchEvent(new Event('change', { bubbles: true }));
    render();
  });
  if (colEl) {
    colEl.addEventListener('change', render);
    new MutationObserver(function () { markOptions(); }).observe(colEl, { childList: true });
  }
  if (yearEl) yearEl.addEventListener('change', function () { setTimeout(load, 0); });
  if (semEl) semEl.addEventListener('change', function () { setTimeout(load, 0); });
  // schedule-page.js fills the year list after its first load
  if (yearEl) new MutationObserver(function () { load(); }).observe(yearEl, { childList: true, attributes: true });
  doc.addEventListener('mcu:schedule-reload', function () { loadedFor = null; load(); });
  load();
})(window);
