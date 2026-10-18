@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MCU-NEX | Log in</title>
@endverbatim
  @fonts
@verbatim
@endverbatim
  @include('partials.auth-styles')
@verbatim
</head>
<body>
  <div class="login-card">
    <div class="brand-header">
      <div class="brand-logo">
        <img src="Attri/mculogo.jpg" alt="School logo">
      </div>
      <div>
        <div class="brand-text-main">MCU-NEX</div>
        <div class="brand-text-sub">Registrar Room Allocation</div>
      </div>
    </div>
    <h1>Log in</h1>
    <p class="login-error" id="login-error" role="alert"></p>
    <form action="#" method="post" id="login-form">
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
          <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password">
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
            <svg class="icon-eye-off" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.2 3.2M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18" /></svg>
          </button>
        </div>
      </div>
      <div class="forgot">
        <button type="button" id="forgot-open">Forgot password?</button>
      </div>
      <button type="submit" class="btn-login">Log in</button>
    </form>
  </div>

  <div id="forgot-modal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="forgot-title" aria-describedby="forgot-desc">
      <button type="button" class="modal-close" id="forgot-close" aria-label="Close">&times;</button>
      <h2 id="forgot-title">Forgot your password?</h2>
      <form id="forgot-form" novalidate>
        <p class="modal-hint" id="forgot-desc">Enter the email you log in with. If your email is confirmed, we’ll send you a link to choose a new password.</p>
        <p class="auth-note" id="forgot-note" role="status" hidden></p>
        <div class="field" id="forgot-field">
          <label for="forgot-email">Email</label>
          <input type="email" id="forgot-email" placeholder="you@example.com" autocomplete="email" required>
        </div>
        <p class="modal-hint">Email not confirmed yet, or no email arrives? Ask the University Registrar to set a new starting password for you in Account management.</p>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" id="forgot-ok">Close</button>
          <button type="submit" class="btn-primary" id="forgot-send">Send link</button>
        </div>
      </form>
    </div>
  </div>

@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
@verbatim
  <script>
    (function () {
      function normalizeEmail(s) {
        return (s || '').trim().toLowerCase();
      }

      function showLoginError(message) {
        var el = document.getElementById('login-error');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('is-visible', !!message);
      }

      var form = document.getElementById('login-form');
      var submitBtn = form.querySelector('.btn-login');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        showLoginError('');
        if (!window.McuApi) {
          showLoginError('Open this page through PHP (http://localhost:8000/) so login can reach MySQL.');
          return;
        }
        var email = normalizeEmail(document.getElementById('email').value);
        var password = document.getElementById('password').value || '';
        submitBtn.disabled = true;
        window.McuApi.send('POST', 'auth.php', { action: 'login', email: email, password: password })
          .then(function (res) {
            // a starting password from the registrar: choose your own first
            if (res.user && res.user.mustChangePassword) {
              window.location.href = 'new-password.html';
              return;
            }
            if (res.user && res.user.home) {
              window.location.href = res.user.home;
              return;
            }
            showLoginError('Logged in, but this role has no home page.');
          })
          .catch(function (err) {
            showLoginError(err.message || 'Invalid email or password.');
          })
          .then(function () {
            submitBtn.disabled = false;
          });
      });

      // Show / hide password.
      var password = document.getElementById('password');
      var toggle = document.getElementById('password-toggle');
      toggle.addEventListener('click', function () {
        var show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        // SVG elements have no .hidden property, so toggle the attribute.
        toggle.querySelector('.icon-eye').toggleAttribute('hidden', show);
        toggle.querySelector('.icon-eye-off').toggleAttribute('hidden', !show);
        password.focus();
      });

      // Forgot password: email a reset link (auth.php action "forgot")
      var modal = document.getElementById('forgot-modal');
      var forgotOpen = document.getElementById('forgot-open');
      var forgotForm = document.getElementById('forgot-form');
      var forgotEmail = document.getElementById('forgot-email');
      var forgotNote = document.getElementById('forgot-note');
      var forgotSend = document.getElementById('forgot-send');

      function forgotMessage(text, tone) {
        forgotNote.textContent = text || '';
        forgotNote.className = 'auth-note' + (tone ? ' is-' + tone : '');
        forgotNote.hidden = !text;
      }

      function openForgot() {
        forgotMessage('');
        forgotSend.hidden = false;
        document.getElementById('forgot-field').hidden = false;
        forgotEmail.value = normalizeEmail(document.getElementById('email').value);
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { forgotEmail.focus(); }, 50);
      }

      forgotForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var email = normalizeEmail(forgotEmail.value);
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          forgotMessage('Enter the email you log in with.', 'bad');
          forgotEmail.focus();
          return;
        }
        forgotSend.disabled = true;
        window.McuApi.send('POST', 'auth.php', { action: 'forgot', email: email })
          .then(function (res) {
            forgotMessage(res.message, 'ok');
            forgotSend.hidden = true;
            document.getElementById('forgot-field').hidden = true;
          })
          .catch(function (err) { forgotMessage(err.message || 'That didn’t work. Try again.', 'bad'); })
          .then(function () { forgotSend.disabled = false; });
      });

      function closeForgot() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        forgotOpen.focus();
      }

      forgotOpen.addEventListener('click', openForgot);
      document.getElementById('forgot-close').addEventListener('click', closeForgot);
      document.getElementById('forgot-ok').addEventListener('click', closeForgot);

      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeForgot();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeForgot();
      });
    })();
  </script>
</body>
</html>


@endverbatim
