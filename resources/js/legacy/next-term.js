/**
 * Start next term (Data management → Sections, University Registrar): a preview of the next
 * term's sections, carried over from this term, to edit before anything is made; then Create.
 * Also offers Undo while nothing is scheduled in the new term yet.
 * Server: POST sections.php action nextTermPreview / nextTermCreate / nextTermUndo (term-rollover.php).
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var state = { preview: null };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function plural(n, one, many) {
    return n + ' ' + (n === 1 ? one : many);
  }

  function api(body) {
    return global.McuApi.send('POST', 'sections.php', body);
  }

  function sectionsFor(students) {
    return students > 0 ? Math.ceil(students / 40) : 0;
  }

  function dialog() {
    var d = doc.getElementById('ntDialog');
    if (d) return d;
    d = doc.createElement('dialog');
    d.id = 'ntDialog';
    d.className = 'ui-dialog nt-dialog';
    doc.body.appendChild(d);
    d.addEventListener('click', onClick);
    d.addEventListener('input', onInput);
    d.addEventListener('change', onChange);
    return d;
  }

  // ---------------------------------------------------------------- the preview

  function curriculumSelect(programId, value, attr) {
    var list = (state.preview.curricula || {})[programId] || [];
    var has = list.some(function (c) { return c.id === value; });
    return '<select class="ui-input nt-cur" ' + attr + '>' +
      (has || !value ? '' : '<option value="' + esc(value) + '" selected>(archived) ' + esc(value) + '</option>') +
      (list.length ? '' : '<option value="">No active curriculum</option>') +
      list.map(function (c) { return '<option value="' + esc(c.id) + '"' + (c.id === value ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join('') +
      '</select>';
  }

  // sections grouped by program, then year level
  function groups() {
    var out = [];
    var at = {};
    state.preview.sections.forEach(function (s, i) {
      var k = s.programId + '|' + s.yearLevel;
      if (!at[k]) {
        at[k] = { key: k, programId: s.programId, programName: s.programName, collegeName: s.collegeName, yearLevel: s.yearLevel, rows: [] };
        out.push(at[k]);
      }
      at[k].rows.push(i);
    });
    return out;
  }

  function totals() {
    var p = state.preview;
    var n = p.sections.filter(function (s) { return s.students > 0; }).length;
    (p.firstYears || []).forEach(function (f) { n += sectionsFor(f.students); });
    return n;
  }

  function render() {
    var p = state.preview;
    var d = dialog();
    var body = '';
    if (p.warnings.length) {
      body += '<div class="nt-warn"><strong>Check these first:</strong><ul>' + p.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul></div>';
    }
    var what = p.newYear ? 'Every section moves up a year level and keeps its curriculum.'
      : p.target.termCode === 'summer' ? 'Only sections whose curriculum has summer subjects for their year level are carried over.'
        : 'Every section is carried over as it is.';
    body += '<p class="nt-lead">' + esc(what) + ' Change the student counts, remove or add sections, then press Create. Nothing is made until then.</p>';
    if (p.graduating.length) {
      body += '<p class="nt-note"><strong>Graduating</strong> (not carried over): ' + p.graduating.map(function (g) {
        return esc(g.programName + ' ' + g.yearLevel + '-' + g.code) + ' (' + g.students + ')';
      }).join(', ') + '.</p>';
    }
    if (p.summerSkipped) body += '<p class="nt-note">' + plural(p.summerSkipped, 'section has', 'sections have') + ' no summer subjects, so they skip summer.</p>';

    var gs = groups();
    body += gs.length ? gs.map(function (g) {
      var first = p.sections[g.rows[0]];
      return '<section class="nt-group"><div class="nt-group-head"><strong>' + esc(g.programName) + ' · ' + g.yearLevel + ordinalSuffix(g.yearLevel) + ' year</strong>' +
        '<span class="nt-muted">' + esc(g.collegeName) + '</span>' +
        '<label class="nt-curlabel">Curriculum ' + curriculumSelect(g.programId, first.curriculumId, 'data-group-cur="' + esc(g.key) + '"') + '</label></div>' +
        '<table class="nt-table"><thead><tr><th>Section</th><th>Students</th><th></th></tr></thead><tbody>' +
        g.rows.map(function (i) {
          var s = p.sections[i];
          return '<tr' + (s.students > 0 ? '' : ' class="is-off"') + '><td><strong>' + esc(g.programName + ' ' + s.yearLevel + '-') + '</strong><input class="ui-input nt-code" value="' + esc(s.code) + '" data-code="' + i + '" maxlength="10" aria-label="Section number"></td>' +
            '<td><input class="ui-input nt-num" type="number" min="0" max="999" value="' + s.students + '" data-students="' + i + '" aria-label="Students"></td>' +
            '<td><button type="button" class="nt-x" data-remove="' + i + '" aria-label="Remove this section">&times;</button></td></tr>';
        }).join('') + '</tbody></table>' +
        '<button type="button" class="nt-add" data-add="' + esc(g.key) + '">+ Add a section</button></section>';
    }).join('') : '<p class="nt-note">There are no sections to carry over' + (p.firstYears.length ? '; add new 1st-years below.' : '.') + '</p>';

    if (p.firstYears.length) {
      body += '<h3 class="nt-h3">New 1st-years</h3><p class="nt-lead">How many students are entering each program? Leave 0 for none. They’re split into sections of up to 40.</p>' +
        '<table class="nt-table nt-table--first"><thead><tr><th>Program</th><th>Curriculum</th><th>Students</th><th>Sections</th></tr></thead><tbody>' +
        p.firstYears.map(function (f, i) {
          return '<tr><td><strong>' + esc(f.programName) + '</strong><span class="nt-muted">' + esc(f.collegeName) + '</span></td>' +
            '<td>' + curriculumSelect(f.programId, f.curriculumId, 'data-first-cur="' + i + '"') + '</td>' +
            '<td><input class="ui-input nt-num" type="number" min="0" max="2000" value="' + f.students + '" data-first="' + i + '"' + (f.curriculumId ? '' : ' disabled') + ' aria-label="New students"></td>' +
            '<td class="nt-muted" data-first-sec="' + i + '">' + (f.students ? plural(sectionsFor(f.students), 'section', 'sections') : '—') + '</td></tr>';
        }).join('') + '</tbody></table>';
    }

    var n = totals();
    d.innerHTML = '<div class="ui-dialog-head"><div><h2 class="ui-dialog-title">Start the next term</h2>' +
      '<p class="nt-muted">From <strong>' + esc(p.from.label) + '</strong> to ' + (p.targets.length > 1
        ? '<select class="ui-input nt-target" data-target>' + p.targets.map(function (t) {
          return '<option value="' + esc(t.schoolYear + '|' + t.termCode) + '"' + (t.termCode === p.target.termCode && t.schoolYear === p.target.schoolYear ? ' selected' : '') + '>' + esc(t.label) + '</option>';
        }).join('') + '</select>'
        : '<strong>' + esc(p.target.label) + '</strong>') + '</p></div>' +
      '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body">' + body + '<p class="ui-field-error" id="ntErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><span class="nt-muted nt-sum" id="ntSum">' + esc(p.from.label) + ' will be archived and ' + esc(p.target.label) + ' becomes the current term.</span>' +
      '<button type="button" class="ui-btn" data-close>Cancel</button>' +
      '<button type="button" class="ui-btn ui-btn--primary" data-create' + (n ? '' : ' disabled') + '>Create ' + plural(n, 'section', 'sections') + '</button></div>';
  }

  function ordinalSuffix(n) {
    return n === 1 ? 'st' : n === 2 ? 'nd' : n === 3 ? 'rd' : 'th';
  }

  function refreshCreate() {
    var n = totals();
    var btn = dialog().querySelector('[data-create]');
    if (btn) {
      btn.textContent = 'Create ' + plural(n, 'section', 'sections');
      btn.disabled = !n;
    }
  }

  function onInput(e) {
    var t = e.target;
    var p = state.preview;
    if (t.hasAttribute('data-students')) {
      var s = p.sections[Number(t.getAttribute('data-students'))];
      s.students = Math.max(0, parseInt(t.value, 10) || 0);
      t.closest('tr').classList.toggle('is-off', !s.students);
      refreshCreate();
    } else if (t.hasAttribute('data-code')) {
      p.sections[Number(t.getAttribute('data-code'))].code = t.value.trim();
    } else if (t.hasAttribute('data-first')) {
      var i = Number(t.getAttribute('data-first'));
      p.firstYears[i].students = Math.max(0, parseInt(t.value, 10) || 0);
      var cell = dialog().querySelector('[data-first-sec="' + i + '"]');
      if (cell) cell.textContent = p.firstYears[i].students ? plural(sectionsFor(p.firstYears[i].students), 'section', 'sections') : '—';
      refreshCreate();
    }
  }

  function onChange(e) {
    var t = e.target;
    var p = state.preview;
    if (t.hasAttribute('data-group-cur')) {
      var key = t.getAttribute('data-group-cur');
      p.sections.forEach(function (s) { if (s.programId + '|' + s.yearLevel === key) s.curriculumId = t.value; });
    } else if (t.hasAttribute('data-first-cur')) {
      p.firstYears[Number(t.getAttribute('data-first-cur'))].curriculumId = t.value;
    } else if (t.hasAttribute('data-target')) {
      var v = t.value.split('|');
      open(v[0], v[1]);
    }
  }

  function onClick(e) {
    var t = e.target;
    var d = dialog();
    var p = state.preview;
    var el;
    if (t === d || t.closest('[data-close]')) return d.close();
    if ((el = t.closest('[data-remove]'))) {
      p.sections.splice(Number(el.getAttribute('data-remove')), 1);
      return render();
    }
    if ((el = t.closest('[data-add]'))) {
      var key = el.getAttribute('data-add');
      var same = p.sections.filter(function (s) { return s.programId + '|' + s.yearLevel === key; });
      var last = same[same.length - 1];
      var used = same.map(function (s) { return s.code; });
      var next = 1;
      while (used.indexOf(String(next)) >= 0) next++;
      var at = p.sections.indexOf(last) + 1;
      p.sections.splice(at, 0, Object.assign({}, last, { code: String(next), students: 0 }));
      return render();
    }
    if (t.closest('[data-create]')) return create(t.closest('[data-create]'));
  }

  function create(btn) {
    var p = state.preview;
    var err = doc.getElementById('ntErr');
    var codes = {};
    for (var i = 0; i < p.sections.length; i++) {
      var s = p.sections[i];
      var k = s.programId + '|' + s.yearLevel + '|' + s.code;
      if (!s.code) { err.textContent = 'Every section needs a number.'; err.hidden = false; return; }
      if (codes[k]) { err.textContent = s.programName + ' ' + s.yearLevel + '-' + s.code + ' is listed twice.'; err.hidden = false; return; }
      codes[k] = true;
    }
    if (!global.confirm('Archive ' + p.from.label + ' and start ' + p.target.label + ' with ' + plural(totals(), 'section', 'sections') + '?')) return;
    btn.disabled = true;
    btn.textContent = 'Creating…';
    api({ action: 'nextTermCreate', schoolYear: p.target.schoolYear, termCode: p.target.termCode, sections: p.sections, firstYears: p.firstYears })
      .then(function (res) {
        dialog().close();
        global.alert(p.target.label + ' started with ' + plural(res.sectionsCreated || 0, 'section', 'sections') + '. You can undo it from this page until classes are scheduled.');
        global.location.reload();
      })
      .catch(function (e) {
        btn.disabled = false;
        refreshCreate();
        err.textContent = e.message;
        err.hidden = false;
      });
  }

  function open(schoolYear, termCode) {
    var d = dialog();
    if (!d.open) {
      d.innerHTML = '<div class="ui-dialog-body"><p class="nt-muted">Working out the next term…</p></div>';
      d.showModal();
    }
    api({ action: 'nextTermPreview', schoolYear: schoolYear || '', termCode: termCode || '' }).then(function (res) {
      state.preview = res.preview;
      render();
    }).catch(function (e) {
      d.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">Can’t start the next term</h2><button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
        '<div class="ui-dialog-body"><p class="ui-field-error">' + esc(e.message) + '</p></div>';
    });
  }

  // ---------------------------------------------------------------- undo

  function showUndo() {
    var box = doc.getElementById('ntUndo');
    if (!box || !global.McuApi) return;
    global.McuApi.get('sections.php').then(function (res) {
      var r = res.rollover;
      if (!r || !r.from) {
        box.hidden = true;
        return;
      }
      box.innerHTML = 'This term was started from <strong>' + esc(r.from.label) + '</strong> with ' + plural(r.sections, 'section', 'sections') +
        '. Nothing is scheduled yet, so you can still <button type="button" class="nt-link" data-undo>undo it</button>.';
      box.hidden = false;
    }).catch(function () {});
  }

  function undo() {
    if (!global.confirm('Undo Start next term? Its sections are removed and the previous term becomes current again.')) return;
    api({ action: 'nextTermUndo' }).then(function () {
      global.location.reload();
    }).catch(function (e) { global.alert(e.message); });
  }

  function init() {
    var btn = doc.getElementById('ntStart');
    if (!btn || !global.McuApi) return;
    btn.addEventListener('click', function () { open(); });
    var box = doc.getElementById('ntUndo');
    if (box) box.addEventListener('click', function (e) { if (e.target.closest('[data-undo]')) undo(); });
    showUndo();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
