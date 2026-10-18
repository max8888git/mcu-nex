<?php

namespace Tests\Feature;

use App\Support\RegistrarSession;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Account emails (App\Support\AccountEmail, AuthController, users.php): a new account gets a
 * starting password and a confirmation link; the first sign-in asks for a new password; a
 * confirmed email can reset a forgotten password. On a copy of the real database
 * (mcu_nex_mail_test); mail goes to the "array" mailer (phpunit.xml).
 */
class AccountEmailTest extends TestCase
{
    private const TEST_DB = 'mcu_nex_mail_test';

    private int $registrar = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $bin = getenv('MYSQL_BIN') ?: 'C:\\xampp\\mysql\\bin';
        $mysql = $bin.DIRECTORY_SEPARATOR.'mysql.exe';
        $dump = $bin.DIRECTORY_SEPARATOR.'mysqldump.exe';
        if (! is_file($mysql) || ! is_file($dump)) {
            $this->markTestSkipped('MySQL command-line tools not found (set MYSQL_BIN).');
        }
        $cfg = config('database.connections.mysql');
        $auth = '-u '.escapeshellarg($cfg['username']).($cfg['password'] !== '' && $cfg['password'] !== null ? ' -p'.escapeshellarg($cfg['password']) : '');
        $file = storage_path('framework/testing-mail-clone.sql');
        @mkdir(dirname($file), 0775, true);
        exec('"'.$dump.'" '.$auth.' --routines --triggers --single-transaction --result-file='.escapeshellarg($file).' mcu_nex 2>&1', $o1, $c1);
        exec('"'.$mysql.'" '.$auth.' -e "DROP DATABASE IF EXISTS '.self::TEST_DB.'; CREATE DATABASE '.self::TEST_DB.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>&1', $o2, $c2);
        exec('"'.$mysql.'" '.$auth.' '.self::TEST_DB.' -e '.escapeshellarg('source '.str_replace('\\', '/', $file)).' 2>&1', $o3, $c3);
        @unlink($file);
        if ($c1 !== 0 || $c2 !== 0 || $c3 !== 0) {
            $this->markTestSkipped('Could not copy the database.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => self::TEST_DB, 'mail.default' => 'array']);
        DB::purge('mysql');
        \App\Support\AccountEmail::forgetSchemaCheck();
        $this->registrar = (int) DB::table('users')->where('role', 'super_admin')->where('is_active', 1)->value('user_id');
    }

    protected function tearDown(): void
    {
        RegistrarSession::logout();
        parent::tearDown();
    }

    private function accounts(array $body)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];

        return $this->postJson('/api/users.php', $body);
    }

    private function auth(array $body)
    {
        return $this->postJson('/api/auth.php', $body);
    }

    /** The token in the last link of that kind that was mailed. */
    private function lastToken(string $page): string
    {
        $messages = app('mailer')->getSymfonyTransport()->messages();
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (preg_match('#'.preg_quote($page, '#').'\?token=([A-Za-z0-9]+)#', $messages[$i]->getOriginalMessage()->getHtmlBody(), $m)) {
                return $m[1];
            }
        }
        $this->fail('No '.$page.' link was mailed.');
    }

    public function test_new_account_is_invited_and_chooses_its_own_password(): void
    {
        RegistrarSession::login($this->registrar);
        $email = 'mcunexmail+test.invite@gmail.com';
        $created = $this->accounts(['action' => 'create', 'name' => 'Invite Test Dean', 'email' => $email, 'role' => 'dean',
            'collegeId' => 'c-'.DB::table('colleges')->where('is_active', 1)->value('college_id')])->assertOk();
        $this->assertTrue($created->json('mail.sent'));
        $this->assertTrue($created->json('user.passwordPending'));
        $user = DB::table('users')->where('email', $email)->first();
        $this->assertSame(1, (int) $user->password_pending);
        $this->assertNull($user->email_verified_at);
        RegistrarSession::logout();

        // lost the invite: Forgot password sends it again (the old link stops working)
        $first = $this->lastToken('setup-account.html');
        $this->auth(['action' => 'forgot', 'email' => $email])->assertOk();
        $token = $this->lastToken('setup-account.html');
        $this->assertNotSame($first, $token);
        $this->assertFalse($this->auth(['action' => 'check_link', 'purpose' => 'invite', 'token' => $first])->json('valid'));

        // setting it up chooses the password and confirms the email; the link works once
        $this->auth(['action' => 'accept_invite', 'token' => $token, 'newPassword' => 'mine-123', 'newPasswordConfirm' => 'mine-123'])->assertOk();
        $this->auth(['action' => 'accept_invite', 'token' => $token, 'newPassword' => 'other-456', 'newPasswordConfirm' => 'other-456'])->assertStatus(400);
        $user = DB::table('users')->where('email', $email)->first();
        $this->assertSame(0, (int) $user->password_pending);
        $this->assertNotNull($user->email_verified_at);
        $login = $this->auth(['action' => 'login', 'email' => $email, 'password' => 'mine-123'])->assertOk();
        $this->assertFalse($login->json('user.mustChangePassword'));
    }

    public function test_new_account_confirms_its_email_sets_its_password_and_can_reset_it(): void
    {
        $before = DB::table('users')->count();
        RegistrarSession::login($this->registrar);
        $email = 'mcunexmail+test.mail@gmail.com';
        $created = $this->accounts(['action' => 'create', 'name' => 'Mail Test Dean', 'email' => $email, 'role' => 'dean',
            'collegeId' => 'c-'.DB::table('colleges')->where('is_active', 1)->value('college_id'), 'password' => 'mcu4321', 'passwordConfirm' => 'mcu4321'])->assertOk();
        $this->assertTrue($created->json("mail.sent"), (string) $created->json("mail.message"));
        $user = DB::table('users')->where('email', $email)->first();
        $this->assertNull($user->email_verified_at);
        $this->assertSame(1, (int) $user->must_change_password);

        // not confirmed yet: Forgot password sends nothing
        RegistrarSession::logout();
        $count = count(app('mailer')->getSymfonyTransport()->messages());
        $this->auth(['action' => 'forgot', 'email' => $email])->assertOk();
        $this->assertCount($count, app('mailer')->getSymfonyTransport()->messages());

        // the confirmation link works once
        $token = $this->lastToken('confirm-email.html');
        $this->auth(['action' => 'confirm_email', 'token' => $token])->assertOk();
        $this->assertNotNull(DB::table('users')->where('email', $email)->value('email_verified_at'));
        $this->auth(['action' => 'confirm_email', 'token' => $token])->assertStatus(400);

        // first sign-in with the starting password: must choose a different one
        $this->assertTrue($this->auth(['action' => 'login', 'email' => $email, 'password' => 'mcu4321'])->assertOk()->json('user.mustChangePassword'));
        $this->auth(['action' => 'set_first_password', 'newPassword' => 'mcu4321', 'newPasswordConfirm' => 'mcu4321'])->assertStatus(400);
        $this->assertFalse($this->auth(['action' => 'set_first_password', 'newPassword' => 'mine-123', 'newPasswordConfirm' => 'mine-123'])->assertOk()->json('user.mustChangePassword'));

        // forgot password: a reset link, single use, then the new password works
        $this->auth(['action' => 'logout']);
        $this->auth(['action' => 'forgot', 'email' => $email])->assertOk();
        $reset = $this->lastToken('reset-password.html');
        $this->assertTrue($this->auth(['action' => 'check_link', 'purpose' => 'reset', 'token' => $reset])->json('valid'));
        $this->auth(['action' => 'reset_password', 'token' => $reset, 'newPassword' => 'again-456', 'newPasswordConfirm' => 'again-456'])->assertOk();
        $this->auth(['action' => 'reset_password', 'token' => $reset, 'newPassword' => 'third-789', 'newPasswordConfirm' => 'third-789'])->assertStatus(400);
        $this->auth(['action' => 'login', 'email' => $email, 'password' => 'again-456'])->assertOk();

        // changing the email needs a new confirmation (and the old link would no longer match)
        $this->auth(['action' => 'logout']);
        RegistrarSession::login($this->registrar);
        $id = 'u-'.DB::table('users')->where('email', $email)->value('user_id');
        $registrarPassword = 'preview-check';
        DB::table('users')->where('user_id', $this->registrar)->update(['password_hash' => password_hash($registrarPassword, PASSWORD_DEFAULT)]);
        $this->accounts(['action' => 'update', 'id' => $id, 'name' => 'Mail Test Dean', 'email' => 'mcunexmail+test.mail2@gmail.com', 'role' => 'dean',
            'collegeId' => 'c-'.DB::table('users')->where('user_id', (int) substr($id, 2))->value('college_id'), 'isActive' => true, 'confirmPassword' => $registrarPassword])->assertOk();
        $this->assertNull(DB::table('users')->where('user_id', (int) substr($id, 2))->value('email_verified_at'));

        // changing emails never brings back the old demo accounts
        $this->assertSame($before + 1, DB::table('users')->count());
    }
}
