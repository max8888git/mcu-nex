# Account emails: confirm email, forgot password, starting passwords (2026-10-17)

## How it works

- **New account** (Account management): the registrar fills in name, email, role and college,
  with **no password**. The person gets an invite email, **Set up your MCU-NEX account**
  (`setup-account.html`, link works 7 days, once). Choosing their password there also confirms
  their email, so nobody else ever knows the password. Until then the account can't be logged
  into and the list shows **Invite sent, not set up yet** with **Resend invite**. Lost the invite?
  **Forgot password?** on the login page sends it again. (Changed 2026-10-17 from starting
  passwords to invites, at the user's request.)
  - Backup, when their email doesn't work: **Give a starting password instead** shows
    **Generate** (`mcu` + 4 digits) and **Copy**; the registrar shares it privately, and the first
    sign-in opens **Choose your password** (`new-password.html`), which every other page
    redirects to until they do. It must differ from the starting one.
- **Can't get in?** On Edit, **Give a new starting password** generates one; again they must
  choose their own at the next sign-in. (Leave it on "Not changing" to keep their password.)
- **Forgot password?** on the login page: enter the email; if the account's email is
  **confirmed**, a **Reset your password** link is emailed (works 60 minutes, once). The answer
  on screen is the same whether or not the email exists, so it can't be used to find accounts.
  At most 3 requests per email and 10 per computer every 15 minutes.
- **Email changed** (by the registrar, or the University Registrar in My account): it's no
  longer confirmed and a new confirmation link is sent. Old links stop working.
- Account management shows **Email confirmed / Email not confirmed** under each email, with
  **Send confirmation email**, and **Hasn't chosen a password yet** for starting passwords.
- Everything is in the Transaction log (confirmation sent, email confirmed, chose password,
  reset by email).

## Files

- `app/Support/AccountEmail.php`: tokens (only a SHA-256 of each link is stored, in
  `account_email_tokens`, with the address it was sent to), sending, the new columns
  `users.email_verified_at`, `users.must_change_password` and `users.password_pending` (invited, no password yet), added automatically on first use.
- `resources/views/emails/account.blade.php`: the email.
- `app/Http/Controllers/AuthController.php`: `auth.php` actions `forgot`, `check_link`,
  `reset_password`, `accept_invite`, `confirm_email`, `set_first_password`; `forgot` re-sends a pending invite; `change_password` also clears the
  "must change" flag. `RegistrarSession::user()` returns `mustChangePassword` and `emailConfirmed`.
- `legacy-api/users.php`: create/update send the confirmation (after the commit) and set the
  flags; new action `send_confirmation`; accounts list has `emailConfirmed`, `mustChangePassword`.
- Pages: `confirm-email.html`, `setup-account.html`, `reset-password.html`, `new-password.html`
  (`partials/auth-page.blade.php`, `resources/js/legacy/account-links.js`), the login page's
  Forgot form, `session-boot.js` (the redirect), `staff-accounts.js` (Generate, email status).
- `partials/auth-styles.blade.php`: the login page's styles, shared.
- Test: `tests/Feature/AccountEmailTest.php`.
- Also fixed: `legacy-api/bootstrap.php` re-created the 5 demo accounts (with their known
  passwords) whenever their email was missing, so changing an email brought them back. Now they
  are only created on a fresh install with no accounts.

## Making it really send (Gmail App Password)

Until this is done, emails are only written to `storage/logs/laravel.log` (`MAIL_MAILER=log`).
You do these steps yourself; never paste the App Password into chat or commit it.

1. Google Account → **Security** → turn on **2-Step Verification** (App Passwords need it).
2. Open <https://myaccount.google.com/apppasswords>, name it `MCU-NEX`, **Create**, and copy the
   16 letters (spaces don't matter).
3. In `.env` (your PC: the project folder; the server: `/var/www/mcu-nex/.env`, step 9 in
   `docs/deploy-azure.md`) set:

   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=mcunexmail@gmail.com
   MAIL_PASSWORD=the16letterapppassword
   MAIL_FROM_ADDRESS=mcunexmail@gmail.com
   MAIL_FROM_NAME="MCU-NEX"
   ```

4. Apply it: on your PC `php artisan config:clear`; on the server
   `cd /var/www/mcu-nex && php artisan config:cache`.
5. Test: Account management → **Send confirmation email** on an account → the email arrives.

The system has its own Gmail, **mcunexmail@gmail.com** (made 2026-10-17), so nobody's personal inbox is
involved; the test accounts use it with +labels (`mcunexmail+dean.cas@gmail.com` …), which all land in
its inbox. At handover the school takes over that Gmail (changes its password and recovery phone)
or swaps in its own address.

Gmail allows about 500 emails a day, plenty for this. Emails come from your Gmail address;
when MCU gives an official address, change `MAIL_USERNAME` / `MAIL_FROM_ADDRESS` (and its App
Password) and nothing else.
