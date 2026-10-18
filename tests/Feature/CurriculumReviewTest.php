<?php

namespace Tests\Feature;

use App\Support\RegistrarSession;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Curriculums made by the colleges and checked by the registrars
 * (legacy-api/curriculum-reviews.php), on a fresh copy of the real database
 * (mcu_nex_curr_test) so real data is never touched. Needs the XAMPP mysql tools.
 */
class CurriculumReviewTest extends TestCase
{
    private const TEST_DB = 'mcu_nex_curr_test';

    private array $users = [];

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
        $file = storage_path('framework/testing-curr-clone.sql');
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
        // one account per role; the Dean is the CAS one (other colleges have Deans too)
        foreach (DB::table('users')->where('is_active', 1)->orderBy('user_id')->get(['user_id', 'role', 'college_id']) as $u) {
            if ($u->role === 'dean' && (int) $u->college_id !== 2) {
                continue;
            }
            $this->users[$u->role] ??= (int) $u->user_id;
        }
    }

    protected function tearDown(): void
    {
        RegistrarSession::logout();
        parent::tearDown();
    }

    private function as(string $role): void
    {
        RegistrarSession::logout();
        RegistrarSession::login($this->users[$role]);
    }

    private function fetch(string $query)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parse_str($query, $_GET);

        return $this->getJson('/api/curriculum-reviews.php?'.$query);
    }

    private function act(string $action, array $extra = [])
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];

        return $this->postJson('/api/curriculum-reviews.php', ['action' => $action] + $extra);
    }

    private function course(string $code, string $title, float $lec, float $lab, int $year, string $term, array $more = []): array
    {
        return ['code' => $code, 'title' => $title, 'lec' => $lec, 'lab' => $lab, 'year' => $year, 'term' => $term,
            'lecRoom' => $lec > 0 ? 'Lecture' : '', 'labRoom' => $lab > 0 ? 'Computer Laboratory' : ''] + $more;
    }

    public function test_curriculum_goes_from_program_chair_to_dean_to_registrar(): void
    {
        // the Program Chair of CSIT-EMC sees their programs only
        $this->as('program_chair');
        $mine = $this->fetch('mine=1')->assertOk()->json();
        $names = array_column($mine['programs'], 'name', 'id');
        $this->assertContains('Computer Science', $names);
        $this->assertNotContains('Pharmacy', $names);
        $this->assertContains('Computer Laboratory', $mine['roomTypes']);
        $cs = array_search('Computer Science', $names, true);

        // start a blank draft
        $draft = $this->act('create', ['programId' => $cs, 'academicYear' => '2027-2028', 'from' => 'blank'])->assertOk()->json('review');
        $id = $draft['id'];
        $this->assertSame('draft', $draft['status']);
        $this->assertSame('Draft 1', $draft['name']);
        // several drafts, each with a name; renaming needs a name
        $second = $this->act('create', ['programId' => $cs, 'academicYear' => '2027-2028', 'name' => '  With new   electives '])->assertOk()->json('review');
        $this->assertSame('With new electives', $second['name']);
        $this->act('rename', ['id' => $second['id'], 'name' => ' '])->assertStatus(400)->assertJsonPath('error', 'Give the draft a name.');
        $this->act('rename', ['id' => $second['id'], 'name' => 'Old prerequisites'])->assertOk()->assertJsonPath('review.name', 'Old prerequisites');
        $this->act('discard', ['id' => $second['id']])->assertOk();

        // checks: duplicate code, a separate "L" lab row, missing room type, prerequisite in a later term
        $bad = [
            $this->course('CCS 101', 'Fundamentals of Programming', 2, 1, 1, '1'),
            $this->course('CCS 101L', 'Fundamentals of Programming Lab', 0, 1, 1, '1'),
            $this->course('ccs  101', 'Duplicate', 3, 0, 1, '2'),
            ['code' => 'PCM 000', 'title' => 'Purposive Communication', 'lec' => 3, 'lab' => 0, 'year' => 1, 'term' => '1', 'lecRoom' => ''],
            $this->course('CCS 102', 'Computer Programming 2', 2, 1, 1, '1', ['prereqs' => ['CCS 201']]),
            $this->course('CCS 201', 'Data Structures', 2, 1, 2, '1'),
        ];
        $saved = $this->act('save', ['id' => $id, 'courses' => $bad])->assertOk()->json('review');
        $messages = implode(' | ', array_column($saved['problems'], 'message'));
        $this->assertStringContainsString('CCS 101 is listed twice', $messages);
        $this->assertStringContainsString('CCS 101L is the lab of CCS 101', $messages);
        $this->assertStringContainsString('PCM 000 has lecture units but no lecture room type', $messages);
        $this->assertStringContainsString('prerequisite CCS 201 must be in an earlier term', $messages);
        $this->act('send_to_dean', ['id' => $id])->assertStatus(400)->assertJsonPath('error', 'Fix the problems in the checklist before sending.');

        // fixed
        $good = [
            $this->course('CCS 101', 'Fundamentals of Programming', 2, 1, 1, '1'),
            $this->course('PCM 000', 'Purposive Communication', 3, 0, 1, '1'),
            $this->course('CCS 102', 'Computer Programming 2', 2, 1, 1, '2', ['prereqs' => ['CCS 101']]),
            $this->course('FRE 101', 'Free Elective', 3, 0, 2, '1', ['elective' => true]),
        ];
        $saved = $this->act('save', ['id' => $id, 'courses' => $good])->assertOk()->json('review');
        $this->assertSame([], array_values(array_filter($saved['problems'], fn ($p) => $p['level'] === 'error')));
        $this->act('send_to_dean', ['id' => $id])->assertOk()->assertJsonPath('review.status', 'pending_dean');
        $this->act('save', ['id' => $id, 'courses' => $good])->assertStatus(400);
        $this->assertSame(1, DB::table('user_notifications')->where('user_id', $this->users['dean'])->where('title', 'A curriculum is waiting for your approval')->count());

        // Dean: note required to send back
        $this->as('dean');
        $this->act('dean_decide', ['id' => $id, 'accept' => false])->assertStatus(400)->assertJsonPath('error', 'Say what the Program Chair should fix.');
        $this->act('dean_decide', ['id' => $id, 'accept' => false, 'note' => 'Add the NSTP subject.'])->assertOk()
            ->assertJsonPath('review.status', 'draft')->assertJsonPath('review.lastReturnFrom', 'dean');

        $this->as('program_chair');
        $this->act('send_to_dean', ['id' => $id])->assertOk();

        $this->as('dean');
        $this->act('dean_decide', ['id' => $id, 'accept' => true])->assertOk()->assertJsonPath('review.status', 'dean_approved');
        $this->act('send_to_registrar', ['ids' => [$id]])->assertOk()->assertJsonPath('reviews.0.status', 'pending_registrar');

        // only the registrars decide; the inbox shows it to both
        $this->as('program_chair');
        $this->act('registrar_decide', ['ids' => [$id], 'accept' => true])->assertStatus(400);
        $this->as('admin');
        $this->assertSame([$id], array_column($this->fetch('inbox=1')->assertOk()->json('pending'), 'id'));
        $this->as('super_admin');
        $this->assertSame([$id], array_column($this->fetch('inbox=1')->assertOk()->json('pending'), 'id'));

        // the Records Officer accepts: it becomes the active curriculum
        $this->as('admin');
        $this->act('registrar_decide', ['ids' => [$id], 'accept' => true])->assertOk()->assertJsonPath('reviews.0.status', 'accepted');
        $live = DB::table('curricula')->where('program_id', (int) substr($cs, 2))->where('academic_year', '2027-2028')->first();
        $this->assertNotNull($live);
        $this->assertSame('active', $live->status);
        $rows = DB::table('curriculum_courses')->where('curriculum_id', $live->curriculum_id)->get()->keyBy('course_id');
        $this->assertCount(4, $rows);
        $this->assertSame(1, (int) $rows['FRE 101']->is_elective);
        $this->assertSame('Computer Laboratory', $rows['CCS 101']->lab_room_type);

        // the other registrar can't decide it again
        $this->as('super_admin');
        $this->act('registrar_decide', ['ids' => [$id], 'accept' => true])->assertStatus(400);

        // a mistake found later: revision (note required) opens version 2; version 1 stays active until v2 is accepted
        $this->act('request_revision', ['id' => $id])->assertStatus(400)->assertJsonPath('error', 'Say what needs to change.');
        $v2 = $this->act('request_revision', ['id' => $id, 'note' => 'CCS 102 lab should be 2 units.'])->assertOk()->json('review');
        $this->assertSame(2, $v2['version']);
        $this->assertSame('draft', $v2['status']);
        $this->assertSame('CCS 102 lab should be 2 units.', $v2['lastReturnNote']);

        $this->as('dean');
        $fixed = $good;
        $fixed[2]['lab'] = 2;
        $this->act('save', ['id' => $v2['id'], 'courses' => $fixed])->assertOk();
        $this->act('send_to_registrar', ['ids' => [$v2['id']]])->assertOk();
        $this->as('super_admin');
        $this->act('registrar_decide', ['ids' => [$v2['id']], 'accept' => true])->assertOk();
        $this->assertSame('superseded', DB::table('curriculum_reviews')->where('review_id', (int) substr($id, 3))->value('status'));
        $this->assertEquals(2, DB::table('curriculum_courses')->where('curriculum_id', $live->curriculum_id)->where('course_id', 'CCS 102')->value('lab_units'));
    }

    public function test_dean_keeps_their_own_draft_beside_the_program_chairs_version(): void
    {
        $good = [
            $this->course('CCS 101', 'Fundamentals of Programming', 2, 1, 1, '1'),
            $this->course('CCS 102', 'Computer Programming 2', 2, 1, 1, '2', ['prereqs' => ['CCS 101']]),
        ];
        $this->as('program_chair');
        $cs = collect($this->fetch('mine=1')->json('programs'))->firstWhere('name', 'Computer Science')['id'];
        $chairs = $this->act('create', ['programId' => $cs, 'academicYear' => '2027-2028'])->assertOk()->json('review.id');
        $this->act('save', ['id' => $chairs, 'courses' => $good])->assertOk();
        $this->act('send_to_dean', ['id' => $chairs])->assertOk();

        // the Dean makes drafts of their own while the Program Chair's waits (as many as they like)
        $this->as('dean');
        $own = $this->act('create', ['programId' => $cs, 'academicYear' => '2027-2028', 'name' => 'Dean’s version'])->assertOk()->json('review');
        $this->assertTrue($own['deanDraft']);
        $this->act('create', ['programId' => $cs, 'academicYear' => '2027-2028'])->assertOk()->assertJsonPath('review.deanDraft', true);
        $this->act('save', ['id' => $own['id'], 'courses' => $good])->assertOk();
        // the Program Chair can't rename the Dean's draft
        $this->as('program_chair');
        $this->act('rename', ['id' => $own['id'], 'name' => 'Mine'])->assertStatus(400);
        $this->as('dean');
        // only one version goes to the Registrar at a time
        $this->act('send_to_registrar', ['ids' => [$own['id']]])->assertStatus(400)
            ->assertJsonPath('error', 'Version 1 is waiting for your approval. Only one version goes to the Registrar at a time, so finish that one first.');

        // the Program Chair doesn't see or open the Dean's draft
        $this->as('program_chair');
        $versions = collect($this->fetch('mine=1')->json('programs'))->firstWhere('id', $cs)['versions'];
        $this->assertSame([$chairs], array_column($versions, 'id'));
        $this->fetch('id='.$own['id'])->assertStatus(400);
        $this->act('save', ['id' => $own['id'], 'courses' => $good])->assertStatus(400);

        // approving the Program Chair's makes it the one going on; the Dean can still edit it after approving
        $this->as('dean');
        $this->act('dean_decide', ['id' => $chairs, 'accept' => true])->assertOk()->assertJsonPath('review.status', 'dean_approved');
        $edited = $good;
        $edited[] = $this->course('NST 001', 'National Service Training Program 1', 3, 0, 1, '1');
        $this->act('save', ['id' => $chairs, 'courses' => $edited])->assertOk()
            ->assertJsonPath('review.status', 'dean_approved')->assertJsonCount(3, 'review.courses');
        $this->act('send_to_registrar', ['ids' => [$chairs]])->assertOk();
        $this->assertSame('draft', DB::table('curriculum_reviews')->where('review_id', (int) substr($own['id'], 3))->value('status'), 'the Dean’s draft is kept');
    }

    public function test_send_back_with_notes_on_single_subjects_keeps_what_was_sent(): void
    {
        $good = [
            $this->course('CCS 101', 'Fundamentals of Programming', 2, 1, 1, '1'),
            $this->course('CCS 102', 'Computer Programming 2', 2, 1, 1, '2', ['prereqs' => ['CCS 101']]),
        ];
        $this->as('program_chair');
        $cs = collect($this->fetch('mine=1')->json('programs'))->firstWhere('name', 'Computer Science')['id'];
        $id = $this->act('create', ['programId' => $cs, 'academicYear' => '2027-2028'])->json('review.id');
        $this->act('save', ['id' => $id, 'courses' => $good])->assertOk();
        $this->act('send_to_dean', ['id' => $id])->assertOk();

        // notes on single subjects are enough (a code not in the version is dropped)
        $this->as('dean');
        $back = $this->act('dean_decide', ['id' => $id, 'accept' => false, 'rowNotes' => ['ccs 102' => 'Lab should be 2 units.', 'XYZ 999' => 'Not here.']])
            ->assertOk()->json('review');
        $this->assertSame('See the notes on 1 subject.', $back['lastReturnNote']);
        $this->assertSame(['CCS 102' => 'Lab should be 2 units.'], $back['returned']['notes']);
        $this->assertSame('dean', $back['returned']['from']);
        $this->assertCount(2, $back['returned']['courses']);

        // the Program Chair fixes it and sends it again; the Dean still has what was sent back to compare
        $this->as('program_chair');
        $fixed = $good;
        $fixed[1]['lab'] = 2;
        $this->act('save', ['id' => $id, 'courses' => $fixed])->assertOk();
        $this->act('send_to_dean', ['id' => $id])->assertOk();
        $this->as('dean');
        $again = $this->fetch('id='.$id)->assertOk()->json('review');
        $this->assertEquals(1, $again['returned']['courses'][1]['lab']);
        $this->assertEquals(2, $again['courses'][1]['lab']);
        // approving clears the Dean's send-back record
        $this->act('dean_decide', ['id' => $id, 'accept' => true])->assertOk()->assertJsonPath('review.returned', null);
    }

    public function test_finished_curriculums_list_and_detail_for_the_registrars_only(): void
    {
        $get = function (string $q) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            parse_str($q, $_GET);

            return $this->getJson('/api/curricula.php?'.$q);
        };
        $this->as('admin');
        $list = $get('finished=1')->assertOk()->json();
        $this->assertTrue($list['canArchive'], 'the Records Officer archives and restores; only the University Registrar deletes');
        $this->assertFalse($list['canDelete']);
        $this->assertArrayHasKey('archived', $list);
        $this->assertArrayHasKey('notInUse', $list);
        $this->as('super_admin');
        $asRegistrar = $get('finished=1')->assertOk()->json();
        $this->assertTrue($asRegistrar['canArchive']);
        $this->assertTrue($asRegistrar['canDelete']);
        $any = DB::table('curricula')->value('curriculum_id');
        $detail = $get('finished_id=cur-'.$any)->assertOk()->json();
        $this->assertSame('cur-'.$any, $detail['curriculum']['id']);
        $this->assertIsArray($detail['terms']);
        // nobody else
        $this->as('dean');
        $get('finished=1')->assertStatus(400);
    }

    public function test_registrar_send_back_needs_a_note_and_returns_it_to_the_college(): void
    {
        $this->as('dean');
        $programs = $this->fetch('mine=1')->assertOk()->json('programs');
        $cs = collect($programs)->firstWhere('name', 'Computer Science')['id'];
        $id = $this->act('create', ['programId' => $cs, 'academicYear' => '2027-2028'])->json('review.id');
        // a subject with no course code can't be saved; rows left blank are dropped
        $this->act('save', ['id' => $id, 'courses' => [['code' => '', 'title' => 'No code yet', 'lec' => 3, 'lab' => 0, 'year' => 1, 'term' => '1', 'lecRoom' => 'Lecture']]])
            ->assertStatus(400)->assertJsonPath('error', 'A subject has no course code (No code yet). Add the code before saving.');
        $blank = ['code' => '', 'title' => '', 'lec' => 3, 'lab' => 0, 'year' => 1, 'term' => '1', 'lecRoom' => 'Lecture'];
        $this->act('save', ['id' => $id, 'courses' => [$blank, $this->course('CCS 101', 'Fundamentals of Programming', 2, 1, 1, '1')]])
            ->assertOk()->assertJsonCount(1, 'review.courses');
        // year-standing prerequisites are allowed
        $this->act('save', ['id' => $id, 'courses' => [
            $this->course('CCS 101', 'Fundamentals of Programming', 2, 1, 1, '1'),
            $this->course('CCS 301', 'Software Engineering', 3, 0, 3, '1', ['prereqs' => ['CCS 101', '3RD YEAR STANDING']]),
        ]])->assertOk()->assertJsonPath('review.problems', []);
        $this->act('save', ['id' => $id, 'courses' => [$this->course('CCS 101', 'Fundamentals of Programming', 2, 1, 1, '1')]])->assertOk();
        // the Dean can send their own draft straight to the Registrar
        $this->act('send_to_registrar', ['ids' => [$id]])->assertOk();

        $this->as('super_admin');
        $this->act('registrar_decide', ['ids' => [$id], 'accept' => false])->assertStatus(400)->assertJsonPath('error', 'Say what the college should fix.');
        $this->act('registrar_decide', ['ids' => [$id], 'accept' => false, 'note' => 'Missing 2nd semester.'])->assertOk()
            ->assertJsonPath('reviews.0.status', 'draft')->assertJsonPath('reviews.0.lastReturnFrom', 'registrar');
        $this->assertSame(1, DB::table('user_notifications')->where('user_id', $this->users['dean'])->where('title', 'The Registrar sent your curriculum back')->count());
    }
}
