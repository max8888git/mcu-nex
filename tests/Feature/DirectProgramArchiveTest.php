<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A college without departments has one direct program (legacy-api/org.php).
 * Archiving it must list it under Archived, and a new program must be addable
 * afterwards (uq_programs_sole). Runs on a copy of the real database
 * (mcu_nex_org_test); needs MySQL and the XAMPP mysql tools.
 */
class DirectProgramArchiveTest extends TestCase
{
    private const SOURCE_DB = 'mcu_nex';

    private const TEST_DB = 'mcu_nex_org_test';

    private ?\PDO $pdo = null;

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
        $file = storage_path('framework/testing-org-clone.sql');
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
    }

    protected function tearDown(): void
    {
        \App\Support\RegistrarSession::logout();
        DB::purge('mysql');
        parent::tearDown();
    }

    /** one request the way the page makes it: the schema check runs first, then the save */
    private function save(array $doc): void
    {
        ensure_department_program_split($this->pdo());
        $this->pdo()->beginTransaction();
        org_save_document($this->pdo(), $doc);
        $this->pdo()->commit();
    }

    private function load(): array
    {
        ensure_department_program_split($this->pdo());

        return org_document($this->pdo());
    }

    /** the legacy db() reads api/config.php (the real database), so connect to the copy explicitly */
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

    /** @return array<string, mixed> */
    private function college(array $doc, string $name): array
    {
        foreach ($doc['active'] as $c) {
            if ($c['name'] === $name) {
                return $c;
            }
        }
        $this->fail("College {$name} is not active.");
    }

    /** @return array<string, int> name => is_active, for the departments of a college */
    private function departmentStates(string $college): array
    {
        return DB::table('departments as d')->join('colleges as c', 'c.college_id', '=', 'd.college_id')
            ->where('c.college_name', $college)->pluck('d.is_active', 'd.department_name')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> */
    private function programStates(string $college): array
    {
        return DB::table('programs as p')->join('colleges as c', 'c.college_id', '=', 'p.college_id')
            ->where('c.college_name', $college)->pluck('p.is_active', 'p.program_name')->map(fn ($v) => (int) $v)->all();
    }

    public function test_restoring_a_college_restores_all_its_departments_and_programs(): void
    {
        $doc = $this->load();
        $doc['active'][] = [
            'id' => '', 'name' => 'Theta Test College', 'code' => 'TTC', 'useDepartments' => true,
            'departments' => [
                ['id' => '', 'name' => 'Alpha Dept', 'programs' => [['id' => '', 'name' => 'Alpha One'], ['id' => '', 'name' => 'Alpha Two']]],
                ['id' => '', 'name' => 'Beta Dept', 'programs' => [['id' => '', 'name' => 'Beta One']]],
            ],
        ];
        $this->save($doc);

        // Alpha Two and the Beta department are archived on their own first
        $doc = $this->load();
        $alpha = null;
        $beta = null;
        $alphaTwo = null;
        foreach ($doc['active'] as &$theta) {
            if ($theta['name'] !== 'Theta Test College') {
                continue;
            }
            foreach ($theta['departments'] as $d) {
                $d['name'] === 'Alpha Dept' ? $alpha = $d : $beta = $d;
            }
            $alphaTwo = array_values(array_filter($alpha['programs'], fn ($p) => $p['name'] === 'Alpha Two'))[0];
            $alpha['programs'] = array_values(array_filter($alpha['programs'], fn ($p) => $p['name'] !== 'Alpha Two'));
            $theta['departments'] = [$alpha];
        }
        unset($theta);
        $doc['archivedPrograms'][] = ['id' => $alphaTwo['id'], 'name' => 'Alpha Two', 'archivedLabel' => 'Sep 2026'];
        $doc['archivedDeptOnly'][] = ['id' => $beta['id'], 'deptName' => 'Beta Dept', 'programs' => $beta['programs'], 'parentCode' => 'TTC', 'archivedLabel' => 'Sep 2026'];
        $this->save($doc);
        $this->assertSame(['Alpha Dept' => 1, 'Beta Dept' => 0], $this->departmentStates('Theta Test College'));
        $this->assertSame(['Alpha One' => 1, 'Alpha Two' => 0, 'Beta One' => 0], $this->programStates('Theta Test College'));

        // the whole college is archived
        $doc = $this->load();
        $row = null;
        foreach ($doc['active'] as $i => $c) {
            if ($c['name'] === 'Theta Test College') {
                $row = $c;
                unset($doc['active'][$i]);
            }
        }
        $doc['active'] = array_values($doc['active']);
        $row['archivedLabel'] = 'Oct 2026';
        $doc['archivedColleges'][] = $row;
        $this->save($doc);
        $this->assertSame(['Alpha Dept' => 0, 'Beta Dept' => 0], $this->departmentStates('Theta Test College'));
        $this->assertSame(['Alpha One' => 0, 'Alpha Two' => 0, 'Beta One' => 0], $this->programStates('Theta Test College'));
        $oneId = (int) DB::table('programs')->where('program_name', 'Alpha One')->value('program_id');
        DB::table('curricula')->insert(['program_id' => $oneId, 'curriculum_name' => 'Alpha One 2026', 'academic_year' => '2026-2027', 'effective_year' => 2026, 'status' => 'archived']);

        // the college is restored (as the page sends it) and everything under it comes back
        $doc = $this->load();
        foreach ($doc['archivedColleges'] as $i => $c) {
            if ($c['name'] === 'Theta Test College') {
                unset($c['archivedLabel']);
                $doc['active'][] = $c;
                unset($doc['archivedColleges'][$i]);
            }
        }
        $doc['archivedColleges'] = array_values($doc['archivedColleges']);
        $this->save($doc);
        $this->assertSame(['Alpha Dept' => 1, 'Beta Dept' => 1], $this->departmentStates('Theta Test College'));
        $this->assertSame(['Alpha One' => 1, 'Alpha Two' => 1, 'Beta One' => 1], $this->programStates('Theta Test College'));
        $this->assertSame(['2026-2027' => 'archived'], $this->curriculumStatuses($oneId), 'Curricula are restored one by one, not with the college.');

        // archiving a program afterwards sticks through later saves
        $this->save($this->load());
        $this->assertSame(['Alpha One' => 1, 'Alpha Two' => 1, 'Beta One' => 1], $this->programStates('Theta Test College'));
    }

    public function test_only_the_university_registrar_may_delete(): void
    {
        $roles = DB::table('users')->where('is_active', 1)->whereIn('role', ['admin', 'super_admin'])->pluck('user_id', 'role');
        $this->assertArrayHasKey('admin', $roles->all());
        $this->assertArrayHasKey('super_admin', $roles->all());

        \App\Support\RegistrarSession::login((int) $roles['admin']);
        try {
            org_require_registrar_to_delete($this->pdo());
            $this->fail('The Records Officer must not be able to delete.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Only the University Registrar', $e->getMessage());
        }

        \App\Support\RegistrarSession::login((int) $roles['super_admin']);
        org_require_registrar_to_delete($this->pdo());
        $this->addToAssertionCount(1);
    }

    public function test_restoring_a_department_restores_all_its_programs(): void
    {
        $doc = $this->load();
        $doc['active'][] = [
            'id' => '', 'name' => 'Kappa Test College', 'code' => 'KTC', 'useDepartments' => true,
            'departments' => [['id' => '', 'name' => 'Kappa Dept', 'programs' => [['id' => '', 'name' => 'Kappa One'], ['id' => '', 'name' => 'Kappa Two']]]],
        ];
        $this->save($doc);

        // Kappa Two is archived on its own, then the department is archived
        $doc = $this->load();
        $dept = null;
        $two = null;
        foreach ($doc['active'] as &$c) {
            if ($c['name'] === 'Kappa Test College') {
                $dept = $c['departments'][0];
                $two = array_values(array_filter($dept['programs'], fn ($p) => $p['name'] === 'Kappa Two'))[0];
                $c['departments'][0]['programs'] = array_values(array_filter($dept['programs'], fn ($p) => $p['name'] !== 'Kappa Two'));
            }
        }
        unset($c);
        $doc['archivedPrograms'][] = ['id' => $two['id'], 'name' => 'Kappa Two', 'archivedLabel' => 'Sep 2026'];
        $this->save($doc);
        $doc = $this->load();
        foreach ($doc['active'] as &$c) {
            if ($c['name'] === 'Kappa Test College') {
                $c['departments'] = [];
            }
        }
        unset($c);
        $doc['archivedDeptOnly'][] = ['id' => $dept['id'], 'deptName' => 'Kappa Dept', 'programs' => [], 'parentCode' => 'KTC', 'archivedLabel' => 'Oct 2026'];
        $this->save($doc);
        $this->assertSame(['Kappa Dept' => 0], $this->departmentStates('Kappa Test College'));
        $this->assertSame(['Kappa One' => 0, 'Kappa Two' => 0], $this->programStates('Kappa Test College'));

        // restore the department as the page sends it: no programs listed
        $doc = $this->load();
        $doc['archivedDeptOnly'] = array_values(array_filter($doc['archivedDeptOnly'], fn ($d) => $d['id'] !== $dept['id']));
        foreach ($doc['active'] as &$c) {
            if ($c['name'] === 'Kappa Test College') {
                $c['departments'][] = ['id' => $dept['id'], 'name' => 'Kappa Dept', 'programs' => []];
            }
        }
        unset($c);
        $this->save($doc);
        $this->assertSame(['Kappa Dept' => 1], $this->departmentStates('Kappa Test College'));
        $this->assertSame(['Kappa One' => 1, 'Kappa Two' => 1], $this->programStates('Kappa Test College'));

        // later saves leave a program archived afterwards alone
        $this->save($this->load());
        $this->assertSame(['Kappa One' => 1, 'Kappa Two' => 1], $this->programStates('Kappa Test College'));
    }

    public function test_restoring_a_college_without_departments_restores_its_program(): void
    {
        $doc = $this->load();
        $doc['active'][] = [
            'id' => '', 'name' => 'Iota Test College', 'code' => 'ITC', 'useDepartments' => false,
            'departments' => [], 'program' => ['id' => '', 'name' => 'Iotology'],
        ];
        $this->save($doc);

        $doc = $this->load();
        foreach ($doc['active'] as $i => $c) {
            if ($c['name'] === 'Iota Test College') {
                $c['archivedLabel'] = 'Oct 2026';
                $doc['archivedColleges'][] = $c;
                unset($doc['active'][$i]);
            }
        }
        $doc['active'] = array_values($doc['active']);
        $this->save($doc);
        $this->assertSame(['Iotology' => 0], $this->programStates('Iota Test College'));

        $doc = $this->load();
        foreach ($doc['archivedColleges'] as $i => $c) {
            if ($c['name'] === 'Iota Test College') {
                unset($c['archivedLabel']);
                $doc['active'][] = $c;
                unset($doc['archivedColleges'][$i]);
            }
        }
        $doc['archivedColleges'] = array_values($doc['archivedColleges']);
        $this->save($doc);
        $this->assertSame(['Iotology' => 1], $this->programStates('Iota Test College'));
        $this->assertSame('Iotology', $this->college($this->load(), 'Iota Test College')['program']['name']);
    }

    private function curriculumStatuses(int $programId): array
    {
        return DB::table('curricula')->where('program_id', $programId)->orderBy('academic_year')->pluck('status', 'academic_year')->all();
    }

    public function test_archiving_a_program_archives_its_curricula_but_restoring_it_does_not_restore_them(): void
    {
        $doc = $this->load();
        $doc['active'][] = [
            'id' => '', 'name' => 'Eta Test College', 'code' => 'ETC', 'useDepartments' => false,
            'departments' => [], 'program' => ['id' => '', 'name' => 'Etology'],
        ];
        $this->save($doc);
        $doc = $this->load();
        $program = $this->college($doc, 'Eta Test College')['program'];
        $pid = (int) substr($program['id'], 2);
        foreach (['2025-2026' => 'active', '2026-2027' => 'active', '2024-2025' => 'archived'] as $year => $status) {
            DB::table('curricula')->insert([
                'program_id' => $pid, 'curriculum_name' => 'Etology '.$year, 'academic_year' => $year,
                'effective_year' => (int) substr($year, 0, 4), 'status' => $status,
            ]);
        }

        // archive the program
        foreach ($doc['active'] as &$c) {
            if ($c['name'] === 'Eta Test College') {
                $c['program'] = null;
            }
        }
        unset($c);
        $doc['archivedPrograms'][] = ['id' => $program['id'], 'name' => 'Etology', 'archivedLabel' => 'Oct 2026'];
        $this->save($doc);
        $this->assertSame(
            ['2024-2025' => 'archived', '2025-2026' => 'archived', '2026-2027' => 'archived'],
            $this->curriculumStatuses($pid)
        );
        $this->assertSame(1, DB::table('transaction_logs')->where('area', 'curriculum')->where('summary', 'like', 'Archived 2 curricula of Etology%')->count());

        // restore the program: the curricula stay archived
        $doc = $this->load();
        foreach ($doc['active'] as &$c) {
            if ($c['name'] === 'Eta Test College') {
                $c['program'] = ['id' => $program['id'], 'name' => 'Etology'];
            }
        }
        unset($c);
        $doc['archivedPrograms'] = array_values(array_filter($doc['archivedPrograms'], fn ($p) => $p['id'] !== $program['id']));
        $this->save($doc);
        $this->assertSame('Etology', $this->college($this->load(), 'Eta Test College')['program']['name']);
        $this->assertSame(['archived'], array_values(array_unique($this->curriculumStatuses($pid))));

        // the Registrar restores one curriculum; saving the page again must not archive it again
        DB::table('curricula')->where('program_id', $pid)->where('academic_year', '2026-2027')->update(['status' => 'active']);
        $this->save($this->load());
        $this->assertSame('active', $this->curriculumStatuses($pid)['2026-2027']);
    }

    public function test_archived_direct_program_is_listed_and_a_new_one_can_be_added(): void
    {
        $doc = $this->load();
        $doc['active'][] = [
            'id' => '', 'name' => 'Zeta Test College', 'code' => 'ZTC', 'useDepartments' => false,
            'departments' => [], 'program' => ['id' => '', 'name' => 'Zetology'],
        ];
        $this->save($doc);

        $departmentsBefore = DB::table('departments')->count();
        $doc = $this->load();
        $this->load();
        $this->assertSame($departmentsBefore, DB::table('departments')->count(), 'Loading the page must not create departments.');
        $college = $this->college($doc, 'Zeta Test College');
        $program = $college['program'];
        $this->assertSame('Zetology', $program['name']);

        // archive the program (what the page sends)
        foreach ($doc['active'] as &$c) {
            if ($c['name'] === 'Zeta Test College') {
                $c['program'] = null;
            }
        }
        unset($c);
        $doc['archivedPrograms'][] = ['id' => $program['id'], 'name' => 'Zetology', 'archivedLabel' => 'Oct 2026'];
        $this->save($doc);

        $this->assertSame($departmentsBefore, DB::table('departments')->count(), 'Saving must not create departments.');
        $doc = $this->load();
        $archived = array_values(array_filter($doc['archivedPrograms'], fn ($p) => $p['name'] === 'Zetology'));
        $this->assertCount(1, $archived, 'The archived program must be listed.');
        $this->assertSame('', $archived[0]['departmentName']);
        $this->assertCount(0, array_filter($doc['archivedDeptOnly'], fn ($d) => $d['deptName'] === 'Zetology'));

        // a leftover department link (from the earlier bug) must not hide it either
        $pid = (int) substr($program['id'], 2);
        $stray = DB::table('departments')->insertGetId([
            'college_id' => (int) substr($this->college($doc, 'Zeta Test College')['id'], 2),
            'department_code' => 'DZETA', 'department_name' => 'Zetology', 'is_active' => 0,
        ]);
        DB::table('programs')->where('program_id', $pid)->update(['department_id' => $stray]);
        $doc = $this->load();
        $this->assertCount(1, array_filter($doc['archivedPrograms'], fn ($p) => $p['name'] === 'Zetology'));

        // add a new program with the same name: used to fail with Duplicate entry 'x-1' for uq_programs_sole
        foreach ($doc['active'] as &$c) {
            if ($c['name'] === 'Zeta Test College') {
                $c['program'] = ['id' => 'id-new', 'name' => 'Zetology'];
            }
        }
        unset($c);
        $this->save($doc);

        $doc = $this->load();
        $this->assertSame('Zetology', $this->college($doc, 'Zeta Test College')['program']['name']);
        $this->assertNotSame($program['id'], $this->college($doc, 'Zeta Test College')['program']['id']);
        $this->assertCount(1, array_filter($doc['archivedPrograms'], fn ($p) => $p['name'] === 'Zetology'), 'The old one stays archived.');
    }
}
