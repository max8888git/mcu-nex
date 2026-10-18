/**
 * The account email pages (partials/auth-page): confirm an email from its link, set up a new
 * account from its invite (choose the password), choose a new password from a reset link, or
 * choose your own password on first sign-in (a starting password from the registrar). Server: auth.php (AuthController, App\Support\AccountEmail).
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var root = doc.getElementById('authRoot');
  if (!root || !global.McuApi) return;

  var mode = root.getAttribute('data-mode');
  var note = doc.getElementById('authNote');
  var form = doc.getElementById('authForm');
  var errorEl = doc.getElementById('authError');
  var save = doc.getElementById('authSave');
  var links = doc.getElementById('authLinks');
  var token = new URLSearchParams(global.location.search).get('token') || '';
  var homeAfter = '';

  // keep the token out of the address bar (and of anything the page links to)
  if (token && global.history && global.history.replaceState) {
    global.history.replaceState(null, '', global.location.pathname);
  }

  function say(text, tone) {
    note.textContent = text;
    note.className = 'auth-note' + (tone ? ' is-' + tone : '');
    note.hidden = !text;
  }

  function showError(text) {
    errorEl.textContent = text || '';
    errorEl.classList.toggle('is-visible', !!text);
  }

  function api(body) {
    return global.McuApi.send('POST', 'auth.php', body);
  }

  function showForm(intro) {
    say(intro, '');
    form.hidden = false;
    doc.getElementById('authPassword').focus();
  }

  function done(text, linkHtml) {
    form.hidden = true;
    say(text, 'ok');
    links.innerHTML = linkHtml;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    showError('');
    var pw = doc.getElementById('authPassword').value;
    var pw2 = doc.getElementById('authPassword2').value;
    if (pw.length < 5) return showError('Use at least 5 characters.');
    if (pw !== pw2) return showError('The two passwords don’t match.');
    save.disabled = true;
    var body = mode === 'reset' || mode === 'invite'
      ? { action: mode === 'reset' ? 'reset_password' : 'accept_invite', token: token, newPassword: pw, newPasswordConfirm: pw2 }
      : { action: 'set_first_password', newPassword: pw, newPasswordConfirm: pw2 };
    api(body)
      .then(function (res) {
        if (mode === 'invite') {
          done('Your account is ready. Log in with your email and the password you just chose.', '<a href="index.html">Go to log in</a>');
        } else if (mode === 'reset') {
          done('Your password is changed. Log in with your new password.', '<a href="index.html">Go to log in</a>');
        } else {
          var home = (res.user && res.user.home) || homeAfter || 'index.html';
          done('Your password is set. Use it from now on.', '<a href="' + home + '">Continue</a>');
          setTimeout(function () { global.location.href = home; }, 1500);
        }
      })
      .catch(function (err) { showError(err.message || 'That didn’t save. Try again.'); })
      .then(function () { save.disabled = false; });
  });

  if (mode === 'confirm') {
    if (!token) {
      say('This page needs the link from your email. Open the email and click “Confirm my email”.', 'bad');
      return;
    }
    api({ action: 'confirm_email', token: token })
      .then(function (res) {
        done('Thanks' + (res.name ? ', ' + res.name : '') + '. Your email is confirmed. If you ever forget your password, use “Forgot password?” on the login page.',
          '<a href="index.html">Go to log in</a>');
      })
      .catch(function (err) { say(err.message || 'This link didn’t work.', 'bad'); });
  } else if (mode === 'reset' || mode === 'invite') {
    if (!token) {
      say('This page needs the link from your email. Ask for one with “Forgot password?” on the login page.', 'bad');
      return;
    }
    api({ action: 'check_link', purpose: mode, token: token })
      .then(function (res) {
        if (!res.valid) {
          say('This link has expired or was already used. Ask for a new one with “Forgot password?” on the login page.', 'bad');
          return;
        }
        showForm(mode === 'invite'
          ? 'Welcome' + (res.name ? ', ' + res.name : '') + '. Choose a password to finish setting up your account. Only you will know it.'
          : 'Hi' + (res.name ? ' ' + res.name : '') + '. Choose a new password for your account.');
      })
      .catch(function (err) { say(err.message || 'This link didn’t work.', 'bad'); });
  } else {
    // first sign-in: only for someone signed in with a starting password
    global.McuApi.get('auth.php')
      .then(function (res) {
        var user = res && res.user;
        if (!user) {
          global.location.href = 'index.html';
          return;
        }
        homeAfter = user.home || '';
        if (!user.mustChangePassword) {
          done('Your password is already set.', '<a href="' + (user.home || 'index.html') + '">Continue</a>');
          return;
        }
        showForm('Welcome, ' + user.name + '. You signed in with a starting password. Choose your own password to continue; only you will know it.');
        links.innerHTML = '<a href="index.html" id="authLogout">Log out</a>';
        doc.getElementById('authLogout').addEventListener('click', function (e) {
          e.preventDefault();
          api({ action: 'logout' }).then(function () { global.location.href = 'index.html'; });
        });
      })
      .catch(function () { global.location.href = 'index.html'; });
  }
})(window);
