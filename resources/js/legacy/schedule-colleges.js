/**
 * Colleges panel on Create schedule: where each college is in the approval
 * steps (Records Officer → University Registrar → VPAA → published), and the
 * per-college actions (send, approve, send back, take back, unlock, lock).
 * Tick several colleges to act on them together.
 *
 * Talks to legacy-api/schedule-colleges.php through api/schedule.php:
 *   GET  schedule.php?colleges=1&year=&sem=   status of every college
 *   POST schedule.php { action: 'college_*', colleges: ['c-2', ...] }
 * schedule-page.js tells this panel which term is showing
 * (mcu:schedule-loaded) and reloads its timetable when asked
 * (mcu:schedule-reload). Locked colleges can't be edited there
 * (window.McuScheduleColleges.isLocked).
 */
(function (global) {
  'use strict';

  var doc = global.document;

  var WHO = { admin: 'Records Officer', super_admin: 'University Registrar', vpaa: 'VPAA' };

  var STEPS = [
    { status: 'draft', label: 'Being prepared', owner: 'admin' },
    { status: 'pending_super_admin', also: 'with_registrar', label: 'University Registrar', owner: 'super_admin' },
    { status: 'pending_vpaa', label: 'VPAA approval', owner: 'vpaa' },
    { status: 'published', label: 'Published', owner: '' }
  ];

  // What each role can do. `eligible` decides per college; `primary` ones count as "ready".
  // `tab`: the workspace tab the button shows on (mine = Working timetable, submitted = For approval / Sent for approval).
  var ACTIONS = {
    admin: [
      {
        id: 'send',
        tab: 'submit',
        label: 'Send to University Registrar',
        tone: 'primary',
        primary: true,
        action: 'college_submit_to_registrar',
        eligible: function (c) { return c.readyToSend; },
        confirm: {
          title: 'Send to the University Registrar?',
          message: 'A copy of these colleges from your Working timetable goes to the University Registrar for approval. You can keep working here.',
          yes: 'Send'
        },
        done: 'Sent {list} to the University Registrar.'
      },
      {
        id: 'retract',
        tab: 'submitted',
        label: 'Take back',
        action: 'college_retract',
        eligible: function (c) { return c.status === 'pending_super_admin'; },
        confirm: {
          title: 'Take these colleges back?',
          message: 'The University Registrar will no longer see them. Send them again when they’re ready.',
          yes: 'Take back'
        },
        done: 'Took back {list}.'
      }
    ],
    super_admin: [
      {
        id: 'approve',
        tab: 'submitted',
        label: 'Approve',
        tone: 'primary',
        primary: true,
        action: 'college_registrar_decide',
        extra: { accept: true },
        eligible: function (c) { return c.status === 'pending_super_admin'; },
        confirm: {
          title: 'Approve the Records Officer’s timetable?',
          message: 'These colleges are copied into your Working timetable, replacing your version of them. Check or edit them there, then send them to the VPAA. To see what’s different first, use Compare with my version.',
          yes: 'Approve'
        },
        done: 'Approved {list}. {It’s} in your Working timetable now; send {it} to the VPAA when you’re happy with {it}.',
        clashChoice: true
      },
      {
        id: 'sendBack',
        tab: 'submitted',
        label: 'Send back to Records Officer',
        tone: 'danger',
        action: 'college_registrar_decide',
        extra: { accept: false },
        eligible: function (c) {
          return c.status === 'pending_super_admin' || (c.status === 'with_registrar' && c.fromRecordsOfficer);
        },
        note: {
          label: 'What should the Records Officer fix?',
          hint: 'The Records Officer sees this note on Create schedule.',
          placeholder: 'For example: Move PCH 111 lab to the morning.',
          required: 'Say what the Records Officer should fix.'
        },
        confirm: { title: 'Send back to the Records Officer?', message: '', yes: 'Send back' },
        done: 'Sent {list} back to the Records Officer.'
      },
      {
        id: 'sendVpaa',
        tab: 'submit',
        label: 'Send to VPAA',
        tone: 'primary',
        primary: true,
        action: 'college_send_to_vpaa',
        eligible: function (c) { return c.readyToSend; },
        confirm: {
          title: 'Send to the VPAA?',
          message: 'These colleges go to the VPAA exactly as they are in your Working timetable.',
          yes: 'Send to VPAA'
        },
        done: 'Sent {list} to the VPAA.'
      },
      {
        id: 'retract',
        tab: 'submitted',
        label: 'Take back from VPAA',
        action: 'college_retract',
        eligible: function (c) { return c.status === 'pending_vpaa'; },
        confirm: {
          title: 'Take these colleges back from the VPAA?',
          message: 'The VPAA will no longer see them. They stay in your Working timetable; send them again when they’re ready.',
          yes: 'Take back'
        },
        done: 'Took back {list} from the VPAA.'
      },
      {
        id: 'unlock',
        tab: 'submitted',
        label: 'Unlock to fix a mistake',
        action: 'college_unlock',
        eligible: function (c) { return c.locked; },
        note: {
          label: 'What needs fixing?',
          hint: 'Kept in the Transaction log. The published timetable stays live until the corrected one is approved.',
          placeholder: 'For example: Wrong room for STS 000.',
          required: 'Say what needs fixing.'
        },
        confirm: {
          title: 'Unlock to fix a mistake?',
          message: 'You can edit and regenerate these colleges again. Send them to the VPAA again when the fix is done.',
          yes: 'Unlock'
        },
        done: 'Unlocked {list}. Fix the timetable, then send it to the VPAA again.'
      },
      {
        id: 'relock',
        tab: 'submitted',
        label: 'Lock again',
        action: 'college_relock',
        // only after Unlock: keep the published version and lock it again
        eligible: function (c) { return (c.status === 'draft' || c.status === 'with_registrar') && c.hasPublished && !!c.unlockedAt; },
        confirm: {
          title: 'Lock again without changes?',
          message: 'The published timetable stays as it is, and these colleges are locked again.',
          yes: 'Lock again'
        },
        done: 'Locked {list} again.'
      }
    ],
    vpaa: [
      {
        id: 'approve',
        tab: 'awaiting',
        label: 'Approve and publish',
        tone: 'primary',
        primary: true,
        action: 'college_vpaa_decide',
        extra: { accept: true },
        eligible: function (c) { return c.status === 'pending_vpaa'; },
        confirm: {
          title: 'Approve and publish?',
          message: 'Deans and program chairs will see these timetables, and the colleges are locked.',
          yes: 'Approve and publish'
        },
        done: 'Published {list}.'
      },
      {
        id: 'sendBack',
        tab: 'awaiting',
        label: 'Send back',
        tone: 'danger',
        action: 'college_vpaa_decide',
        extra: { accept: false },
        eligible: function (c) { return c.status === 'pending_vpaa'; },
        note: {
          label: 'What should the University Registrar fix?',
          hint: 'If an earlier version was published, deans and program chairs keep seeing it.',
          placeholder: 'For example: Move PCH 111 lab to the morning.',
          required: 'Say what the University Registrar should fix.'
        },
        confirm: { title: 'Send back to the University Registrar?', message: '', yes: 'Send back' },
        done: 'Sent {list} back to the University Registrar.'
      }
    ]
  };

  var state = {
    actor: '',
    term: null, // { schoolYear, termCode }
    colleges: [],
    selected: {},
    showIdle: false,
    workspace: 'mine',
    copies: null, // promise of { sent: [...], mine: [...] } classes, for Compare
    open: false,
    loading: false,
    error: '',
    skipNextLoad: false,
    lockKey: ''
  };

  var root = null;
  var dialog = null;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatWhen(value) {
    if (!value) return '';
    var d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) +
      ', ' + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  }

  function plural(n, one, many) {
    return n + ' ' + (n === 1 ? one : many);
  }

  function actions() {
    return (ACTIONS[state.actor] || []).filter(function (a) {
      return !a.tab || a.tab === state.workspace;
    });
  }

  function hasAnyAction(c) {
    return actions().some(function (a) { return a.eligible(c); });
  }

  // colleges with nothing to show yet are tucked away
  function isIdle(c) {
    return c.status === 'draft' && !c.workspaceClasses && !c.hasPublished && !c.lastRejectFrom;
  }

  function byId(id) {
    for (var i = 0; i < state.colleges.length; i++) {
      if (state.colleges[i].id === id) return state.colleges[i];
    }
    return null;
  }

  function selectedColleges() {
    return state.colleges.filter(function (c) { return state.selected[c.id]; });
  }

  function notice(text) {
    var el = doc.createElement('div');
    el.className = 'ui-notice';
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4000);
  }

  function nameList(list) {
    var names = list.map(function (c) { return c.name; });
    if (names.length <= 1) return names.join('');
    return names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1];
  }

  // ----- status wording -----

  function statusInfo(c) {
    var me = state.actor;
    if (c.status === 'pending_super_admin') {
      return {
        chip: me === 'super_admin' ? 'Waiting for your approval' : 'With the University Registrar',
        tone: 'waiting',
        when: c.sentAt ? 'Sent ' + formatWhen(c.sentAt) : ''
      };
    }
    if (c.status === 'with_registrar') {
      if (c.lastRejectFrom === 'vpaa') {
        return { chip: me === 'super_admin' ? 'Sent back to you by the VPAA' : me === 'vpaa' ? 'You sent it back' : 'Sent back by the VPAA', tone: 'back', when: c.lastRejectAt ? formatWhen(c.lastRejectAt) : '' };
      }
      if (c.hasPublished && c.unlockedAt) {
        return { chip: 'Unlocked for fixes', tone: 'waiting', when: formatWhen(c.unlockedAt) };
      }
      if (me === 'super_admin') {
        return { chip: c.readyToSend ? 'Approved · ready for VPAA' : 'Approved · being finished', tone: 'waiting', when: '' };
      }
      if (me === 'vpaa') return { chip: 'With the University Registrar', tone: 'neutral', when: '' };
      return { chip: 'Approved by the University Registrar', tone: 'done', when: '' };
    }
    if (c.status === 'pending_vpaa') {
      return {
        chip: me === 'vpaa' ? 'Waiting for your approval' : 'With the VPAA',
        tone: 'waiting',
        when: c.sentAt ? 'Sent ' + formatWhen(c.sentAt) : ''
      };
    }
    if (c.status === 'published') {
      return {
        chip: c.locked ? 'Published · locked' : 'Published',
        tone: 'done',
        when: c.publishedAt ? formatWhen(c.publishedAt) : ''
      };
    }
    if (c.lastRejectFrom) {
      return {
        chip: 'Sent back by ' + (WHO[c.lastRejectFrom] || 'the approver'),
        tone: 'back',
        when: c.lastRejectAt ? formatWhen(c.lastRejectAt) : ''
      };
    }
    if (c.hasPublished) {
      return {
        chip: 'Unlocked for fixes',
        tone: 'waiting',
        when: c.unlockedAt ? formatWhen(c.unlockedAt) : ''
      };
    }
    return { chip: 'Not sent yet', tone: 'neutral', when: '' };
  }

  function noteLine(c) {
    if ((c.status === 'draft' || c.status === 'with_registrar') && c.lastRejectFrom && c.lastRejectNote) {
      return '<p class="cs-note cs-note--back"><strong>' + esc(WHO[c.lastRejectFrom] || 'Approver') + ':</strong> ' + esc(c.lastRejectNote) + '</p>';
    }
    if ((c.status === 'draft' || c.status === 'with_registrar') && c.hasPublished && c.unlockNote) {
      return '<p class="cs-note"><strong>Why it was unlocked:</strong> ' + esc(c.unlockNote) +
        ' The published timetable stays live until the fix is approved.</p>';
    }
    if (c.status === 'pending_super_admin' && state.actor === 'super_admin' && state.workspace === 'mine') {
      return '<p class="cs-note">The Records Officer sent this college. Approve it on the For approval tab to bring it into your Working timetable.</p>';
    }
    if (c.status === 'with_registrar' && state.actor === 'super_admin' && c.editedFromSent) {
      return '<p class="cs-note">You changed this college after approving it. The Records Officer is told when you send it to the VPAA.</p>';
    }
    if (c.locked && state.actor !== 'vpaa') {
      return '<p class="cs-note">Kept as published: Generate won’t move these classes and they can’t be edited' +
        (state.actor === 'super_admin' ? ' until you unlock it.' : '. Ask the University Registrar to unlock it to fix a mistake.') + '</p>';
    }
    return '';
  }

  function progressHtml(c) {
    if (state.actor === 'vpaa') {
      var n = c.status === 'pending_vpaa' ? c.pendingClasses : c.publishedClasses;
      return n ? '<span class="cs-progress">' + plural(n, 'class', 'classes') + '</span>' : '';
    }
    var total = c.workspaceClasses || 0;
    var open = c.workspaceUnscheduled || 0;
    if (!total) {
      return '<span class="cs-progress cs-progress--muted">No classes in your Working timetable</span>';
    }
    var pct = Math.round(((total - open) / total) * 100);
    var text = open
      ? '<strong>' + open + '</strong> of ' + total + ' not placed yet'
      : 'All ' + plural(total, 'class', 'classes') + ' placed';
    return '<span class="cs-progress' + (open ? ' cs-progress--warn' : ' cs-progress--ok') + '">' +
      '<span class="cs-bar" aria-hidden="true"><span style="width:' + pct + '%"></span></span>' + text + '</span>';
  }

  // ----- render -----

  function stepsHtml(active) {
    var counts = {};
    active.forEach(function (c) {
      var st = c.status === 'with_registrar' ? 'pending_super_admin' : c.status;
      counts[st] = (counts[st] || 0) + 1;
    });
    return '<ol class="cs-steps" aria-label="Approval steps">' + STEPS.map(function (s, i) {
      var n = counts[s.status] || 0;
      var mine = s.owner === state.actor;
      return '<li class="cs-step' + (n ? ' has-items' : '') + (mine ? ' is-mine' : '') + (s.status === 'published' ? ' is-final' : '') + '">' +
        '<span class="cs-step-num" aria-hidden="true">' + (s.status === 'published' ? '&#10003;' : i + 1) + '</span>' +
        '<span class="cs-step-text"><span class="cs-step-label">' + esc(s.label) + (mine ? ' <span class="cs-you">You</span>' : '') + '</span>' +
        '<span class="cs-step-count">' + (n ? plural(n, 'college', 'colleges') : 'None') + '</span></span></li>';
    }).join('') + '</ol>';
  }

  function rowHtml(c) {
    var info = statusInfo(c);
    var can = hasAnyAction(c);
    var checked = !!state.selected[c.id];
    return '<li class="cs-row' + (checked ? ' is-selected' : '') + (can ? '' : ' is-static') + '">' +
      '<label class="cs-pick">' +
      '<input type="checkbox" data-cs-pick="' + esc(c.id) + '"' + (checked ? ' checked' : '') + (can ? '' : ' disabled') +
      ' aria-label="Select ' + esc(c.name) + '">' +
      '<span class="cs-name">' + esc(c.name) + '</span></label>' +
      '<span class="cs-status"><span class="ui-chip ui-chip--' + info.tone + '">' + esc(info.chip) + '</span>' +
      (info.when ? '<span class="cs-when">' + esc(info.when) + '</span>' : '') + '</span>' +
      '<span class="cs-work">' + progressHtml(c) +
      (state.actor === 'super_admin' && state.workspace === 'submitted' && c.status === 'pending_super_admin'
        ? '<button type="button" class="cs-linkbtn cs-compare-link" data-cs-compare="' + esc(c.id) + '">Compare with my version</button>'
        : '') +
      '</span>' +
      noteLine(c) +
      '</li>';
  }

  function toolbarHtml() {
    var picked = selectedColleges();
    var list = actions().filter(function (a) {
      return state.colleges.some(function (c) { return a.eligible(c); });
    });
    if (!list.length) return '<p class="cs-hint">' + esc(idleHint()) + '</p>';
    var buttons = list.map(function (a) {
      var n = picked.filter(function (c) { return a.eligible(c); }).length;
      var cls = 'ui-btn' + (a.tone === 'primary' ? ' ui-btn--primary' : a.tone === 'danger' ? ' ui-btn--danger' : '');
      return '<button type="button" class="' + cls + '" data-cs-action="' + a.id + '"' + (n ? '' : ' disabled aria-disabled="true"') + '>' +
        esc(a.label) + (n ? ' <span class="cs-count">' + n + '</span>' : '') + '</button>';
    }).join('');
    var readyCount = state.colleges.filter(isReady).length;
    return '<div class="cs-toolbar">' +
      '<div class="cs-select">' +
      '<span class="cs-picked">' + (picked.length ? plural(picked.length, 'college', 'colleges') + ' selected' : 'Tick colleges to act on them') + '</span>' +
      (readyCount ? '<button type="button" class="cs-linkbtn" data-cs-select="ready">Select all ready (' + readyCount + ')</button>' : '') +
      (picked.length ? '<button type="button" class="cs-linkbtn" data-cs-select="none">Clear</button>' : '') +
      '</div><div class="cs-buttons">' + buttons + '</div></div>';
  }

  function idleHint() {
    var anyWork = state.colleges.some(function (c) { return c.workspaceClasses; });
    if (state.actor === 'vpaa') {
      return state.workspace === 'awaiting'
        ? 'Nothing is waiting for your approval right now. The University Registrar sends colleges here one at a time.'
        : 'Colleges you approve show here as published.';
    }
    if (state.actor === 'super_admin' && state.workspace === 'submitted') return 'Nothing is waiting for your approval right now.';
    if (!anyWork) return 'Your Working timetable is empty. Generate it below, then send each college from here when its classes are placed.';
    if (state.colleges.some(function (c) { return c.workspaceUnscheduled && c.status === 'draft'; })) {
      return 'Place every class of a college before sending it. Use Schedule remaining classes or Edit below.';
    }
    return 'Nothing to do right now.';
  }

  function isReady(c) {
    return actions().some(function (a) { return a.primary && a.eligible(c); });
  }

  function subtitle() {
    if (state.actor === 'admin') {
      return 'Where each college is. Take one back if you need to change it; send colleges from the Working timetable with Submit to University Registrar.';
    }
    if (state.actor === 'super_admin') {
      return 'Check what the Records Officer sent. Approve a college to bring it into your Working timetable, or send it back with a note. When it’s finished there, use Submit to VPAA.';
    }
    return state.workspace === 'awaiting'
      ? 'Approve each college to publish it, or send it back to the University Registrar with a note. Tick several, or use Select all ready, to approve them together.'
      : 'Where every college is. Colleges waiting for you are on the For your approval tab.';
  }

  function panelShown() {
    return state.actor === 'vpaa' || state.workspace === 'submitted';
  }

  // Working timetable: which colleges came back (with the note), which are away, or "all approved"
  function inWorking(c) {
    if (state.actor === 'admin') return c.status === 'draft';
    if (state.actor === 'super_admin') return ['draft', 'pending_super_admin', 'with_registrar'].indexOf(c.status) >= 0;
    return false;
  }

  function awayText(c) {
    if (c.status === 'published') return 'approved by the VPAA (in Reports)';
    if (c.status === 'pending_vpaa') return 'waiting for the VPAA';
    if (c.status === 'pending_super_admin') return 'waiting for the University Registrar';
    if (c.status === 'with_registrar') return 'with the University Registrar';
    return 'with someone else';
  }

  function cameBack(c) {
    if (state.actor === 'super_admin' && c.status === 'with_registrar') {
      if (c.lastRejectFrom === 'vpaa' && c.lastRejectNote) {
        return { who: 'The VPAA sent it back', note: c.lastRejectNote, at: c.lastRejectAt };
      }
      if (c.unlockedAt && c.unlockNote) return { who: 'You unlocked it to fix', note: c.unlockNote, at: c.unlockedAt };
    }
    if (state.actor === 'admin' && c.status === 'draft' && c.lastRejectFrom === 'super_admin' && c.lastRejectNote) {
      return { who: 'The University Registrar sent it back', note: c.lastRejectNote, at: c.lastRejectAt };
    }
    return null;
  }

  function renderWorkingNotes() {
    var box = doc.getElementById('schedWorkingNotes');
    if (!box) return;
    if (!state.term || state.workspace !== 'mine' || !state.colleges.length || state.actor === 'vpaa') {
      box.hidden = true;
      return;
    }
    var back = state.colleges.map(function (c) { return { c: c, b: cameBack(c) }; }).filter(function (x) { return x.b; });
    var away = state.colleges.filter(function (c) { return !inWorking(c); });
    var started = state.colleges.filter(function (c) { return c.status !== 'draft' || c.workspaceClasses || c.hasPublished; });
    var allApproved = started.length > 0 && started.every(function (c) { return c.status === 'published'; });
    var html = '';
    if (allApproved) {
      html += '<div class="cs-working-done"><strong>All colleges are approved for this term.</strong> ' +
        'Your Working timetable is empty because there is nothing left to schedule. The approved timetables are in <a href="final-report.html">Reports</a>.</div>';
    }
    back.forEach(function (x) {
      html += '<div class="cs-working-back"><strong>' + esc(x.c.name) + ' is back in your Working timetable.</strong> ' + esc(x.b.who) +
        (x.b.at ? ' (' + esc(formatWhen(x.b.at)) + ')' : '') + ': <q>' + esc(x.b.note) + '</q></div>';
    });
    if (away.length && !allApproved) {
      html += '<p class="cs-working-away"><strong>Not in your Working timetable:</strong> ' + away.map(function (c) {
        return esc(c.name) + ' (' + esc(awayText(c)) + ')';
      }).join(' · ') + '. Their rooms stay booked when you generate.</p>';
    }
    box.innerHTML = html;
    box.hidden = !html;
  }

  function render() {
    renderWorkingNotes();
    if (!root) return;
    syncSubmitButton();
    if (!state.term || !panelShown()) {
      root.hidden = true;
      return;
    }
    root.hidden = false;
    if (state.error && !state.colleges.length) {
      root.innerHTML = headHtml() + '<p class="cs-error" role="alert">' + esc(state.error) + '</p>';
      return;
    }
    if (!state.colleges.length) {
      root.innerHTML = headHtml() + '<p class="cs-empty">' + (state.loading ? 'Loading colleges…' : 'No colleges yet.') + '</p>';
      return;
    }
    var active = state.colleges.filter(function (c) { return !isIdle(c); });
    var idle = state.colleges.filter(isIdle);
    if (!state.open) {
      root.classList.add('is-collapsed');
      root.innerHTML = summaryHtml(active);
      return;
    }
    root.classList.remove('is-collapsed');
    var html = headHtml() + stepsHtml(active) + toolbarHtml();
    if (state.error) html += '<p class="cs-error" role="alert">' + esc(state.error) + '</p>';
    html += '<ul class="cs-list">' + active.map(rowHtml).join('') + '</ul>';
    if (idle.length) {
      html += '<button type="button" class="cs-linkbtn cs-idle-toggle" data-cs-idle aria-expanded="' + (state.showIdle ? 'true' : 'false') + '">' +
        (state.showIdle ? 'Hide' : 'Show') + ' ' + plural(idle.length, 'college', 'colleges') + ' with no classes yet</button>';
      if (state.showIdle) html += '<ul class="cs-list cs-list--idle">' + idle.map(rowHtml).join('') + '</ul>';
    }
    root.innerHTML = html;
  }

  function headHtml() {
    var term = state.term ? state.termLabel || '' : '';
    return '<div class="cs-head"><div><h2 class="cs-title" id="csTitle">Colleges</h2>' +
      '<p class="cs-sub">' + esc(subtitle()) + '</p></div>' +
      '<div class="cs-head-side">' + (term ? '<span class="ui-tag">' + esc(term) + '</span>' : '') +
      '<button type="button" class="ui-btn" data-cs-toggle aria-expanded="true" aria-controls="schedColleges">Hide</button></div></div>';
  }

  // one line: where the colleges are, and what's waiting for you
  function summaryHtml(active) {
    var mine = { admin: 'draft', super_admin: 'pending_super_admin', vpaa: 'pending_vpaa' }[state.actor];
    var n = { waiting: 0, ready: 0, back: 0, pending: 0, published: 0 };
    active.forEach(function (c) {
      if (c.status === 'published') n.published++;
      else if (c.status === mine && c.status !== 'draft') n.waiting++;
      else if (c.status === 'with_registrar' && state.actor === 'super_admin') n.ready++;
      else if (c.status === 'draft' && c.lastRejectFrom) n.back++;
      else if (c.status !== 'draft') n.pending++;
      if (isReady(c) && c.status === 'draft' && state.actor !== 'super_admin') n.ready++;
    });
    var chips = [];
    if (n.waiting) chips.push('<span class="ui-chip ui-chip--waiting">' + n.waiting + ' waiting for you</span>');
    if (n.back) chips.push('<span class="ui-chip ui-chip--back">' + n.back + ' sent back</span>');
    if (n.ready) chips.push('<span class="ui-chip">' + n.ready + (state.actor === 'super_admin' ? ' approved, not sent yet' : ' ready to send') + '</span>');
    if (n.pending) chips.push('<span class="ui-chip ui-chip--neutral">' + n.pending + (state.actor === 'vpaa' ? ' still with the registrars' : ' with the next approver') + '</span>');
    if (n.published) chips.push('<span class="ui-chip ui-chip--done">' + n.published + ' published</span>');
    if (!chips.length) chips.push('<span class="cs-summary-none">' + (active.length ? 'Nothing to do right now' : 'No colleges have classes yet') + '</span>');
    // the main thing to do on this tab, one click away (e.g. "Send to VPAA (2)")
    var quick = actions().filter(function (a) {
      return a.primary && state.colleges.some(function (c) { return a.eligible(c); });
    })[0];
    var quickCount = quick ? state.colleges.filter(function (c) { return quick.eligible(c); }).length : 0;
    return '<div class="cs-summary"><h2 class="cs-title" id="csTitle">Colleges</h2>' +
      '<div class="cs-summary-chips">' + chips.join('') + '</div>' +
      '<div class="cs-summary-actions">' +
      '<button type="button" class="ui-btn" data-cs-toggle aria-expanded="false" aria-controls="schedColleges">Review colleges</button>' +
      (quick ? '<button type="button" class="ui-btn ui-btn--primary" data-cs-quick="' + quick.id + '">' + esc(quick.label) + ' <span class="cs-count">' + quickCount + '</span></button>' : '') +
      '</div></div>';
  }

  function setOpen(open) {
    state.open = open;
    try {
      global.localStorage.setItem('mcu.colleges.open', open ? '1' : '0');
    } catch (err) {}
    render();
    var btn = root.querySelector('[data-cs-toggle]');
    if (btn) btn.focus();
  }

  // ----- Submit (Working timetable): pick the finished colleges to send on -----

  function submitAction() {
    return (ACTIONS[state.actor] || []).filter(function (a) { return a.tab === 'submit'; })[0] || null;
  }

  function notReadyReason(c) {
    var vpaa = state.actor === 'super_admin';
    if (c.status === 'pending_super_admin') {
      return vpaa ? 'Waiting for your approval. Approve it on For approval first.' : 'Already with the University Registrar.';
    }
    if (c.status === 'pending_vpaa') return 'Already with the VPAA.';
    if (c.status === 'with_registrar' && !vpaa) return 'With the University Registrar.';
    if (c.locked) return 'Published and locked.';
    if (!c.workspaceClasses) return 'No classes yet. Generate this college in your Working timetable first.';
    if (c.workspaceUnscheduled) return plural(c.workspaceUnscheduled, 'class', 'classes') + ' not placed yet.';
    return 'Not ready.';
  }

  function syncSubmitButton() {
    var btn = doc.getElementById('schedSubmitColleges');
    if (!btn) return;
    var a = submitAction();
    var show = !!(a && state.term && state.workspace === 'mine');
    btn.hidden = !show;
    if (!show) return;
    var n = state.colleges.filter(function (c) { return a.eligible(c); }).length;
    btn.innerHTML = esc(state.actor === 'super_admin' ? 'Submit to VPAA' : 'Submit to University Registrar') +
      (n ? ' <span class="cs-count">' + n + '</span>' : '');
  }

  function ensureSubmitDialog() {
    var d = doc.getElementById('csSubmit');
    if (d) return d;
    d = doc.createElement('dialog');
    d.id = 'csSubmit';
    d.className = 'ui-dialog cs-dialog cs-submit';
    d.setAttribute('aria-labelledby', 'csSubmitTitle');
    d.innerHTML =
      '<div class="ui-dialog-head"><h2 class="ui-dialog-title" id="csSubmitTitle"></h2>' +
      '<button type="button" class="ui-icon-btn" data-cs-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body" id="csSubmitBody"></div>' +
      '<div class="ui-dialog-foot"><p class="ui-field-error" id="csSubmitError" role="alert" hidden></p>' +
      '<button type="button" class="ui-btn" data-cs-close>Cancel</button>' +
      '<button type="button" class="ui-btn ui-btn--primary" id="csSubmitYes"></button></div>';
    doc.body.appendChild(d);
    d.addEventListener('click', function (e) {
      if (e.target.closest('[data-cs-close]') || e.target === d) d.close();
    });
    d.addEventListener('change', function (e) {
      if (e.target.matches('[data-submit-all]')) {
        d.querySelectorAll('[data-submit-pick]').forEach(function (b) { b.checked = e.target.checked; });
      }
      syncSubmitYes();
    });
    return d;
  }

  function submitPicked() {
    var d = doc.getElementById('csSubmit');
    return Array.prototype.filter.call(d.querySelectorAll('[data-submit-pick]'), function (b) { return b.checked; })
      .map(function (b) { return byId(b.getAttribute('data-submit-pick')); })
      .filter(Boolean);
  }

  function syncSubmitYes() {
    var yes = doc.getElementById('csSubmitYes');
    var picked = submitPicked();
    var a = submitAction();
    yes.textContent = (state.actor === 'super_admin' ? 'Submit to VPAA' : 'Submit to University Registrar') + (picked.length ? ' (' + picked.length + ')' : '');
    yes.disabled = !picked.length;
    var all = doc.querySelector('#csSubmit [data-submit-all]');
    var boxes = doc.querySelectorAll('#csSubmit [data-submit-pick]');
    if (all) all.checked = boxes.length > 0 && picked.length === boxes.length;
    return a;
  }

  function openSubmit() {
    var a = submitAction();
    if (!a) return;
    // still loading the colleges: open once they're here
    if (state.loading && state.loadPromise) {
      state.loadPromise.then(openSubmit, openSubmit);
      return;
    }
    var d = ensureSubmitDialog();
    var vpaa = state.actor === 'super_admin';
    doc.getElementById('csSubmitTitle').textContent = vpaa ? 'Submit to VPAA' : 'Submit to University Registrar';
    doc.getElementById('csSubmitError').hidden = true;
    // every college shows: ready ones to tick, the rest with the reason (Dentistry with no classes too)
    var ready = state.colleges.filter(function (c) { return a.eligible(c); });
    var waiting = state.colleges.filter(function (c) { return !a.eligible(c); });
    var html = '<p class="cs-dialog-msg">' + esc(vpaa
      ? 'These colleges are finished in your Working timetable. Tick the ones to send to the VPAA now, or send them all. Each one goes exactly as it is.'
      : 'These colleges are finished in your Working timetable. Tick the ones to send to the University Registrar now, or send them all.') + '</p>';
    if (ready.length) {
      html += '<label class="cs-submit-all"><input type="checkbox" data-submit-all checked> Select all (' + ready.length + ')</label>' +
        '<ul class="cs-submit-list">' + ready.map(function (c) {
          var note = c.lastRejectFrom && c.lastRejectNote
            ? '<span class="cs-submit-note">' + esc((WHO[c.lastRejectFrom] || 'Approver') + ': ' + c.lastRejectNote) + '</span>' : '';
          if (vpaa && c.status === 'pending_super_admin') {
            note = '<span class="cs-submit-info">The Records Officer also sent a version. Submitting yours closes their request (they’re told).</span>';
          }
          return '<li><label><input type="checkbox" data-submit-pick="' + esc(c.id) + '" checked>' +
            '<span class="cs-submit-name"><strong>' + esc(c.name) + '</strong><span>' + plural(c.workspaceClasses, 'class', 'classes') + ', all placed</span>' + note + '</span></label></li>';
        }).join('') + '</ul>';
    } else {
      html += '<p class="cs-hint">No college is ready to send yet.</p>';
    }
    if (waiting.length) {
      html += '<h3 class="cs-compare-h">Not ready yet</h3><ul class="cs-submit-list cs-submit-list--muted">' + waiting.map(function (c) {
        return '<li><span class="cs-submit-name"><strong>' + esc(c.name) + '</strong><span>' + esc(notReadyReason(c)) + '</span></span></li>';
      }).join('') + '</ul>';
    }
    doc.getElementById('csSubmitBody').innerHTML = html;
    var yes = doc.getElementById('csSubmitYes');
    yes.hidden = !ready.length;
    syncSubmitYes();
    yes.onclick = function () {
      var list = submitPicked();
      if (!list.length) return;
      var label = yes.textContent;
      yes.disabled = true;
      yes.textContent = 'Sending…';
      global.McuApi.send('POST', 'schedule.php', {
        action: a.action,
        actor: state.actor,
        schoolYear: state.term.schoolYear,
        termCode: state.term.termCode,
        colleges: list.map(function (c) { return c.id; })
      })
        .then(function (res) {
          d.close();
          state.copies = null;
          apply(res);
          notice(a.done.replace('{list}', nameList(list)));
          state.skipNextLoad = true;
          doc.dispatchEvent(new CustomEvent('mcu:schedule-reload'));
        })
        .catch(function (e) {
          yes.disabled = false;
          yes.textContent = label;
          var err = doc.getElementById('csSubmitError');
          err.textContent = (e && e.message) || 'That didn’t work. Try again.';
          err.hidden = false;
        });
    };
    d.showModal();
  }

  // ----- data -----

  function apply(res) {
    state.colleges = (res && res.colleges) || [];
    if (res && res.term && res.term.label) state.termLabel = res.term.label;
    var keep = {};
    state.colleges.forEach(function (c) {
      if (state.selected[c.id] && hasAnyAction(c)) keep[c.id] = true;
    });
    state.selected = keep;
    render();
    var key = state.colleges.filter(function (c) { return c.locked; }).map(function (c) { return c.id; }).join(',');
    if (key !== state.lockKey) {
      state.lockKey = key;
      doc.dispatchEvent(new CustomEvent('mcu:colleges-changed'));
    }
  }

  function load() {
    if (!state.term || !global.McuApi) return;
    state.copies = null;
    state.loading = true;
    render();
    var q = 'schedule.php?colleges=1&year=' + encodeURIComponent(state.term.schoolYear) + '&sem=' + encodeURIComponent(state.term.termCode);
    state.loadPromise = global.McuApi.get(q)
      .then(function (res) {
        state.loading = false;
        state.error = '';
        apply(res);
      })
      .catch(function (err) {
        state.loading = false;
        state.error = (err && err.message) || 'Could not load the colleges.';
        render();
      });
  }

  // ----- dialog -----

  function ensureDialog() {
    if (dialog) return dialog;
    dialog = doc.createElement('dialog');
    dialog.className = 'ui-dialog cs-dialog';
    dialog.setAttribute('aria-labelledby', 'csDialogTitle');
    dialog.innerHTML =
      '<div class="ui-dialog-head"><h2 class="ui-dialog-title" id="csDialogTitle"></h2>' +
      '<button type="button" class="ui-icon-btn" data-cs-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body">' +
      '<p class="cs-dialog-msg" id="csDialogMsg"></p>' +
      '<ul class="cs-dialog-list" id="csDialogList"></ul>' +
      '<div class="cs-dialog-note" id="csDialogNoteWrap" hidden>' +
      '<label class="ui-field-label" for="csDialogNote" id="csDialogNoteLabel"></label>' +
      '<textarea class="ui-textarea" id="csDialogNote" maxlength="2000" aria-describedby="csDialogNoteHint csDialogError"></textarea>' +
      '<p class="ui-field-hint" id="csDialogNoteHint"></p></div>' +
      '<div id="csDialogClash" hidden></div>' +
      '<p class="ui-field-error" id="csDialogError" role="alert" hidden></p>' +
      '</div>' +
      '<div class="ui-dialog-foot"><button type="button" class="ui-btn" data-cs-close>Cancel</button>' +
      '<button type="button" class="ui-btn" id="csDialogYes"></button></div>';
    doc.body.appendChild(dialog);
    dialog.addEventListener('click', function (e) {
      if (e.target.closest('[data-cs-close]') || e.target === dialog) dialog.close();
    });
    return dialog;
  }

  function setDialogError(text) {
    var el = doc.getElementById('csDialogError');
    el.textContent = text || '';
    el.hidden = !text;
    var note = doc.getElementById('csDialogNote');
    if (note) note.setAttribute('aria-invalid', text && !note.value.trim() ? 'true' : 'false');
  }

  function openAction(a) {
    var list = selectedColleges().filter(function (c) { return a.eligible(c); });
    if (!list.length) return;
    var d = ensureDialog();
    var skipped = selectedColleges().length - list.length;
    doc.getElementById('csDialogTitle').textContent = a.confirm.title;
    var msg = a.confirm.message || '';
    if (skipped) msg += (msg ? ' ' : '') + plural(skipped, 'selected college doesn’t', 'selected colleges don’t') + ' apply and will be left as they are.';
    var msgEl = doc.getElementById('csDialogMsg');
    msgEl.textContent = msg;
    msgEl.hidden = !msg;
    doc.getElementById('csDialogList').innerHTML = list.map(function (c) {
      var extra = state.actor !== 'vpaa' && c.workspaceClasses && (a.id === 'send' || a.id === 'sendVpaa')
        ? ' <span>' + plural(c.workspaceClasses, 'class', 'classes') + '</span>' : '';
      return '<li><strong>' + esc(c.name) + '</strong>' + extra + '</li>';
    }).join('');
    var wrap = doc.getElementById('csDialogNoteWrap');
    var note = doc.getElementById('csDialogNote');
    wrap.hidden = !a.note;
    note.value = '';
    if (a.note) {
      doc.getElementById('csDialogNoteLabel').textContent = a.note.label;
      doc.getElementById('csDialogNoteHint').textContent = a.note.hint || '';
      note.placeholder = a.note.placeholder || '';
    }
    setDialogError('');
    var clashBox = doc.getElementById('csDialogClash');
    clashBox.innerHTML = '';
    clashBox.hidden = !a.clashChoice;
    if (a.clashChoice) {
      clashBox.innerHTML = '<p class="cs-empty">Checking for clashes with your other colleges…</p>';
      var ids = list.map(function (c) { return c.id; });
      loadCopies()
        .then(function (copies) { clashBox.innerHTML = clashChoiceHtml(clashesIfUsingSent(copies, ids)); })
        .catch(function () { clashBox.innerHTML = ''; });
    }
    var yes = doc.getElementById('csDialogYes');
    yes.className = 'ui-btn' + (a.tone === 'danger' ? ' ui-btn--danger' : ' ui-btn--primary');
    yes.textContent = a.confirm.yes + (list.length > 1 ? ' (' + list.length + ')' : '');
    yes.disabled = false;
    yes.onclick = function () { run(a, list, note.value.trim(), yes); };
    d.showModal();
    if (a.note) note.focus();
    else yes.focus();
  }

  function run(a, list, note, btn) {
    if (a.note && !note) {
      setDialogError(a.note.required);
      doc.getElementById('csDialogNote').focus();
      return;
    }
    var label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Working…';
    setDialogError('');
    var body = {
      action: a.action,
      actor: state.actor,
      schoolYear: state.term.schoolYear,
      termCode: state.term.termCode,
      colleges: list.map(function (c) { return c.id; })
    };
    if (a.extra) Object.keys(a.extra).forEach(function (k) { body[k] = a.extra[k]; });
    if (a.note) body.note = note;
    global.McuApi.send('POST', 'schedule.php', body)
      .then(function (res) {
        dialog.close();
        if (a.clashChoice) {
          var one0 = list.length === 1;
          afterApprove(res, list, a.done.replace('{list}', nameList(list)).replace(/\{it\}/g, one0 ? 'it' : 'them')
            .replace(/\{It’s\}/g, one0 ? 'It’s' : 'They’re'), chosenMove(doc.getElementById('csDialogClash')));
          return;
        }
        state.selected = {};
        state.error = '';
        state.copies = null;
        apply(res);
        var one = list.length === 1;
        notice(a.done.replace('{list}', nameList(list)).replace(/\{them\}/g, one ? 'it' : 'them')
          .replace(/\{it\}/g, one ? 'it' : 'them').replace(/\{It’s\}/g, one ? 'It’s' : 'They’re'));
        // the read-only tabs and locks changed; reload the timetable below
        state.skipNextLoad = true;
        doc.dispatchEvent(new CustomEvent('mcu:schedule-reload'));
      })
      .catch(function (err) {
        btn.disabled = false;
        btn.textContent = label;
        setDialogError((err && err.message) || 'That didn’t work. Try again.');
      });
  }

  // ----- compare (University Registrar, For approval tab) -----

  var DAY_ORDER = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

  function loadCopies() {
    if (!state.copies) {
      var base = 'schedule.php?actor=super_admin&page=create&live=1&workspace=';
      state.copies = Promise.all([global.McuApi.get(base + 'submitted'), global.McuApi.get(base + 'mine')])
        .then(function (r) {
          return { sent: r[0].classes || [], mine: r[1].classes || [] };
        })
        .catch(function (err) {
          state.copies = null;
          throw err;
        });
    }
    return state.copies;
  }

  function mins(hhmm) {
    var p = String(hhmm || '').split(':');
    return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
  }

  function clock(hhmm) {
    var m = mins(hhmm);
    var h = Math.floor(m / 60);
    var mm = m % 60;
    return ((h + 11) % 12 + 1) + (mm ? ':' + (mm < 10 ? '0' : '') + mm : '') + (h < 12 ? 'am' : 'pm');
  }

  function meetingText(m) {
    return m.day + ' ' + clock(m.start) + '–' + clock(m.end) + ' · ' + (m.roomCode || m.roomLabel || (m.roomId ? m.roomId : 'Off-campus'));
  }

  function whenText(c) {
    var ms = (c && c.meetings) || [];
    if (!ms.length) return 'Not placed';
    return ms
      .slice()
      .sort(function (a, b) { return (DAY_ORDER[a.day] || 9) - (DAY_ORDER[b.day] || 9) || mins(a.start) - mins(b.start); })
      .map(meetingText)
      .join('; ');
  }

  function sig(c) {
    return ((c && c.meetings) || []).map(function (m) { return m.day + m.start + m.end + (m.roomId || ''); }).sort().join('|');
  }

  function ofCollege(list, cid) {
    return list.filter(function (c) { return c.collegeId === cid; });
  }

  // what differs between the Records Officer's and the Registrar's version of these colleges
  function compareColleges(copies, ids) {
    var sent = copies.sent.filter(function (c) { return ids.indexOf(c.collegeId) >= 0; });
    var mine = copies.mine.filter(function (c) { return ids.indexOf(c.collegeId) >= 0; });
    var mineById = {};
    mine.forEach(function (c) { mineById[c.id] = c; });
    var changed = [];
    var onlySent = [];
    sent.forEach(function (c) {
      var m = mineById[c.id];
      if (!m) onlySent.push(c);
      else if (sig(m) !== sig(c)) changed.push({ sent: c, mine: m });
      delete mineById[c.id];
    });
    var onlyMine = Object.keys(mineById).map(function (k) { return mineById[k]; });
    return { sent: sent, mine: mine, changed: changed, onlySent: onlySent, onlyMine: onlyMine };
  }

  // room or students double-booked if the Records Officer's version of these colleges
  // joins the Registrar's other colleges
  function clashesIfUsingSent(copies, ids) {
    var incoming = copies.sent.filter(function (c) { return ids.indexOf(c.collegeId) >= 0; });
    var staying = copies.mine.filter(function (c) { return ids.indexOf(c.collegeId) < 0; });
    var out = [];
    incoming.forEach(function (a) {
      (a.meetings || []).forEach(function (am) {
        staying.forEach(function (b) {
          (b.meetings || []).forEach(function (bm) {
            if (am.day !== bm.day) return;
            if (!(mins(am.start) < mins(bm.end) && mins(bm.start) < mins(am.end))) return;
            var room = am.roomId && am.roomId === bm.roomId;
            var students = a.sectionId && a.sectionId === b.sectionId;
            if (!room && !students) return;
            out.push({ sent: a, sentMeeting: am, mine: b, mineMeeting: bm, why: room ? 'room' : 'students' });
          });
        });
      });
    });
    return out;
  }

  function classLabel(c) {
    return c.courseCode + ' ' + c.component + ' · ' + (c.sectionName || '');
  }

  function clashListHtml(clashes) {
    var shown = clashes.slice(0, 8);
    return '<ul class="cs-clash-list">' + shown.map(function (x) {
      return '<li><strong>' + esc(x.sentMeeting.day + ' ' + clock(x.sentMeeting.start) + '–' + clock(x.sentMeeting.end)) + '</strong>' +
        (x.why === 'room' ? ' in ' + esc(x.sentMeeting.roomCode || x.sentMeeting.roomId) : ' (same students)') + ': ' +
        esc(classLabel(x.sent)) + ' <span>(Records Officer)</span> and ' + esc(classLabel(x.mine)) + ' <span>(yours)</span></li>';
    }).join('') + (clashes.length > shown.length ? '<li>…and ' + (clashes.length - shown.length) + ' more</li>' : '') + '</ul>';
  }

  // radio choice: which side moves when approving would clash
  function clashChoiceHtml(clashes) {
    if (!clashes.length) {
      return '<p class="cs-ok-line">No clashes: the Records Officer’s version fits with your other colleges.</p>';
    }
    var mineMoving = {};
    var sentMoving = {};
    clashes.forEach(function (x) { mineMoving[x.mine.id] = x.mine; sentMoving[x.sent.id] = x.sent; });
    var nMine = Object.keys(mineMoving).length;
    var nSent = Object.keys(sentMoving).length;
    return '<div class="cs-clash"><p class="cs-clash-head"><strong>' + plural(clashes.length, 'clash', 'clashes') +
      '</strong> with your other colleges if you use the Records Officer’s version:</p>' + clashListHtml(clashes) +
      '<fieldset class="cs-choice"><legend>Who moves?</legend>' +
      '<label><input type="radio" name="csMove" value="mine" checked> Move my classes (' + nMine + ') to free rooms. The Records Officer’s version stays as sent.</label>' +
      '<label><input type="radio" name="csMove" value="theirs"> Move the Records Officer’s classes (' + nSent + ') to free rooms. Your other colleges stay as they are.</label>' +
      '<label><input type="radio" name="csMove" value="none"> Don’t move anything. I’ll fix the clashes myself with Needs attention.</label>' +
      '</fieldset><p class="ui-field-hint">Moved classes go to the first free room and time that fits their room type, size, and college. Anything with no free spot is left unplaced, never double-booked.</p></div>';
  }

  function ensureCompareDialog() {
    var d = doc.getElementById('csCompare');
    if (d) return d;
    d = doc.createElement('dialog');
    d.id = 'csCompare';
    d.className = 'ui-dialog cs-compare';
    d.setAttribute('aria-labelledby', 'csCompareTitle');
    d.innerHTML =
      '<div class="ui-dialog-head"><div><h2 class="ui-dialog-title" id="csCompareTitle">Compare</h2>' +
      '<p class="cs-compare-sub">The Records Officer’s version next to yours</p></div>' +
      '<button type="button" class="ui-icon-btn" data-cs-close aria-label="Close">&times;</button></div>' +
      '<div class="ui-dialog-body" id="csCompareBody"></div>' +
      '<div class="ui-dialog-foot"><p class="ui-field-error" id="csCompareError" role="alert" hidden></p>' +
      '<button type="button" class="ui-btn ui-btn--danger" id="csCompareBack">Send back</button>' +
      '<button type="button" class="ui-btn" id="csCompareKeep">Keep mine</button>' +
      '<button type="button" class="ui-btn ui-btn--primary" id="csCompareUse">Use the Records Officer’s</button></div>';
    doc.body.appendChild(d);
    d.addEventListener('click', function (e) {
      if (e.target.closest('[data-cs-close]') || e.target === d) d.close();
    });
    return d;
  }

  function openCompare(collegeId) {
    var c = byId(collegeId);
    if (!c) return;
    var d = ensureCompareDialog();
    doc.getElementById('csCompareTitle').textContent = 'Compare: ' + c.name;
    var body = doc.getElementById('csCompareBody');
    var err = doc.getElementById('csCompareError');
    err.hidden = true;
    body.innerHTML = '<p class="cs-empty">Loading both versions…</p>';
    d.showModal();
    loadCopies()
      .then(function (copies) {
        var cmp = compareColleges(copies, [collegeId]);
        var clashes = clashesIfUsingSent(copies, [collegeId]);
        var hasMine = cmp.mine.length > 0;
        var unplaced = function (list) { return list.filter(function (x) { return !(x.meetings || []).length; }).length; };
        var html = '<div class="cs-compare-stats">' +
          '<div><span>Records Officer’s</span><strong>' + plural(cmp.sent.length, 'class', 'classes') + '</strong>' +
          (unplaced(cmp.sent) ? '<em>' + unplaced(cmp.sent) + ' not placed</em>' : '') + '</div>' +
          '<div><span>Yours</span><strong>' + (hasMine ? plural(cmp.mine.length, 'class', 'classes') : 'None yet') + '</strong>' +
          (unplaced(cmp.mine) ? '<em>' + unplaced(cmp.mine) + ' not placed</em>' : '') + '</div>' +
          '<div><span>Different</span><strong>' + (hasMine ? cmp.changed.length + cmp.onlySent.length + cmp.onlyMine.length : '—') + '</strong></div></div>';
        if (!hasMine) {
          html += '<p class="cs-ok-line">You haven’t made your own version of this college, so there’s nothing to compare. Use the Records Officer’s or send it back.</p>';
        } else if (!cmp.changed.length && !cmp.onlySent.length && !cmp.onlyMine.length) {
          html += '<p class="cs-ok-line">Both versions are the same.</p>';
        } else {
          var rows = cmp.changed.slice(0, 40).map(function (x) {
            return '<tr><td>' + esc(classLabel(x.sent)) + '</td><td>' + esc(whenText(x.sent)) + '</td><td>' + esc(whenText(x.mine)) + '</td></tr>';
          });
          cmp.onlySent.slice(0, 10).forEach(function (x) {
            rows.push('<tr><td>' + esc(classLabel(x)) + '</td><td>' + esc(whenText(x)) + '</td><td><em>Not in yours</em></td></tr>');
          });
          cmp.onlyMine.slice(0, 10).forEach(function (x) {
            rows.push('<tr><td>' + esc(classLabel(x)) + '</td><td><em>Not in theirs</em></td><td>' + esc(whenText(x)) + '</td></tr>');
          });
          var total = cmp.changed.length + cmp.onlySent.length + cmp.onlyMine.length;
          html += '<h3 class="cs-compare-h">What’s different</h3><div class="cs-compare-scroll"><table class="cs-compare-table"><thead><tr><th>Class</th><th>Records Officer’s</th><th>Yours</th></tr></thead><tbody>' +
            rows.join('') + '</tbody></table></div>' +
            (total > rows.length ? '<p class="ui-field-hint">Showing ' + rows.length + ' of ' + total + '. Open the For approval and Working timetable tabs to see every section.</p>' : '');
        }
        html += '<h3 class="cs-compare-h">If you use the Records Officer’s</h3>' + clashChoiceHtml(clashes);
        body.innerHTML = html;
        var keep = doc.getElementById('csCompareKeep');
        keep.hidden = !hasMine;
        keep.onclick = function () { decideFromCompare(c, 'keep', keep); };
        var use = doc.getElementById('csCompareUse');
        use.onclick = function () { decideFromCompare(c, 'use', use); };
        doc.getElementById('csCompareBack').onclick = function () {
          d.close();
          state.selected = {};
          state.selected[c.id] = true;
          render();
          var a = actions().filter(function (x) { return x.id === 'sendBack'; })[0];
          if (a) openAction(a);
        };
      })
      .catch(function (e) {
        body.innerHTML = '<p class="cs-error">' + esc((e && e.message) || 'Couldn’t load both versions.') + '</p>';
      });
  }

  function decideFromCompare(c, how, btn) {
    var err = doc.getElementById('csCompareError');
    var label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Working…';
    err.hidden = true;
    var move = how === 'use' ? chosenMove(doc.getElementById('csCompareBody')) : 'none';
    var body = {
      action: 'college_registrar_decide',
      actor: state.actor,
      schoolYear: state.term.schoolYear,
      termCode: state.term.termCode,
      colleges: [c.id],
      accept: true
    };
    if (how === 'keep') body.keepMine = true;
    global.McuApi.send('POST', 'schedule.php', body)
      .then(function (res) {
        doc.getElementById('csCompare').close();
        btn.disabled = false;
        btn.textContent = label;
        afterApprove(res, [c], how === 'keep'
          ? 'Approved ' + c.name + ', keeping your version. Send it to the VPAA when you’re happy with it.'
          : 'Approved ' + c.name + ' with the Records Officer’s version.', move);
      })
      .catch(function (e) {
        btn.disabled = false;
        btn.textContent = label;
        err.textContent = (e && e.message) || 'That didn’t work. Try again.';
        err.hidden = false;
      });
  }

  function chosenMove(scope) {
    var r = scope && scope.querySelector('input[name="csMove"]:checked');
    return r ? r.value : 'none';
  }

  // after approving: if something has to move, do it on the Working timetable tab
  function afterApprove(res, list, message, move) {
    state.selected = {};
    state.error = '';
    state.copies = null;
    apply(res);
    if (move === 'mine' || move === 'theirs') {
      try {
        global.sessionStorage.setItem('mcu.resolveClashes', JSON.stringify({
          move: move,
          colleges: list.map(function (c) { return c.id; })
        }));
      } catch (err) {}
      notice(message + ' Moving the clashing classes now…');
      var tab = doc.querySelector('[data-ws-tab="mine"]');
      if (tab) {
        tab.click();
        return;
      }
    }
    notice(message);
    state.skipNextLoad = true;
    doc.dispatchEvent(new CustomEvent('mcu:schedule-reload'));
  }

  // ----- events -----

  function onClick(e) {
    var t = e.target;
    var act = t.closest('[data-cs-action]');
    if (act && !act.disabled) {
      var id = act.getAttribute('data-cs-action');
      var a = actions().filter(function (x) { return x.id === id; })[0];
      if (a) openAction(a);
      return;
    }
    var cmpBtn = t.closest('[data-cs-compare]');
    if (cmpBtn) {
      openCompare(cmpBtn.getAttribute('data-cs-compare'));
      return;
    }
    var sel = t.closest('[data-cs-select]');
    if (sel) {
      var mode = sel.getAttribute('data-cs-select');
      state.selected = {};
      if (mode === 'ready') {
        state.colleges.filter(isReady).forEach(function (c) { state.selected[c.id] = true; });
      }
      render();
      return;
    }
    var quickBtn = t.closest('[data-cs-quick]');
    if (quickBtn) {
      var qa = actions().filter(function (x) { return x.id === quickBtn.getAttribute('data-cs-quick'); })[0];
      if (!qa) return;
      // open the panel with every ready college ticked, then confirm (untick any to leave them out)
      state.selected = {};
      state.colleges.forEach(function (c) { if (qa.eligible(c)) state.selected[c.id] = true; });
      setOpen(true);
      openAction(qa);
      return;
    }
    if (t.closest('[data-cs-toggle]')) {
      setOpen(!state.open);
      return;
    }
    if (t.closest('[data-cs-idle]')) {
      state.showIdle = !state.showIdle;
      render();
    }
  }

  function onChange(e) {
    var box = e.target.closest('[data-cs-pick]');
    if (!box) return;
    var id = box.getAttribute('data-cs-pick');
    if (box.checked) state.selected[id] = true;
    else delete state.selected[id];
    render();
    var again = root.querySelector('[data-cs-pick="' + id + '"]');
    if (again) again.focus();
  }

  function onScheduleLoaded(e) {
    var d = (e && e.detail) || {};
    // Create schedule (Records Officer, University Registrar) or the VPAA's Reports page
    var offering = d.offering && !d.archived ? { schoolYear: d.offering.schoolYear, termCode: d.offering.termCode } : null;
    // the report page can show another term; its year list may still be filling on first load
    var term = d.page === 'report' && d.year ? { schoolYear: d.year, termCode: d.sem || '1' } : offering;
    var tabs = d.page === 'report' ? ['awaiting', 'final'] : ['mine', 'submitted'];
    if (!term || tabs.indexOf(d.workspace) < 0) {
      state.term = null;
      render();
      return;
    }
    var same = state.term && state.term.schoolYear === term.schoolYear && state.term.termCode === term.termCode;
    state.term = term;
    state.workspace = d.workspace;
    if (state.skipNextLoad && same) {
      state.skipNextLoad = false;
      render();
      return;
    }
    state.skipNextLoad = false;
    load();
  }

  function init() {
    root = doc.getElementById('schedColleges');
    var page = doc.getElementById('schedPage');
    state.actor = (page && page.getAttribute('data-actor')) || '';
    if (!root || !ACTIONS[state.actor]) return;
    try {
      state.open = global.localStorage.getItem('mcu.colleges.open') === '1';
    } catch (err) {}
    root.addEventListener('click', onClick);
    var submitBtn = doc.getElementById('schedSubmitColleges');
    if (submitBtn) submitBtn.addEventListener('click', openSubmit);
    root.addEventListener('change', onChange);
    doc.addEventListener('mcu:schedule-loaded', onScheduleLoaded);
  }

  global.McuScheduleColleges = {
    isLocked: function (collegeId) {
      var c = byId(collegeId);
      return !!(c && c.locked);
    },
    reload: load
  };

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
