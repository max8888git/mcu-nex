<?php

namespace Tests\Feature;

use App\Support\RegistrarSession;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Per-college timetable approval (legacy-api/schedule-colleges.php), run
 * against a fresh copy of the real database (mcu_nex_sched_test) so the
 * real timetables are never touched. Needs MySQL and the XAMPP mysql tools.
 */
class CollegeApprovalTest extends TestCase
{
    private const SOURCE_DB = 'mcu_nex';

    private const TEST_DB = 'mcu_nex_sched_test';

    private const YEAR = '2026-2027';

    private const TERM = '1';

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
        $file = storage_path('framework/testing-sched-clone.sql');
        @mkdir(dirname($file), 0775, true);
        exec('"'.$dump.'" '.$auth.' --routines --triggers --single-transaction --result-file='.escapeshellarg($file).' '.self::SOURCE_DB.' 2>&1', $o1, $c1);
        if ($c1 !== 0) {
            $this->markTestSkipped('Could not copy the database: '.implode(' ', $o1));
        }
        exec('"'.$mysql.'" '.$auth.' -e "DROP DATABASE IF EXISTS '.self::TEST_DB.'; CREATE DATABASE '.self::TEST_DB.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>&1', $o2, $c2);
        exec('"'.$mysql.'" '.$auth.' '.self::TEST_DB.' -e '.escapeshellarg('source '.str_replace('\\', '/', $file)).' 2>&1', $o3, $c3);
        @unlink($file);
        if ($c2 !== 0 || $c3 !== 0) {
            $this->markTestSkipped('Could not load the database copy: '.implode(' ', array_merge($o2, $o3)));
        }

        config(['database.default' => 'mysql', 'database.connections.mysql.database' => self::TEST_DB]);
        DB::purge('mysql');
        foreach (DB::table('users')->where('is_active', 1)->orderBy('user_id')->get(['user_id', 'role']) as $u) {
            $this->users[$u->role] ??= (int) $u->user_id;
        }
        $this->resetApprovalBaseline();
        $this->placeUnscheduledFromSavedCopies();
    }

    // the copy is taken from live data, where people keep sending and deciding timetables;
    // start every run from the same point: every college in the Records Officer's latest
    // saved timetable (one set that was scheduled together) waiting for the University
    // Registrar, the rest not started, no notifications
    private function resetApprovalBaseline(): void
    {
        // load the legacy API the way the app does (json_ok must answer, not exit)
        if (! defined('REGISTRAR_LIBRARY')) {
            define('REGISTRAR_LIBRARY', true);
        }
        (new \ReflectionMethod(\App\Services\DomainCatalog::class, 'load'))->invoke(null);
        $termId = (int) DB::table('terms as t')->join('school_years as y', 'y.school_year_id', '=', 't.school_year_id')
            ->where('y.label', self::YEAR)->where('t.term_code', self::TERM)->value('t.term_id');
        $saved = DB::table('schedule_copies')->where('term_id', $termId)->where('kind', 'save')->orderByDesc('updated_at')->value('payload');
        $this->assertNotNull($saved, 'The test needs a saved timetable for '.self::YEAR.' term '.self::TERM.'.');
        $snap = decode_snapshot($saved);
        foreach (DB::table('term_college_approvals')->where('term_id', $termId)->get() as $r) {
            $sent = encode_snapshot(snapshot_for_college($snap, (int) $r->college_id));
            DB::table('term_college_approvals')->where('term_id', $termId)->where('college_id', $r->college_id)->update($sent ? [
                'status' => 'pending_super_admin', 'sa_json' => $sent, 'sa_at' => now(), 'vpaa_json' => null, 'vpaa_at' => null,
                'is_locked' => 1, 'unlock_note' => null, 'unlocked_at' => null,
                'last_reject_from' => null, 'last_reject_note' => null, 'last_reject_at' => null,
            ] : ['status' => 'draft', 'sa_json' => null, 'vpaa_json' => null, 'last_reject_from' => null, 'last_reject_note' => null, 'last_reject_at' => null]);
        }
        term_from_college_rows(db(), $termId);
        DB::table('user_notifications')->delete();
    }

    // the copy is taken from live data, where someone may have just unplaced a class;
    // put it back the way the saved approval copies have it so every run starts complete
    private function placeUnscheduledFromSavedCopies(): void
    {
        $placed = [];
        foreach (DB::table('term_schedule_approvals')->get(['pending_sa_json', 'pending_vpaa_json', 'published_json']) as $row) {
            foreach ([$row->pending_sa_json, $row->pending_vpaa_json, $row->published_json] as $json) {
                foreach ($json ? (json_decode($json, true)['classes'] ?? []) : [] as $c) {
                    if (! empty($c['meetings']) && ($c['status'] ?? '') === 'scheduled') {
                        $placed[$c['id']] = $c;
                    }
                }
            }
        }
        foreach (DB::table('schedule_copies')->where('kind', 'workspace')->get(['copy_id', 'payload']) as $copy) {
            $snap = json_decode($copy->payload, true);
            $changed = false;
            foreach ($snap['classes'] ?? [] as $i => $c) {
                if ((empty($c['meetings']) || ($c['status'] ?? '') !== 'scheduled') && isset($placed[$c['id']])) {
                    $snap['classes'][$i] = $placed[$c['id']];
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('schedule_copies')->where('copy_id', $copy->copy_id)->update(['payload' => json_encode($snap, JSON_UNESCAPED_UNICODE)]);
            }
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

    // the legacy API reads PHP's request globals, which the test client doesn't set
    private function legacyRequest(string $method, array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET = $query;
    }

    private function act(string $action, array $extra = [])
    {
        $this->legacyRequest('POST');

        return $this->postJson('/api/schedule.php', $extra + [
            'action' => $action,
            'schoolYear' => self::YEAR,
            'termCode' => self::TERM,
        ]);
    }

    private function colleges(): array
    {
        $query = ['colleges' => '1', 'year' => self::YEAR, 'sem' => self::TERM];
        $this->legacyRequest('GET', $query);
        $res = $this->getJson('/api/schedule.php?'.http_build_query($query))->assertOk();

        return collect($res->json('colleges'))->keyBy('id')->all();
    }

    private function termRow(): object
    {
        $termId = DB::table('terms as t')->join('school_years as y', 'y.school_year_id', '=', 't.school_year_id')
            ->where('y.label', self::YEAR)->where('t.term_code', self::TERM)->value('t.term_id');

        return DB::table('term_schedule_approvals')->where('term_id', $termId)->first();
    }

    private function collegesIn(?string $json): array
    {
        $snap = $json ? json_decode($json, true) : null;
        $ids = [];
        foreach ($snap['classes'] ?? [] as $c) {
            $ids[$c['collegeId']] = true;
        }
        ksort($ids);

        return array_keys($ids);
    }

    private function workspaceClasses(string $slot): array
    {
        $row = DB::table('schedule_copies')->where('slot', $slot)->orderByDesc('copy_id')->first();

        return $row ? (json_decode($row->payload, true)['classes'] ?? []) : [];
    }

    /** A day/time with the same length where the class's room and section are both free. */
    private function freeSlotFor(array $class, array $all): array
    {
        $meeting = $class['meetings'][0];
        // prefer a room no class uses, so only the section's own timetable matters
        $used = [];
        foreach ($all as $c) {
            foreach ($c['meetings'] ?? [] as $m) {
                $used[$m['roomId']] = true;
            }
        }
        $pub = json_decode((string) $this->termRow()->published_json, true);
        $type = null;
        foreach ($pub['rooms'] ?? [] as $r) {
            if ($r['id'] === $meeting['roomId']) {
                $type = $r['typeName'] ?? null;
            }
        }
        foreach ($pub['rooms'] ?? [] as $r) {
            if (! isset($used[$r['id']]) && ($r['typeName'] ?? null) === $type && ($r['status'] ?? 'active') === 'active') {
                $meeting['roomId'] = $r['id'];
                break;
            }
        }
        $toMin = fn ($hm) => (int) substr($hm, 0, 2) * 60 + (int) substr($hm, 3, 2);
        $len = $toMin($meeting['end']) - $toMin($meeting['start']);
        $busy = [];
        foreach ($all as $c) {
            foreach ($c['meetings'] ?? [] as $m) {
                if ($m['roomId'] === $meeting['roomId'] || $c['sectionId'] === $class['sectionId']) {
                    $busy[$m['day']][] = [$toMin($m['start']), $toMin($m['end'])];
                }
            }
        }
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day) {
            for ($s = 7 * 60; $s + $len <= 19 * 60; $s += 30) {
                if ($s < 13 * 60 && $s + $len > 12 * 60) {
                    continue; // lunch
                }
                $free = true;
                foreach ($busy[$day] ?? [] as [$a, $b]) {
                    if ($s < $b && $s + $len > $a) {
                        $free = false;
                        break;
                    }
                }
                if ($free && ($day !== $meeting['day'] || $s !== $toMin($meeting['start']))) {
                    $fmt = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

                    return [$meeting, $day, $fmt($s), $fmt($s + $len)];
                }
            }
        }
        $this->fail('No free slot to test with.');
    }

    public function test_registrar_approves_into_their_working_timetable_then_sends_it(): void
    {
        $cas = 'c-2';
        $this->as('super_admin');
        $this->assertSame('pending_super_admin', $this->colleges()[$cas]['status']);
        $sent = array_values(array_filter(json_decode($this->termRow()->pending_sa_json, true)['classes'], fn ($c) => $c['collegeId'] === $cas));


        // approving brings the Records Officer's copy into the Registrar's working timetable
        $this->act('college_registrar_decide', ['colleges' => [$cas], 'accept' => true])->assertOk();
        $now = $this->colleges()[$cas];
        $this->assertSame('with_registrar', $now['status']);
        $this->assertTrue($now['fromRecordsOfficer']);
        $this->assertFalse($now['editedFromSent']);
        $this->assertTrue($now['readyToSend']);
        $mine = array_values(array_filter($this->workspaceClasses('sa_private'), fn ($c) => $c['collegeId'] === $cas));
        $this->assertEquals(array_column($sent, 'meetings', 'id'), array_column($mine, 'meetings', 'id'));
        $this->assertNotContains($cas, $this->collegesIn($this->termRow()->pending_sa_json));
        $this->assertSame(1, DB::table('user_notifications')->where('title', 'University Registrar approved your timetables')->count());

        // the Records Officer can't send it again while the Registrar has it
        $this->as('admin');
        $this->act('college_submit_to_registrar', ['colleges' => [$cas]])
            ->assertStatus(400)->assertJson(['error' => 'College Of Arts and Science is with the University Registrar now. They can send it back to you if it needs changes.']);

        // when the Registrar is happy, it goes to the VPAA
        $this->as('super_admin');
        $this->act('college_send_to_vpaa', ['colleges' => [$cas]])->assertOk();
        $this->assertSame('pending_vpaa', $this->colleges()[$cas]['status']);
        $this->assertContains($cas, $this->collegesIn($this->termRow()->pending_vpaa_json));

        // taking it back from the VPAA returns it to the Registrar, not the Records Officer
        $this->act('college_retract', ['colleges' => [$cas]])->assertOk();
        $this->assertSame('with_registrar', $this->colleges()[$cas]['status']);
    }

    public function test_vpaa_checks_a_college_in_its_box_approves_and_can_retract_with_a_note(): void
    {
        $cas = 'c-2';
        $pharmacy = 'c-3';
        $this->as('super_admin');
        $this->act('college_registrar_decide', ['colleges' => [$cas, $pharmacy], 'accept' => true])->assertOk();
        $this->act('college_send_to_vpaa', ['colleges' => [$cas, $pharmacy]])->assertOk();

        // the box opens the college's waiting copy, by program
        $this->as('vpaa');
        $this->legacyRequest('GET', ['college_copy' => '1', 'college' => $cas, 'which' => 'pending']);
        $copy = $this->getJson('/api/schedule.php?college_copy=1&college='.$cas.'&which=pending')->assertOk()->json();
        $this->assertSame('pending_vpaa', $copy['status']);
        $this->assertNotEmpty($copy['classes']);
        $this->assertNotEmpty($copy['programs']);
        $this->assertSame(['c-2'], array_values(array_unique(array_column($copy['classes'], 'collegeId'))));
        // the overview works without a term (the current offering)
        $this->legacyRequest('GET', ['colleges' => '1']);
        $this->getJson('/api/schedule.php?colleges=1')->assertOk()->assertJsonPath('term.schoolYear', self::YEAR);

        $this->act('college_vpaa_decide', ['colleges' => [$cas, $pharmacy], 'accept' => true])->assertOk();

        // History lists the term with both colleges
        $this->legacyRequest('GET', ['vpaa_history' => '1']);
        $terms = $this->getJson('/api/schedule.php?vpaa_history=1')->assertOk()->json('terms');
        $this->assertSame([$cas, $pharmacy], array_column($terms[0]['colleges'], 'id'));

        // retracting needs a note; it goes back to the Registrar and the approved copy stays in use
        $this->act('college_vpaa_retract', ['colleges' => [$pharmacy]])
            ->assertStatus(400)->assertJson(['error' => 'Say why you’re retracting it and what the University Registrar should fix.']);
        $this->act('college_vpaa_retract', ['colleges' => [$pharmacy], 'note' => 'PCH 111 lab is in the wrong building.'])->assertOk();
        $now = $this->colleges()[$pharmacy];
        $this->assertSame('with_registrar', $now['status']);
        $this->assertSame('vpaa', $now['lastRejectFrom']);
        $this->assertTrue($now['hasPublished']);
        $this->assertFalse($now['locked']);
        $this->assertSame(1, DB::table('user_notifications')->where('user_id', $this->users['super_admin'])->where('title', 'The VPAA retracted an approval')->count());
        $this->assertSame(1, DB::table('transaction_logs')->where('summary', 'like', 'Retracted the approval of %wrong building.%')->count());
        // only an approved one can be retracted
        $this->act('college_vpaa_retract', ['colleges' => [$pharmacy], 'note' => 'Again.'])->assertStatus(400);

        // nobody but the VPAA and the registrars opens these
        $this->as('dean');
        $this->legacyRequest('GET', ['college_copy' => '1', 'college' => $cas, 'which' => 'published']);
        $this->getJson('/api/schedule.php?college_copy=1&college='.$cas.'&which=published')->assertStatus(400);
    }

    public function test_a_sent_college_leaves_the_working_timetable_and_comes_back_with_the_note(): void
    {
        $cas = 'c-2';
        $in = fn (string $slot) => (bool) array_filter($this->workspaceClasses($slot), fn ($c) => $c['collegeId'] === $cas);
        $saves = fn (string $owner, string $like) => DB::table('schedule_copies')->where('kind', 'save')->where('owner_role', $owner)->where('title', 'like', $like)->count();

        // the Registrar approves the Records Officer's copy into their Working timetable, then sends it on
        $this->as('super_admin');
        $this->act('college_registrar_decide', ['colleges' => [$cas], 'accept' => true])->assertOk();
        $this->assertTrue($in('sa_private'));
        $this->act('college_send_to_vpaa', ['colleges' => [$cas]])->assertOk();
        $this->assertFalse($in('sa_private'), 'sent: out of the Working timetable');
        $this->assertSame(1, $saves('super_admin', 'College Of Arts and Science – sent to the VPAA, %'), 'a copy is kept in the saves');

        // the VPAA sends it back: into the Registrar's Working timetable, with the note
        $this->as('vpaa');
        $this->act('college_vpaa_decide', ['colleges' => [$cas], 'accept' => false, 'note' => 'Move STS 000 to the morning.'])->assertOk();
        $this->as('super_admin');
        $this->assertTrue($in('sa_private'), 'back in the Working timetable');
        $now = $this->colleges()[$cas];
        $this->assertSame(['with_registrar', 'vpaa', 'Move STS 000 to the morning.'], [$now['status'], $now['lastRejectFrom'], $now['lastRejectNote']]);

        // the Registrar sends it back to the Records Officer: out of theirs, into the Records Officer's
        $this->act('college_registrar_decide', ['colleges' => [$cas], 'accept' => false, 'note' => 'Please redo the CS labs.'])->assertOk();
        $this->assertFalse($in('sa_private'));
        $this->assertTrue($in('admin_private'));
        $this->as('admin');
        $now = $this->colleges()[$cas];
        $this->assertSame(['draft', 'super_admin', 'Please redo the CS labs.'], [$now['status'], $now['lastRejectFrom'], $now['lastRejectNote']]);

        // the Records Officer sends it again (out of theirs, copy kept), then takes it back (in again)
        $this->act('college_submit_to_registrar', ['colleges' => [$cas]])->assertOk();
        $this->assertFalse($in('admin_private'));
        $this->assertSame(1, $saves('admin', 'College Of Arts and Science – sent to the University Registrar, %'));
        $this->act('college_retract', ['colleges' => [$cas]])->assertOk();
        $this->assertTrue($in('admin_private'));
    }

    public function test_registrar_can_send_their_own_version_while_a_request_waits(): void
    {
        $pharmacy = 'c-3';
        $this->as('super_admin');
        $this->assertSame('pending_super_admin', $this->colleges()[$pharmacy]['status']);
        $this->assertTrue($this->colleges()[$pharmacy]['readyToSend'], 'their own version is finished');
        $this->act('college_send_to_vpaa', ['colleges' => [$pharmacy]])->assertOk();
        $this->assertSame('pending_vpaa', $this->colleges()[$pharmacy]['status']);
        $this->assertNotContains($pharmacy, $this->collegesIn($this->termRow()->pending_sa_json), 'the request is closed');
        $this->assertSame(1, DB::table('user_notifications')->where('title', 'University Registrar sent their own version')->count());
    }

    public function test_registrar_can_approve_but_keep_their_own_version(): void
    {
        $pharmacy = 'c-3';
        $this->as('super_admin');
        $before = array_values(array_filter($this->workspaceClasses('sa_private'), fn ($c) => $c['collegeId'] === $pharmacy));
        $this->assertNotEmpty($before);

        // no version of their own to keep: refused
        $this->act('college_registrar_decide', ['colleges' => ['c-5'], 'accept' => true, 'keepMine' => true])->assertStatus(400);

        $this->act('college_registrar_decide', ['colleges' => [$pharmacy], 'accept' => true, 'keepMine' => true])->assertOk();
        $now = $this->colleges()[$pharmacy];
        $this->assertSame('with_registrar', $now['status']);
        $after = array_values(array_filter($this->workspaceClasses('sa_private'), fn ($c) => $c['collegeId'] === $pharmacy));
        $this->assertEquals(array_column($before, 'meetings', 'id'), array_column($after, 'meetings', 'id'), 'the Registrar’s version is untouched');
        $this->assertSame(1, DB::table('user_notifications')->where('body', 'like', '%will use their own version%')->count());
    }

    public function test_auto_scheduling_only_uses_rooms_the_college_may_use(): void
    {
        $this->as('super_admin');
        $this->colleges(); // loads the legacy schedule API
        // set the GPL building aside for Pharmacy, like Data management → Facilities does
        $gpl = DB::table('buildings')->where('building_code', 'gpl')->value('building_id');
        DB::table('buildings')->where('building_id', $gpl)->update(['exclusive_college_id' => 3]);
        $pdo = DB::connection()->getPdo();
        $gplRooms = DB::table('rooms as r')->join('floors as f', 'f.floor_id', '=', 'r.floor_id')
            ->where('f.building_id', $gpl)->pluck('r.room_id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotEmpty($gplRooms);

        $allowed = room_allowed_colleges($pdo);
        foreach ($gplRooms as $rid) {
            $this->assertSame([3], $allowed[$rid]);
        }
        $room = DB::table('rooms')->where('room_id', $gplRooms[0])->first();
        $byType = placement_rooms_by_type($pdo);
        $group = fn (int $college) => ['usesCampusRoom' => true, 'roomTypeId' => (int) $room->room_type_id, 'combinedCount' => 1,
            'members' => [['collegeId' => $college, 'studentCount' => 1]]];
        $this->assertContains((int) $room->room_id, placement_room_choices($group(3), $byType), 'Pharmacy may use GPL');
        $this->assertNotContains((int) $room->room_id, placement_room_choices($group(2), $byType), 'Arts and Science may not');

        // the page gets the same rule, so editing by hand can warn and still allow it
        $listed = collect(list_campus_rooms($pdo))->firstWhere('id', 'r-'.$room->room_id);
        $this->assertSame(['c-3'], $listed['colleges']);
    }

    public function test_colleges_move_through_approval_one_by_one(): void
    {
        $cas = 'c-2';
        $pharmacy = 'c-3';

        // starting state comes from the existing whole-campus approval
        $this->as('super_admin');
        $start = $this->colleges();
        $this->assertSame('pending_super_admin', $start[$cas]['status']);
        $this->assertSame('pending_super_admin', $start[$pharmacy]['status']);
        $this->assertSame('draft', $start['c-5']['status'], 'Dentistry has no timetable yet');

        // roles are checked on the server, not taken from the request
        $this->as('admin');
        $this->act('college_vpaa_decide', ['colleges' => [$pharmacy], 'accept' => true, 'actor' => 'vpaa'])
            ->assertStatus(400)->assertJson(['error' => 'Only the VPAA can approve or send back these colleges.']);

        // University Registrar sends CAS back to the Records Officer (note required)
        $this->as('super_admin');
        $this->act('college_registrar_decide', ['colleges' => [$cas], 'accept' => false])
            ->assertStatus(400)->assertJson(['error' => 'Say what the Records Officer should fix before sending it back.']);
        $this->act('college_registrar_decide', ['colleges' => [$cas], 'accept' => false, 'note' => 'Check the STS 000 room.'])->assertOk();
        $now = $this->colleges();
        $this->assertSame('draft', $now[$cas]['status']);
        $this->assertSame('super_admin', $now[$cas]['lastRejectFrom']);
        $this->assertSame('pending_super_admin', $now[$pharmacy]['status'], 'Pharmacy is unaffected');
        // (approving is covered above; here the Registrar sends Pharmacy back too and sends their own version)
        $this->act('college_registrar_decide', ['colleges' => [$pharmacy], 'accept' => false, 'note' => 'I’ll redo the labs.'])->assertOk();
        $this->act('college_send_to_vpaa', ['colleges' => [$pharmacy]])->assertOk();
        $this->assertSame('pending_vpaa', $this->colleges()[$pharmacy]['status']);

        // VPAA sends Pharmacy back (note required)
        $this->as('vpaa');
        $this->act('college_vpaa_decide', ['colleges' => [$pharmacy], 'accept' => false])
            ->assertStatus(400)->assertJson(['error' => 'Say what the University Registrar should fix before sending it back.']);
        $this->act('college_vpaa_decide', ['colleges' => [$pharmacy], 'accept' => false, 'note' => 'Move PCH 111 lab to the morning.'])->assertOk();
        $now = $this->colleges();
        $this->assertSame('with_registrar', $now[$pharmacy]['status'], 'back with the University Registrar');
        $this->assertSame('Move PCH 111 lab to the morning.', $now[$pharmacy]['lastRejectNote']);
        $this->assertNull($this->termRow()->pending_vpaa_json);

        // University Registrar sends both colleges from their own schedule (bulk)
        $this->as('super_admin');
        $this->act('college_send_to_vpaa', ['colleges' => [$cas, $pharmacy]])->assertOk();
        $now = $this->colleges();
        $this->assertSame('pending_vpaa', $now[$cas]['status']);
        $this->assertSame('pending_vpaa', $now[$pharmacy]['status']);
        $this->assertSame([$cas, $pharmacy], $this->collegesIn($this->termRow()->pending_vpaa_json));

        // VPAA: CAS alone would clash with Pharmacy's still-live old timetable...
        $this->as('vpaa');
        $res = $this->act('college_vpaa_decide', ['colleges' => [$cas], 'accept' => true])->assertStatus(400);
        $this->assertStringContainsString('approve them together', (string) $res->json('error'));
        // ...so approve both together (bulk)
        $this->act('college_vpaa_decide', ['colleges' => [$cas, $pharmacy], 'accept' => true])->assertOk();
        $now = $this->colleges();
        foreach ([$cas, $pharmacy] as $c) {
            $this->assertSame('published', $now[$c]['status']);
            $this->assertTrue($now[$c]['locked']);
        }
        $this->assertSame([$cas, $pharmacy], $this->collegesIn($this->termRow()->published_json));
        $this->assertSame('published', $this->termRow()->status);

        // the CAS Dean is told, and sees only CAS's approved timetable on their dashboard
        $this->assertSame(1, DB::table('user_notifications')->where('user_id', $this->users['dean'])
            ->where('title', 'Your college’s timetable is approved')->count());
        $this->as('dean');
        $this->legacyRequest('GET', ['college_final' => '1']);
        $mine = $this->getJson('/api/schedule.php?college_final=1')->assertOk()->json();
        $this->assertSame('published', $mine['status']);
        $this->assertNotNull($mine['publishedAt']);
        $this->assertNotEmpty($mine['classes']);
        $this->assertSame(['c-2'], array_values(array_unique(array_column($mine['classes'], 'collegeId'))));
        $this->assertCount(1, $mine['terms'], 'the Dashboard shows the current term only');
        // Past timetables leaves the current term out (it moves there when the term changes)
        $this->legacyRequest('GET', ['college_final' => '1', 'scope' => 'past']);
        $past = $this->getJson('/api/schedule.php?college_final=1&scope=past')->assertOk()->json();
        $this->assertNull($past['term']);
        $this->assertSame([], $past['classes']);
        $this->legacyRequest('GET', ['college_final' => '1']);
        // the Program Chair gets their own programs' classes only
        $this->as('program_chair');
        $chair = $this->getJson('/api/schedule.php?college_final=1')->assertOk()->json();
        $chairPrograms = array_column($chair['programs'], 'id');
        $this->assertNotEmpty($chair['classes']);
        $this->assertLessThanOrEqual(count($mine['classes']), count($chair['classes']));
        foreach ($chair['classes'] as $c) {
            $this->assertContains($c['programId'], $chairPrograms);
        }
        // nobody else uses this
        $this->as('admin');
        $this->getJson('/api/schedule.php?college_final=1')->assertStatus(400);

        // unlock to fix a mistake: reason required, logged; the published copy stays live
        $this->as('super_admin');
        $this->act('college_unlock', ['colleges' => [$cas]])
            ->assertStatus(400)->assertJson(['error' => 'Say what needs fixing. The reason is kept in the Transaction log.']);
        $this->act('college_unlock', ['colleges' => [$cas], 'note' => 'Wrong room for STS 000.'])->assertOk();
        $now = $this->colleges();
        $this->assertSame('with_registrar', $now[$cas]['status'], 'the University Registrar fixes it');
        $this->assertFalse($now[$cas]['locked']);
        $this->assertContains($cas, $this->collegesIn($this->termRow()->published_json));
        $this->assertSame(1, DB::table('transaction_logs')->where('summary', 'like', 'Unlocked %Wrong room for STS 000.%')->count());
        // unlocking puts the approved copy into the Registrar's Working timetable to fix; locking again takes it out
        $this->assertNotEmpty(array_filter($this->workspaceClasses('sa_private'), fn ($c) => $c['collegeId'] === $cas));
        $this->act('college_relock', ['colleges' => [$cas]])->assertOk();
        $this->assertTrue($this->colleges()[$cas]['locked']);
        $this->assertEmpty(array_filter($this->workspaceClasses('sa_private'), fn ($c) => $c['collegeId'] === $cas), 'an approved college is not in the Working timetable');

        // Generate with CAS approved and Pharmacy unlocked: CAS is away from the Working timetable, so its
        // sections aren't scheduled and its approved classes only block their rooms (handed to the generator
        // as already-placed classes it schedules around); this database has no sections, so the generator
        // itself can't run here
        $this->act('college_unlock', ['colleges' => [$pharmacy], 'note' => 'Regenerate Pharmacy rooms.'])->assertOk();
        $this->assertNotEmpty(array_filter($this->workspaceClasses('sa_private'), fn ($c) => $c['collegeId'] === $pharmacy), 'unlocked: back in the Working timetable');
        $termId = (int) $this->termRow()->term_id;
        $body = ['action' => 'generate', 'actor' => 'super_admin'];
        apply_college_locks_to_generate(db(), $termId, $body);
        $this->assertTrue($body['onlyUnscheduled']);
        $seedColleges = array_values(array_unique(array_map(fn ($c) => $c['collegeId'], $body['seedPrivateClasses'])));
        $this->assertSame([$cas], $seedColleges, 'only the away college blocks rooms');
        $this->assertSame([2], $body['skipCollegeIds'], 'and its sections are skipped');
        $published = [];
        foreach (json_decode($this->termRow()->published_json, true)['classes'] as $c) {
            if ($c['collegeId'] === $cas) {
                $published[$c['id']] = $c['meetings'];
            }
        }
        $seeded = [];
        foreach ($body['seedPrivateClasses'] as $c) {
            $seeded[$c['id']] = $c['meetings'];
        }
        $this->assertEquals($published, $seeded, 'its rooms are blocked exactly as approved');
        // for the Records Officer, a college with the Registrar is away too
        $ro = ['action' => 'generate', 'actor' => 'admin'];
        apply_college_locks_to_generate(db(), $termId, $ro);
        $this->assertEqualsCanonicalizing([2, 3], $ro['skipCollegeIds']);

        // sending a locked college again is refused until it's unlocked
        $this->act('college_send_to_vpaa', ['colleges' => [$cas]])
            ->assertStatus(400)->assertJson(['error' => 'College Of Arts and Science is published and locked. Unlock it first to send a corrected version.']);
    }
}
