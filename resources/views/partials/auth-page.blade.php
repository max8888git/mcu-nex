{{--
    The account email pages, in the login page's look (account-links.js does the work):
    $mode "confirm" (confirm-email.html?token=…), "reset" (reset-password.html?token=…),
    "invite" (setup-account.html?token=…: a new account chooses its password),
    "first" (new-password.html: first sign-in with a starting password). Needs $title.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="no-referrer">
  <title>MCU-NEX | {{ $title }}</title>
  @fonts
  @include('partials.auth-styles')
</head>
<body>
  <div class="login-card" id="authRoot" data-mode="{{ $mode }}">
    <div class="brand-header">
      <div class="brand-logo"><img src="Attri/mculogo.jpg" alt="School logo"></div>
      <div>
        <div class="brand-text-main">MCU-NEX</div>
        <div class="brand-text-sub">Registrar Room Allocation</div>
      </div>
    </div>
    <h1 id="authTitle">{{ $title }}</h1>
    <p class="auth-note" id="authNote" role="status">Checking your link…</p>
    <form id="authForm" novalidate hidden>
      <div class="field">
        <label for="authPassword">New password</label>
        <input type="password" id="authPassword" autocomplete="new-password" required>
        <p class="field-hint">At least 5 characters.</p>
      </div>
      <div class="field">
        <label for="authPassword2">Type it again</label>
        <input type="password" id="authPassword2" autocomplete="new-password" required>
      </div>
      <p class="login-error" id="authError" role="alert"></p>
      <button type="submit" class="btn-login" id="authSave">Save my password</button>
    </form>
    <p class="auth-links" id="authLinks"><a href="index.html">Back to log in</a></p>
  </div>
  <x-legacy-js name="api-client" />
  <x-legacy-js name="account-links" />
</body>
</html>
