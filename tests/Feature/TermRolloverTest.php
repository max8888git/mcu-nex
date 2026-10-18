<?php

namespace Tests\Feature;

use App\Support\RegistrarSession;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Start next term (legacy-api/term-rollover.php), on a fresh copy of the real database
 * (mcu_nex_term_test) so real data is never touched. Needs the XAMPP mysql tools.
 */
class TermRolloverTest extends TestCase
{
    private const TEST_DB = 'mcu_nex_term_test';

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
        $file = storage_path('framework/testing-term-clone.sql');
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
        foreach (DB::table('users')->where('is_active', 1)->orderBy('user_id')->get(['user_id', 'role']) as $u) {
            $this->users[$u->role] ??= (int) $u->user_id;
        }
        // start from a clean 2026-2027 1st semester with no sections
        $termId = $this->termId('2026-2027', '1');
        DB::table('sections')->where('term_id', $termId)->delete();
        DB::table('section_plans')->where('term_id', $termId)->delete();
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

    private function send(array $body)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];

        return $this->postJson('/api/sections.php', $body);
    }

    private function termId(string $year, string $code): int
    {
        return (int) DB::table('terms as t')->join('school_years as y', 'y.school_year_id', '=', 't.school_year_id')
            ->where('y.label', $year)->where('t.term_code', $code)->value('t.term_id');
    }

    private function sectionsIn(string $year, string $code): array
    {
        return DB::table('sections')->where('term_id', $this->termId($year, $code))
            ->orderBy('year_level')->orderBy('section_code')
            ->get(['program_id', 'year_level', 'section_code', 'student_count', 'curriculum_id'])
            ->map(fn ($s) => $s->program_id.' '.$s->year_level.'-'.$s->section_code.' '.$s->student_count.' cur'.$s->curriculum_id)->all();
    }

    public function test_start_next_term_carries_sections_over_and_can_be_undone(): void
    {
        // IT (program 9, curriculum 7, 4 years, summer subjects in 2nd year): 1st, 2nd and 4th years
        $this->as('super_admin');
        $term = ['schoolYear' => '2026-2027', 'termCode' => '1'];
        $this->send($term + ['departmentId' => 'd-9', 'yearLevel' => 1, 'curriculumId' => 'cur-7', 'studentCount' => 70])->assertOk();
        $this->send($term + ['departmentId' => 'd-9', 'yearLevel' => 2, 'curriculumId' => 'cur-7', 'studentCount' => 30])->assertOk();
        $this->send($term + ['departmentId' => 'd-9', 'yearLevel' => 4, 'curriculumId' => 'cur-7', 'studentCount' => 25])->assertOk();
        $this->assertSame(['9 1-1 35 cur7', '9 1-2 35 cur7', '9 2-1 30 cur7', '9 4-1 25 cur7'], $this->sectionsIn('2026-2027', '1'));

        // only the University Registrar
        $this->as('admin');
        $this->send(['action' => 'nextTermPreview'])->assertStatus(400)->assertJsonPath('error', 'Only the University Registrar starts the next term.');
        $this->as('super_admin');

        // 1st → 2nd semester: every section as is
        $p = $this->send(['action' => 'nextTermPreview'])->assertOk()->json('preview');
        $this->assertSame(['2026-2027', '2'], [$p['target']['schoolYear'], $p['target']['termCode']]);
        $this->assertCount(4, $p['sections']);
        $sections = $p['sections'];
        $sections[0]['students'] = 33; // two dropped out
        $this->send(['action' => 'nextTermCreate', 'schoolYear' => '2026-2027', 'termCode' => '2', 'sections' => $sections])
            ->assertOk()->assertJsonPath('sectionsCreated', 4)->assertJsonPath('currentTerm.termCode', '2');
        $this->assertSame(['9 1-1 33 cur7', '9 1-2 35 cur7', '9 2-1 30 cur7', '9 4-1 25 cur7'], $this->sectionsIn('2026-2027', '2'));
        $this->assertSame(1, DB::table('transaction_logs')->where('summary', 'like', 'Started 2026-2027%2nd semester from 2026-2027%: 4 sections created.')->count());

        // undo (nothing scheduled yet): back to the 1st semester, the new sections gone
        $state = $this->send(['action' => 'nextTermPreview'])->json('preview.from');
        $this->assertSame('2', $state['termCode']);
        $this->send(['action' => 'nextTermUndo'])->assertOk()->assertJsonPath('currentTerm.termCode', '1');
        $this->assertSame([], $this->sectionsIn('2026-2027', '2'));
        $this->assertCount(4, $this->sectionsIn('2026-2027', '1'));

        // again, then 2nd → Summer: only 2nd-years (their curriculum has summer subjects)
        $p = $this->send(['action' => 'nextTermPreview'])->json('preview');
        $this->send(['action' => 'nextTermCreate', 'schoolYear' => '2026-2027', 'termCode' => '2', 'sections' => $p['sections']])->assertOk();
        $p = $this->send(['action' => 'nextTermPreview', 'schoolYear' => '2026-2027', 'termCode' => 'summer'])->json('preview');
        $this->assertSame('summer', $p['target']['termCode']);
        $this->assertSame([2], array_values(array_unique(array_column($p['sections'], 'yearLevel'))));
        $this->assertSame(3, $p['summerSkipped']);

        // 2nd → next school year's 1st: up a year, 4th-years graduate, new 1st-years from the newest curriculum
        $p = $this->send(['action' => 'nextTermPreview', 'schoolYear' => '2027-2028', 'termCode' => '1'])->json('preview');
        $this->assertTrue($p['newYear']);
        $this->assertSame([2, 2, 3], array_column($p['sections'], 'yearLevel'));
        $this->assertSame([4], array_column($p['graduating'], 'yearLevel'));
        $it = collect($p['firstYears'])->firstWhere('programId', 'd-9');
        $this->assertSame('cur-7', $it['curriculumId']);
        $this->assertContains('Computer Science has no active curriculum, so it can’t get new 1st-year sections.', $p['warnings']);
        $firstYears = $p['firstYears'];
        foreach ($firstYears as $i => $f) {
            if ($f['programId'] === 'd-9') {
                $firstYears[$i]['students'] = 45;
            }
        }
        $this->send(['action' => 'nextTermCreate', 'schoolYear' => '2027-2028', 'termCode' => '1', 'sections' => $p['sections'], 'firstYears' => $firstYears])
            ->assertOk()->assertJsonPath('currentTerm.schoolYear', '2027-2028');
        $this->assertSame(['9 1-1 23 cur7', '9 1-2 22 cur7', '9 2-1 35 cur7', '9 2-2 35 cur7', '9 3-1 30 cur7'], $this->sectionsIn('2027-2028', '1'));

        // a term that already has sections can't be started again
        $this->send(['action' => 'nextTermCreate', 'schoolYear' => '2027-2028', 'termCode' => '1', 'sections' => $p['sections']])->assertStatus(400);
    }
}
