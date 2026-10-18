<?php

namespace App\Http\Controllers;

use App\Models\TransactionLog;
use App\Support\AccountEmail;
use App\Models\User;
use App\Support\ProfilePhoto;
use App\Support\RegistrarSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function handle(Request $request)
    {
        if ($request->isMethod('GET')) {
            return response()->json(['ok' => true, 'user' => RegistrarSession::user()]);
        }

        $action = (string) $request->input('action', 'login');
        if ($action === 'logout') {
            RegistrarSession::logout();

            return response()->json(['ok' => true, 'user' => null]);
        }
        if ($action === 'lookup') {
            return response()->json([
                'ok' => true,
                'found' => $this->emailHasActiveAccount((string) $request->input('email', '')),
            ]);
        }
        if ($action === 'change_password') {
            return $this->changePassword($request);
        }
        // account emails (AccountEmail): forgot password, the links, and the first sign-in
        if ($action === 'forgot') {
            return $this->forgot($request);
        }
        if ($action === 'check_link') {
            return $this->checkLink($request);
        }
        if ($action === 'reset_password') {
            return $this->resetPassword($request);
        }
        if ($action === 'accept_invite') {
            return $this->acceptInvite($request);
        }
        if ($action === 'confirm_email') {
            return $this->confirmEmail($request);
        }
        if ($action === 'set_first_password') {
            return $this->setFirstPassword($request);
        }
        if ($action === 'set_photo' || $action === 'remove_photo') {
            return $this->updatePhoto($request, $action === 'remove_photo');
        }
        if ($action !== 'login') {
            return response()->json(['ok' => false, 'error' => 'Unknown auth action'], 400);
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        if ($email === '' || $password === '') {
            return response()->json(['ok' => false, 'error' => 'Enter your email and password.'], 400);
        }

        $account = User::query()->where('email', $email)->first();
        if (! $account || ! password_verify($password, (string) $account->password_hash)) {
            return response()->json(['ok' => false, 'error' => 'Invalid email or password.'], 401);
        }
        if (! $account->is_active) {
            return response()->json(['ok' => false, 'error' => 'This account is inactive. Ask the University Registrar to turn it back on.'], 403);
        }

        RegistrarSession::login((int) $account->user_id);
        $user = RegistrarSession::user();
        if (! $user) {
            return response()->json(['ok' => false, 'error' => 'Could not start a session for this account.'], 500);
        }

        $this->recordLogin();

        return response()->json(['ok' => true, 'user' => $user]);
    }

    /**
     * Any signed-in user changes their own password (current password required).
     * Same 5-character minimum as accounts created on Account management.
     */
    private function changePassword(Request $request)
    {
        $id = RegistrarSession::id();
        $account = $id ? User::query()->find($id) : null;
        if (! $account || ! $account->is_active) {
            return response()->json(['ok' => false, 'error' => 'Log in again, then change your password.'], 401);
        }

        $current = (string) $request->input('currentPassword', '');
        $new = (string) $request->input('newPassword', '');
        $confirm = (string) $request->input('newPasswordConfirm', '');
        if ($current === '' || ! password_verify($current, (string) $account->password_hash)) {
            return response()->json(['ok' => false, 'error' => 'Your current password is incorrect.'], 400);
        }
        if (strlen($new) < 5) {
            return response()->json(['ok' => false, 'error' => 'Use a new password of at least 5 characters.'], 400);
        }
        if ($new !== $confirm) {
            return response()->json(['ok' => false, 'error' => 'The new passwords do not match.'], 400);
        }
        if (password_verify($new, (string) $account->password_hash)) {
            return response()->json(['ok' => false, 'error' => 'Choose a password different from your current one.'], 400);
        }

        AccountEmail::ensureSchema();
        DB::table('users')->where('user_id', $id)->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0]);
        $this->record('Account', 'Changed their password.');

        return response()->json(['ok' => true]);
    }

    /**
     * Forgot password: email a reset link if the address belongs to an active account whose email
     * is confirmed. The answer is the same either way, so this can't be used to find accounts.
     */
    private function forgot(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'error' => 'Enter the email you log in with.'], 400);
        }
        $keys = ['forgot-ip:'.$request->ip() => 10, 'forgot-email:'.$email => 3];
        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return response()->json(['ok' => false, 'error' => 'Too many requests. Try again in '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' minutes.'], 429);
            }
        }
        foreach ($keys as $key => $max) {
            RateLimiter::hit($key, 15 * 60);
        }
        AccountEmail::ensureSchema();
        $account = User::query()->where('email', $email)->where('is_active', 1)->first();
        try {
            if ($account && (int) $account->password_pending) {
                // invited but never set up (lost the link): send the invite again
                AccountEmail::sendInvite((int) $account->user_id, (string) $account->email, (string) $account->full_name, RegistrarSession::roleLabel((string) $account->role));
            } elseif ($account && $account->email_verified_at) {
                AccountEmail::sendReset((int) $account->user_id, (string) $account->email, (string) $account->full_name);
            }
        } catch (\Throwable $e) {
            Log::warning('Password email failed: '.$e->getMessage());
        }

        return response()->json(['ok' => true, 'message' => 'If that email belongs to an account, a link is on its way. Check your inbox (and Spam) in a few minutes. Nothing after that? Ask the University Registrar.']);
    }

    /** Is a confirm / reset link still good? (the pages ask before showing the form) */
    private function checkLink(Request $request)
    {
        $purpose = in_array((string) $request->input('purpose'), ['reset', 'invite'], true) ? (string) $request->input('purpose') : 'confirm';
        $found = AccountEmail::find((string) $request->input('token', ''), $purpose);

        return response()->json(['ok' => true, 'valid' => (bool) $found, 'name' => $found ? (string) $found->full_name : null]);
    }

    /** Set a new password from a reset link (this also proves the email works). */
    private function resetPassword(Request $request)
    {
        $found = AccountEmail::find((string) $request->input('token', ''), 'reset');
        if (! $found) {
            return response()->json(['ok' => false, 'error' => 'This link has expired or was already used. Ask for a new one from the login page.'], 400);
        }
        $new = (string) $request->input('newPassword', '');
        if (($bad = $this->passwordProblem($new, (string) $request->input('newPasswordConfirm', ''))) !== null) {
            return response()->json(['ok' => false, 'error' => $bad], 400);
        }
        DB::table('users')->where('user_id', $found->user_id)->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0]);
        AccountEmail::useUp($found, 'reset');
        AccountEmail::markConfirmed((int) $found->user_id);
        $this->recordFor((int) $found->user_id, 'Account', 'Reset their password with an email link.');

        return response()->json(['ok' => true]);
    }

    /** Set up an invited account: choose the password (this also confirms the email). */
    private function acceptInvite(Request $request)
    {
        $found = AccountEmail::find((string) $request->input('token', ''), 'invite');
        if (! $found) {
            return response()->json(['ok' => false, 'error' => 'This link has expired or was already used. Use “Forgot password?” on the login page to get a new one, or ask the University Registrar.'], 400);
        }
        $new = (string) $request->input('newPassword', '');
        if (($bad = $this->passwordProblem($new, (string) $request->input('newPasswordConfirm', ''))) !== null) {
            return response()->json(['ok' => false, 'error' => $bad], 400);
        }
        DB::table('users')->where('user_id', $found->user_id)->update([
            'password_hash' => password_hash($new, PASSWORD_DEFAULT), 'password_pending' => 0, 'must_change_password' => 0,
        ]);
        AccountEmail::useUp($found, 'invite');
        AccountEmail::useUp($found, 'confirm');
        AccountEmail::markConfirmed((int) $found->user_id);
        $this->recordFor((int) $found->user_id, 'Account', 'Set up their account from the invite email.');

        return response()->json(['ok' => true]);
    }

    /** Confirm an email address from the link. */
    private function confirmEmail(Request $request)
    {
        $found = AccountEmail::find((string) $request->input('token', ''), 'confirm');
        if (! $found) {
            return response()->json(['ok' => false, 'error' => 'This link has expired or was already used. Ask the University Registrar or Records Officer to send a new one.'], 400);
        }
        AccountEmail::useUp($found, 'confirm');
        AccountEmail::markConfirmed((int) $found->user_id);
        $this->recordFor((int) $found->user_id, 'Account', 'Confirmed their email ('.$found->email.').');

        return response()->json(['ok' => true, 'name' => (string) $found->full_name]);
    }

    /** First sign-in with the starting password the registrar gave: choose your own. */
    private function setFirstPassword(Request $request)
    {
        AccountEmail::ensureSchema();
        $id = RegistrarSession::id();
        $account = $id ? User::query()->find($id) : null;
        if (! $account || ! $account->is_active) {
            return response()->json(['ok' => false, 'error' => 'Log in again, then choose your password.'], 401);
        }
        if (! (int) $account->must_change_password) {
            return response()->json(['ok' => false, 'error' => 'Your password is already set. Change it in My account.'], 400);
        }
        $new = (string) $request->input('newPassword', '');
        if (($bad = $this->passwordProblem($new, (string) $request->input('newPasswordConfirm', ''))) !== null) {
            return response()->json(['ok' => false, 'error' => $bad], 400);
        }
        if (password_verify($new, (string) $account->password_hash)) {
            return response()->json(['ok' => false, 'error' => 'Choose a password different from the starting one.'], 400);
        }
        DB::table('users')->where('user_id', $id)->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0]);
        $this->record('Account', 'Chose their own password on first sign-in.');

        return response()->json(['ok' => true, 'user' => RegistrarSession::user()]);
    }

    /** Same rule as everywhere else (5 characters for now; the security round may raise it). */
    private function passwordProblem(string $new, string $confirm): ?string
    {
        if (strlen($new) < 5) {
            return 'Use a password of at least 5 characters.';
        }
        if ($new !== $confirm) {
            return 'The passwords do not match.';
        }

        return null;
    }

    /** Any signed-in user sets or removes their own profile photo. */
    private function updatePhoto(Request $request, bool $remove)
    {
        $id = RegistrarSession::id();
        if (! $id || ! RegistrarSession::user()) {
            return response()->json(['ok' => false, 'error' => 'Log in again, then update your photo.'], 401);
        }
        if ($remove) {
            ProfilePhoto::delete($id);
        } else {
            try {
                ProfilePhoto::store($id, (string) $request->input('photo', ''));
            } catch (\InvalidArgumentException $e) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 400);
            }
        }

        return response()->json(['ok' => true, 'user' => RegistrarSession::user()]);
    }

    private function emailHasActiveAccount(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return User::query()->where('email', $email)->where('is_active', 1)->exists();
    }

    private function recordLogin(): void
    {
        $this->record('Login', 'Signed in.', 'login');
    }

    /** Add a Transaction log entry for the signed-in user. */
    private function record(string $action, string $summary, string $area = 'account'): void
    {
        $this->recordFor(RegistrarSession::id(), $action, $summary, $area);
    }

    /** Add a Transaction log entry for an account (links are used without signing in). */
    private function recordFor(?int $id, string $action, string $summary, string $area = 'account'): void
    {
        $account = $id ? User::query()->find($id) : null;
        $name = trim((string) ($account->full_name ?? ''));
        TransactionLog::query()->create([
            'user_id' => $id,
            'actor_name' => $name !== '' ? $name : 'Unknown',
            'actor_role' => trim((string) ($account->role ?? '')) !== '' ? (string) $account->role : 'unknown',
            'action' => $action,
            'area' => $area,
            'summary' => $summary,
        ]);
    }
}
