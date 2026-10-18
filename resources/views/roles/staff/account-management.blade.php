@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | Account management</title>
@endverbatim
  @fonts
@verbatim
  <style>
    :root {
      --purple: #5c267e;
      --purple-soft: #f6f1fc;
      --gold: #f9b937;
      --purple-dark: #2d163f;
      --bg: #f5f5f9;
      --text-main: #1f1230;
      --text-muted: #7a6992;
      --border-soft: #e3d9f0;
      --success: #0d9668;
      --danger: #e05454;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text-main);
      display: flex;
      flex-direction: column;
    }
    .app-shell { display: flex; flex-direction: column; min-height: 100vh; }
    .topbar {
      background: var(--purple);
      color: #fff;
      padding: 0.75rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
      position: sticky;
      top: 0;
      z-index: 20;
    }
    .topbar-left { display: flex; align-items: center; gap: 0.75rem; }
    .topbar-brand-link { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit; cursor: pointer; }
    .topbar-brand-link:hover { opacity: 0.92; }
    .topbar-logo {
      width: 32px; height: 32px;
      border-radius: 999px;
      overflow: hidden;
      border: 2px solid var(--gold);
      background: #f3edf8;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .topbar-logo img { width: 100%; height: 100%; object-fit: contain; }
    .topbar-title-main { font-size: 1rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
    .topbar-title-sub { font-size: 0.75rem; color: #e2d4ff; letter-spacing: 0.16em; text-transform: uppercase; }
    .topbar-right { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; }
    .chip {
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }
    .topbar-notify { position: relative; display: inline-flex; align-items: center; justify-content: center; }
    .topbar-notify-btn {
      position: relative;
      width: 30px; height: 30px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.4);
      display: inline-flex; align-items: center; justify-content: center;
      cursor: pointer;
      background: rgba(0, 0, 0, 0.16);
      color: #fff;
      font-size: 0.9rem;
    }
    .topbar-notify-dot {
      position: absolute;
      top: 6px; right: 6px;
      width: 7px; height: 7px;
      border-radius: 999px;
      background: var(--gold);
      box-shadow: 0 0 0 3px rgba(249, 185, 55, 0.35);
    }
    .topbar-user { position: relative; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
    .avatar {
      width: 28px; height: 28px;
      border-radius: 999px;
      background: #fff;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 0.75rem; font-weight: 600; color: var(--purple);
    }
    .user-menu,
    .notify-menu {
      position: absolute;
      top: 130%; right: 0;
      background: #fff;
      color: var(--text-main);
      min-width: 160px;
      border-radius: 0.6rem;
      border: 1px solid var(--border-soft);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.16);
      padding: 0.35rem 0;
      font-size: 0.85rem;
      display: none;
      z-index: 10;
    }
    .user-menu a,
    .notify-menu div {
      display: block;
      padding: 0.45rem 0.9rem;
      text-decoration: none;
      color: inherit;
      white-space: nowrap;
    }
    .user-menu a:hover,
    .notify-menu div:hover { background: var(--purple-soft); color: var(--purple); }
    .user-menu a.logout { color: #e05454; }
    .topbar-user.open .user-menu { display: block; }
    .tooltip { position: relative; }
    .tooltip::after {
      content: attr(data-tooltip);
      position: absolute;
      top: calc(100% + 10px); right: 0;
      background: #fff; color: var(--text-main);
      padding: 0.55rem 0.9rem;
      border-radius: 999px;
      box-shadow: 0 14px 26px rgba(0, 0, 0, 0.22);
      opacity: 0;
      transform: translateY(-4px);
      transition: opacity 0.15s ease, transform 0.15s ease;
      pointer-events: none; white-space: nowrap; z-index: 50;
    }
    .tooltip:hover::after { opacity: 1; transform: translateY(0); }
    .tooltip.open::after, .tooltip.open:hover::after { opacity: 0; transform: translateY(-4px); }
    .tooltip-left::after { left: 0; right: auto; }
    .nav-toggle {
      display: inline-flex;
      flex-direction: column;
      justify-content: center;
      gap: 3px;
      cursor: pointer;
      padding: 0.25rem 0.4rem;
      border-radius: 0.35rem;
      border: 1px solid rgba(255, 255, 255, 0.4);
      background: transparent;
      margin-right: 0.75rem;
    }
    .nav-toggle:hover { background: rgba(0, 0, 0, 0.2); }
    .nav-toggle-line { width: 18px; height: 2px; border-radius: 999px; background: #fff; }
    .topbar-tabs {
      display: flex;
      align-items: center;
      gap: 1.75rem;
      padding: 0.4rem 1.5rem;
      font-size: 0.8rem;
      overflow-x: visible;
      overflow-y: visible;
      background: var(--purple-dark);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.35);
    }
    .topbar-tab {
      text-decoration: none;
      color: #e2d4ff;
      padding-bottom: 0.3rem;
      border-bottom: 2px solid transparent;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      white-space: nowrap;
      font-size: 0.8rem;
    }
    .topbar-tab:hover {
      border-bottom-color: rgba(255, 255, 255, 0.6);
      color: #fff;
    }
    .topbar-tab.active {
      border-bottom-color: var(--gold);
      color: #fff;
      font-weight: 600;
    }
    .am-panel-hidden {
      display: none !important;
    }
    #account-panel-create form {
      display: grid;
      gap: 0.9rem;
      margin-top: 0.5rem;
    }
    #account-panel-create label {
      font-size: 0.85rem;
      font-weight: 500;
      display: block;
      margin-bottom: 0.25rem;
    }
    #account-panel-create input,
    #account-panel-create select {
      width: 100%;
      padding: 0.55rem 0.8rem;
      border-radius: 0.6rem;
      border: 1px solid var(--border-soft);
      font-family: inherit;
      font-size: 0.9rem;
    }
    #account-panel-create .hint,
    .am-status {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 0.25rem;
    }
    .am-status.is-bad { color: var(--danger); font-weight: 600; }
    .am-status.is-ok { color: var(--success); font-weight: 600; }
    .user-modal .hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem; }
    .am-check-list {
      display: grid;
      gap: 0.35rem;
      padding: 0.55rem 0.75rem;
      border: 1px solid var(--border-soft);
      border-radius: 0.6rem;
      max-height: 12rem;
      overflow: auto;
      background: #fff;
    }
    .am-check-list label {
      display: flex;
      align-items: center;
      gap: 0.45rem;
      font-weight: 500;
      margin: 0;
    }
    #account-panel-create .am-check-list input,
    .user-modal .am-check-list input {
      width: auto;
    }
    #account-panel-create .actions {
      margin-top: 0.8rem;
      display: flex;
      gap: 0.5rem;
    }
    #account-panel-create .btn-primary {
      border: 0;
      border-radius: 999px;
      padding: 0.55rem 1.5rem;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      background: var(--gold);
      color: var(--purple);
      box-shadow: 0 12px 24px rgba(249, 185, 55, 0.4);
    }
    #account-panel-create .btn-secondary {
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      background: #fff;
      padding: 0.55rem 1.15rem;
      font-size: 0.85rem;
      cursor: pointer;
      color: var(--text-muted);
    }
    .layout-row { display: flex; flex: 1; min-height: 0; }
    .nav-bar {
      background: var(--purple-dark);
      color: #fff;
      padding: 1.5rem 1rem;
      width: 210px;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      position: fixed;
      top: 88px;
      left: 0;
      height: calc(100vh - 88px);
      transform: translateX(-100%);
      transition: transform 0.25s ease;
      z-index: 15;
    }
    .nav-bar.nav-bar-open { transform: translateX(0); }
    .nav-section-title {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: #c9b8f3;
      margin-bottom: 0.25rem;
    }
    .nav-link {
      display: block;
      padding: 0.45rem 0.75rem;
      border-radius: 0.6rem;
      font-size: 0.85rem;
      color: #e8ddff;
      text-decoration: none;
    }
    .nav-link:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
    .nav-link.active { background: #fff; color: var(--purple-dark); font-weight: 600; }
    .shell { max-width: 980px; margin: 2rem auto; padding: 0 1.25rem; flex: 1; }
    @media (max-width: 768px) { .nav-bar { width: 75%; max-width: 260px; } }
    .page-header { margin-bottom: 1.25rem; }
    .page-title { font-size: 1.35rem; font-weight: 600; }
    .page-sub { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }
    .card {
      background: #fff;
      border-radius: 0.9rem;
      border: 1px solid var(--border-soft);
      padding: 1.25rem 1.4rem 1.4rem;
      box-shadow: 0 10px 26px rgba(0, 0, 0, 0.06);
    }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 0.5rem; }
    .users-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    .users-table th,
    .users-table td {
      padding: 0.6rem 0.75rem;
      text-align: left;
      border-bottom: 1px solid var(--border-soft);
    }
    .users-table th {
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.06em;
    }
    .users-table tbody tr:hover { background: var(--purple-soft); }
    .user-status {
      display: inline-block;
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .user-status.active {
      background: #d1fae5;
      color: var(--success);
    }
    .user-status.inactive {
      background: #fef3c7;
      color: #92400e;
    }
    .users-table-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-bottom: 0.75rem;
    }
    .am-inactive-filter {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.35rem 0.85rem;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      background: #fff;
      font-size: 0.82rem;
      font-family: inherit;
      color: var(--text-main);
      cursor: pointer;
      transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .am-inactive-filter:hover {
      border-color: var(--purple);
      background: var(--purple-soft);
    }
    .am-inactive-filter.am-inactive-filter--active {
      border-color: #f59e0b;
      background: #fffbeb;
      box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    }
    .am-inactive-filter .am-inactive-count {
      font-weight: 700;
      color: var(--purple-dark);
    }
    .am-inactive-filter.am-inactive-filter--active .am-inactive-count {
      color: #92400e;
    }
    .user-edit-btn {
      border: 0;
      background: transparent;
      color: var(--purple-dark);
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: underline;
      text-underline-offset: 0.15rem;
    }
    .user-edit-btn:hover {
      color: var(--purple);
    }
    .user-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 100;
      padding: 1rem;
    }
    .user-modal-overlay.open {
      display: flex;
    }
    .user-modal {
      background: #fff;
      border-radius: 0.9rem;
      border: 1px solid var(--border-soft);
      padding: 1.25rem 1.4rem 1.4rem;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 18px 50px rgba(0,0,0,0.25);
    }
    .user-modal h2 {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 0.75rem;
      color: var(--purple-dark);
    }
    .user-modal form {
      display: grid;
      gap: 0.75rem;
    }
    .user-modal label {
      font-size: 0.8rem;
      font-weight: 500;
      display: block;
      margin-bottom: 0.15rem;
    }
    .user-modal input,
    .user-modal select {
      width: 100%;
      padding: 0.45rem 0.7rem;
      border-radius: 0.55rem;
      border: 1px solid var(--border-soft);
      font-family: inherit;
      font-size: 0.85rem;
    }
    .toggle-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-top: 0.4rem;
    }
    .toggle-switch {
      position: relative;
      display: inline-block;
      width: 40px;
      height: 20px;
    }
    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .toggle-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #e5e7eb;
      transition: 0.2s;
      border-radius: 999px;
    }
    .toggle-slider:before {
      position: absolute;
      content: "";
      height: 16px;
      width: 16px;
      left: 2px;
      bottom: 2px;
      background-color: white;
      transition: 0.2s;
      border-radius: 50%;
      box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    }
    .toggle-switch input:checked + .toggle-slider {
      background-color: #22c55e;
    }
    .toggle-switch input:checked + .toggle-slider:before {
      transform: translateX(20px);
    }
    .user-modal-actions {
      margin-top: 0.9rem;
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
    }
    .user-modal-btn-primary {
      border-radius: 999px;
      border: 0;
      padding: 0.45rem 1.2rem;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      background: var(--purple);
      color: #fff;
    }
    .user-modal-btn-secondary {
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      padding: 0.45rem 1rem;
      font-size: 0.82rem;
      cursor: pointer;
      background: #fff;
      color: var(--text-muted);
    }
      .topbar-tabs .topbar-datetime {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      justify-content: center;
      gap: 0.05rem;
      font-size: 0.72rem;
      color: #c9b8f3;
      line-height: 1.2;
      text-align: right;
      font-variant-numeric: tabular-nums;
      letter-spacing: 0.02em;
      flex-shrink: 0;
      margin-left: auto;
      order: 99;
      padding-left: 1rem;
      border-left: 1px solid rgba(255, 255, 255, 0.12);
    }
    .topbar-tabs .topbar-datetime .topbar-datetime-date { opacity: 0.95; }
    .topbar-tabs .topbar-datetime .topbar-datetime-time {
      font-size: 0.78rem;
      font-weight: 600;
      color: #fff;
    }
</style>
@endverbatim
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-css name="ui" />
  <x-legacy-css name="staff-accounts" />
  <x-legacy-js name="page-loading" />
@verbatim
</head>
<body>
@endverbatim
  <div class="app-shell" id="amPage" data-actor="{{ $role === 'super-admin' ? 'super_admin' : 'admin' }}">
@verbatim
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-left">
        <a href="home-dashboard.html" class="topbar-brand-link" aria-label="Go to dashboard">
          <div class="topbar-logo">
            <img src="../Attri/mculogo.jpg" alt="MCU logo" />
          </div>
          <div>
            <div class="topbar-title-main">MCU-NEX</div>
            <div class="topbar-title-sub">Registrar Room Allocation</div>
          </div>
        </a>
      </div>
      <div class="topbar-right">
@endverbatim
        <span class="chip">{{ $role === 'super-admin' ? 'University Registrar' : 'Records Officer' }}</span>
@verbatim
        <div class="topbar-notify" aria-label="Notifications">
          <button class="topbar-notify-btn" type="button">
@endverbatim
            <x-icon-bell />
@verbatim
            <span class="topbar-notify-dot" aria-hidden="true"></span>
          </button>
          <div class="notify-menu" role="menu" aria-label="Notification list"></div>
        </div>
@endverbatim
        <x-profile-menu :role="$role" />
@verbatim
      </div>
    </header>

    <div class="topbar-tabs" aria-label="Account management">
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
@endverbatim
      <x-app-nav-button />
@verbatim
    </div>

    </div>
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim

      <main class="am-page">
        <header class="ui-page-head">
          <div>
            <h1 class="ui-page-title">Account management</h1>
@endverbatim
            <p class="ui-page-sub">{{ $role === 'super-admin'
                ? 'Add and manage Records Officer, VPAA, Dean, and Program Chair accounts. Deans belong to a college; Program Chairs to one department in it.'
                : 'Add and manage Dean and Program Chair accounts. Deans belong to a college; Program Chairs to one department in it.' }}</p>
@verbatim
          </div>
          <button type="button" class="ui-btn ui-btn--primary" id="amAdd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
            Add account
          </button>
        </header>

        <div class="am-toolbar">
          <div class="ui-tabs" role="tablist" aria-label="Show accounts">
            <button type="button" class="ui-tab" role="tab" aria-selected="true" data-am-filter="active">Active <span class="ui-tab-count" data-am-count="active">0</span></button>
            <button type="button" class="ui-tab" role="tab" aria-selected="false" data-am-filter="inactive">Inactive <span class="ui-tab-count" data-am-count="inactive">0</span></button>
            <button type="button" class="ui-tab" role="tab" aria-selected="false" data-am-filter="all">All <span class="ui-tab-count" data-am-count="all">0</span></button>
          </div>
          <label>
            <span class="ui-sr">Filter by role</span>
            <select class="ui-input" id="amRoleFilter"><option value="">All roles</option></select>
          </label>
          <label class="ui-search">
            <span class="ui-sr">Search accounts</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
            <input type="search" class="ui-input" id="amSearch" placeholder="Search by name, email, or college" autocomplete="off">
          </label>
        </div>

        <div class="am-list" id="amList" aria-busy="true" aria-live="polite">
          <div style="padding:1rem;display:grid;gap:0.75rem">
            <span class="skeleton" style="height:2.4rem"></span>
            <span class="skeleton" style="height:2.4rem"></span>
            <span class="skeleton" style="height:2.4rem"></span>
          </div>
          <span class="ui-sr">Loading accounts</span>
        </div>
      </main>
    </div>
  </div>
  <dialog class="ui-dialog" id="amDialog" aria-labelledby="amDialogTitle">
    <form id="amForm" novalidate>
      <div class="ui-dialog-head">
        <h2 class="ui-dialog-title" id="amDialogTitle">Add an account</h2>
        <button type="button" class="ui-icon-btn" data-am-close aria-label="Close">&times;</button>
      </div>
      <div class="ui-dialog-body am-form">
        <div class="am-grid-2">
          <div>
            <label class="ui-field-label" for="amName">Full name</label>
            <input class="ui-input" id="amName" autocomplete="off" placeholder="Juan Dela Cruz" aria-describedby="amNameError">
            <p class="ui-field-error" id="amNameError" hidden></p>
          </div>
          <div>
            <label class="ui-field-label" for="amEmail">Email</label>
            <input class="ui-input" id="amEmail" type="email" autocomplete="off" placeholder="name@mcu.edu.ph" aria-describedby="amEmailError">
            <p class="ui-field-error" id="amEmailError" hidden></p>
          </div>
        </div>

        <div>
          <label class="ui-field-label" for="amRole">Role</label>
          <select class="ui-input" id="amRole" aria-describedby="amRoleHint amRoleError"></select>
          <div class="am-role-fixed" id="amRoleFixed" hidden>University Registrar (can’t be changed)</div>
          <p class="ui-field-hint" id="amRoleHint"></p>
          <p class="ui-field-error" id="amRoleError" hidden></p>
        </div>

        <div class="am-grid-2">
          <div id="amCollegeRow" hidden>
            <label class="ui-field-label" for="amCollege">College</label>
            <select class="ui-input" id="amCollege" aria-describedby="amCollegeError"></select>
            <p class="ui-field-error" id="amCollegeError" hidden></p>
          </div>
          <div id="amDeptRow" hidden>
            <label class="ui-field-label" for="amDept">Department</label>
            <select class="ui-input" id="amDept" aria-describedby="amDeptHint amDeptError"></select>
            <p class="ui-field-hint" id="amDeptHint"></p>
            <p class="ui-field-error" id="amDeptError" hidden></p>
          </div>
        </div>

        <div class="am-invite" id="amInviteRow">
          <span class="ui-field-label">Password</span>
          <p class="am-invite-text">They’ll get an email: <strong>Set up your MCU-NEX account</strong>. They choose their own password from it, which also confirms their email. Nobody else ever knows it.</p>
          <button type="button" class="am-link" id="amUseStart">Their email doesn’t work? Give a starting password instead</button>
        </div>
        <div id="amStartRow">
          <label class="ui-field-label" for="amPassword" id="amPasswordLabel">Starting password</label>
          <div class="am-start-pw">
            <input class="ui-input am-start-input" id="amPassword" type="text" readonly autocomplete="off" spellcheck="false" placeholder="Not changing" aria-describedby="amPasswordHint amPasswordError">
            <button type="button" class="ui-btn" id="amGenerate">Generate</button>
            <button type="button" class="ui-btn" id="amCopy" hidden>Copy</button>
            <button type="button" class="ui-btn" id="amClearPw" hidden>Keep current</button>
          </div>
          <input type="hidden" id="amPassword2">
          <p class="ui-field-hint" id="amPasswordHint"></p>
          <p class="ui-field-error" id="amPasswordError" hidden></p>
        </div>

        <div id="amActiveRow" hidden>
          <span class="ui-field-label">Access</span>
          <label class="am-switch"><input type="checkbox" id="amActive"> <span id="amActiveText">Active: can log in</span></label>
          <p class="ui-field-hint">Turn this off instead of deleting an account. Their history stays, but they can’t log in.</p>
        </div>

        <div class="am-confirm" id="amConfirmRow" hidden>
          <label class="ui-field-label" for="amConfirm">Your password</label>
          <div class="am-pw">
            <input class="ui-input" id="amConfirm" type="password" autocomplete="current-password" aria-describedby="amConfirmHint amConfirmError">
            <button type="button" class="am-reveal" data-am-reveal="amConfirm" aria-pressed="false" aria-label="Show password">Show</button>
          </div>
          <p class="ui-field-hint" id="amConfirmHint">For security, enter your own password to save changes to an account.</p>
          <p class="ui-field-error" id="amConfirmError" hidden></p>
        </div>
      </div>
      <div class="ui-dialog-foot">
        <p class="am-form-error" id="amFormError" role="alert" hidden></p>
        <button type="button" class="ui-btn" data-am-close>Cancel</button>
        <button type="submit" class="ui-btn ui-btn--primary" id="amSave">Create account</button>
      </div>
    </form>
  </dialog>
  <script>
    (function () {
      var navToggle = document.querySelector('.nav-toggle');
      var navBar = document.querySelector('.nav-bar');
      var user = document.querySelector('.topbar-user');
      var userMenu = document.querySelector('.user-menu');
      if (navToggle && navBar) {
        navToggle.addEventListener('click', function () {
          navBar.classList.toggle('nav-bar-open');
          navToggle.classList.toggle('open', navBar.classList.contains('nav-bar-open'));
          navToggle.setAttribute('aria-expanded', navBar.classList.contains('nav-bar-open') ? 'true' : 'false');
        });
      }
      if (user && userMenu) {
        user.addEventListener('click', function (e) {
          e.stopPropagation();
          user.classList.toggle('open');
          user.setAttribute('aria-expanded', user.classList.contains('open') ? 'true' : 'false');
        });
        userMenu.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () {
          user.classList.remove('open');
          user.setAttribute('aria-expanded', 'false');
        });
      }
    })();
  </script>
  <script>
    (function () {
      var el = document.getElementById('topbar-datetime');
      if (!el) return;
      function tick() {
        var d = new Date();
        var opts = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
        var tOpts = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
        el.innerHTML = '<span class="topbar-datetime-date">' + d.toLocaleDateString(undefined, opts) +
          '</span><span class="topbar-datetime-time">' + d.toLocaleTimeString(undefined, tOpts) + '</span>';
      }
      tick();
      setInterval(tick, 1000);
    })();
  </script>
@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="org-store" />
  <x-legacy-js name="data-boot" />
  <x-legacy-js name="staff-accounts" />
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>

@endverbatim
