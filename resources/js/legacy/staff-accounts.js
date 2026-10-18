/**
 * Account management for the Records Officer and University Registrar (api/users.php).
 *
 * The server decides who each role may see and assign: University Registrar manages
 * Records Officer, VPAA, Dean and Program Chair accounts; Records Officer manages Dean and
 * Program Chair accounts. Deans need a college; Program Chairs a college
 * and one of its departments. Editing an account asks for your own
 * password, as the server requires.
 *
 * Passwords: a new account is invited by email ("Set up your account": they choose their own
 * password, which also confirms the email). The backup, and "Give a new starting password" on
 * Edit: Generate makes a starting password (mcu + 4 digits) to share privately; the person signs
 * in with it once and must choose their own. Changed emails get a confirmation link (needed for
 * "Forgot password?"); the list shows who hasn't confirmed or set up yet.
 *
 * Colleges and departments come from OrgStore (org-store.js + data-boot.js).
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var list = doc.getElementById('amList');
  if (!list) return;

  var ROLE_LABEL = {
    super_admin: 'University Registrar',
    admin: 'Records Officer',
    vpaa: 'VPAA',
    dean: 'Dean',
    program_chair: 'Program Chair',
  };
  var ROLE_HINT = {
    admin: 'Builds schedules and manages data. Can add Dean and Program Chair accounts.',
    vpaa: 'Reviews and approves timetables.',
    dean: 'Views their college’s schedules and sends change requests.',
    program_chair: 'Views their department’s schedules and sends requests to their dean.',
  };

  var users = [];
  var roles = [];
  var statusFilter = 'active';
  var roleFilter = '';
  var query = '';
  var editing = null; // user being edited, or null when adding

  var dialog = doc.getElementById('amDialog');
  var form = doc.getElementById('amForm');
  var f = {
    name: doc.getElementById('amName'),
    email: doc.getElementById('amEmail'),
    role: doc.getElementById('amRole'),
    roleHint: doc.getElementById('amRoleHint'),
    roleFixed: doc.getElementById('amRoleFixed'),
    collegeRow: doc.getElementById('amCollegeRow'),
    college: doc.getElementById('amCollege'),
    deptRow: doc.getElementById('amDeptRow'),
    dept: doc.getElementById('amDept'),
    deptHint: doc.getElementById('amDeptHint'),
    pwLabel: doc.getElementById('amPasswordLabel'),
    pwHint: doc.getElementById('amPasswordHint'),
    pw: doc.getElementById('amPassword'),
    pw2: doc.getElementById('amPassword2'),
    activeRow: doc.getElementById('amActiveRow'),
    active: doc.getElementById('amActive'),
    activeText: doc.getElementById('amActiveText'),
    confirmRow: doc.getElementById('amConfirmRow'),
    confirm: doc.getElementById('amConfirm'),
    save: doc.getElementById('amSave'),
    formError: doc.getElementById('amFormError'),
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function initials(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(function (p) { return /[A-Za-z]/.test(p); });
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function needsCollege(role) {
    return role === 'dean' || role === 'program_chair';
  }

  // ----- list -----

  function visible() {
    var q = query.trim().toLowerCase();
    return users.filter(function (u) {
      if (statusFilter === 'active' && !u.isActive) return false;
      if (statusFilter === 'inactive' && u.isActive) return false;
      if (roleFilter && u.role !== roleFilter) return false;
      if (!q) return true;
      return [u.name, u.email, u.roleLabel, u.scopeLabel].join(' ').toLowerCase().indexOf(q) !== -1;
    });
  }

  function canEdit(u) {
    return u.role === 'super_admin' ? doc.getElementById('amPage').getAttribute('data-actor') === 'super_admin' : roles.indexOf(u.role) !== -1;
  }

  // account emails: confirmed or not (with "Send confirmation email"), and a starting password not changed yet
  function emailState(u) {
    var bits = [];
    if (u.passwordPending) {
      bits.push('<span class="am-mail am-mail--no">Invite sent, not set up yet</span>' +
        (canEdit(u) && u.isActive ? '<button type="button" class="am-link" data-am-send="' + esc(u.id) + '">Resend invite</button>' : ''));
      return '<span class="am-mailrow">' + bits.join('') + '</span>';
    }
    if (u.emailConfirmed) {
      bits.push('<span class="am-mail am-mail--ok">Email confirmed</span>');
    } else {
      bits.push('<span class="am-mail am-mail--no">Email not confirmed</span>' +
        (canEdit(u) && u.isActive ? '<button type="button" class="am-link" data-am-send="' + esc(u.id) + '">Send confirmation email</button>' : ''));
    }
    if (u.mustChangePassword) bits.push('<span class="am-mail am-mail--wait">Hasn’t chosen a password yet</span>');
    return '<span class="am-mailrow">' + bits.join('') + '</span>';
  }

  // a starting password like "mcu4827": easy to read out, used once, then they choose their own
  function generatePassword() {
    var n = new Uint32Array(1);
    (global.crypto || global.msCrypto).getRandomValues(n);
    return 'mcu' + String(1000 + (n[0] % 9000));
  }

  function setStartPassword(value) {
    f.pw.value = value;
    f.pw2.value = value;
    doc.getElementById('amCopy').hidden = !value;
    doc.getElementById('amClearPw').hidden = !value || !editing;
    doc.getElementById('amGenerate').textContent = value ? 'Generate another' : (editing ? 'Give a new starting password' : 'Generate');
    setError(f.pw, '');
  }

  function useStart(on) {
    doc.getElementById('amInviteRow').hidden = on;
    doc.getElementById('amStartRow').hidden = !on;
    if (on && !editing && !f.pw.value) setStartPassword(generatePassword());
    if (!on) setStartPassword('');
  }

  function renderCounts() {
    var c = { active: 0, inactive: 0, all: users.length };
    users.forEach(function (u) { c[u.isActive ? 'active' : 'inactive']++; });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-am-count]'), function (el) {
      el.textContent = String(c[el.getAttribute('data-am-count')]);
    });
  }

  function render() {
    renderCounts();
    list.removeAttribute('aria-busy');
    var rows = visible();
    if (!rows.length) {
      var title = query.trim() || roleFilter ? 'No accounts match your filters' : statusFilter === 'inactive' ? 'No inactive accounts' : 'No accounts yet';
      var body = query.trim() || roleFilter ? 'Try a different name, email, or role.' : statusFilter === 'inactive' ? 'Accounts you turn off will show up here.' : 'Add an account to give someone access.';
      list.innerHTML = '<div class="ui-empty"><h3>' + esc(title) + '</h3><p>' + esc(body) + '</p></div>';
      return;
    }
    list.innerHTML = '<table class="am-table"><thead><tr><th scope="col">Name</th><th scope="col">Role</th>' +
      '<th scope="col">College / department</th><th scope="col">Status</th><th scope="col"><span class="ui-sr">Actions</span></th></tr></thead><tbody>' +
      rows.map(function (u) {
        return '<tr>' +
          '<td><div class="am-person"><span class="am-avatar" aria-hidden="true">' + esc(initials(u.name)) + '</span>' +
            '<span><span class="am-name">' + esc(u.name) + '</span><span class="am-email" title="' + esc(u.email) + '">' + esc(u.email) + '</span>' + emailState(u) + '</span></div></td>' +
          '<td data-label="Role"><span class="ui-tag">' + esc(u.roleLabel || ROLE_LABEL[u.role] || u.role) + '</span></td>' +
          '<td data-label="College / department" class="am-scope">' + (u.scopeLabel ? esc(u.scopeLabel) : '<span class="am-none">All colleges</span>') + '</td>' +
          '<td data-label="Status"><span class="ui-chip ' + (u.isActive ? 'ui-chip--done">Active' : 'ui-chip--neutral">Inactive') + '</span></td>' +
          '<td class="am-actions">' + (canEdit(u)
            ? '<button type="button" class="ui-btn am-edit" data-am-edit="' + esc(u.id) + '">Edit<span class="ui-sr"> ' + esc(u.name) + '</span></button>'
            : '') + '</td>' +
          '</tr>';
      }).join('') + '</tbody></table>';
  }

  // ----- form -----

  function fillRoles(selected) {
    f.role.innerHTML = '<option value="">Choose a role…</option>' + roles.map(function (r) {
      return '<option value="' + r + '">' + esc(ROLE_LABEL[r] || r) + '</option>';
    }).join('');
    f.role.value = selected || '';
  }

  function fillColleges(selected) {
    var cols = global.OrgStore ? global.OrgStore.colleges() : [];
    f.college.innerHTML = '<option value="">' + (cols.length ? 'Choose a college…' : 'No colleges yet. Add one in Data management.') + '</option>' +
      cols.map(function (c) { return '<option value="' + esc(c.id) + '">' + esc(c.name) + '</option>'; }).join('');
    f.college.value = selected || '';
  }

  function fillDepts(selected) {
    var depts = global.OrgStore && f.college.value ? global.OrgStore.orgDepartments(f.college.value) || [] : [];
    f.dept.innerHTML = '<option value="">' + (depts.length ? 'Choose a department…' : '—') + '</option>' +
      depts.map(function (d) { return '<option value="' + esc(d.id) + '">' + esc(d.name) + '</option>'; }).join('');
    f.dept.value = selected || '';
    f.dept.disabled = !depts.length;
    f.deptHint.textContent = !f.college.value
      ? 'Choose a college first.'
      : depts.length
        ? 'A Program Chair covers every program in their department.'
        : 'This college has no departments, so it can only have a Dean.';
  }

  function syncRoleFields() {
    var role = editing && editing.role === 'super_admin' ? 'super_admin' : f.role.value;
    f.roleHint.textContent = ROLE_HINT[role] || '';
    f.collegeRow.hidden = !needsCollege(role);
    f.deptRow.hidden = role !== 'program_chair';
    if (role === 'program_chair') fillDepts(f.dept.value);
  }

  function setError(input, msg) {
    var el = doc.getElementById(input.id + 'Error');
    if (el) {
      el.textContent = msg || '';
      el.hidden = !msg;
    }
    if (msg) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  }

  function clearErrors() {
    Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid]'), function (el) { setError(el, ''); });
    f.formError.hidden = true;
  }

  function openForm(user) {
    editing = user || null;
    form.reset();
    clearErrors();
    var isEdit = !!editing;
    doc.getElementById('amDialogTitle').textContent = isEdit ? 'Edit ' + editing.name : 'Add an account';
    f.save.textContent = isEdit ? 'Save changes' : 'Create account';
    var fixedSuper = isEdit && editing.role === 'super_admin';
    f.role.hidden = fixedSuper;
    f.roleFixed.hidden = !fixedSuper;
    fillRoles(isEdit ? editing.role : '');
    fillColleges(isEdit ? editing.collegeId : '');
    fillDepts(isEdit ? editing.departmentId : '');
    if (isEdit) {
      f.name.value = editing.name || '';
      f.email.value = editing.email || '';
      f.active.checked = !!editing.isActive;
    }
    f.pwLabel.textContent = 'Starting password';
    f.pwHint.textContent = isEdit
      ? 'Only if they can’t get in: give them a new starting password. They sign in with it once, then choose their own.'
      : 'Share it with them privately. They sign in with it once, then choose their own password. We still email them a link to confirm their email.';
    setStartPassword('');
    // new accounts: an invite by default; a starting password only if their email doesn't work
    useStart(isEdit);
    f.activeRow.hidden = !isEdit;
    f.confirmRow.hidden = !isEdit;
    syncActiveText();
    syncRoleFields();
    dialog.showModal();
    f.name.focus();
  }

  function syncActiveText() {
    f.activeText.textContent = f.active.checked ? 'Active: can log in' : 'Inactive: can’t log in';
  }

  function validate() {
    clearErrors();
    var ok = true;
    function bad(input, msg) {
      setError(input, msg);
      ok = false;
    }
    var role = editing && editing.role === 'super_admin' ? 'super_admin' : f.role.value;
    if (!f.name.value.trim()) bad(f.name, 'Enter their full name.');
    var email = f.email.value.trim();
    if (!email) bad(f.email, 'Enter their email address.');
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) bad(f.email, 'That doesn’t look like an email address.');
    if (!role) bad(f.role, 'Choose a role.');
    if (needsCollege(role) && !f.college.value) bad(f.college, 'Choose the college this ' + ROLE_LABEL[role] + ' belongs to.');
    if (role === 'program_chair' && f.college.value && !f.dept.value) {
      bad(f.dept, f.dept.disabled ? 'This college has no departments. Choose another college or make this account a Dean.' : 'Choose their department.');
    }
    if (!editing && !doc.getElementById('amStartRow').hidden && !f.pw.value) bad(f.pw, 'Generate a starting password.');
    if (editing && !f.confirm.value) bad(f.confirm, 'Enter your password to save these changes.');
    if (!ok) {
      var first = form.querySelector('[aria-invalid="true"]');
      if (first) first.focus();
    }
    return ok;
  }

  // Server messages that belong next to a field.
  function placeServerError(msg) {
    var m = String(msg || '');
    var map = [
      [/does not match your account|confirm with your password|your password to confirm/i, f.confirm],
      [/confirmation do not match/i, f.pw2], [/password of at least/i, f.pw],
      [/email/i, f.email], [/department|Program Chair leads/i, f.dept], [/college/i, f.college],
      [/role/i, f.role], [/name/i, f.name],
    ];
    for (var i = 0; i < map.length; i++) {
      if (map[i][0].test(m) && !map[i][1].closest('[hidden]')) {
        setError(map[i][1], m);
        map[i][1].focus();
        return;
      }
    }
    f.formError.textContent = m || 'That didn’t save. Try again.';
    f.formError.hidden = false;
  }

  function notice(text, bad) {
    var el = doc.createElement('div');
    el.className = 'ui-notice' + (bad ? ' ui-notice--bad' : '');
    el.setAttribute('role', 'status');
    el.textContent = text;
    doc.body.appendChild(el);
    setTimeout(function () { el.remove(); }, bad ? 8000 : 4500);
  }

  function submit(e) {
    e.preventDefault();
    if (!validate()) return;
    var role = editing && editing.role === 'super_admin' ? 'super_admin' : f.role.value;
    var body = {
      action: editing ? 'update' : 'create',
      name: f.name.value.trim(),
      email: f.email.value.trim(),
      role: role,
      collegeId: needsCollege(role) ? f.college.value : '',
      departmentId: role === 'program_chair' ? f.dept.value : '',
    };
    if (f.pw.value) {
      body.password = f.pw.value;
      body.passwordConfirm = f.pw2.value;
    }
    if (editing) {
      body.id = editing.id;
      body.isActive = f.active.checked;
      body.confirmPassword = f.confirm.value;
    }
    f.save.disabled = true;
    global.McuApi.send('POST', 'users.php', body)
      .then(function (res) {
        if (res.users) users = res.users;
        var name = body.name;
        var wasEdit = !!editing;
        dialog.close();
        render();
        var text = wasEdit ? 'Changes to ' + name + ' saved.' : 'Account created for ' + name + '.';
        if (res.mail) text += ' ' + res.mail.message;
        notice(text, res.mail && !res.mail.sent);
      })
      .catch(function (err) { placeServerError(err && err.message); })
      .then(function () { f.save.disabled = false; });
  }

  // ----- wiring -----

  function load() {
    if (!global.McuApi) return;
    global.McuApi.get('users.php')
      .then(function (res) {
        users = res.users || [];
        roles = res.roles || [];
        var sel = doc.getElementById('amRoleFilter');
        sel.innerHTML = '<option value="">All roles</option>' + Object.keys(ROLE_LABEL).filter(function (r) {
          return users.some(function (u) { return u.role === r; });
        }).map(function (r) { return '<option value="' + r + '">' + ROLE_LABEL[r] + '</option>'; }).join('');
        doc.getElementById('amAdd').disabled = !roles.length;
        render();
      })
      .catch(function (err) {
        list.removeAttribute('aria-busy');
        list.innerHTML = '<div class="ui-empty"><h3>Accounts couldn’t load</h3><p>' + esc((err && err.message) || 'Refresh the page to try again.') + '</p></div>';
      });
  }

  var tabs = Array.prototype.slice.call(doc.querySelectorAll('[data-am-filter]'));
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      statusFilter = tab.getAttribute('data-am-filter');
      tabs.forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
      render();
    });
  });
  doc.getElementById('amSearch').addEventListener('input', function (e) { query = e.target.value; render(); });
  doc.getElementById('amRoleFilter').addEventListener('change', function (e) { roleFilter = e.target.value; render(); });
  doc.getElementById('amAdd').addEventListener('click', function () { openForm(null); });
  doc.getElementById('amGenerate').addEventListener('click', function () { setStartPassword(generatePassword()); });
  doc.getElementById('amUseStart').addEventListener('click', function () { useStart(true); });
  doc.getElementById('amClearPw').addEventListener('click', function () { setStartPassword(''); });
  doc.getElementById('amCopy').addEventListener('click', function () {
    var btn = this;
    var copied = function () { btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = 'Copy'; }, 1500); };
    if (global.navigator.clipboard) global.navigator.clipboard.writeText(f.pw.value).then(copied, function () { f.pw.select(); });
    else { f.pw.select(); doc.execCommand('copy'); copied(); }
  });
  list.addEventListener('click', function (e) {
    var send = e.target.closest('[data-am-send]');
    if (send) {
      send.disabled = true;
      global.McuApi.send('POST', 'users.php', { action: 'send_confirmation', id: send.getAttribute('data-am-send') })
        .then(function (res) {
          if (res.users) users = res.users;
          render();
          notice(res.mail ? res.mail.message : 'Sent.');
        })
        .catch(function (err) { send.disabled = false; notice((err && err.message) || 'That didn’t send.', true); });
      return;
    }
    var b = e.target.closest && e.target.closest('[data-am-edit]');
    if (!b) return;
    var id = b.getAttribute('data-am-edit');
    openForm(users.filter(function (u) { return u.id === id; })[0]);
  });

  form.addEventListener('submit', submit);
  f.role.addEventListener('change', function () { setError(f.role, ''); syncRoleFields(); });
  f.college.addEventListener('change', function () { setError(f.college, ''); fillDepts(''); });
  f.active.addEventListener('change', syncActiveText);
  Array.prototype.forEach.call(form.querySelectorAll('input, select'), function (el) {
    el.addEventListener('input', function () { if (el.getAttribute('aria-invalid')) setError(el, ''); });
  });
  Array.prototype.forEach.call(doc.querySelectorAll('[data-am-close]'), function (b) {
    b.addEventListener('click', function () { dialog.close(); });
  });
  Array.prototype.forEach.call(doc.querySelectorAll('[data-am-reveal]'), function (b) {
    b.addEventListener('click', function () {
      var input = doc.getElementById(b.getAttribute('data-am-reveal'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.textContent = show ? 'Hide' : 'Show';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });
  dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
  dialog.addEventListener('close', function () {
    setStartPassword('');
    f.confirm.value = '';
    f.confirm.type = 'password';
    Array.prototype.forEach.call(doc.querySelectorAll('[data-am-reveal]'), function (b) { b.textContent = 'Show'; b.setAttribute('aria-pressed', 'false'); });
  });

  // colleges/departments arrive with the rest of the page data
  (global.MCUNexReady || Promise.resolve()).then(load);
})(typeof window !== 'undefined' ? window : this);
