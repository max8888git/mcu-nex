@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MCU-NEX | Rooms available</title>
@endverbatim
  @fonts
@verbatim
  <style>
    :root {
      --purple: #5c267e;
      --purple-dark: #2d163f;
      --purple-soft: #f6f1fc;
      --gold: #f9b937;
      --gold-soft: #fff6de;
      --bg: #f5f5f9;
      --text-main: #1f1230;
      --text-muted: #7a6992;
      --border-soft: #e3d9f0;
      --danger: #e05454;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text-main);
      display: flex;
      flex-direction: column;
    }

    .app-shell {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

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

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .topbar-logo {
      width: 32px;
      height: 32px;
      border-radius: 999px;
      overflow: hidden;
      border: 2px solid var(--gold);
      background: #f3edf8;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .topbar-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .topbar-brand-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: inherit;
      cursor: pointer;
    }
    .topbar-brand-link:hover {
      opacity: 0.92;
    }

    .topbar-title-main {
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .topbar-title-sub {
      font-size: 0.75rem;
      color: #e2d4ff;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: 0.875rem;
    }
    .topbar-link {
      color: white;
      text-decoration: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.2s;
      background: rgba(255, 255, 255, 0.1);
    }

    .topbar-link:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
    }

    .topbar-notify {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .topbar-notify-btn {
      position: relative;
      width: 30px;
      height: 30px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.4);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      background: rgba(0, 0, 0, 0.16);
      color: #fff;
      font-size: 0.9rem;
    }

    .topbar-notify-dot {
      position: absolute;
      top: 6px;
      right: 6px;
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: var(--gold);
      box-shadow: 0 0 0 3px rgba(249, 185, 55, 0.35);
    }

    .topbar-user {
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
    }

    .avatar {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      background: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--purple);
    }

    .chip {
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }

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

    .nav-toggle:hover {
      background: rgba(0, 0, 0, 0.2);
    }

    .nav-toggle-line {
      width: 18px;
      height: 2px;
      border-radius: 999px;
      background: #fff;
    }

    .user-menu,
    .notify-menu {
      position: absolute;
      top: 130%;
      right: 0;
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
    .notify-menu div:hover {
      background: var(--purple-soft);
      color: var(--purple);
    }

    .user-menu a.logout {
      color: var(--danger);
    }

    .topbar-user.open .user-menu {
      display: block;
    }

    .tooltip {
      position: relative;
    }

    .tooltip::after {
      content: attr(data-tooltip);
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      background: #fff;
      color: var(--text-main);
      padding: 0.55rem 0.9rem;
      border-radius: 999px;
      box-shadow: 0 14px 26px rgba(0, 0, 0, 0.22);
      opacity: 0;
      transform: translateY(-4px);
      transition: opacity 0.15s ease, transform 0.15s ease;
      pointer-events: none;
      white-space: nowrap;
      z-index: 50;
    }

    .tooltip:hover::after {
      opacity: 1;
      transform: translateY(0);
    }

    .tooltip.open::after,
    .tooltip.open:hover::after {
      opacity: 0;
      transform: translateY(-4px);
    }

    .tooltip-left::after {
      left: 0;
      right: auto;
    }

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

    .layout-row {
      display: flex;
      flex: 1;
      min-height: 0;
    }

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

    .nav-bar.nav-bar-open {
      transform: translateX(0);
    }

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

    .nav-link:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
    }

    .nav-link.active {
      background: #fff;
      color: var(--purple-dark);
      font-weight: 600;
    }

    main.page {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      max-width: 1400px;
      width: 100%;
      margin: 2rem auto;
      margin-inline: auto;
      padding: 1.5rem 2rem;
      box-sizing: border-box;
    }

    .filters-row {
      background: #fff;
      border-radius: 0.75rem;
      border: 1px solid var(--border-soft);
      padding: 0.75rem 1rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
      flex-wrap: wrap;
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .filters-row label {
      font-weight: 500;
      color: var(--text-main);
      font-size: 0.85rem;
    }

    .filters-row select {
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      padding: 0.35rem 0.9rem;
      font-family: inherit;
      font-size: 0.85rem;
      background: var(--purple-soft);
      color: var(--text-main);
      min-width: 180px;
      outline: none;
    }

    .main-card {
      background: #fff;
      border-radius: 0.9rem;
      border: 1px solid var(--border-soft);
      padding: 1.25rem 1.25rem 1.4rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .main-header {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .main-header-title {
      font-size: 1.15rem;
    }

    .main-header-title span {
      font-weight: 600;
    }

    .main-header-sub {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-top: 0.15rem;
    }

    .toolbar {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
      font-size: 0.78rem;
    }

    .toolbar-group {
      display: inline-flex;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      background: var(--purple-soft);
      overflow: hidden;
    }

    .toolbar-group button,
    .toolbar-group span {
      border: 0;
      padding: 0.35rem 0.7rem;
      background: transparent;
      font-size: 0.78rem;
      cursor: pointer;
      color: var(--text-muted);
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }

    .toolbar-group button.primary {
      background: #fff;
      color: var(--purple);
      font-weight: 600;
    }

    .toolbar-icon {
      font-size: 0.9rem;
    }

    .toolbar-pill {
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      padding: 0.35rem 0.8rem;
      background: #fff;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }

    .toolbar-pill-label {
      font-size: 0.78rem;
      color: var(--text-muted);
    }

    .toolbar-pill-value {
      font-size: 0.78rem;
      font-weight: 500;
    }

    .calendar {
      border-radius: 0.75rem;
      border: 1px solid var(--border-soft);
      overflow: hidden;
    }

    .calendar-header {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      background: #fbf9ff;
      border-bottom: 1px solid var(--border-soft);
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    .calendar-header div {
      padding: 0.6rem 0.75rem;
      border-right: 1px solid var(--border-soft);
    }

    .calendar-header div:last-child {
      border-right: none;
    }

    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      background: #fff;
      font-size: 0.78rem;
    }

    .day-cell {
      min-height: 110px;
      border-right: 1px solid var(--border-soft);
      border-bottom: 1px solid var(--border-soft);
      padding: 0.5rem 0.6rem;
      position: relative;
      background: #fff;
    }

    .day-cell:last-child {
      border-right: none;
    }

    .day-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--text-muted);
      margin-bottom: 0.1rem;
    }

    .day-number {
      font-size: 0.95rem;
      font-weight: 600;
      margin-bottom: 0.35rem;
    }

    .day-muted {
      color: #c2b6d2;
    }

    .day-highlight {
      background: var(--gold-soft);
    }

    .shift-card {
      margin-top: 0.35rem;
      padding: 0.4rem 0.45rem;
      background: var(--purple-soft);
      border-radius: 0.45rem;
      border: 1px solid var(--border-soft);
      font-size: 0.75rem;
      line-height: 1.3;
      color: var(--text-main);
    }

    .shift-time {
      font-weight: 600;
    }

    .shift-location {
      font-size: 0.72rem;
      color: var(--text-muted);
    }

    .shift-count {
      position: absolute;
      right: 0.45rem;
      bottom: 0.45rem;
      font-size: 0.7rem;
      color: #b2a5c5;
    }

    @media (max-width: 900px) {
      .calendar-header,
      .calendar-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }

      .calendar-header div:nth-child(n+5),
      .calendar-grid .day-cell:nth-child(n+5) {
        display: none;
      }
    }

    @media (max-width: 768px) {
      .nav-bar {
        width: 75%;
        max-width: 260px;
      }
    }

    @media (max-width: 640px) {
      .page {
        padding: 1rem;
      }

      .nav-bar {
        padding-inline: 1rem;
      }

      .topbar {
        padding-inline: 1rem;
      }

      .calendar-header,
      .calendar-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .calendar-header div:nth-child(n+3),
      .calendar-grid .day-cell:nth-child(n+3) {
        display: none;
      }
    }
  
    /* Page-specific styles for Rooms Available */
    .page-header {
      margin-bottom: 2rem;
    }

    .page-header h1 {
      font-size: 2rem;
      color: var(--purple);
      margin-bottom: 0;
    }

    .page-header-with-action {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 0.65rem;
    }

    .link-change-filters {
      font-weight: 600;
      color: var(--purple);
      text-decoration: none;
      white-space: nowrap;
      font-size: 0.95rem;
    }

    .link-change-filters:hover {
      text-decoration: underline;
    }

    .filters-card {
      background: white;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      margin-bottom: 1.5rem;
      display: flex;
      justify-content: center;
    }

    .filters-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      justify-content: center;
      gap: 0.65rem 0.85rem;
      max-width: 100%;
    }

    .filters-toolbar .filter-group {
      display: flex;
      flex-direction: column;
      flex: 0 1 auto;
      min-width: min(100%, 7rem);
      max-width: 16rem;
    }

    .filters-toolbar .filter-group--type {
      min-width: min(100%, 9rem);
      max-width: 18rem;
    }

    .filters-toolbar .filter-group--apply {
      flex: 0 0 auto;
      min-width: auto;
      max-width: none;
    }

    .filters-toolbar .filter-select {
      width: 100%;
      min-width: 0;
    }

    .filter-label-spacer {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      line-height: 1.25;
      min-height: 1.25em;
      visibility: hidden;
      user-select: none;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
    }

    .filter-group label {
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: var(--text-main);
      font-size: 0.875rem;
    }

    .filter-select {
      padding: 0.65rem 0.75rem;
      border: 2px solid var(--border-soft);
      border-radius: 8px;
      font-family: inherit;
      font-size: 0.95rem;
      background: white;
      cursor: pointer;
      transition: all 0.2s;
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--purple);
      box-shadow: 0 0 0 3px rgba(92, 38, 126, 0.1);
    }

    .btn-filter {
      padding: 0.65rem 1.25rem;
      border: none;
      border-radius: 8px;
      background: var(--purple);
      color: white;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    }

    .btn-filter:hover {
      background: var(--purple-dark);
      transform: translateY(-2px);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1.5rem;
      justify-content: center;
      max-width: 900px;
      margin-left: auto;
      margin-right: auto;
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      text-align: center;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .stat-card.available .stat-number { color: #10b981; }
    .stat-card.occupied .stat-number { color: #f59e0b; }
    .stat-card.maintenance .stat-number { color: #ef4444; }

    @media (max-width: 640px) {
      .stats-grid {
        width: 100%;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
      }

      .stat-card {
        padding: 1rem 0.5rem;
      }

      .stat-number {
        font-size: 1.75rem;
      }
    }

    .stat-label {
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.875rem;
      letter-spacing: 0.05em;
    }

    .rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }

    .room-card {
      background: white;
      border: 2px solid var(--border-soft);
      border-radius: 12px;
      padding: 1.5rem;
      transition: all 0.3s;
    }

    .room-card.hidden {
      display: none;
    }

    .room-card.available {
      border-color: #10b981;
      background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    }

    .room-card.occupied {
      border-color: #f59e0b;
      background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
    }

    .room-card.maintenance {
      border-color: #ef4444;
      background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
    }

    .room-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .room-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--border-soft);
    }

    .room-number {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--purple);
    }

    .room-status {
      padding: 0.375rem 1rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .status-available {
      background: #d1fae5;
      color: #065f46;
    }

    .status-occupied {
      background: #fef3c7;
      color: #92400e;
    }

    .status-maintenance {
      background: #fee2e2;
      color: #991b1b;
    }

    .room-details {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }

    .room-info {
      display: flex;
      justify-content: space-between;
      font-size: 0.9375rem;
    }

    .room-label {
      color: var(--text-muted);
      font-weight: 500;
    }

    .room-value {
      color: var(--text-main);
      font-weight: 600;
      text-align: right;
    }

    .room-features {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
    }

    .feature-tag {
      background: var(--purple-soft);
      color: var(--purple);
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.8125rem;
      font-weight: 600;
    }

    .btn-allocate {
      width: 100%;
      padding: 0.875rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.2s;
      font-family: inherit;
      background: var(--purple);
      color: white;
    }

    .btn-allocate:hover:not(:disabled) {
      background: var(--purple-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(92, 38, 126, 0.3);
    }

    .btn-allocate:disabled {
      background: #d1d5db;
      color: #6b7280;
      cursor: not-allowed;
      transform: none;
    }

    .no-results {
      text-align: center;
      padding: 3rem;
      color: var(--text-muted);
      font-size: 1.125rem;
    }

    .room-number.clickable {
      cursor: pointer;
      text-decoration: underline;
      text-underline-offset: 3px;
    }
    .room-number.clickable:hover {
      color: var(--purple-dark);
    }

    .room-schedule-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 100;
      padding: 1rem;
    }
    .room-schedule-modal-overlay.open {
      display: flex;
    }
    .room-schedule-modal {
      background: #fff;
      border-radius: 12px;
      padding: 1.5rem;
      max-width: 520px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.25);
      border: 1px solid var(--border-soft);
    }
    .room-schedule-modal h2 {
      font-size: 1.25rem;
      color: var(--purple);
      margin-bottom: 1rem;
    }
    .modal-day-picker {
      margin-bottom: 1.25rem;
    }
    .modal-day-picker-label {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
    }
    .modal-day-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
    }
    .modal-day-chip {
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      border: 2px solid var(--border-soft);
      background: #fff;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      color: var(--text-main);
    }
    .modal-day-chip:hover:not(:disabled) {
      border-color: var(--purple);
      color: var(--purple);
    }
    .modal-day-chip.is-active {
      background: var(--purple);
      border-color: var(--purple);
      color: #fff;
    }
    .modal-day-chip.is-closed {
      opacity: 0.42;
      cursor: not-allowed;
    }
    .room-schedule-modal .schedule-table-wrap {
      margin-bottom: 1.25rem;
    }
    .room-schedule-modal .schedule-table-wrap h3 {
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
      font-weight: 600;
    }
    .room-schedule-modal table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    .room-schedule-modal table th,
    .room-schedule-modal table td {
      padding: 0.5rem 0.75rem;
      text-align: left;
      border: 1px solid var(--border-soft);
    }
    .room-schedule-modal table th {
      background: var(--purple-soft);
      color: var(--purple);
      font-weight: 600;
    }
    .room-schedule-modal .allocate-form {
      border-top: 2px solid var(--border-soft);
      padding-top: 1.25rem;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem 1rem;
      align-items: end;
    }
    .room-schedule-modal .allocate-form .full-width {
      grid-column: 1 / -1;
    }
    .room-schedule-modal .allocate-form label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 0.35rem;
    }
    .room-schedule-modal .allocate-form select,
    .room-schedule-modal .allocate-form input {
      width: 100%;
      padding: 0.5rem 0.6rem;
      border: 1px solid var(--border-soft);
      border-radius: 8px;
      font-family: inherit;
      font-size: 0.9rem;
    }
    .room-schedule-modal .btn-allocate-modal {
      grid-column: 1 / -1;
      padding: 0.75rem;
      border: none;
      border-radius: 8px;
      background: var(--purple);
      color: #fff;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      font-family: inherit;
    }
    .room-schedule-modal .btn-allocate-modal:hover {
      background: var(--purple-dark);
    }
    .room-schedule-modal .btn-close {
      margin-top: 1rem;
      width: 100%;
      padding: 0.6rem;
      border: 1px solid var(--border-soft);
      border-radius: 8px;
      background: var(--purple-soft);
      color: var(--purple);
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
    }
    .room-schedule-modal .btn-close:hover {
      background: var(--purple);
      color: #fff;
    }
    .btn-view {
      width: 100%;
      padding: 0.875rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.2s;
      font-family: inherit;
      background: var(--purple);
      color: white;
    }
    .btn-view:hover:not(:disabled) {
      background: var(--purple-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(92, 38, 126, 0.3);
    }
    .btn-view:disabled {
      background: #d1d5db;
      color: #6b7280;
      cursor: not-allowed;
      transform: none;
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

    .facilities-actions-bar {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      width: 100%;
      padding: 0.5rem 1.5rem;
      background: var(--purple-soft);
      border-bottom: 1px solid var(--border-soft);
      box-sizing: border-box;
    }

    .btn-rooms-available-bar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.45rem 1rem;
      border-radius: 10px;
      border: 1px solid rgba(92, 38, 126, 0.35);
      background: #fff;
      color: var(--purple);
      font-weight: 600;
      font-size: 0.85rem;
      text-decoration: none;
      font-family: inherit;
      transition: background 0.15s, border-color 0.15s;
    }

    .btn-rooms-available-bar:hover {
      background: #fff;
      border-color: var(--purple);
      box-shadow: 0 2px 8px rgba(92, 38, 126, 0.12);
    }

    .btn-rooms-available-bar:focus-visible {
      outline: 2px solid var(--purple);
      outline-offset: 2px;
    }

</style>
@endverbatim
  <x-legacy-css name="app-header-sticky" />
  <x-legacy-css name="shell-responsive" />
  <x-legacy-css name="app-nav" />
  <x-legacy-css name="profile" />
  <x-legacy-css name="page-loading" />
  <x-legacy-css name="ui" />
  <x-legacy-css name="rooms-finder" />
  <x-legacy-js name="page-loading" />
@verbatim
</head>
<body>
  <div class="app-shell">
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

    <div class="topbar-tabs" aria-label="Page tools">
      <div class="topbar-datetime" id="topbar-datetime" aria-live="polite"></div>
@endverbatim
      <x-app-nav-button />
@verbatim
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
<main class="rf-page" id="rfPage">
        <header class="ui-page-head">
          <div>
            <h1 class="ui-page-title">Rooms available</h1>
            <p class="ui-page-sub">Find a room that’s free when you need it. Bookings come from the published timetable for <span class="rf-term" id="rfTerm">this term</span>.</p>
          </div>
        </header>

        <section class="ui-card rf-search" aria-label="Search rooms">
          <div>
            <span class="ui-field-label" id="rfDayLabel">Day</span>
            <div class="rf-days" role="group" aria-labelledby="rfDayLabel">
              <button type="button" class="rf-day-btn" data-rf-day="mon" aria-pressed="false"><span aria-hidden="true">Mon</span><span class="ui-sr">Monday</span></button>
              <button type="button" class="rf-day-btn" data-rf-day="tue" aria-pressed="false"><span aria-hidden="true">Tue</span><span class="ui-sr">Tuesday</span></button>
              <button type="button" class="rf-day-btn" data-rf-day="wed" aria-pressed="false"><span aria-hidden="true">Wed</span><span class="ui-sr">Wednesday</span></button>
              <button type="button" class="rf-day-btn" data-rf-day="thu" aria-pressed="false"><span aria-hidden="true">Thu</span><span class="ui-sr">Thursday</span></button>
              <button type="button" class="rf-day-btn" data-rf-day="fri" aria-pressed="false"><span aria-hidden="true">Fri</span><span class="ui-sr">Friday</span></button>
              <button type="button" class="rf-day-btn" data-rf-day="sat" aria-pressed="false"><span aria-hidden="true">Sat</span><span class="ui-sr">Saturday</span></button>
            </div>
          </div>
          <div class="rf-fields">
            <div>
              <label class="ui-field-label" for="rfStart">From</label>
              <select class="ui-input" id="rfStart"></select>
            </div>
            <div>
              <label class="ui-field-label" for="rfEnd">To</label>
              <select class="ui-input" id="rfEnd" aria-describedby="rfTimeError"></select>
            </div>
            <div>
              <label class="ui-field-label" for="rfBldg">Building</label>
              <select class="ui-input" id="rfBldg"><option value="">All buildings</option></select>
            </div>
            <div>
              <label class="ui-field-label" for="rfType">Room type</label>
              <select class="ui-input" id="rfType"><option value="">All room types</option></select>
            </div>
            <div>
              <label class="ui-field-label" for="rfSeats">Seats needed</label>
              <input class="ui-input" id="rfSeats" inputmode="numeric" pattern="[0-9]*" placeholder="Any" autocomplete="off">
            </div>
          </div>
          <p class="ui-field-error" id="rfTimeError" hidden>The end time must be after the start time.</p>
          <p class="rf-now-hint" id="rfNowHint" role="status" hidden></p>
          <div class="rf-options">
            <label><input type="checkbox" id="rfShowBooked"> Show booked rooms</label>
            <label><input type="checkbox" id="rfShowRepair"> Show rooms under maintenance</label>
            <span class="rf-spacer"></span>
            <button type="button" class="rf-link" id="rfNow">Use current time</button>
            <button type="button" class="ui-btn" id="rfClear">Clear filters</button>
            <button type="button" class="ui-btn ui-btn--primary" id="rfShow">Show</button>
          </div>
        </section>

        <p class="rf-note" id="rfTermNote" hidden></p>
        <p class="rf-summary" id="rfSummary" aria-live="polite"></p>
        <ul class="rf-results" id="rfResults" aria-busy="true">
          <li><span class="skeleton rf-skeleton"></span></li>
          <li><span class="skeleton rf-skeleton"></span></li>
          <li><span class="skeleton rf-skeleton"></span></li>
          <li class="ui-sr">Loading rooms</li>
        </ul>
      </main>

  <dialog class="ui-dialog" id="rfDialog" aria-labelledby="rfDialogTitle">
    <div class="ui-dialog-head">
      <div>
        <h2 class="ui-dialog-title" id="rfDialogTitle">Room</h2>
        <p class="rf-dialog-meta" id="rfDialogMeta"></p>
      </div>
      <button type="button" class="ui-icon-btn" data-rf-close aria-label="Close">&times;</button>
    </div>
    <div class="ui-dialog-body">
      <div class="rf-week" id="rfWeek"></div>
    </div>
    <div class="ui-dialog-foot">
      <button type="button" class="ui-btn" data-rf-close>Close</button>
      <a class="ui-btn ui-btn--primary" href="create-schedule.html">Open Create schedule</a>
    </div>
  </dialog>
    </div>

@endverbatim
  <x-legacy-js name="api-client" />
  <x-legacy-js name="session-boot" />
  <x-legacy-js name="org-store" />
  <x-legacy-js name="room-types" />
  <x-legacy-js name="facilities-store" />
  <x-legacy-js name="data-boot" />
  <x-legacy-js name="rooms-finder" />
@verbatim
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
  <x-legacy-js name="notifications" defer />
@verbatim
</body>
</html>
@endverbatim
