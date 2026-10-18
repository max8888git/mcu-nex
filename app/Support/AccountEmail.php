<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Account emails: confirm an address, and reset a forgotten password.
 *
 * A link carries a random token; only its SHA-256 is stored (account_email_tokens), with the
 * address it was sent to, so a link stops working once the account's email changes. Links are
 * single-use. Mail goes out through Laravel's mailer (MAIL_* in .env: "log" writes it to
 * storage/logs/laravel.log, "smtp" sends it, e.g. through Gmail with an App Password).
 */
class AccountEmail
{
    public const CONFIRM_DAYS = 7;

    public const RESET_MINUTES = 60;

    /**
     * users.email_verified_at, users.must_change_password, users.password_pending (invited, no
     * password chosen yet) and the token table (added on first use).
     */
    /** Databases already checked this request (tests switch between copies). */
    private static array $checked = [];

    /** Tests that drop and re-create a database copy check it again. */
    public static function forgetSchemaCheck(): void
    {
        self::$checked = [];
    }

    public static function ensureSchema(): void
    {
        $db = (string) DB::connection()->getDatabaseName();
        if (isset(self::$checked[$db]) || DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('users')) {
            return;
        }
        if (! Schema::hasColumn('users', 'email_verified_at')) {
            DB::statement('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL');
        }
        if (! Schema::hasColumn('users', 'must_change_password')) {
            DB::statement('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (! Schema::hasColumn('users', 'password_pending')) {
            DB::statement('ALTER TABLE users ADD COLUMN password_pending TINYINT(1) NOT NULL DEFAULT 0');
        }
        DB::statement(
            'CREATE TABLE IF NOT EXISTS account_email_tokens (
              token_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
              user_id INT NOT NULL,
              purpose VARCHAR(16) NOT NULL,
              email VARCHAR(255) NOT NULL,
              token_hash CHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              used_at DATETIME NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_account_email_token (token_hash),
              KEY idx_account_email_user (user_id, purpose)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$checked[$db] = true;
    }

    /** Email a "confirm your email" link. Throws if the mail can't be sent. */
    public static function sendConfirmation(int $userId, string $email, string $name): void
    {
        self::ensureSchema();
        $token = self::issue($userId, 'confirm', $email, self::CONFIRM_DAYS * 24 * 60);
        self::send($email, 'Confirm your email for MCU-NEX', 'Confirm your email',
            'Hi '.$name.', please confirm that this is your email address for your MCU-NEX account. Once it’s confirmed, you can reset your password by email if you ever forget it.',
            'Confirm my email', self::link('confirm-email.html?token='.$token),
            'This link works for '.self::CONFIRM_DAYS.' days. If you weren’t expecting this, you can ignore it.');
    }

    /**
     * Email a new account's invite: "Set up your account". Choosing a password from it also
     * confirms the email, so nobody else ever knows the password. Throws if the mail can't be sent.
     */
    public static function sendInvite(int $userId, string $email, string $name, string $roleText): void
    {
        self::ensureSchema();
        $token = self::issue($userId, 'invite', $email, self::CONFIRM_DAYS * 24 * 60);
        self::send($email, 'Set up your MCU-NEX account', 'Your MCU-NEX account is ready',
            'Hi '.$name.', an MCU-NEX account was made for you'.($roleText !== '' ? ' ('.$roleText.')' : '').'. Choose your password with the button below; after that you log in with this email and that password.',
            'Set up my account', self::link('setup-account.html?token='.$token),
            'This link works for '.self::CONFIRM_DAYS.' days and only once. If it expires, ask the University Registrar or Records Officer to send a new one, or use “Forgot password?” on the login page.');
    }

    /** Email a "reset your password" link. Throws if the mail can't be sent. */
    public static function sendReset(int $userId, string $email, string $name): void
    {
        self::ensureSchema();
        $token = self::issue($userId, 'reset', $email, self::RESET_MINUTES);
        self::send($email, 'Reset your MCU-NEX password', 'Reset your password',
            'Hi '.$name.', someone (hopefully you) asked to reset the password for your MCU-NEX account. Choose a new password with the button below.',
            'Choose a new password', self::link('reset-password.html?token='.$token),
            'This link works for '.self::RESET_MINUTES.' minutes and only once. If you didn’t ask for this, ignore this email; your password stays the same.');
    }

    /**
     * The account a link is for, if the link is still good: right purpose, not used, not expired,
     * and the account is active and still has the address it was sent to.
     */
    public static function find(string $raw, string $purpose): ?object
    {
        self::ensureSchema();
        $raw = trim($raw);
        if ($raw === '' || ! preg_match('/^[A-Za-z0-9]{20,100}$/', $raw)) {
            return null;
        }
        $row = DB::table('account_email_tokens as t')
            ->join('users as u', 'u.user_id', '=', 't.user_id')
            ->where('t.token_hash', hash('sha256', $raw))
            ->where('t.purpose', $purpose)
            ->whereNull('t.used_at')
            ->where('t.expires_at', '>', now())
            ->where('u.is_active', 1)
            ->first(['t.token_id', 't.email as sent_to', 'u.user_id', 'u.full_name', 'u.email', 'u.role']);
        if (! $row || strcasecmp((string) $row->sent_to, (string) $row->email) !== 0) {
            return null;
        }

        return $row;
    }

    /** Use up a link, and every other open link of that kind for the account. */
    public static function useUp(object $found, string $purpose): void
    {
        DB::table('account_email_tokens')
            ->where('user_id', $found->user_id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    public static function markConfirmed(int $userId): void
    {
        DB::table('users')->where('user_id', $userId)->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    private static function issue(int $userId, string $purpose, string $email, int $minutes): string
    {
        // a new link replaces the older open ones
        DB::table('account_email_tokens')->where('user_id', $userId)->where('purpose', $purpose)->whereNull('used_at')
            ->update(['used_at' => now()]);
        $raw = bin2hex(random_bytes(24));
        DB::table('account_email_tokens')->insert([
            'user_id' => $userId,
            'purpose' => $purpose,
            'email' => strtolower($email),
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return $raw;
    }

    /** A full link to a page on this site (the address the request came in on, else APP_URL). */
    private static function link(string $path): string
    {
        $base = '';
        try {
            $request = request();
            if ($request && $request->getHost() !== '') {
                $base = $request->getSchemeAndHttpHost().$request->getBasePath();
            }
        } catch (\Throwable $e) {
            $base = '';
        }
        if ($base === '') {
            $base = (string) config('app.url');
        }

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private static function send(string $to, string $subject, string $heading, string $text, string $button, string $url, string $foot): void
    {
        $html = view('emails.account', compact('heading', 'text', 'button', 'url', 'foot'))->render();
        Mail::html($html, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }
}
