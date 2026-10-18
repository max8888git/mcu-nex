/**
 * Dean / Program Chair dashboard: their college's approved timetable (the copy the
 * VPAA published), by section, with CSV / PDF / print copies.
 * Data: GET schedule.php?college_final=1&scope=current|past[&term=ID] (schedule-colleges.php college_final_for_viewer).
 * #ctRoot data-mode: "current" (Dashboard, this term only) or "past" (Past timetables, earlier terms).
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  var DAY_NAMES = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday' };
  var root;
  var state = { data: null, program: '', find: '', mode: 'current' };

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
    return isNaN(d) ? String(v) : d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function kind(c) {
    return c.component === 'lab' || c.component === 'laboratory' ? 'Lab' : 'Lecture';
  }

  // one row per meeting; classes not placed yet get one row with no day
  function rows() {
    var d = state.data;
    var q = state.find.toLowerCase();
    var out = [];
    (d.classes || []).forEach(function (c) {
      if (state.program && c.programId !== state.program) return;
      var meetings = (c.meetings || []).length ? c.meetings : [null];
      meetings.forEach(function (m) {
        var r = {
          section: c.sectionName || '',
          year: c.yearLevel || '',
          code: (c.courseCode || '') + (kind(c) === 'Lab' && !/L$/.test(c.courseCode || '') ? 'L' : ''),
          title: c.courseTitle || '',
          kind: kind(c),
          day: m ? m.day : '',
          start: m ? m.start : '',
          end: m ? m.end : '',
          room: m ? (m.roomCode || (c.requiresCampusRoom === false ? 'Off campus' : '')) : '',
          students: c.partitionStudents || c.studentCount || '',
          placed: !!m,
          classId: c.id
        };
        if (q && (r.section + ' ' + r.code + ' ' + r.title + ' ' + r.room).toLowerCase().indexOf(q) < 0) return;
        out.push(r);
      });
    });
    out.sort(function (a, b) {
      return a.section.localeCompare(b.section, undefined, { numeric: true }) ||
        (a.placed === b.placed ? 0 : a.placed ? -1 : 1) ||
        DAYS.indexOf(a.day) - DAYS.indexOf(b.day) || a.start.localeCompare(b.start) || a.code.localeCompare(b.code);
    });
    return out;
  }

  function bySection(list) {
    var map = {};
    var order = [];
    list.forEach(function (r) {
      if (!map[r.section]) {
        map[r.section] = [];
        order.push(r.section);
      }
      map[r.section].push(r);
    });
    return order.map(function (s) { return { name: s, rows: map[s] }; });
  }

  function statusHtml(d) {
    if (state.mode === 'past') return d.publishedAt ? '<span class="ui-chip ui-chip--done">Approved by the VPAA · ' + esc(dateText(d.publishedAt)) + '</span>' : '';
    if (d.publishedAt) {
      var updating = d.status !== 'published';
      return '<span class="ui-chip ui-chip--done">Approved by the VPAA · ' + esc(dateText(d.publishedAt)) + '</span>' +
        (updating ? '<p class="ct-note">The Registrar’s office is preparing an update. This is the last approved version until the VPAA approves the new one.</p>' : '');
    }
    var text = {
      pending_vpaa: 'Waiting for the VPAA’s approval',
      pending_super_admin: 'With the University Registrar',
      with_registrar: 'With the University Registrar'
    }[d.status] || 'Being prepared by the Registrar’s office';
    return '<span class="ui-chip ui-chip--waiting">' + esc(text) + '</span>';
  }

  function scope() {
    var d = state.data;
    var p = (d.programs || []).filter(function (x) { return x.id === state.program; })[0];
    return p ? p.name : (d.role === 'program_chair' && (d.programs || []).length === 1 ? d.programs[0].name : d.collegeName);
  }

  function render() {
    var d = state.data;
    var list = d.publishedAt ? rows() : [];
    var groups = bySection(list);
    var sections = {};
    var unplaced = 0;
    list.forEach(function (r) {
      sections[r.section] = true;
      if (!r.placed) unplaced++;
    });
    var termSelect = (d.terms || []).length > 1
      ? '<label class="ct-field"><span>Term</span><select class="ui-input" data-term>' + d.terms.map(function (t) {
        return '<option value="' + t.id + '"' + (d.term && d.term.id === t.id ? ' selected' : '') + '>' + esc(t.label) + '</option>';
      }).join('') + '</select></label>'
      : '';
    var programSelect = (d.programs || []).length > 1
      ? '<label class="ct-field"><span>Program</span><select class="ui-input" data-program><option value="">All programs</option>' + d.programs.map(function (p) {
        return '<option value="' + esc(p.id) + '"' + (state.program === p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>';
      }).join('') + '</select></label>'
      : '';

    var past = state.mode === 'past';
    var html = '<div class="ct-head"><div><span class="ct-kicker">' + (past ? 'Past timetable' : 'Approved timetable · this term') + '</span>' +
      '<h2 class="ct-title">' + esc(d.term ? d.term.label : past ? 'No past terms yet' : 'No current offering yet') + '</h2>' +
      '<p class="ct-sub">' + esc(d.collegeName) + '</p></div><div class="ct-status">' + statusHtml(d) + '</div></div>';

    if (past && !d.term) html = '';
    if (!d.publishedAt) {
      html += past
        ? '<div class="ui-empty ct-empty"><h3>No past timetables yet</h3><p>When a new term starts, this term’s approved timetable moves here, so you can still look it up and download it.</p></div>'
        : '<div class="ui-empty ct-empty"><h3>No approved timetable yet</h3><p>Your college’s timetable for this term shows here, ready to check and download, once the VPAA approves it. You’ll get a notification. Earlier terms are in <a href="past-timetables.html">Past timetables</a>.</p></div>';
      root.innerHTML = html + (termSelect ? '<div class="ct-bar">' + termSelect + '</div>' : '');
      return;
    }

    if (noting()) html += notesHtml();
    html += '<div class="ct-bar">' + termSelect + programSelect +
      '<label class="ct-field ct-find"><span>Search</span><input class="ui-input" type="search" data-find placeholder="Section, subject or room" value="' + esc(state.find) + '"></label>' +
      '<div class="ct-actions"><button type="button" class="ui-btn" data-csv>Download CSV</button><button type="button" class="ui-btn" data-pdf>Download PDF</button><button type="button" class="ui-btn" data-print>Print</button></div></div>' +
      '<p class="ct-count">' + Object.keys(sections).length + ' sections · ' + list.length + ' class meetings' +
      (unplaced ? ' · <strong>' + unplaced + ' not scheduled</strong>' : '') + '</p>';

    if (!groups.length) {
      html += '<div class="ui-empty ct-empty"><h3>Nothing matches</h3><p>Try another program or search.</p></div>';
    } else {
      html += groups.map(function (g, i) {
        return '<details class="ct-section"' + (i < 3 ? ' open' : '') + '><summary><strong>' + esc(g.name) + '</strong><span>' + g.rows.length + (g.rows.length === 1 ? ' meeting' : ' meetings') + '</span></summary>' +
          '<div class="ct-scroll"><table class="ct-table"><thead><tr><th>Day</th><th>Time</th><th>Code</th><th>Subject</th><th>Type</th><th>Room</th>' + (noting() ? '<th class="no-print"><span class="ct-sr">Note</span></th>' : '') + '</tr></thead><tbody>' +
          g.rows.map(function (r) {
            return '<tr' + (r.placed ? '' : ' class="is-unplaced"') + '><td>' + esc(r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled') + '</td>' +
              '<td class="ct-nowrap">' + (r.placed ? esc(time12(r.start) + ' – ' + time12(r.end)) : '—') + '</td>' +
              '<td class="ct-nowrap"><strong>' + esc(r.code) + '</strong></td><td>' + esc(r.title) + '</td><td>' + esc(r.kind) + '</td><td>' + esc(r.room || '—') + '</td>' +
              (noting() ? '<td class="ct-notecell no-print">' + (noted()[r.classId] ? '<span class="ct-noted" title="You sent a note about this class">Note sent</span> ' : '') +
                (r.placed ? '<button type="button" class="ct-notebtn" data-note="' + esc(r.classId) + '">Note</button>' : '') + '</td>' : '') + '</tr>';
          }).join('') + '</tbody></table></div></details>';
      }).join('');
    }
    root.innerHTML = html;
  }

  // ---------------------------------------------------------------- notes on classes (this term)
  // A Program Chair's note goes to their Dean, who forwards it to the Registrar's office or answers it;
  // a Dean's goes to the Records Officer (the Requests flow, requests.php).

  var TOPICS = [['time', 'Change day or time'], ['room', 'Change room'], ['meetings', 'Split a session or add a meeting'],
    ['merge', 'Merge'], ['unmerge', 'Unmerge'], ['partition', 'Section partition'], ['remove', 'Remove schedule'], ['other', 'Other']];

  function noting() {
    return state.mode === 'current' && !!(state.data && state.data.publishedAt) && !!state.requests;
  }

  // class id → true for classes this person already sent a note about that's still open
  function noted() {
    var out = {};
    (state.requests || []).forEach(function (q) {
      if (q.fromRole !== state.data.role || ['done', 'held', 'sent_back'].indexOf(q.status) >= 0) return;
      (q.classes || []).forEach(function (c) { out[c.id] = true; });
    });
    return out;
  }

  function noteLine(q) {
    var who = q.fromRole === 'program_chair' ? (q.fromName || 'Program Chair') : (q.fromName || 'Dean');
    var cls = (q.classes || []).map(function (c) { return c.label; }).join(', ') || q.classLabel || 'General request';
    var meta = (q.topicLabels || []).concat([q.atLabel]).filter(Boolean).join(' · ');
    return '<div class="ct-note-main"><strong>' + esc(cls) + '</strong><span class="ct-note-meta">' + esc(meta) +
      (state.data.role === 'dean' && q.fromRole === 'program_chair' ? ' · from ' + esc(who) : '') + '</span>' +
      '<span class="ct-note-body">' + esc(q.body) + '</span>' +
      (q.holdNote ? '<span class="ct-note-reply"><strong>Dean’s answer:</strong> ' + esc(q.holdNote) + '</span>' : '') +
      (q.forwardNote ? '<span class="ct-note-reply"><strong>Dean, when forwarding:</strong> ' + esc(q.forwardNote) + '</span>' : '') +
      (q.adminReply ? '<span class="ct-note-reply"><strong>Registrar’s office:</strong> ' + esc(q.adminReply) + '</span>' : '') + '</div>';
  }

  function chip(q) {
    var tone = { pending_dean: 'waiting', pending_admin: 'waiting', done: 'done', held: 'neutral', sent_back: 'back' }[q.status] || 'neutral';
    var text = state.data.role === 'dean' && q.status === 'pending_dean' ? 'Waiting for you'
      : q.status === 'held' ? 'Answered by the Dean' : q.status === 'pending_admin' ? 'With the Registrar’s office' : (q.statusLabel || q.status);
    return '<span class="ui-chip ui-chip--' + tone + '">' + esc(text) + '</span>';
  }

  function notesHtml() {
    var d = state.data;
    var reqs = state.requests || [];
    if (d.role === 'dean') {
      var waiting = reqs.filter(function (q) { return q.fromRole === 'program_chair' && q.status === 'pending_dean'; });
      var mine = reqs.filter(function (q) { return q.fromRole === 'dean'; }).slice(0, 3);
      if (!waiting.length && !mine.length) return '<p class="ct-notes-hint">Your Program Chairs can send you a note on any class below; it shows here for you to forward to the Registrar’s office or answer. You can send notes too (they go to the Records Officer).</p>';
      return '<section class="ct-notes"><h3 class="ct-notes-title">Notes from your Program Chairs' + (waiting.length ? ' <span class="ct-count-pill">' + waiting.length + '</span>' : '') + '</h3>' +
        (waiting.length ? '<ul class="ct-note-list">' + waiting.map(function (q) {
          return '<li class="ct-nitem">' + noteLine(q) + '<div class="ct-note-acts">' + chip(q) +
            '<button type="button" class="ui-btn ui-btn--primary" data-forward="' + q.id + '">Forward to the Registrar</button>' +
            '<button type="button" class="ui-btn" data-answer="' + q.id + '">Answer instead</button></div></li>';
        }).join('') + '</ul>' : '<p class="ct-notes-hint">Nothing waiting for you.</p>') +
        (mine.length ? '<h4 class="ct-notes-sub">Your notes</h4><ul class="ct-note-list">' + mine.map(function (q) {
          return '<li class="ct-nitem">' + noteLine(q) + '<div class="ct-note-acts">' + chip(q) + '</div></li>';
        }).join('') + '</ul>' : '') +
        '<p class="ct-notes-more"><a href="viewer-requests.html">All requests →</a></p></section>';
    }
    var own = reqs.filter(function (q) { return q.fromRole === 'program_chair'; }).slice(0, 5);
    if (!own.length) return '<p class="ct-notes-hint">See something wrong in a class? Press <strong>Note</strong> on its row; it goes to your Dean to check, and the Dean can forward it to the Registrar’s office.</p>';
    return '<section class="ct-notes"><h3 class="ct-notes-title">Your notes to the Dean</h3><ul class="ct-note-list">' + own.map(function (q) {
      return '<li class="ct-nitem">' + noteLine(q) + '<div class="ct-note-acts">' + chip(q) + '</div></li>';
    }).join('') + '</ul><p class="ct-notes-more"><a href="viewer-requests.html">All requests →</a></p></section>';
  }

  function noteDialog() {
    var dlg = doc.getElementById('ctNoteDialog');
    if (dlg) return dlg;
    dlg = doc.createElement('dialog');
    dlg.id = 'ctNoteDialog';
    dlg.className = 'ui-dialog ct-note-dialog';
    doc.body.appendChild(dlg);
    dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
    return dlg;
  }

  function sendNote(classId) {
    var c = (state.data.classes || []).filter(function (x) { return x.id === classId; })[0];
    if (!c) return;
    var dean = state.data.role === 'dean';
    var m = (c.meetings || [])[0];
    var dlg = noteDialog();
    dlg.innerHTML = '<div class="ui-dialog-head"><div><h2 class="ui-dialog-title">Note on ' + esc(c.courseCode) + ' · ' + esc(c.sectionName) + '</h2>' +
      '<p class="ct-note-meta">' + esc(c.courseTitle) + (m ? ' · ' + esc((DAY_NAMES[m.day] || m.day) + ' ' + time12(m.start) + '–' + time12(m.end) + ' · ' + (m.roomCode || '')) : '') + '</p></div>' +
      '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body"><label class="ui-field-label" for="ctNoteTopic">What should change?</label>' +
      '<select class="ui-input" id="ctNoteTopic">' + TOPICS.map(function (t) { return '<option value="' + t[0] + '">' + esc(t[1]) + '</option>'; }).join('') + '</select>' +
      '<label class="ui-field-label" for="ctNoteText" style="margin-top:0.7rem">Your note</label>' +
      '<textarea class="ui-textarea" id="ctNoteText" maxlength="4000" placeholder="For example: This lab is during our weekly department meeting. Could it move to Thursday?"></textarea>' +
      '<p class="ui-field-hint">' + (dean ? 'It goes to the Records Officer, on their Requests page.' : 'It goes to your Dean to check first. The Dean can forward it to the Registrar’s office or answer you.') + '</p>' +
      '<p class="ui-field-error" id="ctNoteErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="ctNoteGo">' + (dean ? 'Send to the Records Officer' : 'Send to the Dean') + '</button></div>';
    dlg.showModal();
    doc.getElementById('ctNoteText').focus();
    doc.getElementById('ctNoteGo').onclick = function () {
      var btn = this;
      var text = doc.getElementById('ctNoteText').value.trim();
      var err = doc.getElementById('ctNoteErr');
      if (!text) { err.textContent = 'Write what should change.'; err.hidden = false; return; }
      btn.disabled = true;
      global.McuApi.send('POST', 'requests.php', { action: 'create', topics: [doc.getElementById('ctNoteTopic').value], classIds: [classId], body: text })
        .then(function () {
          dlg.close();
          notice(dean ? 'Sent to the Records Officer.' : 'Sent to your Dean.');
          return loadRequests().then(render);
        })
        .catch(function (e) { btn.disabled = false; err.textContent = e.message; err.hidden = false; });
    };
  }

  // the Dean: forward a Program Chair's note to the Registrar's office (optional note), or answer it instead
  function decide(id, forward) {
    var q = (state.requests || []).filter(function (x) { return x.id === id; })[0];
    if (!q) return;
    var dlg = noteDialog();
    dlg.innerHTML = '<div class="ui-dialog-head"><h2 class="ui-dialog-title">' + (forward ? 'Forward to the Registrar’s office?' : 'Answer the Program Chair') + '</h2>' +
      '<button type="button" class="ui-icon-btn" data-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body">' + noteLine(q) +
      '<label class="ui-field-label" for="ctDecideText" style="margin-top:0.8rem">' + (forward ? 'Add a note for the Records Officer (optional)' : 'Your answer') + '</label>' +
      '<textarea class="ui-textarea" id="ctDecideText" maxlength="4000"></textarea>' +
      (forward ? '' : '<p class="ui-field-hint">It isn’t forwarded; the Program Chair sees your answer on their dashboard.</p>') +
      '<p class="ui-field-error" id="ctDecideErr" role="alert" hidden></p></div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-close>Cancel</button><button type="button" class="ui-btn ui-btn--primary" id="ctDecideGo">' + (forward ? 'Forward' : 'Send answer') + '</button></div>';
    dlg.showModal();
    doc.getElementById('ctDecideGo').onclick = function () {
      var btn = this;
      var text = doc.getElementById('ctDecideText').value.trim();
      var err = doc.getElementById('ctDecideErr');
      if (!forward && !text) { err.textContent = 'Write your answer.'; err.hidden = false; return; }
      btn.disabled = true;
      global.McuApi.send('POST', 'requests.php', { action: forward ? 'forward' : 'hold', id: id, note: text })
        .then(function () {
          dlg.close();
          notice(forward ? 'Forwarded to the Registrar’s office.' : 'Answer sent.');
          return loadRequests().then(render);
        })
        .catch(function (e) { btn.disabled = false; err.textContent = e.message; err.hidden = false; });
    };
  }

  function loadRequests() {
    if (state.mode !== 'current') return Promise.resolve();
    return global.McuApi.get('requests.php').then(function (res) {
      state.requests = res.textRequests || [];
    }).catch(function () {
      state.requests = null; // no notes panel if Requests can't load
    });
  }

  // ---------------------------------------------------------------- copies

  function fileBase() {
    var d = state.data;
    return (scope() + ' timetable ' + (d.term ? d.term.label : '')).replace(/[\\/:*?"<>|·]+/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function download(name, blob) {
    var a = doc.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    doc.body.appendChild(a);
    a.click();
    setTimeout(function () {
      URL.revokeObjectURL(a.href);
      a.remove();
    }, 1000);
  }

  function csv() {
    var cell = function (v) {
      v = String(v == null ? '' : v);
      return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    };
    var lines = [['Section', 'Year level', 'Day', 'Start', 'End', 'Course Code', 'Course Title', 'Type', 'Room', 'Students'].join(',')];
    rows().forEach(function (r) {
      lines.push([r.section, r.year, r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled', time12(r.start), time12(r.end), r.code, r.title, r.kind, r.room, r.students].map(cell).join(','));
    });
    download(fileBase() + '.csv', new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
  }

  function logoUrl() {
    var img = doc.querySelector('.topbar-logo img');
    return img ? img.src : new URL('/Attri/mculogo.jpg', global.location.href).href;
  }

  function subtitle() {
    var d = state.data;
    return (d.term ? d.term.label + ' · ' : '') + 'Approved by the VPAA ' + dateText(d.publishedAt);
  }

  function pdf() {
    if (!global.McuTimetablePdf) {
      notice('The PDF maker didn’t load. Refresh the page and try again.', 'bad');
      return;
    }
    var d = state.data;
    global.McuTimetablePdf.download({
      logoUrl: logoUrl(),
      collegeName: d.collegeName,
      title: scope() + ' Timetable',
      subtitle: subtitle(),
      sections: bySection(rows()).map(function (g) {
        return {
          title: g.name,
          rows: g.rows.map(function (r) {
            return [r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled', r.placed ? time12(r.start) + ' – ' + time12(r.end) : '—', r.code, r.title, r.kind, r.room || '—'];
          })
        };
      }),
      footer: 'MCU-NEX · ' + scope() + ' · printed ' + new Date().toLocaleDateString(),
      fileName: fileBase() + '.pdf'
    }).catch(function (e) { notice(e.message, 'bad'); });
  }

  function print() {
    var d = state.data;
    var body = bySection(rows()).map(function (g) {
      return '<h3>' + esc(g.name) + '</h3><table><thead><tr><th>Day</th><th>Time</th><th>Code</th><th>Subject</th><th>Type</th><th>Room</th></tr></thead><tbody>' +
        g.rows.map(function (r) {
          return '<tr><td>' + esc(r.placed ? DAY_NAMES[r.day] || r.day : 'Not scheduled') + '</td><td>' + (r.placed ? esc(time12(r.start) + ' – ' + time12(r.end)) : '—') + '</td><td><b>' + esc(r.code) + '</b></td><td>' + esc(r.title) + '</td><td>' + esc(r.kind) + '</td><td>' + esc(r.room || '—') + '</td></tr>';
        }).join('') + '</tbody></table>';
    }).join('');
    var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(fileBase()) + '</title><style>' +
      '@page{size:A4;margin:14mm}body{font-family:"DM Sans",Arial,sans-serif;color:#1f1230;font-size:10.5px}' +
      'header{display:flex;gap:12px;align-items:center;border-bottom:3px solid #f9b937;padding-bottom:8px;margin-bottom:10px}' +
      'header img{width:46px;height:46px;border-radius:50%}h1{font-size:16px;margin:0;color:#2d163f}h2{font-size:13px;margin:2px 0 0;color:#5c267e;font-weight:600}' +
      '.meta{color:#6f5f86;margin-top:2px}h3{font-size:12px;margin:14px 0 4px;color:#2d163f}' +
      'table{width:100%;border-collapse:collapse;page-break-inside:avoid}th,td{border:1px solid #d9cfe6;padding:3px 5px;text-align:left;vertical-align:top}' +
      'th{background:#f3eef9;color:#4b3d5e;font-size:9.5px;text-transform:uppercase}' +
      '</style></head><body><header><img src="' + esc(logoUrl()) + '" alt=""><div><h1>' + esc(d.collegeName) + '</h1><h2>' + esc(scope()) + ' Timetable</h2>' +
      '<div class="meta">' + esc(subtitle()) + '</div></div></header>' + body + '</body></html>';
    var frame = doc.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    doc.body.appendChild(frame);
    var w = frame.contentWindow;
    w.document.open();
    w.document.write(html);
    w.document.close();
    setTimeout(function () {
      w.focus();
      w.print();
      setTimeout(function () { frame.remove(); }, 1000);
    }, 400);
  }

  function notice(text, tone) {
    var el = doc.createElement('div');
    el.className = 'ui-notice' + (tone === 'bad' ? ' ui-notice--bad' : '');
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  // ---------------------------------------------------------------- load

  function load(termId) {
    root.innerHTML = '<p class="ct-loading">Loading your timetable…</p>';
    return Promise.all([
      global.McuApi.get('schedule.php?college_final=1&scope=' + state.mode + (termId ? '&term=' + encodeURIComponent(termId) : '')),
      loadRequests()
    ]).then(function (res) {
      var data = res[0];
      state.data = data;
      if (!(data.programs || []).some(function (p) { return p.id === state.program; })) state.program = '';
      render();
    }).catch(function (e) {
      root.innerHTML = '<div class="ui-empty ct-empty"><h3>The timetable couldn’t be loaded</h3><p>' + esc(e.message) + '</p></div>';
    });
  }

  function init() {
    root = doc.getElementById('ctRoot');
    if (!root || !global.McuApi) return;
    state.mode = root.getAttribute('data-mode') === 'past' ? 'past' : 'current';
    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-csv]')) return csv();
      if (e.target.closest('[data-pdf]')) return pdf();
      if (e.target.closest('[data-print]')) return print();
      var el;
      if ((el = e.target.closest('[data-note]'))) return sendNote(el.getAttribute('data-note'));
      if ((el = e.target.closest('[data-forward]'))) return decide(Number(el.getAttribute('data-forward')), true);
      if ((el = e.target.closest('[data-answer]'))) return decide(Number(el.getAttribute('data-answer')), false);
    });
    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-term]')) return load(e.target.value);
      if (e.target.matches('[data-program]')) {
        state.program = e.target.value;
        render();
      }
    });
    root.addEventListener('input', function (e) {
      if (!e.target.matches('[data-find]')) return;
      state.find = e.target.value;
      var pos = e.target.selectionStart;
      render();
      var el = root.querySelector('[data-find]');
      if (el) {
        el.focus();
        el.setSelectionRange(pos, pos);
      }
    });
    load();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
