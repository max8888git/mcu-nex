<?php

namespace Tests\Feature;

use App\Support\RegistrarSession;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Notes on a class from the Dean/Program Chair dashboard (requests.php): a Program Chair's
 * note goes to their Dean, who forwards it to the Registrar's office or answers it.
 * Runs on a fresh copy of the real database (mcu_nex_notes_test). Needs the XAMPP mysql tools.
 */
class ClassNotesTest extends TestCase
{
    private const TEST_DB = 'mcu_nex_notes_test';

    private int $chair = 0;

    private int $dean = 0;

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
        $file = storage_path('framework/testing-notes-clone.sql');
        @mkdir(dirname($file), 0775, true);
        exec('"'.$dump.'" '.$auth.' --routines --triggers --single-transaction --result-file='.escapeshellarg($file).' mcu_nex 2>&1', $o1, $c1);
        exec('"'.$mysql.'" '.$auth.' -e "DROP DATABASE IF EXISTS '.self::TEST_DB.'; CREATE DATABASE '.self::TEST_DB.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>&1', $o2, $c2);
        exec('"'.$mysql.'" '.$auth.' '.self::TEST_DB.' -e '.escapeshellarg('source '.str_replace('\\', '/', $file)).' 2>&1', $o3, $c3);
        @unlink($file);
        if ($c1 !== 0 || $c2 !== 0 || $c3 !== 0) {
            $this->markTestSkipped('Could not copy the database.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => self::TEST_DB]);
        DB::purge('mysql');
        // a Program Chair and the Dean of the same college
        $chair = DB::table('users')->where('role', 'program_chair')->where('is_active', 1)->orderBy('user_id')->first(['user_id', 'college_id']);
        $dean = $chair ? DB::table('users')->where('role', 'dean')->where('is_active', 1)->where('college_id', $chair->college_id)->value('user_id') : null;
        if (! $chair || ! $dean) {
            $this->markTestSkipped('Needs a Program Chair and a Dean in the same college.');
        }
        $this->chair = (int) $chair->user_id;
        $this->dean = (int) $dean;
    }

    protected function tearDown(): void
    {
        RegistrarSession::logout();
        parent::tearDown();
    }

    private function as(int $userId): void
    {
        RegistrarSession::logout();
        RegistrarSession::login($userId);
    }

    private function list()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        return $this->getJson('/api/requests.php');
    }

    private function send(array $body)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];

        return $this->postJson('/api/requests.php', $body);
    }

    private function find(array $list, int $id): ?array
    {
        foreach ($list as $q) {
            if ((int) $q['id'] === $id) {
                return $q;
            }
        }

        return null;
    }

    public function test_a_program_chair_note_goes_to_the_dean_who_forwards_or_answers_it(): void
    {
        $this->as($this->chair);
        $classes = $this->list()->assertOk()->json('classes');
        if (count($classes) < 2) {
            $this->markTestSkipped('Needs an approved timetable with classes in the Program Chair\'s programs.');
        }

        // the chair sends two notes; both wait for the Dean
        $first = $this->send(['action' => 'create', 'topics' => ['time'], 'classIds' => [$classes[0]['id']], 'body' => 'Could this move to Thursday?'])->assertOk();
        $second = $this->send(['action' => 'create', 'topics' => ['room'], 'classIds' => [$classes[1]['id']], 'body' => 'This room is too small.'])->assertOk();
        $ids = array_map(fn ($q) => (int) $q['id'], array_filter($this->list()->json('textRequests'), fn ($q) => in_array($q['body'], ['Could this move to Thursday?', 'This room is too small.'], true)));
        $this->assertCount(2, $ids);
        rsort($ids);
        [$roomNote, $timeNote] = $ids;

        $mine = $this->find($this->list()->json('textRequests'), $timeNote);
        $this->assertSame('pending_dean', $mine['status']);
        $this->assertSame($classes[0]['id'], $mine['classes'][0]['id']);
        $this->assertFalse($mine['canForward']);

        // the chair can't forward their own note
        $this->send(['action' => 'forward', 'id' => $timeNote])->assertStatus(400);

        // the Dean sees both, forwards one and answers the other
        $this->as($this->dean);
        $seen = $this->list()->json('textRequests');
        $this->assertTrue($this->find($seen, $timeNote)['canForward']);
        $this->send(['action' => 'forward', 'id' => $timeNote, 'note' => 'Agreed.'])->assertOk();
        $this->send(['action' => 'hold', 'id' => $roomNote, 'note' => 'It fits 40; your section has 35.'])->assertOk();

        $this->assertSame('pending_admin', DB::table('text_schedule_requests')->where('request_id', $timeNote)->value('status'));
        $this->assertSame('held', DB::table('text_schedule_requests')->where('request_id', $roomNote)->value('status'));

        // the chair sees where each one went and the Dean's answer
        $this->as($this->chair);
        $back = $this->list()->json('textRequests');
        $this->assertSame('Agreed.', $this->find($back, $timeNote)['forwardNote']);
        $this->assertSame('It fits 40; your section has 35.', $this->find($back, $roomNote)['holdNote']);

        // a note on a class outside the chair's programs is refused
        $this->send(['action' => 'create', 'topics' => ['time'], 'classIds' => ['off-not-a-class'], 'body' => 'x'])->assertStatus(400);
    }
}
