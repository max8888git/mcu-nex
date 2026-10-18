@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MCU-NEX | College and departments</title>
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
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem 1.25rem;
      padding: 0.4rem 1.5rem;
      font-size: 0.8rem;
      overflow-x: visible;
      background: var(--purple-dark);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.35);
    }
    .topbar-tab {
      text-decoration: none;
      color: #e2d4ff;
      padding-bottom: 0.3rem;
      border-bottom: 2px solid transparent;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      white-space: nowrap;
      font-size: 0.72rem;
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
    .layout-row { display: flex; flex: 1; min-height: 0; }
    .layout-row > main { flex: 1; min-width: 0; }
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
    .shell.dm-main--fullbleed {
      max-width: none;
      width: 100%;
      margin: 0;
      padding: 0;
      align-self: stretch;
    }
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
    [hidden] { display: none !important; }
    .dm-sr {
      position: absolute;
      width: 1px; height: 1px;
      overflow: hidden;
      clip: rect(0 0 0 0);
      white-space: nowrap;
    }

    /* ---- Page header (same pattern as Sections) ---- */
    .page-header--flex {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
    }
    .page-header--flex .page-header-text { flex: 1; min-width: 12rem; }
    .page-header-actions {
      flex-shrink: 0;
      align-self: flex-start;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem 0.65rem;
    }
    .dm-ref-callout {
      background: var(--purple-soft);
      border: 1px solid var(--border-soft);
      border-radius: 0.75rem;
      padding: 0.9rem 1.05rem;
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-bottom: 1.35rem;
      line-height: 1.5;
    }
    .dm-ref-callout strong { color: var(--text-main); }
    .dm-ref-callout ul { margin: 0.4rem 0 0; padding-left: 1.1rem; }
    .dm-ref-callout li + li { margin-top: 0.2rem; }
    body.dm-saving .dm-main,
    body.dm-saving .dm-modal-overlay { pointer-events: none; cursor: progress; }
    body.dm-saving .dm-main { opacity: 0.75; }

    /* ---- Buttons ---- */
    .dm-btn-add {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      padding: 0.5rem 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      color: #fff;
      background: var(--purple);
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 0.5rem;
    }
    .dm-btn-add:hover { filter: brightness(1.06); }
    .btn-save-sections {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1.15rem;
      border-radius: 8px;
      border: 1px solid rgba(0, 0, 0, 0.1);
      background: var(--gold);
      color: var(--purple-dark);
      font-weight: 600;
      font-size: 0.88rem;
      font-family: inherit;
      cursor: pointer;
      transition: filter 0.15s, box-shadow 0.15s;
    }
    .btn-save-sections:hover { filter: brightness(1.06); box-shadow: 0 2px 12px rgba(249, 185, 55, 0.45); }
    .dm-btn-sm {
      padding: 0.32rem 0.7rem;
      font-size: 0.78rem;
      font-weight: 600;
      font-family: inherit;
      border-radius: 0.4rem;
      cursor: pointer;
      border: 1px solid var(--border-soft);
      background: #fff;
      color: var(--text-main);
    }
    .dm-btn-sm:hover { background: var(--purple-soft); border-color: #d4c4e8; color: var(--purple); }
    .dm-btn-sm:disabled { opacity: 0.55; cursor: not-allowed; }
    .dm-btn-sm--archive { border-color: #e8d5b0; background: #fffbeb; color: #92400e; }
    .dm-btn-sm--archive:hover { background: #fef3c7; border-color: #fcd34d; color: #78350f; }
    .dm-btn-sm--danger { border-color: #fecaca; background: #fff; color: #9f1239; }
    .dm-btn-sm--danger:hover { background: #fff1f2; border-color: #fda4af; color: #9f1239; }
    .dm-btn-sm--unarchive { border-color: #86efac; background: #f0fdf4; color: #166534; }
    .dm-btn-sm--unarchive:hover { background: #dcfce7; border-color: #4ade80; color: #14532d; }
    .dm-btn-solid-danger {
      padding: 0.5rem 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      color: #fff;
      background: #b91c1c;
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 0.5rem;
    }
    .dm-btn-solid-danger:hover { background: #991b1b; }
    .dm-btn-add:focus-visible, .btn-save-sections:focus-visible, .dm-btn-sm:focus-visible,
    .dm-btn-solid-danger:focus-visible, .dm-card-toggle:focus-visible, .dm-add-dept:focus-visible,
    .dm-modal-close:focus-visible {
      outline: 3px solid var(--gold);
      outline-offset: 2px;
    }

    /* ---- Summary tiles ---- */
    .dm-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
      gap: 0.75rem;
      margin-bottom: 1.25rem;
    }
    .dm-stat {
      background: #fff;
      border: 1px solid var(--border-soft);
      border-radius: 0.75rem;
      padding: 0.75rem 1rem;
    }
    .dm-stat__value {
      font-size: 1.45rem;
      font-weight: 700;
      line-height: 1.1;
      color: var(--purple);
      font-variant-numeric: tabular-nums;
    }
    .dm-stat__label {
      margin-top: 0.3rem;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-muted);
    }
    .dm-stat--warn { background: #fff7ed; border-color: #fdba74; }
    .dm-stat--warn .dm-stat__value { color: #9a3412; }
    .dm-stat--warn .dm-stat__label { color: #9a3412; }

    /* ---- Toolbar ---- */
    .card.dm-list-card { padding: 1.1rem 1.25rem 1.25rem; }
    .dm-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.6rem 0.75rem;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border-soft);
    }
    .dm-search { flex: 1 1 16rem; max-width: 26rem; }
    .dm-search input {
      width: 100%;
      font-size: 0.88rem;
      font-family: inherit;
      padding: 0.45rem 0.65rem;
      border-radius: 0.45rem;
      border: 1px solid var(--border-soft);
      background: #fff;
      color: var(--text-main);
    }
    .dm-toolbar__right { margin-left: auto; display: flex; gap: 0.4rem; }
    .dm-search input:focus,
    .dm-sec-field input:focus {
      outline: none;
      border-color: var(--purple);
      box-shadow: 0 0 0 2px rgba(92, 38, 126, 0.15);
    }

    /* ---- College cards ---- */
    .dm-college-stack { display: flex; flex-direction: column; gap: 1rem; }
    .dm-college-card {
      border: 1px solid var(--border-soft);
      border-radius: 0.85rem;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 6px 18px rgba(45, 22, 63, 0.05);
    }
    .dm-college-card__top {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      gap: 0.6rem 0.75rem;
      padding: 0.9rem 1.15rem;
      background: linear-gradient(180deg, #faf8ff 0%, #fff 55%);
      cursor: pointer;
    }
    .dm-college-card:not(.is-collapsed) .dm-college-card__top { border-bottom: 1px solid var(--border-soft); }
    .dm-card-toggle {
      flex-shrink: 0;
      width: 1.9rem;
      height: 1.9rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--border-soft);
      border-radius: 0.45rem;
      background: #fff;
      color: var(--purple);
      font-size: 0.8rem;
      cursor: pointer;
    }
    .dm-card-toggle:hover { background: var(--purple-soft); }
    .dm-card-toggle span {
      display: block;
      width: 0.45rem;
      height: 0.45rem;
      margin-top: -0.2rem;
      border-right: 2px solid currentColor;
      border-bottom: 2px solid currentColor;
      transform: rotate(45deg);
      transition: transform 0.15s ease;
    }
    .dm-college-card.is-collapsed .dm-card-toggle span { margin-top: 0; transform: rotate(-45deg); }
    .dm-college-card__headings { flex: 1; min-width: 12rem; }
    .dm-college-card__title {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.35rem 0.55rem;
      font-size: 1.02rem;
      font-weight: 600;
      color: var(--purple);
    }
    .dm-college-card__meta { margin-top: 0.15rem; font-size: 0.8rem; color: var(--text-muted); }
    .dm-college-card__actions { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: center; }
    .dm-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.1rem 0.5rem;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      background: var(--purple-soft);
      color: var(--purple);
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .dm-badge--muted { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
    .dm-dean-missing {
      display: inline-block;
      margin-top: 0.4rem;
      padding: 0.2rem 0.55rem;
      border-radius: 0.4rem;
      background: #fff7ed;
      border: 1px solid #fdba74;
      color: #9a3412;
      font-size: 0.78rem;
      font-weight: 700;
      text-decoration: none;
    }
    a.dm-dean-missing:hover { background: #ffedd5; }
    .dm-dean-ok { margin-top: 0.3rem; font-size: 0.82rem; color: var(--text-main); }
    .dm-college-card__body { padding: 0.9rem 1.15rem 1.1rem; }
    .dm-section-head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      margin-bottom: 0.6rem;
    }
    .dm-dept-label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-muted);
    }
    .dm-dept-list { display: flex; flex-direction: column; gap: 0.65rem; }
    .dm-dept {
      border: 1px solid var(--border-soft);
      border-radius: 0.65rem;
      background: #fff;
      overflow: hidden;
    }
    .dm-dept__head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.4rem 0.75rem;
      padding: 0.55rem 0.8rem;
      background: #faf8ff;
      border-bottom: 1px solid var(--border-soft);
    }
    .dm-dept__name { flex: 1; min-width: 8rem; font-size: 0.92rem; font-weight: 600; color: var(--text-main); }
    .dm-dept__count { margin-left: 0.4rem; font-size: 0.75rem; font-weight: 500; color: var(--text-muted); }
    .dm-row-actions { display: inline-flex; flex-wrap: wrap; gap: 0.35rem; justify-content: flex-end; }
    .dm-program-list { list-style: none; margin: 0; padding: 0; }
    .dm-program-list--boxed { border: 1px solid var(--border-soft); border-radius: 0.65rem; overflow: hidden; }
    .dm-program-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.35rem 0.75rem;
      padding: 0.45rem 0.8rem 0.45rem 1.1rem;
      border-bottom: 1px solid #f0eaf5;
      font-size: 0.88rem;
    }
    .dm-program-row:last-child { border-bottom: none; }
    .dm-program-row__name { flex: 1; min-width: 8rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; }
    .dm-program-row__name::before {
      content: '';
      width: 0.4rem; height: 0.4rem;
      border-radius: 50%;
      background: #c4b3dc;
      flex-shrink: 0;
    }
    .dm-usage { font-size: 0.75rem; color: var(--text-muted); }
    .dm-empty-note { padding: 0.6rem 0.8rem; font-size: 0.84rem; color: var(--text-muted); line-height: 1.45; }
    .dm-dept__foot { padding: 0.25rem 0.8rem 0.55rem 1.1rem; }
    .dm-add-dept {
      padding: 0.3rem 0;
      font-size: 0.82rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      color: var(--purple);
      background: none;
      border: none;
      text-decoration: underline;
      text-underline-offset: 0.15em;
    }
    .dm-add-dept:hover { color: var(--purple-dark); }
    .dm-empty {
      padding: 2.25rem 1rem;
      text-align: center;
      font-size: 0.9rem;
      line-height: 1.5;
      color: var(--text-muted);
      border: 1px dashed var(--border-soft);
      border-radius: 0.85rem;
      background: #fff;
    }
    .dm-empty strong { display: block; margin-bottom: 0.2rem; font-size: 1rem; color: var(--text-main); }
    .dm-empty .dm-btn-add, .dm-empty .dm-btn-sm { margin-top: 0.9rem; }

    /* ---- Archived pane (same amber look as archived offerings in Sections) ---- */
    .dm-archive-hint { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.1rem; line-height: 1.5; }
    .dm-archive-group + .dm-archive-group { margin-top: 1.5rem; }
    .dm-archive-group__title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.6rem;
      font-size: 0.74rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-muted);
    }
    .dm-archive-list { display: flex; flex-direction: column; gap: 0.55rem; }
    .dm-archive-item {
      border: 1px dashed #e8d5b0;
      border-radius: 12px;
      background: #fffbeb;
    }
    .dm-archive-item__top {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.65rem;
      padding: 0.75rem 0.9rem;
    }
    .dm-archive-item__main { flex: 1; min-width: 12rem; }
    .dm-archive-item__actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 0.5rem; }
    .dm-keep { max-width: 22rem; text-align: right; color: #a16207; }
    .dm-archive-item__title {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.35rem 0.55rem;
      font-size: 1rem;
      font-weight: 700;
      color: #92400e;
    }
    .dm-archive-item__meta { margin-top: 0.15rem; font-size: 0.8rem; color: #a16207; }
    .dm-archive-children {
      list-style: none;
      margin: 0;
      padding: 0.1rem 0.9rem 0.75rem;
      border-top: 1px dashed #e8d5b0;
    }
    .dm-archive-children li {
      padding: 0.4rem 0;
      font-size: 0.85rem;
      color: var(--text-main);
      border-bottom: 1px solid #f5e9c8;
    }
    .dm-archive-children li:last-child { border-bottom: none; }
    .dm-archive-children small { margin-left: 0.4rem; color: var(--text-muted); }
    .dm-archive-children__label {
      padding-top: 0.55rem;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #a16207;
    }

    /* ---- Dialogs ---- */
    .dm-sec-field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; }
    .dm-sec-field label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
    }
    .dm-sec-field input {
      font-size: 0.88rem;
      font-family: inherit;
      padding: 0.45rem 0.65rem;
      border-radius: 0.45rem;
      border: 1px solid var(--border-soft);
      background: #fff;
      color: var(--text-main);
    }
    .dm-sec-field input[aria-invalid="true"] { border-color: #b91c1c; }
    .dm-field-hint { margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted); line-height: 1.45; }
    .dm-field-error {
      margin-top: 0.85rem;
      padding: 0.5rem 0.7rem;
      font-size: 0.82rem;
      font-weight: 600;
      border-radius: 0.45rem;
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }
    .dm-form-grid { display: grid; grid-template-columns: minmax(0, 1fr) 8rem; gap: 0.85rem; }
    .dm-choice-group { margin: 1rem 0 0; padding: 0; border: none; min-width: 0; }
    .dm-choice-group legend {
      margin-bottom: 0.4rem;
      padding: 0;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
    }
    .dm-choice-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.6rem; }
    .dm-choice {
      display: flex;
      align-items: flex-start;
      gap: 0.55rem;
      padding: 0.6rem 0.75rem;
      border: 1px solid var(--border-soft);
      border-radius: 0.6rem;
      background: #fff;
      cursor: pointer;
      font-size: 0.86rem;
    }
    .dm-choice:hover { border-color: #c4b3dc; }
    .dm-choice:has(input:checked) { border-color: var(--purple); background: var(--purple-soft); }
    .dm-choice:has(input:focus-visible) { outline: 3px solid var(--gold); outline-offset: 2px; }
    .dm-choice input { margin-top: 0.2rem; accent-color: var(--purple); }
    .dm-choice strong { display: block; font-weight: 600; }
    .dm-choice small { display: block; margin-top: 0.1rem; font-size: 0.76rem; color: var(--text-muted); line-height: 1.35; }
    .dm-modal-section { margin-top: 1rem; }
    .dm-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(45, 22, 63, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 200;
      padding: 1rem;
    }
    .dm-modal {
      background: #fff;
      border-radius: 0.85rem;
      max-width: 34rem;
      width: 100%;
      max-height: 90vh;
      overflow: auto;
      box-shadow: 0 24px 48px rgba(0, 0, 0, 0.22);
      border: 1px solid var(--border-soft);
    }
    .dm-modal--small { max-width: 26rem; }
    .dm-modal__head {
      padding: 1rem 1.2rem;
      border-bottom: 1px solid var(--border-soft);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.75rem;
    }
    .dm-modal__head h2 { font-size: 1.05rem; font-weight: 600; margin: 0; }
    .dm-modal-close {
      flex-shrink: 0;
      width: 2rem;
      height: 2rem;
      border: none;
      background: var(--purple-soft);
      color: var(--purple);
      border-radius: 0.4rem;
      font-size: 1.25rem;
      line-height: 1;
      cursor: pointer;
    }
    .dm-modal-close:hover { background: #ebe4f7; }
    .dm-modal__body { padding: 1.1rem 1.2rem 1.2rem; }
    .dm-modal__body p.dm-confirm-text { font-size: 0.9rem; line-height: 1.55; color: var(--text-main); }
    .dm-modal__foot {
      padding: 0.85rem 1.2rem;
      border-top: 1px solid var(--border-soft);
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem 1rem;
      justify-content: space-between;
      align-items: center;
    }
    .dm-modal__foot-group { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
    .dm-modal__foot-group:only-child { margin-left: auto; }
    .dm-modal-dept-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      padding: 0.5rem 0;
      border-bottom: 1px solid #f0eaf5;
      font-size: 0.88rem;
    }
    .dm-modal-dept-row:last-child { border-bottom: none; }
    .dm-modal-dept-name { font-weight: 500; color: var(--text-main); min-width: 0; flex: 1; }
    .dm-modal-dept-name small { display: block; margin-top: 0.1rem; font-size: 0.76rem; font-weight: 400; color: var(--text-muted); }
    #dmCollegeModalAddDept { margin-top: 0.5rem; }
    @media (max-width: 560px) {
      .dm-form-grid, .dm-choice-row { grid-template-columns: minmax(0, 1fr); }
    }

    /* ---- Toast ---- */
    .dm-toast {
      position: fixed;
      left: 50%;
      bottom: 1.25rem;
      transform: translateX(-50%);
      z-index: 300;
      display: flex;
      align-items: center;
      gap: 0.85rem;
      max-width: calc(100vw - 2rem);
      padding: 0.65rem 1rem;
      border-radius: 0.75rem;
      background: #14532d;
      color: #fff;
      font-size: 0.88rem;
      font-weight: 600;
      box-shadow: 0 12px 32px rgba(20, 10, 30, 0.3);
    }
    .dm-toast--error { background: #7f1d1d; }
    .dm-toast button {
      padding: 0;
      border: none;
      background: none;
      color: #fde68a;
      font: inherit;
      font-weight: 700;
      text-decoration: underline;
      cursor: pointer;
      white-space: nowrap;
    }
    .sec-subtab-count {
      display: inline-block;
      min-width: 1.25rem;
      margin-left: 0.35rem;
      padding: 0 0.4rem;
      border-radius: 999px;
      background: rgba(92, 38, 126, 0.1);
      font-size: 0.68rem;
      letter-spacing: 0;
      text-align: center;
    }
    .sec-subtab.is-active .sec-subtab-count { background: rgba(255, 255, 255, 0.22); }
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
  <x-legacy-css name="dm-sections" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-js name="page-loading" />
@verbatim
</head>
@endverbatim
<body data-can-delete="{{ $role === 'super-admin' ? '1' : '0' }}">
@verbatim
  <div class="app-shell">
    <div class="app-header-sticky">
    <header class="topbar">
      <div class="topbar-left">
        <a href="../home-dashboard.html" class="topbar-brand-link" aria-label="Go to dashboard">
          <div class="topbar-logo">
            <img src="../../Attri/mculogo.jpg" alt="MCU logo" />
          </div>
          <div>
            <div class="topbar-title-main">MCU-NEX</div>
            <div class="topbar-title-sub">Registrar Room Allocation</div>
          </div>
        </a>
      </div>
      <div class="topbar-right">
@endverbatim
@if ($role === 'super-admin')
@verbatim
        <span class="chip">University Registrar</span>
@endverbatim
@else
@verbatim
        <span class="chip">Records Officer</span>
@endverbatim
@endif
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

    <div class="topbar-tabs" aria-label="Data management sections">
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
@endverbatim
      <x-app-nav-button />
@verbatim
      <a href="college-departments.html" class="topbar-tab active" id="dm-tab-college">College and departments</a>
      <a href="sections.html" class="topbar-tab" id="dm-tab-sections">Sections</a>
      <a href="facilities.html" class="topbar-tab" id="dm-tab-facilities">Facilities</a>
    </div>

    </div>
@endverbatim
@if ($role === 'super-admin')
@else
@verbatim

@endverbatim
@endif
@verbatim
    <div class="layout-row">
@endverbatim
      <x-app-nav :role="$role" />
@verbatim

      <div class="layout-main">
      <nav class="sec-subtabs-wrap" aria-label="College and department pages">
        <div class="sec-subtabs" role="tablist">
          <button type="button" class="sec-subtab is-active" role="tab" id="dmTabActive" data-dm-pane="active" aria-selected="true" aria-controls="dmPaneActive">Active<span class="sec-subtab-count" id="dmCountActive">0</span></button>
          <button type="button" class="sec-subtab" role="tab" id="dmTabArchived" data-dm-pane="archived" aria-selected="false" aria-controls="dmPaneArchived">Archived<span class="sec-subtab-count" id="dmCountArchived">0</span></button>
        </div>
      </nav>

      <main class="shell dm-main">
        <div id="dmPaneActive" class="sec-pane" role="tabpanel" aria-labelledby="dmTabActive">
          <header class="page-header page-header--flex">
            <div class="page-header-text">
              <h1 class="page-title">College and departments</h1>
              <p class="page-sub">Set up the colleges, departments, and programs the rest of the system builds on.</p>
            </div>
            <div class="page-header-actions">
              <button type="button" class="dm-btn-add" id="dmCollegeBtnAdd">+ Add college</button>
            </div>
          </header>

          <div class="dm-ref-callout">
            <strong>How this page works.</strong> What you add, edit, or archive here is what accounts, curricula, scheduling, and reports use.
            <ul>
              <li>A college has either one program, or several departments that each have their own programs. Sections, curricula, and schedules belong to a program.</li>
              <li><strong>Archive</strong> hides a record from new work and keeps its history. You can unarchive it later.</li>
              <li><strong>Delete</strong> is only for a record with no sections, curricula, or accounts. You will find it inside each Edit window, and on archived records in the Archived tab.</li>
            </ul>
          </div>

          <div class="dm-stats" aria-label="Summary">
            <div class="dm-stat"><div class="dm-stat__value" id="dmStatColleges">0</div><div class="dm-stat__label">Colleges</div></div>
            <div class="dm-stat"><div class="dm-stat__value" id="dmStatDepts">0</div><div class="dm-stat__label">Departments</div></div>
            <div class="dm-stat"><div class="dm-stat__value" id="dmStatPrograms">0</div><div class="dm-stat__label">Programs</div></div>
            <div class="dm-stat" id="dmStatDeanWrap"><div class="dm-stat__value" id="dmStatNoDean">0</div><div class="dm-stat__label">Without a dean</div></div>
          </div>

          <section class="card dm-list-card">
            <div class="dm-toolbar" role="search">
              <div class="dm-search">
                <label class="dm-sr" for="dmSearch">Search colleges, departments, and programs</label>
                <input type="search" id="dmSearch" placeholder="Search colleges, departments, or programs" autocomplete="off" />
              </div>
              <div class="dm-toolbar__right">
                <button type="button" class="dm-btn-sm" id="dmExpandAll">Expand all</button>
                <button type="button" class="dm-btn-sm" id="dmCollapseAll">Collapse all</button>
              </div>
            </div>
            <div class="dm-college-stack" id="dmCollegeStackActive" aria-live="polite">
              <div class="dm-empty">Loading colleges…</div>
            </div>
          </section>
        </div>

        <div id="dmPaneArchived" class="sec-pane" role="tabpanel" aria-labelledby="dmTabArchived" hidden>
          <header class="page-header">
            <h1 class="page-title">Archived records</h1>
            <p class="page-sub">Archived colleges, departments, and programs are hidden from new accounts, curricula, and schedules, but their history is kept. Unarchive a record to use it again. Records that nothing uses can also be deleted permanently; the rest are kept for history.</p>
          </header>
          <div id="dmCollegeStackArchived"></div>
        </div>
      </main>
      </div>
    </div>
  </div>

  <div id="dmCollegeModalOverlay" class="dm-modal-overlay" hidden>
    <div class="dm-modal" role="dialog" aria-modal="true" aria-labelledby="dmCollegeModalTitle">
      <div class="dm-modal__head">
        <h2 id="dmCollegeModalTitle">Edit college</h2>
        <button type="button" class="dm-modal-close" id="dmCollegeModalCloseX" aria-label="Close">×</button>
      </div>
      <div class="dm-modal__body">
        <div class="dm-form-grid">
          <div class="dm-sec-field">
            <label for="dmCollegeModalName">College name</label>
            <input type="text" id="dmCollegeModalName" autocomplete="organization" maxlength="150" />
          </div>
          <div class="dm-sec-field">
            <label for="dmCollegeModalCode">Short code</label>
            <input type="text" id="dmCollegeModalCode" maxlength="12" autocomplete="off" placeholder="e.g. CAS" />
          </div>
        </div>
        <fieldset class="dm-choice-group">
          <legend>Structure</legend>
          <div class="dm-choice-row">
            <label class="dm-choice">
              <input type="radio" name="dmCollegeStructure" id="dmCollegeStructProgram" value="program" />
              <span><strong>One program</strong><small>The college offers a single program directly.</small></span>
            </label>
            <label class="dm-choice">
              <input type="radio" name="dmCollegeStructure" id="dmCollegeStructDept" value="departments" />
              <span><strong>Departments</strong><small>Each department has its own programs.</small></span>
            </label>
          </div>
        </fieldset>
        <div id="dmCollegeModalProgramSection" class="dm-modal-section">
          <div class="dm-sec-field">
            <label for="dmCollegeModalProgram">Program name</label>
            <input type="text" id="dmCollegeModalProgram" autocomplete="off" maxlength="150" placeholder="e.g. Bachelor of Science in Nursing" />
          </div>
          <p class="dm-field-hint">This program sits directly under the college. You can leave it blank for now and add it later.</p>
        </div>
        <div id="dmCollegeModalDeptSection" class="dm-modal-section">
          <div class="dm-dept-label">Departments</div>
          <div id="dmCollegeModalDeptList"></div>
          <p class="dm-field-hint" id="dmCollegeModalDeptHint" hidden></p>
          <button type="button" class="dm-add-dept" id="dmCollegeModalAddDept">+ Add department</button>
        </div>
        <p class="dm-field-error" id="dmCollegeModalError" role="alert" hidden></p>
      </div>
      <div class="dm-modal__foot">
        <div class="dm-modal__foot-group">
          <button type="button" class="dm-btn-sm dm-btn-sm--archive" id="dmCollegeModalArchive">Archive college</button>
          <button type="button" class="dm-btn-sm dm-btn-sm--danger" id="dmCollegeModalDelete" hidden>Delete college</button>
        </div>
        <div class="dm-modal__foot-group">
          <button type="button" class="dm-btn-sm" id="dmCollegeModalCancel">Cancel</button>
          <button type="button" class="btn-save-sections" id="dmCollegeModalSave">Save</button>
        </div>
      </div>
    </div>
  </div>

  <div id="dmDeptModalOverlay" class="dm-modal-overlay" hidden style="z-index: 220;">
    <div class="dm-modal dm-modal--small" role="dialog" aria-modal="true" aria-labelledby="dmDeptModalTitle">
      <div class="dm-modal__head">
        <h2 id="dmDeptModalTitle">Edit department</h2>
        <button type="button" class="dm-modal-close" id="dmDeptModalCloseX" aria-label="Close">×</button>
      </div>
      <div class="dm-modal__body">
        <p class="dm-field-hint" id="dmDeptModalHint" style="margin: 0 0 0.85rem;"></p>
        <div class="dm-sec-field">
          <label for="dmDeptModalName">Department name</label>
          <input type="text" id="dmDeptModalName" autocomplete="off" maxlength="150" />
        </div>
        <p class="dm-field-error" id="dmDeptModalError" role="alert" hidden></p>
      </div>
      <div class="dm-modal__foot">
        <div class="dm-modal__foot-group">
          <button type="button" class="dm-btn-sm dm-btn-sm--archive" id="dmDeptModalArchive">Archive</button>
          <button type="button" class="dm-btn-sm dm-btn-sm--danger" id="dmDeptModalDelete" hidden>Delete</button>
        </div>
        <div class="dm-modal__foot-group">
          <button type="button" class="dm-btn-sm" id="dmDeptModalCancel">Cancel</button>
          <button type="button" class="btn-save-sections" id="dmDeptModalSave">Save</button>
        </div>
      </div>
    </div>
  </div>

  <div id="dmProgramModalOverlay" class="dm-modal-overlay" hidden style="z-index: 230;">
    <div class="dm-modal dm-modal--small" role="dialog" aria-modal="true" aria-labelledby="dmProgramModalTitle">
      <div class="dm-modal__head">
        <h2 id="dmProgramModalTitle">Add program</h2>
        <button type="button" class="dm-modal-close" id="dmProgramModalCloseX" aria-label="Close">×</button>
      </div>
      <div class="dm-modal__body">
        <p class="dm-field-hint" id="dmProgramModalHint" style="margin: 0 0 0.85rem;"></p>
        <div class="dm-sec-field">
          <label for="dmProgramModalName">Program name</label>
          <input type="text" id="dmProgramModalName" autocomplete="off" maxlength="150" />
        </div>
        <p class="dm-field-error" id="dmProgramModalError" role="alert" hidden></p>
      </div>
      <div class="dm-modal__foot">
        <div class="dm-modal__foot-group">
          <button type="button" class="dm-btn-sm dm-btn-sm--archive" id="dmProgramModalArchive" hidden>Archive</button>
          <button type="button" class="dm-btn-sm dm-btn-sm--danger" id="dmProgramModalDelete" hidden>Delete</button>
        </div>
        <div class="dm-modal__foot-group">
          <button type="button" class="dm-btn-sm" id="dmProgramModalCancel">Cancel</button>
          <button type="button" class="btn-save-sections" id="dmProgramModalSave">Save</button>
        </div>
      </div>
    </div>
  </div>

  <div id="dmConfirmOverlay" class="dm-modal-overlay" hidden style="z-index: 250;">
    <div class="dm-modal dm-modal--small" role="alertdialog" aria-modal="true" aria-labelledby="dmConfirmTitle" aria-describedby="dmConfirmBody">
      <div class="dm-modal__head">
        <h2 id="dmConfirmTitle">Are you sure?</h2>
      </div>
      <div class="dm-modal__body">
        <p class="dm-confirm-text" id="dmConfirmBody"></p>
      </div>
      <div class="dm-modal__foot">
        <div class="dm-modal__foot-group">
          <button type="button" class="dm-btn-sm" id="dmConfirmNo">Cancel</button>
          <button type="button" class="dm-btn-add" id="dmConfirmYes">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <div class="dm-toast" id="dmToast" role="status" aria-live="polite" hidden>
    <span id="dmToastMsg"></span>
    <button type="button" id="dmToastAction" hidden></button>
  </div>

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
@verbatim
  <script>
    (function () {
      function $(id) { return document.getElementById(id); }

      var stackA = $('dmCollegeStackActive');
      var stackH = $('dmCollegeStackArchived');
      var paneA = $('dmPaneActive');
      var paneH = $('dmPaneArchived');
      var tabA = $('dmTabActive');
      var tabH = $('dmTabArchived');
      var main = document.querySelector('.dm-main');
      var inpSearch = $('dmSearch');

      var ovC = $('dmCollegeModalOverlay');
      var titleC = $('dmCollegeModalTitle');
      var inpCname = $('dmCollegeModalName');
      var inpCcode = $('dmCollegeModalCode');
      var radProgram = $('dmCollegeStructProgram');
      var radDept = $('dmCollegeStructDept');
      var secCprogram = $('dmCollegeModalProgramSection');
      var inpCprogram = $('dmCollegeModalProgram');
      var secCdept = $('dmCollegeModalDeptSection');
      var listCdept = $('dmCollegeModalDeptList');
      var hintCdept = $('dmCollegeModalDeptHint');
      var errC = $('dmCollegeModalError');
      var btnCarchive = $('dmCollegeModalArchive');
      var btnCdelete = $('dmCollegeModalDelete');
      // Only the University Registrar deletes; the Records Officer archives. The server enforces this too.
      var canDelete = document.body.getAttribute('data-can-delete') === '1';

      var ovD = $('dmDeptModalOverlay');
      var titleD = $('dmDeptModalTitle');
      var hintD = $('dmDeptModalHint');
      var inpDname = $('dmDeptModalName');
      var errD = $('dmDeptModalError');
      var btnDarchive = $('dmDeptModalArchive');
      var btnDdelete = $('dmDeptModalDelete');

      var ovP = $('dmProgramModalOverlay');
      var titleP = $('dmProgramModalTitle');
      var hintP = $('dmProgramModalHint');
      var inpPname = $('dmProgramModalName');
      var errP = $('dmProgramModalError');
      var btnPsave = $('dmProgramModalSave');
      var btnParchive = $('dmProgramModalArchive');
      var btnPdelete = $('dmProgramModalDelete');

      var ovK = $('dmConfirmOverlay');
      var titleK = $('dmConfirmTitle');
      var bodyK = $('dmConfirmBody');
      var btnKyes = $('dmConfirmYes');
      var btnKno = $('dmConfirmNo');

      var toastEl = $('dmToast');
      var toastMsg = $('dmToastMsg');
      var toastAction = $('dmToastAction');

      if (!stackA || !stackH || !ovC || !ovD || !ovP || !ovK || !window.OrgStore) return;

      // Colleges live in the shared store, not in this page, so that the
      // curriculum upload dialog sees the same list whether or not this page
      // has been opened. js/org-store.js supplies the data and saves it.
      var DM = window.OrgStore.load();
      var orgReady = false;
      var query = '';
      var collapsed = {};

      var collegeModalDraft = null;
      var collegeModalMode = 'edit';
      var deptModalCollegeId = null;
      var deptModalDeptId = null;
      var deptModalFromCollegeModal = false;
      var programModalCollegeId = null;
      var programModalDeptId = null;
      var programModalProgramId = null;
      var confirmCallback = null;
      var toastTimer = null;

      // ------------------------------------------------------------ helpers

      function esc(s) {
        return String(s == null ? '' : s)
          .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      }
      function norm(s) { return String(s == null ? '' : s).trim().toLowerCase(); }
      function clean(s) { return String(s == null ? '' : s).trim().replace(/\s+/g, ' '); }
      function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
      function uid() { return 'id-' + Math.random().toString(36).slice(2, 11); }
      function archiveLabel() {
        return new Date().toLocaleString(undefined, { month: 'short', year: 'numeric' });
      }
      function emptyUsage() { return { sections: 0, curricula: 0, drafts: 0, accounts: 0 }; }
      function isSaved(id, prefix) { return String(id || '').indexOf(prefix + '-') === 0; }

      function ensureShape() {
        if (!DM.active) DM.active = [];
        if (!DM.archivedColleges) DM.archivedColleges = [];
        if (!DM.archivedDeptOnly) DM.archivedDeptOnly = [];
        if (!DM.archivedPrograms) DM.archivedPrograms = [];
        DM.active.forEach(function (c) { if (!c.departments) c.departments = []; });
      }

      function usageSentence(usage) {
        usage = usage || {};
        var parts = [];
        if (usage.sections) parts.push(plural(usage.sections, 'section'));
        if (usage.curricula) parts.push(usage.curricula === 1 ? '1 curriculum' : usage.curricula + ' curricula');
        if (usage.drafts) parts.push(plural(usage.drafts, 'curriculum draft'));
        if (usage.accounts) parts.push(plural(usage.accounts, 'account'));
        return parts.join(', ');
      }

      function blockedDeleteMessage(label, usage) {
        var why = usageSentence(usage);
        if (!why) return '';
        return label + ' is still used by ' + why + '. Archive it instead. Archiving hides it from new work and keeps the sections, curricula, and schedules.';
      }

      function findActiveCollege(id) {
        return DM.active.find(function (c) { return c.id === id; });
      }
      function findArchivedCollege(id) {
        return DM.archivedColleges.find(function (c) { return c.id === id; });
      }
      function findActiveByCode(code) {
        return DM.active.find(function (c) { return c.code === code; });
      }
      function programsOf(d) { return (d && d.programs) || []; }
      function directProgram(c) { return !c.useDepartments && c.program && c.program.name ? c.program : null; }
      function collegeProgramCount(c) {
        if (!c.useDepartments) return directProgram(c) ? 1 : 0;
        return (c.departments || []).reduce(function (n, d) { return n + programsOf(d).length; }, 0);
      }

      // ------------------------------------------------------ toast + dialogs

      function toast(message, opts) {
        opts = opts || {};
        toastMsg.textContent = message;
        toastEl.classList.toggle('dm-toast--error', !!opts.error);
        if (opts.actionLabel && opts.onAction) {
          toastAction.textContent = opts.actionLabel;
          toastAction.hidden = false;
          toastAction.onclick = function () { toastEl.hidden = true; opts.onAction(); };
        } else {
          toastAction.hidden = true;
          toastAction.onclick = null;
        }
        toastEl.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.hidden = true; }, opts.error ? 7000 : 5000);
      }

      function openOverlay(ov, focusEl) {
        ov._returnTo = document.activeElement;
        ov.hidden = false;
        if (focusEl) {
          setTimeout(function () {
            focusEl.focus();
            if (focusEl.tagName === 'INPUT' && typeof focusEl.select === 'function') focusEl.select();
          }, 0);
        }
      }
      function closeOverlay(ov) {
        ov.hidden = true;
        var back = ov._returnTo;
        ov._returnTo = null;
        if (back && document.body.contains(back) && typeof back.focus === 'function') back.focus();
      }

      function showError(el, message, field) {
        el.textContent = message;
        el.hidden = false;
        if (field) {
          field.setAttribute('aria-invalid', 'true');
          field.focus();
        }
      }
      function clearError(el) {
        el.hidden = true;
        el.textContent = '';
        var fields = el.parentNode.querySelectorAll('[aria-invalid]');
        for (var i = 0; i < fields.length; i++) fields[i].removeAttribute('aria-invalid');
      }

      // One styled confirmation used for archive, delete, and "can't do that" notices.
      function ask(opts, onYes) {
        titleK.textContent = opts.title;
        bodyK.textContent = opts.message;
        btnKyes.textContent = opts.yes || 'Confirm';
        btnKyes.className = opts.tone === 'danger' ? 'dm-btn-solid-danger' : 'dm-btn-add';
        btnKyes.hidden = !!opts.info;
        btnKno.textContent = opts.info ? 'Close' : 'Cancel';
        confirmCallback = opts.info ? null : onYes;
        openOverlay(ovK, opts.info || opts.tone === 'danger' ? btnKno : btnKyes);
      }
      function closeConfirm(accepted) {
        var cb = accepted ? confirmCallback : null;
        confirmCallback = null;
        closeOverlay(ovK);
        if (cb) cb();
      }

      function setBusy(on) { document.body.classList.toggle('dm-saving', on); }

      // ----------------------------------------------------------- tab panes

      function showPane(name) {
        var active = name !== 'archived';
        tabA.classList.toggle('is-active', active);
        tabH.classList.toggle('is-active', !active);
        tabA.setAttribute('aria-selected', active ? 'true' : 'false');
        tabH.setAttribute('aria-selected', active ? 'false' : 'true');
        paneA.hidden = !active;
        paneH.hidden = active;
        if (window.history && history.replaceState) {
          history.replaceState(null, '', window.location.pathname + window.location.search + (active ? '' : '#archived'));
        }
      }
      tabA.addEventListener('click', function () { showPane('active'); });
      tabH.addEventListener('click', function () { showPane('archived'); });

      // ----------------------------------------------------------- rendering

      function collegeView(c, q) {
        var depts = c.useDepartments ? (c.departments || []) : [];
        var direct = directProgram(c);
        var full = {
          c: c,
          depts: depts.map(function (d) { return { d: d, programs: programsOf(d) }; }),
          direct: direct
        };
        if (!q) return full;
        if (norm(c.name).indexOf(q) >= 0 || norm(c.code).indexOf(q) >= 0 || norm(c.deanName).indexOf(q) >= 0) return full;
        var out = [];
        depts.forEach(function (d) {
          var progs = programsOf(d);
          if (norm(d.name).indexOf(q) >= 0) {
            out.push({ d: d, programs: progs });
            return;
          }
          var hits = progs.filter(function (p) { return norm(p.name).indexOf(q) >= 0; });
          if (hits.length) out.push({ d: d, programs: hits });
        });
        var directHit = direct && norm(direct.name).indexOf(q) >= 0 ? direct : null;
        if (!out.length && !directHit) return null;
        return { c: c, depts: out, direct: directHit };
      }

      function actionBtn(act, label, extraClass, ids) {
        var attrs = '';
        if (ids) {
          Object.keys(ids).forEach(function (k) {
            if (ids[k] != null && ids[k] !== '') attrs += ' data-' + k + '="' + esc(ids[k]) + '"';
          });
        }
        return '<button type="button" class="dm-btn-sm' + (extraClass ? ' ' + extraClass : '') + '" data-act="' + act + '"' + attrs + '>' + label + '</button>';
      }

      function programRow(collegeId, deptId, program) {
        var ids = { 'college-id': collegeId, 'dept-id': deptId, 'program-id': program.id };
        var used = usageSentence(program.usage);
        return '<li class="dm-program-row"><span class="dm-program-row__name">' + esc(program.name) + '</span>' +
          (used ? '<span class="dm-usage">In use: ' + esc(used) + '</span>' : '') +
          '<span class="dm-row-actions">' +
          actionBtn('edit-program', 'Edit', '', ids) +
          actionBtn('archive-program', 'Archive', 'dm-btn-sm--archive', ids) +
          '</span></li>';
      }

      function deptBlock(c, entry) {
        var d = entry.d;
        var ids = { 'college-id': c.id, 'dept-id': d.id };
        var all = programsOf(d).length;
        var rows = entry.programs.map(function (p) { return programRow(c.id, d.id, p); }).join('');
        return '<section class="dm-dept">' +
          '<div class="dm-dept__head"><div class="dm-dept__name">' + esc(d.name) +
          '<span class="dm-dept__count">' + plural(all, 'program') + '</span></div>' +
          '<div class="dm-row-actions">' +
          actionBtn('edit-dept', 'Edit', '', ids) +
          actionBtn('archive-dept', 'Archive', 'dm-btn-sm--archive', ids) +
          '</div></div>' +
          (rows ? '<ul class="dm-program-list">' + rows + '</ul>' : '<p class="dm-empty-note">No programs yet.</p>') +
          '<div class="dm-dept__foot"><button type="button" class="dm-add-dept" data-act="add-program" data-college-id="' + esc(c.id) + '" data-dept-id="' + esc(d.id) + '">+ Add program</button></div>' +
          '</section>';
      }

      function collegeCard(v, forceOpen) {
        var c = v.c;
        var open = forceOpen || !collapsed[c.id];
        var nDepts = c.useDepartments ? (c.departments || []).length : 0;
        var nProgs = collegeProgramCount(c);
        var meta = c.useDepartments
          ? 'Departments · ' + plural(nDepts, 'department') + ' · ' + plural(nProgs, 'program')
          : (nProgs ? 'One program' : 'One program · not added yet');
        var dean = c.deanName
          ? '<div class="dm-dean-ok">Dean: ' + esc(c.deanName) + '</div>'
          : '<a class="dm-dean-missing" href="../account-management.html">No dean assigned. Add one in Accounts</a>';
        var body = '';
        if (c.useDepartments) {
          body += '<div class="dm-section-head"><div class="dm-dept-label">Departments</div>' +
            '<button type="button" class="dm-add-dept" data-act="add-dept" data-college-id="' + esc(c.id) + '">+ Add department</button></div>';
          if (v.depts.length) {
            body += '<div class="dm-dept-list">' + v.depts.map(function (e) { return deptBlock(c, e); }).join('') + '</div>';
          } else if (!(c.departments || []).length) {
            body += '<p class="dm-empty-note">No departments yet.' + (c.program && c.program.name ? ' The program under this college moves into the first department you add.' : '') + '</p>';
          } else {
            body += '<p class="dm-empty-note">Nothing here matches your search.</p>';
          }
        } else {
          body += '<div class="dm-section-head"><div class="dm-dept-label">Program</div>' +
            (v.direct || (query && directProgram(c)) ? '' : '<button type="button" class="dm-add-dept" data-act="add-direct-program" data-college-id="' + esc(c.id) + '">+ Add program</button>') +
            '</div>';
          body += v.direct
            ? '<ul class="dm-program-list dm-program-list--boxed">' + programRow(c.id, '', v.direct) + '</ul>'
            : '<p class="dm-empty-note">No program yet.</p>';
        }
        var ids = { 'college-id': c.id };
        return '<article class="dm-college-card' + (open ? '' : ' is-collapsed') + '">' +
          '<div class="dm-college-card__top" data-act="toggle-college" data-college-id="' + esc(c.id) + '">' +
          '<button type="button" class="dm-card-toggle" data-act="toggle-college" data-college-id="' + esc(c.id) + '" aria-expanded="' + (open ? 'true' : 'false') + '" aria-controls="dm-body-' + esc(c.id) + '" aria-label="' + (open ? 'Collapse ' : 'Expand ') + esc(c.name) + '"><span aria-hidden="true"></span></button>' +
          '<div class="dm-college-card__headings">' +
          '<div class="dm-college-card__title">' + esc(c.name) + '<span class="dm-badge">' + esc(c.code) + '</span></div>' +
          '<div class="dm-college-card__meta">' + esc(meta) + '</div>' + dean + '</div>' +
          '<div class="dm-college-card__actions">' +
          actionBtn('edit-college', 'Edit', '', ids) +
          actionBtn('archive-college', 'Archive', 'dm-btn-sm--archive', ids) +
          '</div></div>' +
          '<div class="dm-college-card__body" id="dm-body-' + esc(c.id) + '"' + (open ? '' : ' hidden') + '>' + body + '</div>' +
          '</article>';
      }

      function updateChrome() {
        var depts = 0;
        var progs = 0;
        var noDean = 0;
        DM.active.forEach(function (c) {
          if (!c.deanName) noDean++;
          if (c.useDepartments) depts += (c.departments || []).length;
          progs += collegeProgramCount(c);
        });
        $('dmStatColleges').textContent = DM.active.length;
        $('dmStatDepts').textContent = depts;
        $('dmStatPrograms').textContent = progs;
        $('dmStatNoDean').textContent = noDean;
        $('dmStatDeanWrap').classList.toggle('dm-stat--warn', noDean > 0);
        $('dmCountActive').textContent = DM.active.length;
        $('dmCountArchived').textContent = DM.archivedColleges.length + DM.archivedDeptOnly.length + DM.archivedPrograms.length;
      }

      function renderActive() {
        ensureShape();
        var q = norm(query);
        if (!DM.active.length) {
          stackA.innerHTML = '<div class="dm-empty"><strong>No colleges yet</strong>Add your first college, then give it departments and programs.<br />' +
            '<button type="button" class="dm-btn-add" data-act="add-college">+ Add college</button></div>';
        } else {
          var cards = [];
          DM.active.forEach(function (c) {
            var v = collegeView(c, q);
            if (v) cards.push(collegeCard(v, !!q));
          });
          stackA.innerHTML = cards.length
            ? cards.join('')
            : '<div class="dm-empty"><strong>No matches</strong>Nothing matches “' + esc(query.trim()) + '”.<br />' +
              '<button type="button" class="dm-btn-sm" data-act="clear-search">Clear search</button></div>';
        }
        updateChrome();
      }

      function archiveItem(opts) {
        return '<article class="dm-archive-item"><div class="dm-archive-item__top">' +
          '<div class="dm-archive-item__main"><div class="dm-archive-item__title">' + esc(opts.title) + opts.badge + '</div>' +
          '<div class="dm-archive-item__meta">' + esc(opts.meta) + '</div></div>' + opts.action + '</div>' + (opts.children || '') + '</article>';
      }

      // Unarchive is always offered. Delete only appears when nothing uses the
      // record; otherwise the row says why it has to stay. The server checks
      // again when the delete is sent.
      function archiveActions(kind, id, unarchiveHtml, usage) {
        var used = usageSentence(usage);
        var remove = used
          ? '<span class="dm-usage dm-keep" title="Delete is only available when no sections, curricula, or accounts use this record.">Kept for history · ' + esc(used) + '</span>'
          : !canDelete
          ? '<span class="dm-usage dm-keep" title="Records are deleted by the University Registrar. You can archive and restore them.">Only the University Registrar can delete</span>'
          : '<button type="button" class="dm-btn-sm dm-btn-sm--danger" data-act="delete-archived" data-kind="' + kind + '" data-id="' + esc(id) + '">Delete</button>';
        return '<div class="dm-archive-item__actions">' + remove + unarchiveHtml + '</div>';
      }

      function deleteArchived(kind, id) {
        if (kind === 'college') {
          var c = findArchivedCollege(id);
          if (!c) return;
          var inside = (c.departments || []).length || (c.program && c.program.name);
          deleteRecord({
            action: 'deleteCollege', id: c.id, label: c.name, usage: c.usage || {},
            note: inside ? 'Its departments and programs are deleted with it.' : ''
          });
        } else if (kind === 'dept') {
          var x = DM.archivedDeptOnly.find(function (d) { return d.id === id; });
          if (!x) return;
          deleteRecord({
            action: 'deleteDepartment', id: x.id, label: x.deptName, usage: x.usage || {},
            note: (x.programs || []).length ? 'The programs under it are deleted with it.' : ''
          });
        } else if (kind === 'program') {
          var p = DM.archivedPrograms.find(function (q) { return q.id === id; });
          if (p) deleteRecord({ action: 'deleteProgram', id: p.id, label: p.name, usage: p.usage || {} });
        }
      }

      function renderArchived() {
        ensureShape();
        var groups = [];

        if (DM.archivedColleges.length) {
          groups.push({ title: 'Colleges', count: DM.archivedColleges.length, items: DM.archivedColleges.map(function (c) {
            var depts = c.departments || [];
            var prog = !c.useDepartments && c.program && c.program.name ? c.program : null;
            var children = '';
            if (depts.length) {
              children = '<ul class="dm-archive-children"><li class="dm-archive-children__label">Departments restored with the college</li>' +
                depts.map(function (d) {
                  return '<li>' + esc(d.name) + '<small>' + plural(programsOf(d).length, 'program') + '</small></li>';
                }).join('') + '</ul>';
            } else if (prog) {
              children = '<ul class="dm-archive-children"><li class="dm-archive-children__label">Program restored with the college</li><li>' + esc(prog.name) + '</li></ul>';
            }
            return archiveItem({
              title: c.name,
              badge: '<span class="dm-badge dm-badge--muted">' + esc(c.code) + '</span>',
              meta: 'Archived' + (c.archivedLabel ? ' ' + c.archivedLabel : '') + (depts.length ? ' · ' + plural(depts.length, 'department') : ''),
              action: archiveActions('college', c.id, actionBtn('unarchive-college', 'Unarchive', 'dm-btn-sm--unarchive', { id: c.id }), c.usage),
              children: children
            });
          }) });
        }

        if (DM.archivedDeptOnly.length) {
          groups.push({ title: 'Departments', count: DM.archivedDeptOnly.length, items: DM.archivedDeptOnly.map(function (x) {
            var parentActive = !!findActiveByCode(x.parentCode);
            var nProgs = (x.programs || []).length;
            return archiveItem({
              title: x.deptName,
              badge: '',
              meta: 'Under ' + x.parentName + (x.parentCode && x.parentCode !== '—' ? ' (' + x.parentCode + ')' : '') +
                ' · Archived' + (x.archivedLabel ? ' ' + x.archivedLabel : '') + (nProgs ? ' · ' + plural(nProgs, 'program') : '') +
                (parentActive ? '' : ' · Unarchive the college first'),
              action: archiveActions('dept', x.id,
                '<button type="button" class="dm-btn-sm dm-btn-sm--unarchive" data-act="unarchive-dept" data-id="' + esc(x.id) + '"' + (parentActive ? '' : ' disabled') + '>Unarchive</button>',
                x.usage)
            });
          }) });
        }

        if (DM.archivedPrograms.length) {
          groups.push({ title: 'Programs', count: DM.archivedPrograms.length, items: DM.archivedPrograms.map(function (p) {
            var where = p.departmentName ? p.departmentName + ' · ' + p.collegeName : p.collegeName;
            return archiveItem({
              title: p.name,
              badge: '',
              meta: 'Under ' + where + ' · Archived' + (p.archivedLabel ? ' ' + p.archivedLabel : ''),
              action: archiveActions('program', p.id, actionBtn('unarchive-program', 'Unarchive', 'dm-btn-sm--unarchive', { id: p.id }), p.usage)
            });
          }) });
        }

        stackH.innerHTML = groups.length
          ? groups.map(function (g) {
              return '<section class="dm-archive-group"><div class="dm-archive-group__title">' + g.title + '<span class="dm-badge">' + g.count + '</span></div>' +
                '<div class="dm-archive-list">' + g.items.join('') + '</div></section>';
            }).join('')
          : '<div class="dm-empty"><strong>Nothing is archived</strong>Archived colleges, departments, and programs will appear here.</div>';
        updateChrome();
      }

      function renderBoth() {
        renderActive();
        renderArchived();
      }

      // ---------------------------------------------------------- persistence

      // Every mutation re-renders and then writes the whole organisation back.
      // The page is locked while that write is in flight so a second change
      // cannot be overwritten by the first one's response.
      function renderAll() {
        renderBoth();
        if (!orgReady) return Promise.resolve();
        setBusy(true);
        return window.OrgStore.save(DM).then(function (res) {
          DM = window.OrgStore.load();
          if (!res || res.ok === false) {
            toast((res && res.error) || 'Could not save colleges and departments.', { error: true });
            return window.OrgStore.hydrate().then(function () { DM = window.OrgStore.load(); });
          }
          // reload so every row carries the server's current usage (restored rows have none yet)
          return window.OrgStore.hydrate().then(function () { DM = window.OrgStore.load(); }).catch(function () {});
        }).catch(function () {
          toast('Could not save colleges and departments.', { error: true });
        }).then(function () {
          renderBoth();
          setBusy(false);
        });
      }

      function deleteRecord(spec, onDone) {
        if (!canDelete) {
          ask({ title: 'Can’t delete ' + spec.label, message: 'Only the University Registrar can delete this. Archive it instead; it can be restored later.', info: true });
          return;
        }
        var blocked = blockedDeleteMessage(spec.label, spec.usage);
        if (blocked) {
          ask({ title: 'Can’t delete ' + spec.label, message: blocked, info: true });
          return;
        }
        var message = 'This removes ' + spec.label + ' permanently. It has no sections, curricula, or accounts.' + (spec.note ? ' ' + spec.note : '');
        ask({ title: 'Delete ' + spec.label + '?', message: message, yes: 'Delete', tone: 'danger' }, function () {
          if (!window.McuApi) {
            toast('The server is not available.', { error: true });
            return;
          }
          setBusy(true);
          window.McuApi.send('POST', 'org.php', { action: spec.action, id: spec.id }).then(function () {
            return window.OrgStore.hydrate();
          }).then(function () {
            DM = window.OrgStore.load();
            if (onDone) onDone();
            renderBoth();
            toast(spec.label + ' deleted.');
          }).catch(function (err) {
            toast(err && err.message ? err.message : 'That could not be deleted.', { error: true });
          }).then(function () { setBusy(false); });
        });
      }

      // ------------------------------------------------- archive / unarchive

      function viewArchivedAction() {
        return { actionLabel: 'View archived', onAction: function () { showPane('archived'); } };
      }
      function viewActiveAction() {
        return { actionLabel: 'View active', onAction: function () { showPane('active'); } };
      }

      function archiveCollege(id, afterOk) {
        var c = findActiveCollege(id);
        if (!c) return;
        ask({
          title: 'Archive ' + c.name + '?',
          message: 'It moves to Archived with its departments and programs, and their active curricula are archived too. Sections and schedules stay attached. You can bring it back, but each curriculum has to be restored separately under Reports → Finished curriculums.',
          yes: 'Archive'
        }, function () {
          var i = DM.active.findIndex(function (x) { return x.id === id; });
          if (i < 0) return;
          var copy = JSON.parse(JSON.stringify(DM.active.splice(i, 1)[0]));
          copy.archivedLabel = archiveLabel();
          DM.archivedColleges.push(copy);
          if (afterOk) afterOk();
          renderAll();
          toast(c.name + ' archived.', viewArchivedAction());
        });
      }

      function unarchiveCollege(id) {
        var i = DM.archivedColleges.findIndex(function (c) { return c.id === id; });
        if (i < 0) return;
        var target = DM.archivedColleges[i];
        var clash = DM.active.find(function (c) { return norm(c.code) === norm(target.code) || norm(c.name) === norm(target.name); });
        if (clash) {
          toast('An active college already uses the same name or code (' + clash.name + '). Rename or archive it first.', { error: true });
          return;
        }
        var c = DM.archivedColleges.splice(i, 1)[0];
        delete c.archivedLabel;
        DM.active.push(c);
        renderAll();
        toast(c.name + ' is active again with its departments and programs. Archive any that should stay unused.', viewActiveAction());
      }

      // Moves one department out of an active college. Programs travel with it.
      function moveDeptToArchive(col, deptId) {
        var di = (col.departments || []).findIndex(function (d) { return d.id === deptId; });
        if (di < 0) return null;
        var d = col.departments.splice(di, 1)[0];
        DM.archivedDeptOnly.push({
          id: d.id,
          deptName: d.name,
          programs: d.programs || [],
          parentCode: col.code,
          parentName: col.name,
          archivedLabel: archiveLabel(),
          usage: d.usage
        });
        return d;
      }

      function archiveDept(collegeId, deptId, afterOk) {
        var col = findActiveCollege(collegeId);
        var dept = col && (col.departments || []).find(function (d) { return d.id === deptId; });
        if (!dept) return;
        var n = programsOf(dept).length;
        ask({
          title: 'Archive ' + dept.name + '?',
          message: 'It moves to Archived' + (n ? ' with its ' + plural(n, 'program') : '') + ', and the active curricula of its programs are archived too. Sections and schedules stay attached. You can bring it back, but each curriculum has to be restored separately under Reports → Finished curriculums.',
          yes: 'Archive'
        }, function () {
          moveDeptToArchive(col, deptId);
          if (afterOk) afterOk();
          renderAll();
          toast(dept.name + ' archived.', viewArchivedAction());
        });
      }

      function unarchiveDept(standaloneId) {
        var i = DM.archivedDeptOnly.findIndex(function (x) { return x.id === standaloneId; });
        if (i < 0) return;
        var x = DM.archivedDeptOnly[i];
        var parent = findActiveByCode(x.parentCode);
        if (!parent) {
          toast('No active college with code ' + x.parentCode + ' was found. Unarchive the college first.', { error: true });
          return;
        }
        if ((parent.departments || []).some(function (d) { return norm(d.name) === norm(x.deptName); })) {
          toast(parent.name + ' already has a department called ' + x.deptName + '. Rename it first.', { error: true });
          return;
        }
        DM.archivedDeptOnly.splice(i, 1);
        if (!parent.departments) parent.departments = [];
        parent.useDepartments = true;
        parent.departments.push({ id: x.id, name: x.deptName, programs: x.programs || [], usage: x.usage || emptyUsage() });
        renderAll();
        toast(x.deptName + ' is active again with its programs. Archive any that should stay unused.', viewActiveAction());
      }

      function findProgram(collegeId, deptId, programId) {
        var col = findActiveCollege(collegeId);
        if (!col) return null;
        if (!deptId) return col.program && col.program.id === programId ? col.program : null;
        var dept = (col.departments || []).find(function (d) { return d.id === deptId; });
        if (!dept) return null;
        return programsOf(dept).find(function (p) { return p.id === programId; }) || null;
      }

      function archiveProgram(collegeId, deptId, programId, afterOk) {
        var col = findActiveCollege(collegeId);
        var program = findProgram(collegeId, deptId, programId);
        if (!col || !program) return;
        ask({
          title: 'Archive ' + program.name + '?',
          message: 'It moves to Archived and its active curricula are archived too. Sections and schedules stay attached. You can bring it back, but each curriculum has to be restored separately under Reports → Finished curriculums.',
          yes: 'Archive'
        }, function () {
          var dept = deptId ? (col.departments || []).find(function (d) { return d.id === deptId; }) : null;
          if (!deptId) col.program = null;
          else if (dept) dept.programs = programsOf(dept).filter(function (p) { return p.id !== programId; });
          DM.archivedPrograms.push({
            id: program.id,
            name: program.name,
            collegeId: col.id,
            collegeName: col.name,
            departmentId: deptId || '',
            departmentName: dept ? dept.name : '',
            archivedLabel: archiveLabel(),
            usage: program.usage
          });
          if (afterOk) afterOk();
          renderAll();
          toast(program.name + ' archived.', viewArchivedAction());
        });
      }

      function unarchiveProgram(programId) {
        var i = DM.archivedPrograms.findIndex(function (p) { return p.id === programId; });
        if (i < 0) return;
        var p = DM.archivedPrograms[i];
        var col = findActiveCollege(p.collegeId);
        if (!col) {
          toast('Unarchive ' + p.collegeName + ' first.', { error: true });
          return;
        }
        // keep the usage the server reported, so a restored program still shows (and respects) its accounts
        var restored = { id: p.id, name: p.name, usage: p.usage || emptyUsage() };
        if (p.departmentId && col.useDepartments) {
          var dept = (col.departments || []).find(function (d) { return d.id === p.departmentId; });
          if (!dept) {
            toast('Unarchive the department ' + (p.departmentName || '') + ' first.', { error: true });
            return;
          }
          if (programsOf(dept).some(function (x) { return norm(x.name) === norm(p.name); })) {
            toast(dept.name + ' already has a program called ' + p.name + '.', { error: true });
            return;
          }
          if (!dept.programs) dept.programs = [];
          dept.programs.push(restored);
        } else if (!col.useDepartments) {
          if (col.program && col.program.name) {
            toast(col.name + ' already has a program. Archive or delete it first.', { error: true });
            return;
          }
          col.program = restored;
        } else if (col.departments && col.departments[0]) {
          if (!col.departments[0].programs) col.departments[0].programs = [];
          col.departments[0].programs.push(restored);
        } else {
          toast('Add a department to ' + col.name + ' first.', { error: true });
          return;
        }
        DM.archivedPrograms.splice(i, 1);
        renderAll();
        toast(p.name + ' is active again.', viewActiveAction());
      }

      // ------------------------------------------------------- program dialog

      function openProgramModal(collegeId, deptId, programId) {
        var col = findActiveCollege(collegeId);
        if (!col) return;
        var dept = null;
        var existing = null;
        if (deptId) {
          dept = (col.departments || []).find(function (d) { return d.id === deptId; });
          if (!dept) return;
          existing = programId ? programsOf(dept).find(function (p) { return p.id === programId; }) : null;
          hintP.textContent = 'This program is under ' + dept.name + '.';
        } else {
          if (col.useDepartments) return;
          existing = col.program && col.program.name ? col.program : null;
          hintP.textContent = 'This program sits directly under ' + col.name + '.';
        }
        programModalCollegeId = collegeId;
        programModalDeptId = deptId || null;
        programModalProgramId = existing ? existing.id : null;
        titleP.textContent = existing ? 'Edit program' : 'Add program';
        btnPsave.textContent = existing ? 'Save' : 'Add program';
        btnParchive.hidden = !existing;
        btnPdelete.hidden = !existing || !canDelete;
        inpPname.value = existing ? existing.name : '';
        clearError(errP);
        openOverlay(ovP, inpPname);
      }

      function closeProgramModal() {
        closeOverlay(ovP);
        programModalCollegeId = null;
        programModalDeptId = null;
        programModalProgramId = null;
      }

      function saveProgramModal() {
        clearError(errP);
        var name = clean(inpPname.value);
        if (!name) return showError(errP, 'Enter a program name.', inpPname);
        var pcol = findActiveCollege(programModalCollegeId);
        if (!pcol) return;
        if (!programModalDeptId) {
          if (pcol.useDepartments) return;
          var keptId = programModalProgramId || (pcol.program && pcol.program.id) || uid();
          var keptUsage = pcol.program && pcol.program.usage ? pcol.program.usage : emptyUsage();
          pcol.program = { id: keptId, name: name, usage: keptUsage };
          pcol.departments = [];
          closeProgramModal();
          renderAll();
          toast('Program saved.');
          return;
        }
        var pdept = (pcol.departments || []).find(function (d) { return d.id === programModalDeptId; });
        if (!pdept) return;
        if (!pdept.programs) pdept.programs = [];
        var dup = pdept.programs.some(function (p) {
          return p.id !== programModalProgramId && norm(p.name) === norm(name);
        });
        if (dup) return showError(errP, '“' + name + '” is already under this department.', inpPname);
        if (programModalProgramId) {
          var editing = pdept.programs.find(function (p) { return p.id === programModalProgramId; });
          if (editing) editing.name = name;
        } else {
          pdept.programs.push({ id: uid(), name: name, usage: emptyUsage() });
        }
        closeProgramModal();
        renderAll();
        toast('Program saved.');
      }

      // ---------------------------------------------------- department dialog

      // The department being edited lives either in the college's draft (when
      // opened from the college dialog) or directly in the saved college.
      function deptSource() {
        if (deptModalFromCollegeModal && collegeModalDraft && collegeModalDraft.id === deptModalCollegeId) return collegeModalDraft;
        return findActiveCollege(deptModalCollegeId);
      }

      function openDeptModal(collegeId, deptId, fromCollegeModal) {
        deptModalCollegeId = collegeId;
        deptModalDeptId = deptId;
        deptModalFromCollegeModal = !!fromCollegeModal;
        var source = deptSource();
        if (!source) return;
        var dept = null;
        if (deptId) {
          dept = (source.departments || []).find(function (x) { return x.id === deptId; });
          if (!dept) return;
        }
        var saved = !!dept && isSaved(dept.id, 'dept');
        titleD.textContent = dept ? 'Edit department' : 'Add department';
        hintD.textContent = 'In ' + (source.name || 'this college') + '.';
        inpDname.value = dept ? dept.name : '';
        btnDarchive.hidden = !saved;
        // a department that was never saved can still be taken off the form; deleting a saved one is for the Registrar
        btnDdelete.hidden = !dept || (saved && !canDelete);
        btnDdelete.textContent = !dept || saved ? 'Delete' : 'Remove';
        clearError(errD);
        openOverlay(ovD, inpDname);
      }

      function closeDeptModal() {
        closeOverlay(ovD);
        deptModalCollegeId = null;
        deptModalDeptId = null;
      }

      function saveDeptModal() {
        clearError(errD);
        var name = clean(inpDname.value);
        if (!name) return showError(errD, 'Enter a department name.', inpDname);
        var source = deptSource();
        if (!source) return;
        var dup = (source.departments || []).some(function (x) {
          return x.id !== deptModalDeptId && norm(x.name) === norm(name);
        });
        if (dup) return showError(errD, '“' + name + '” is already a department in this college.', inpDname);
        var inDraft = deptModalFromCollegeModal && source === collegeModalDraft;
        if (deptModalDeptId) {
          var d = source.departments.find(function (x) { return x.id === deptModalDeptId; });
          if (d) d.name = name;
        } else {
          source.useDepartments = true;
          source.departments.push({ id: uid(), name: name, programs: programsForNewDepartment(source) });
        }
        closeDeptModal();
        if (inDraft) {
          if (radDept) radDept.checked = true;
          refreshCollegeModalDeptList();
          return;
        }
        renderAll();
        toast('Department saved.');
      }

      // A college that held one program directly hands it to its first department.
      function programsForNewDepartment(col) {
        if (col.departments && col.departments.length) return [];
        if (col.program && col.program.name) {
          var kept = col.program;
          col.program = null;
          col.useDepartments = true;
          return [kept];
        }
        col.useDepartments = true;
        return [];
      }

      function moveDirectProgramIntoFirstDepartment(col) {
        if (!col || !col.useDepartments || !col.program || !col.program.name) return;
        if (!col.departments || !col.departments.length) return;
        var pid = col.program.id;
        var placed = col.departments.some(function (d) {
          return programsOf(d).some(function (p) { return pid && p.id === pid; });
        });
        if (!placed) {
          if (!col.departments[0].programs) col.departments[0].programs = [];
          col.departments[0].programs.unshift(col.program);
        }
        col.program = null;
      }

      // ------------------------------------------------------ college dialog

      function isDeptMode() { return radDept.checked; }

      function applyStructure() {
        var on = isDeptMode();
        secCdept.hidden = !on;
        secCprogram.hidden = on;
        if (collegeModalDraft) collegeModalDraft.useDepartments = on;
        refreshCollegeModalDeptList();
      }

      function refreshCollegeModalDeptList() {
        listCdept.innerHTML = '';
        if (!collegeModalDraft || !collegeModalDraft.useDepartments) return;
        var depts = collegeModalDraft.departments || [];
        depts.forEach(function (d) {
          var n = programsOf(d).length;
          var row = document.createElement('div');
          row.className = 'dm-modal-dept-row';
          row.innerHTML = '<span class="dm-modal-dept-name">' + esc(d.name) + '<small>' + plural(n, 'program') + (isSaved(d.id, 'dept') ? '' : ' · not saved yet') + '</small></span>' +
            '<button type="button" class="dm-btn-sm" data-act="modal-edit-dept" data-dept-id="' + esc(d.id) + '">Edit</button>';
          listCdept.appendChild(row);
        });
        if (!depts.length) {
          listCdept.innerHTML = '<p class="dm-empty-note" style="padding-left:0;">No departments yet.</p>';
        }
        var carry = !depts.length && collegeModalDraft.program && collegeModalDraft.program.name;
        hintCdept.hidden = !carry;
        if (carry) hintCdept.textContent = 'The program “' + collegeModalDraft.program.name + '” moves into the first department you add.';
      }

      function openCollegeModal(mode, collegeId) {
        collegeModalMode = mode;
        if (mode === 'new') {
          collegeModalDraft = { id: uid(), name: '', code: '', useDepartments: false, departments: [], program: null };
          titleC.textContent = 'Add college';
        } else {
          var c = findActiveCollege(collegeId);
          if (!c) return;
          collegeModalDraft = JSON.parse(JSON.stringify(c));
          if (!collegeModalDraft.departments) collegeModalDraft.departments = [];
          titleC.textContent = 'Edit college';
        }
        var editing = mode !== 'new';
        btnCarchive.hidden = !editing;
        btnCdelete.hidden = !editing || !canDelete;
        inpCname.value = collegeModalDraft.name;
        inpCcode.value = collegeModalDraft.code;
        radDept.checked = !!collegeModalDraft.useDepartments;
        radProgram.checked = !collegeModalDraft.useDepartments;
        inpCprogram.value = collegeModalDraft.program && collegeModalDraft.program.name ? collegeModalDraft.program.name : '';
        clearError(errC);
        applyStructure();
        openOverlay(ovC, inpCname);
      }

      function closeCollegeModal() {
        closeOverlay(ovC);
        collegeModalDraft = null;
      }

      function saveCollegeModal() {
        if (!collegeModalDraft) return;
        clearError(errC);
        var name = clean(inpCname.value);
        var code = inpCcode.value.trim().toUpperCase();
        if (!name) return showError(errC, 'Enter the college name.', inpCname);
        if (!code) return showError(errC, 'Enter a short code, for example CAS.', inpCcode);
        var others = DM.active.concat(DM.archivedColleges).filter(function (c) { return c.id !== collegeModalDraft.id; });
        var sameName = others.find(function (c) { return norm(c.name) === norm(name); });
        if (sameName) {
          return showError(errC, 'A college called “' + sameName.name + '” already exists' + (findArchivedCollege(sameName.id) ? ' in Archived. Unarchive it instead.' : '.'), inpCname);
        }
        var sameCode = others.find(function (c) { return norm(c.code) === norm(code); });
        if (sameCode) {
          return showError(errC, 'The code ' + code + ' is already used by ' + sameCode.name + '.', inpCcode);
        }
        collegeModalDraft.name = name;
        collegeModalDraft.code = code;
        collegeModalDraft.useDepartments = isDeptMode();
        if (!isDeptMode()) {
          var directName = clean(inpCprogram.value);
          collegeModalDraft.departments = [];
          if (directName) {
            var directId = collegeModalDraft.program && collegeModalDraft.program.id ? collegeModalDraft.program.id : uid();
            collegeModalDraft.program = { id: directId, name: directName };
          } else {
            collegeModalDraft.program = null;
          }
        } else {
          moveDirectProgramIntoFirstDepartment(collegeModalDraft);
        }
        if (collegeModalMode === 'new') {
          DM.active.push(collegeModalDraft);
        } else {
          var idx = DM.active.findIndex(function (c) { return c.id === collegeModalDraft.id; });
          if (idx >= 0) DM.active[idx] = collegeModalDraft;
        }
        closeCollegeModal();
        renderAll();
        toast('College saved.');
      }

      function removeFromDraft(deptId) {
        if (!collegeModalDraft) return;
        collegeModalDraft.departments = (collegeModalDraft.departments || []).filter(function (d) { return d.id !== deptId; });
        refreshCollegeModalDeptList();
      }

      // ------------------------------------------------------------- events

      radProgram.addEventListener('change', function () {
        if (radProgram.checked && collegeModalDraft && (collegeModalDraft.departments || []).length) {
          radDept.checked = true;
          radProgram.checked = false;
          showError(errC, 'Archive or delete the departments first. A program can sit directly under a college only when it has no departments.');
          return;
        }
        clearError(errC);
        applyStructure();
      });
      radDept.addEventListener('change', function () {
        clearError(errC);
        applyStructure();
      });

      $('dmCollegeModalAddDept').addEventListener('click', function () {
        if (collegeModalDraft) openDeptModal(collegeModalDraft.id, null, true);
      });
      listCdept.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act="modal-edit-dept"]');
        if (btn && collegeModalDraft) openDeptModal(collegeModalDraft.id, btn.getAttribute('data-dept-id'), true);
      });

      $('dmCollegeModalSave').addEventListener('click', saveCollegeModal);
      $('dmCollegeModalCancel').addEventListener('click', closeCollegeModal);
      $('dmCollegeModalCloseX').addEventListener('click', closeCollegeModal);
      btnCarchive.addEventListener('click', function () {
        if (!collegeModalDraft || collegeModalMode !== 'edit') return;
        archiveCollege(collegeModalDraft.id, closeCollegeModal);
      });
      btnCdelete.addEventListener('click', function () {
        if (!collegeModalDraft || collegeModalMode !== 'edit') return;
        var draft = collegeModalDraft;
        deleteRecord({ action: 'deleteCollege', id: draft.id, label: draft.name, usage: draft.usage || {} }, closeCollegeModal);
      });
      ovC.addEventListener('click', function (e) { if (e.target === ovC) closeCollegeModal(); });

      $('dmDeptModalSave').addEventListener('click', saveDeptModal);
      $('dmDeptModalCancel').addEventListener('click', closeDeptModal);
      $('dmDeptModalCloseX').addEventListener('click', closeDeptModal);
      ovD.addEventListener('click', function (e) { if (e.target === ovD) closeDeptModal(); });
      btnDdelete.addEventListener('click', function () {
        if (!deptModalCollegeId || !deptModalDeptId) return;
        var source = deptSource();
        var dept = source && (source.departments || []).find(function (d) { return d.id === deptModalDeptId; });
        if (!dept) return;
        var inDraft = deptModalFromCollegeModal && source === collegeModalDraft;
        if (!isSaved(dept.id, 'dept')) {
          removeFromDraft(dept.id);
          closeDeptModal();
          return;
        }
        var note = programsOf(dept).length ? 'The unused programs under it are removed too.' : '';
        deleteRecord({ action: 'deleteDepartment', id: dept.id, label: dept.name, usage: dept.usage || {}, note: note }, function () {
          if (inDraft) removeFromDraft(dept.id);
          closeDeptModal();
        });
      });
      btnDarchive.addEventListener('click', function () {
        if (!deptModalCollegeId || !deptModalDeptId) return;
        var deptId = deptModalDeptId;
        var inDraft = deptModalFromCollegeModal && collegeModalDraft && collegeModalDraft.id === deptModalCollegeId;
        // Archiving is immediate, so it applies to the saved college even when
        // the dialog was opened from the college form.
        archiveDept(deptModalCollegeId, deptId, function () {
          if (inDraft) removeFromDraft(deptId);
          closeDeptModal();
        });
      });

      btnPsave.addEventListener('click', saveProgramModal);
      $('dmProgramModalCancel').addEventListener('click', closeProgramModal);
      $('dmProgramModalCloseX').addEventListener('click', closeProgramModal);
      ovP.addEventListener('click', function (e) { if (e.target === ovP) closeProgramModal(); });
      btnParchive.addEventListener('click', function () {
        if (!programModalCollegeId || !programModalProgramId) return;
        archiveProgram(programModalCollegeId, programModalDeptId || '', programModalProgramId, closeProgramModal);
      });
      btnPdelete.addEventListener('click', function () {
        if (!programModalCollegeId || !programModalProgramId) return;
        var program = findProgram(programModalCollegeId, programModalDeptId || '', programModalProgramId);
        if (!program) return;
        deleteRecord({ action: 'deleteProgram', id: program.id, label: program.name, usage: program.usage || {} }, closeProgramModal);
      });

      btnKyes.addEventListener('click', function () { closeConfirm(true); });
      btnKno.addEventListener('click', function () { closeConfirm(false); });
      ovK.addEventListener('click', function (e) { if (e.target === ovK) closeConfirm(false); });

      // Enter saves from any single-line field in a dialog.
      [[inpCname, saveCollegeModal], [inpCcode, saveCollegeModal], [inpCprogram, saveCollegeModal],
       [inpDname, saveDeptModal], [inpPname, saveProgramModal]].forEach(function (pair) {
        pair[0].addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            pair[1]();
          }
        });
      });

      function topOverlay() {
        return [ovK, ovP, ovD, ovC].find(function (o) { return !o.hidden; });
      }
      document.addEventListener('keydown', function (e) {
        var top = topOverlay();
        if (!top) return;
        if (e.key === 'Escape') {
          if (top === ovK) closeConfirm(false);
          else if (top === ovP) closeProgramModal();
          else if (top === ovD) closeDeptModal();
          else closeCollegeModal();
          return;
        }
        if (e.key !== 'Tab') return;
        var items = Array.prototype.filter.call(
          top.querySelectorAll('button, input, select, textarea, a[href]'),
          function (el) { return !el.disabled && el.offsetParent !== null; }
        );
        if (!items.length) return;
        var first = items[0];
        var last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      });

      $('dmCollegeBtnAdd').addEventListener('click', function () { openCollegeModal('new'); });
      inpSearch.addEventListener('input', function () {
        query = inpSearch.value;
        renderActive();
      });
      $('dmExpandAll').addEventListener('click', function () {
        collapsed = {};
        renderActive();
      });
      $('dmCollapseAll').addEventListener('click', function () {
        DM.active.forEach(function (c) { collapsed[c.id] = true; });
        renderActive();
      });

      main.addEventListener('click', function (e) {
        var t = e.target.closest('[data-act]');
        if (!t || !main.contains(t)) return;
        // Links inside the card header (for example the dean notice) just navigate.
        if (t.getAttribute('data-act') === 'toggle-college' && e.target.closest('a')) return;
        var act = t.getAttribute('data-act');
        var collegeId = t.getAttribute('data-college-id');
        var deptId = t.getAttribute('data-dept-id') || '';
        var programId = t.getAttribute('data-program-id');
        var id = t.getAttribute('data-id');

        if (act === 'toggle-college') {
          collapsed[collegeId] = !collapsed[collegeId];
          renderActive();
          var again = stackA.querySelector('.dm-card-toggle[data-college-id="' + collegeId + '"]');
          if (again && e.target.closest('.dm-card-toggle')) again.focus();
        } else if (act === 'add-college') openCollegeModal('new');
        else if (act === 'clear-search') {
          inpSearch.value = '';
          query = '';
          renderActive();
          inpSearch.focus();
        }
        else if (act === 'edit-college') openCollegeModal('edit', collegeId);
        else if (act === 'archive-college') archiveCollege(collegeId);
        else if (act === 'add-dept') openDeptModal(collegeId, null, false);
        else if (act === 'edit-dept') openDeptModal(collegeId, deptId, false);
        else if (act === 'archive-dept') archiveDept(collegeId, deptId);
        else if (act === 'add-program') openProgramModal(collegeId, deptId);
        else if (act === 'add-direct-program') openProgramModal(collegeId, '');
        else if (act === 'edit-program') openProgramModal(collegeId, deptId, programId);
        else if (act === 'archive-program') archiveProgram(collegeId, deptId, programId);
        else if (act === 'unarchive-college') unarchiveCollege(id);
        else if (act === 'unarchive-dept') unarchiveDept(id);
        else if (act === 'unarchive-program') unarchiveProgram(id);
        else if (act === 'delete-archived') deleteArchived(t.getAttribute('data-kind'), id);
      });

      // Other tabs, or the curriculum dialog, may change the list.
      window.OrgStore.onChange(function () {
        if (!orgReady) return;
        DM = window.OrgStore.load();
        renderBoth();
      });

      function start() {
        orgReady = true;
        DM = window.OrgStore.load();
        renderBoth();
        if (window.location.hash === '#archived') showPane('archived');
      }
      if (window.MCUNexReady) window.MCUNexReady.then(start, start);
      else start();
    })();
  </script>
@endverbatim
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>

@endverbatim
