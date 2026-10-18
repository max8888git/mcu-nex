<?php

namespace Tests\Feature;

use App\Support\RegistrarSession;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deleting a curriculum (legacy-api/curriculum-archive.php): University Registrar only, archived
 * and never-used curricula only. Runs on a copy of the real database (mcu_nex_curdel_test); the
 * legacy code reads api/config.php (the real database), so the copy is passed in explicitly.
 * Needs MySQL and the XAMPP mysql tools.
 */
class CurriculumDeleteTest extends TestCase
{
    private const SOURCE_DB = 'mcu_nex';

    private const TEST_DB = 'mcu_nex_curdel_test';

    private ?\PDO $pdo = null;

    private int $registrar = 0;

    private int $officer = 0;

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
        $file = storage_path('framework/testing-curdel-clone.sql');
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
        if (! defined('REGISTRAR_LIBRARY')) {
            define('REGISTRAR_LIBRARY', true);
        }
        (new \ReflectionMethod(\App\Services\DomainCatalog::class, 'load'))->invoke(null);
        $users = DB::table('users')->where('is_active', 1)->whereIn('role', ['admin', 'super_admin'])->pluck('user_id', 'role');
        $this->registrar = (int) $users['super_admin'];
        $this->officer = (int) $users['admin'];
    }

    protected function tearDown(): void
    {
        RegistrarSession::logout();
        DB::purge('mysql');
        parent::tearDown();
    }

    private function pdo(): \PDO
    {
        $this->pdo ??= pdo_from_config([
            'name' => self::TEST_DB,
            'user' => config('database.connections.mysql.username'),
            'pass' => (string) config('database.connections.mysql.password'),
        ]);
        $this->assertSame(self::TEST_DB, $this->pdo->query('SELECT DATABASE()')->fetchColumn());

        return $this->pdo;
    }

    /** a curriculum with two subjects on a brand-new program (so no old timetable can be matched to it) */
    private function makeCurriculum(string $status, string $year = '2031-2032'): int
    {
        $collegeId = (int) DB::table('colleges')->where('is_active', 1)->orderBy('college_id')->value('college_id');
        $programId = DB::table('programs')->insertGetId([
            'college_id' => $collegeId, 'program_code' => 'DT-'.$year, 'program_name' => 'Delete Test Program '.$year,
            'represents_college' => 0, 'normal_years' => 4, 'is_active' => 1,
        ]);
        $id = DB::table('curricula')->insertGetId([
            'program_id' => $programId, 'curriculum_name' => 'Delete Test '.$year, 'academic_year' => $year,
            'effective_year' => (int) substr($year, 0, 4), 'status' => $status,
        ]);
        foreach (['DT101', 'DT102'] as $code) {
            DB::table('curriculum_courses')->insert([
                'curriculum_id' => $id, 'year_level' => 1, 'term_code' => '1', 'course_id' => $code, 'course_title' => 'Subject '.$code,
            ]);
        }

        return $id;
    }

    private function exists(int $id): bool
    {
        return DB::table('curricula')->where('curriculum_id', $id)->exists();
    }

    public function test_the_records_officer_cannot_delete(): void
    {
        $id = $this->makeCurriculum('archived');
        RegistrarSession::login($this->officer);
        try {
            curriculum_delete($this->pdo(), $id);
            $this->fail('The Records Officer must not be able to delete a curriculum.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Only the University Registrar', $e->getMessage());
        }
        $this->assertTrue($this->exists($id));
    }

    public function test_an_active_curriculum_must_be_archived_first(): void
    {
        $id = $this->makeCurriculum('active');
        RegistrarSession::login($this->registrar);
        try {
            curriculum_delete($this->pdo(), $id);
            $this->fail('An active curriculum must not be deleted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Archive it first', $e->getMessage());
        }
        $this->assertTrue($this->exists($id));
    }

    public function test_the_registrar_deletes_an_archived_unused_curriculum(): void
    {
        $id = $this->makeCurriculum('archived');
        DB::table('curriculum_reviews')->where('review_id', DB::table('curriculum_reviews')->value('review_id'))->update(['curriculum_id' => $id]);
        RegistrarSession::login($this->registrar);

        $overview = curriculum_finished_overview($this->pdo());
        $this->assertTrue($overview['canDelete']);
        $row = array_values(array_filter($overview['archived'], fn ($c) => $c['id'] === 'cur-'.$id))[0];
        $this->assertNull($row['deleteBlocked'], 'The page offers Delete for it.');

        $out = curriculum_delete($this->pdo(), $id);
        $this->assertSame(2, $out['subjects']);
        $this->assertFalse($this->exists($id));
        $this->assertSame(0, DB::table('curriculum_courses')->where('curriculum_id', $id)->count());
        $this->assertNull(DB::table('curriculum_reviews')->value('curriculum_id'), 'The review stays, without the pointer.');
        $this->assertSame(1, DB::table('transaction_logs')->where('action', 'Delete')->where('area', 'curriculum')
            ->where('summary', 'like', 'Deleted curriculum Delete Test 2031-2032%2 subjects%')->count());
    }

    public function test_a_curriculum_that_was_used_is_kept(): void
    {
        $usedId = (int) DB::table('sections')->value('curriculum_id');
        $this->assertGreaterThan(0, $usedId, 'The test needs a section in the database copy.');
        DB::table('curricula')->where('curriculum_id', $usedId)->update(['status' => 'archived']);
        RegistrarSession::login($this->registrar);

        $overview = curriculum_finished_overview($this->pdo());
        $row = array_values(array_filter($overview['archived'], fn ($c) => $c['id'] === 'cur-'.$usedId))[0];
        $this->assertNotNull($row['deleteBlocked'], 'The page shows it as kept for history.');

        try {
            curriculum_delete($this->pdo(), $usedId);
            $this->fail('A curriculum that sections follow must not be deleted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('stays as the record', $e->getMessage());
        }
        $this->assertTrue($this->exists($usedId));
        $this->assertGreaterThan(0, DB::table('curriculum_courses')->where('curriculum_id', $usedId)->count());
    }

    public function test_the_records_officer_may_archive_but_a_dean_may_not(): void
    {
        RegistrarSession::login($this->officer);
        $this->assertSame('admin', curriculum_archive_role($this->pdo()));
        RegistrarSession::login($this->registrar);
        $this->assertSame('super_admin', curriculum_archive_role($this->pdo()));
        $dean = (int) DB::table('users')->where('role', 'dean')->where('is_active', 1)->value('user_id');
        RegistrarSession::login($dean);
        try {
            curriculum_archive_role($this->pdo());
            $this->fail('A Dean must not be able to archive or restore.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Only the Records Officer and the University Registrar', $e->getMessage());
        }
    }

    public function test_the_records_officer_is_not_offered_delete(): void
    {
        $this->makeCurriculum('archived');
        RegistrarSession::login($this->officer);
        $overview = curriculum_finished_overview($this->pdo());
        $this->assertTrue($overview['canArchive'], 'the Records Officer archives and restores');
        $this->assertFalse($overview['canDelete']);
        foreach ($overview['archived'] as $c) {
            $this->assertNull($c['deleteBlocked']);
        }
    }
}
