{{-- The login page look, shared by the login page and the account email pages. --}}
@verbatim
  <style>
    :root {
      --purple: #5c267e;
      --gold: #f9b937;
      --white: #ffffff;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-image:
        linear-gradient(
          to bottom,
          rgba(92, 38, 126, 0) 0%,
          rgba(92, 38, 126, 0) 0%,
          rgba(92, 38, 126, 1) 50%,
          rgba(92, 38, 126, 1) 100%
        ),
        url("Attri/samplebglogin2.png");
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      padding: 1rem;
    }

    .login-card {
      background: var(--white);
      border-radius: 12px;
      padding: 2.25rem 2.5rem 2.5rem;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
      display: flex;
      flex-direction: column;
    }

    .brand-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.75rem;
    }

    .brand-logo {
      width: 44px;
      height: 44px;
      border-radius: 999px;
      overflow: hidden;
      border: 2px solid var(--gold);
      flex-shrink: 0;
      background: #f3edf8;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .brand-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .brand-text-main {
      color: var(--purple);
      font-size: 1.25rem;
      font-weight: 700;
      letter-spacing: 0.04em;
    }

    .brand-text-sub {
      color: #7a6992;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.16em;
    }

    .login-card h1 {
      color: var(--purple);
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      text-align: left;
    }

    .field {
      margin-bottom: 1.25rem;
    }

    .field label {
      display: block;
      color: var(--purple);
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 0.375rem;
    }

    .field input {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 2px solid #e8e0ed;
      border-radius: 8px;
      font-size: 1rem;
      font-family: inherit;
      color: var(--purple);
      transition: border-color 0.2s;
    }

    .field input::placeholder {
      color: #a090a8;
    }

    .field input:focus {
      outline: none;
      border-color: var(--gold);
    }

    .forgot {
      text-align: right;
      margin-bottom: 1.5rem;
    }

    .forgot button {
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      color: var(--purple);
      font-size: 0.875rem;
      font-weight: 500;
      font-family: inherit;
      text-decoration: none;
    }

    .forgot button:hover {
      text-decoration: underline;
      color: #4a1f66;
    }

    .btn-login {
      width: 100%;
      padding: 0.875rem 1.5rem;
      background: var(--gold);
      color: var(--purple);
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: background 0.2s, transform 0.05s;
    }

    .btn-login:hover {
      background: #e5a82a;
    }

    .btn-login:active {
      transform: scale(0.99);
    }
    .btn-login:disabled {
      opacity: 0.65;
      cursor: wait;
    }
    .login-error {
      display: none;
      margin: 0 0 0.85rem;
      padding: 0.55rem 0.75rem;
      border-radius: 0.55rem;
      background: #fef2f2;
      color: #991b1b;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .login-error.is-visible { display: block; }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(40, 20, 60, 0.45);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      z-index: 100;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s, visibility 0.2s;
    }

    .modal-overlay.is-open {
      opacity: 1;
      visibility: visible;
    }

    .modal-dialog {
      background: var(--white);
      border-radius: 12px;
      padding: 1.75rem 1.75rem 1.5rem;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      position: relative;
    }

    .modal-dialog h2 {
      color: var(--purple);
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .modal-dialog .field {
      margin-bottom: 1rem;
    }

    .modal-hint {
      font-size: 0.8rem;
      color: #7a6992;
      margin-bottom: 1rem;
      line-height: 1.4;
    }

    .modal-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-top: 1.25rem;
    }


    .modal-actions .btn-primary {
      flex: 1;
      min-width: 6rem;
      padding: 0.65rem 1rem;
      border: none;
      border-radius: 8px;
      background: var(--gold);
      color: var(--purple);
      font-family: inherit;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
    }

    .modal-actions .btn-primary:hover {
      background: #e5a82a;
    }

    .modal-close {
      position: absolute;
      top: 0.75rem;
      right: 0.75rem;
      width: 2rem;
      height: 2rem;
      border: none;
      background: transparent;
      color: #7a6992;
      font-size: 1.5rem;
      line-height: 1;
      cursor: pointer;
      border-radius: 6px;
    }

    .modal-close:hover {
      background: #f3edf8;
      color: var(--purple);
    }

    .password-wrap {
      position: relative;
    }

    .password-wrap input {
      padding-right: 3rem;
    }

    /* Edge adds its own reveal eye to password fields; hide it so only ours shows. */
    .password-wrap input::-ms-reveal,
    .password-wrap input::-ms-clear {
      display: none;
    }

    .password-toggle {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      width: 3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      background: none;
      color: #7a6992;
      cursor: pointer;
      border-radius: 0 8px 8px 0;
    }

    .password-toggle:hover {
      color: var(--purple);
    }

    .password-toggle:focus-visible {
      outline: 2px solid var(--gold);
      outline-offset: -4px;
    }

    .password-toggle svg {
      width: 20px;
      height: 20px;
    }

    .password-toggle svg[hidden] {
      display: none;
    }
    /* account email pages and the forgot form (partials/auth-page) */
    .auth-note { margin: 0 0 1rem; padding: 0.65rem 0.8rem; border-radius: 0.6rem; background: #f6f1fc; color: #4b3d5e; font-size: 0.86rem; line-height: 1.5; }
    .auth-note.is-ok { background: #f0fdf4; color: #14532d; }
    .auth-note.is-bad { background: #fef2f2; color: #7f1d1d; }
    .auth-note[hidden] { display: none; }
    .auth-links { margin-top: 1rem; text-align: center; font-size: 0.86rem; }
    .auth-links a { color: var(--purple, #5c267e); font-weight: 600; }
    .modal-actions .btn-secondary { padding: 0.55rem 1.1rem; border: 1px solid #e3d9f0; border-radius: 0.6rem; background: #fff; color: #5c267e; font: inherit; font-weight: 600; cursor: pointer; }
    .modal-actions .btn-secondary:hover { background: #f6f1fc; }
    .field-hint { margin-top: 0.3rem; font-size: 0.78rem; color: #7a6992; }
  </style>
@endverbatim
