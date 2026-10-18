<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function run_org_request(): void
{
$pdo = db();
ensure_department_program_split($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  json_ok(['org' => org_document($pdo)]);
}

if ($method === 'PUT') {
  $body = json_input();
  try {
    $pdo->beginTransaction();
    org_save_document($pdo, $body);
    $pdo->commit();
    json_ok(['org' => org_document($pdo)]);
  } catch (Throwable $e) {
    registrar_reraise($e);
    registrar_log_unexpected($e);
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_error($e->getMessage(), 400);
  }
}

if ($method === 'POST') {
  $body = json_input();
  $action = (string) ($body['action'] ?? $_GET['action'] ?? '');
  try {
    $pdo->beginTransaction();
    if ($action === 'addCollege') {
      $college = add_college($pdo, (string) ($body['name'] ?? ''), (string) ($body['code'] ?? ''), !empty($body['useDepartments']));
      record_transaction($pdo, 'Add', 'college', 'Added college ' . $college['name'] . '.');
      $pdo->commit();
      json_ok(['college' => $college, 'org' => org_document($pdo)]);
    }
    if ($action === 'addDepartment') {
      $dept = add_department($pdo, (string) ($body['collegeId'] ?? ''), (string) ($body['name'] ?? ''));
      record_transaction($pdo, 'Add', 'department', 'Added department ' . $dept['name'] . '.');
      $pdo->commit();
      json_ok(['department' => $dept, 'org' => org_document($pdo)]);
    }
    if ($action === 'addProgram') {
      $program = add_program(
        $pdo,
        (string) ($body['departmentId'] ?? ''),
        (string) ($body['name'] ?? '')
      );
      record_transaction($pdo, 'Add', 'program', 'Added program ' . (string) ($program['name'] ?? 'a program') . '.');
      $pdo->commit();
      json_ok(['program' => $program, 'org' => org_document($pdo)]);
    }
    if ($action === 'deleteCollege' || $action === 'deleteDepartment' || $action === 'deleteProgram') {
      org_require_registrar_to_delete($pdo);
      org_delete($pdo, $action, (string) ($body['id'] ?? ''));
      $pdo->commit();
      json_ok(['org' => org_document($pdo)]);
    }
    $pdo->rollBack();
    json_error('Unknown org action');
  } catch (Throwable $e) {
    registrar_reraise($e);
    registrar_log_unexpected($e);
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_error($e->getMessage(), 400);
  }
}

json_error('Unsupported method', 405);
}

if (!defined('REGISTRAR_LIBRARY')) {
  run_org_request();
}

function college_direct_program(array $programs, int $collegeId): ?array
{
  $fallback = null;
  foreach ($programs as $p) {
    if ((int) $p['college_id'] !== $collegeId || (int) $p['is_active'] !== 1) {
      continue;
    }
    $row = [
      'id' => public_id('d', (int) $p['program_id']),
      'name' => (string) $p['program_name'],
    ];
    if ((int) $p['represents_college'] === 1) {
      return $row;
    }
    if ((int) ($p['department_id'] ?? 0) < 1) {
      $fallback = $row;
    }
  }
  return $fallback;
}

function org_document(PDO $pdo): array
{
  $colleges = $pdo->query(
    'SELECT college_id, college_code, college_name, use_departments, archived_label, is_active
     FROM colleges
     ORDER BY college_name'
  )->fetchAll();

  $departments = $pdo->query(
    'SELECT department_id, college_id, department_name, archived_label, is_active
     FROM departments
     ORDER BY department_name'
  )->fetchAll();
  $programs = $pdo->query(
    'SELECT program_id, college_id, department_id, program_code, program_name, represents_college, archived_label, is_active
     FROM programs
     ORDER BY program_name'
  )->fetchAll();

  $programsByDept = [];
  foreach ($programs as $p) {
    $deptId = (int) ($p['department_id'] ?? 0);
    if ($deptId < 1) {
      continue;
    }
    $programsByDept[$deptId][] = $p;
  }
  $deptsByCollege = [];
  foreach ($departments as $d) {
    $deptsByCollege[(int) $d['college_id']][] = $d;
  }

  // Archived records carry the same usage numbers as active ones so the page
  // can tell what is safe to delete. Like the delete itself, they count every
  // program (archived or not) that sits under the record.
  $counts = org_usage_counts($pdo);
  $programsByCollege = [];
  foreach ($programs as $p) {
    $programsByCollege[(int) $p['college_id']][] = $p;
  }

  $active = [];
  $archivedColleges = [];
  $archivedDeptOnly = [];
  $collegeIndex = [];

  foreach ($colleges as $c) {
    $cid = (int) $c['college_id'];
    $useDept = (int) $c['use_departments'] === 1;
    $depts = [];
    if (!$useDept) {
      $directProgram = college_direct_program($programs, $cid);
    } else {
      $directProgram = null;
    foreach ($deptsByCollege[$cid] ?? [] as $d) {
      $did = (int) $d['department_id'];
      $plist = [];
      foreach ($programsByDept[$did] ?? [] as $p) {
        if ((int) $p['is_active'] !== 1) {
          continue;
        }
        $plist[] = [
          'id' => public_id('d', (int) $p['program_id']),
          'name' => $p['program_name'],
        ];
      }
      if ((int) $d['is_active'] === 1) {
        $depts[] = [
          'id' => public_id('dept', $did),
          'name' => $d['department_name'],
          'programs' => $plist,
        ];
      } elseif ((int) $c['is_active'] === 1) {
        $archivedDeptOnly[] = [
          'id' => public_id('dept', $did),
          'deptName' => $d['department_name'],
          'programs' => $plist,
          'parentCode' => $c['college_code'],
          'parentName' => $c['college_name'],
          'archivedLabel' => $d['archived_label'] ?: '',
          'usage' => org_programs_usage($counts, $programsByDept[$did] ?? [], (int) ($counts['chairs'][$did] ?? 0)),
        ];
      }
    }
    }

    $row = [
      'id' => public_id('c', $cid),
      'name' => $c['college_name'],
      'code' => $c['college_code'],
      'useDepartments' => $useDept,
      'departments' => $depts,
      'program' => $directProgram,
    ];
    $collegeIndex[$cid] = $row;
    if ((int) $c['is_active'] === 1) {
      $active[] = $row;
    } else {
      $row['archivedLabel'] = $c['archived_label'] ?: '';
      $row['usage'] = org_programs_usage($counts, $programsByCollege[$cid] ?? [], (int) ($counts['collegeAccounts'][$cid] ?? 0));
      $archivedColleges[] = $row;
    }
  }

  $archivedPrograms = [];
  foreach ($programs as $p) {
    if ((int) $p['is_active'] === 1) {
      continue;
    }
    $cid = (int) $p['college_id'];
    $collegeRow = null;
    foreach ($colleges as $college) {
      if ((int) $college['college_id'] === $cid) {
        $collegeRow = $college;
        break;
      }
    }
    if (!$collegeRow || (int) $collegeRow['is_active'] !== 1) {
      continue;
    }
    $deptId = (int) ($p['department_id'] ?? 0);
    $deptName = '';
    $deptActive = $deptId < 1;
    foreach ($departments as $department) {
      if ((int) $department['department_id'] === $deptId) {
        $deptName = (string) $department['department_name'];
        $deptActive = (int) $department['is_active'] === 1;
        break;
      }
    }
    // A college's own (direct) program does not belong to any department, so a
    // stray department link must neither hide it nor be shown as its parent.
    if ((int) $p['represents_college'] === 1) {
      $deptId = 0;
      $deptName = '';
      $deptActive = true;
    }
    if (!$deptActive) {
      continue;
    }
    $archivedPrograms[] = [
      'id' => public_id('d', (int) $p['program_id']),
      'name' => (string) $p['program_name'],
      'collegeId' => public_id('c', $cid),
      'collegeName' => (string) $collegeRow['college_name'],
      'departmentId' => $deptId > 0 ? public_id('dept', $deptId) : '',
      'departmentName' => $deptName,
      'archivedLabel' => $p['archived_label'] ?: '',
      'usage' => org_program_usage($counts, (int) $p['program_id']),
    ];
  }

  org_attach_usage($pdo, $active, $counts);

  return [
    'active' => $active,
    'archivedColleges' => $archivedColleges,
    'archivedDeptOnly' => $archivedDeptOnly,
    'archivedPrograms' => $archivedPrograms,
  ];
}

/** @param array<int, array<string, mixed>> $active */
function org_attach_usage(PDO $pdo, array &$active, ?array $counts = null): void
{
  $counts = $counts ?? org_usage_counts($pdo);
  foreach ($active as &$college) {
    $cid = (int) parse_id((string) ($college['id'] ?? ''), 'c');
    $college['deanName'] = (string) ($counts['deans'][$cid] ?? '');
    $sections = 0;
    $curricula = 0;
    $drafts = 0;
    $accounts = (int) ($counts['collegeAccounts'][$cid] ?? 0);
    if (is_array($college['program'] ?? null) && !empty($college['program']['id'])) {
      $usage = org_program_usage($counts, (int) parse_id((string) $college['program']['id'], 'd'));
      $college['program']['usage'] = $usage;
      $sections += $usage['sections'];
      $curricula += $usage['curricula'];
      $drafts += $usage['drafts'];
      $accounts += $usage['accounts'];
    }
    $departments = is_array($college['departments'] ?? null) ? $college['departments'] : [];
    foreach ($departments as &$department) {
      $did = (int) parse_id((string) ($department['id'] ?? ''), 'dept');
      $dSections = 0;
      $dCurricula = 0;
      $dDrafts = 0;
      $dAccounts = (int) ($counts['chairs'][$did] ?? 0);
      $programs = is_array($department['programs'] ?? null) ? $department['programs'] : [];
      foreach ($programs as &$program) {
        $usage = org_program_usage($counts, (int) parse_id((string) ($program['id'] ?? ''), 'd'));
        $program['usage'] = $usage;
        $dSections += $usage['sections'];
        $dCurricula += $usage['curricula'];
        $dDrafts += $usage['drafts'];
        $dAccounts += $usage['accounts'];
      }
      unset($program);
      $department['programs'] = $programs;
      $department['usage'] = org_usage_row($dSections, $dCurricula, $dDrafts, $dAccounts);
      $sections += $dSections;
      $curricula += $dCurricula;
      $drafts += $dDrafts;
      $accounts += $dAccounts - (int) ($counts['chairs'][$did] ?? 0);
    }
    unset($department);
    $college['departments'] = $departments;
    $college['usage'] = org_usage_row($sections, $curricula, $drafts, $accounts);
  }
  unset($college);
}

/** @return array{sections: array<int, int>, curricula: array<int, int>, drafts: array<int, int>, accounts: array<int, int>, deans: array<int, string>, chairs: array<int, int>, collegeAccounts: array<int, int>} */
function org_usage_counts(PDO $pdo): array
{
  $out = [
    'sections' => [],
    'curricula' => [],
    'drafts' => [],
    'accounts' => [],
    'deans' => [],
    'chairs' => [],
    'collegeAccounts' => [],
  ];
  foreach ($pdo->query('SELECT program_id, COUNT(*) n FROM sections GROUP BY program_id') as $row) {
    $out['sections'][(int) $row['program_id']] = (int) $row['n'];
  }
  if (schema_table_exists($pdo, 'curricula')) {
    foreach ($pdo->query('SELECT program_id, COUNT(*) n FROM curricula GROUP BY program_id') as $row) {
      $out['curricula'][(int) $row['program_id']] = (int) $row['n'];
    }
  }
  if (schema_table_exists($pdo, 'curriculum_reviews')) {
    foreach ($pdo->query('SELECT program_id, COUNT(*) n FROM curriculum_reviews GROUP BY program_id') as $row) {
      $out['drafts'][(int) $row['program_id']] = (int) $row['n'];
    }
  }
  if (schema_table_exists($pdo, 'user_programs')) {
    foreach ($pdo->query('SELECT program_id, COUNT(*) n FROM user_programs GROUP BY program_id') as $row) {
      $out['accounts'][(int) $row['program_id']] = (int) $row['n'];
    }
  }
  foreach ($pdo->query("SELECT college_id, full_name FROM users WHERE role = 'dean' AND is_active = 1 AND college_id IS NOT NULL ORDER BY full_name") as $row) {
    $cid = (int) $row['college_id'];
    if (!isset($out['deans'][$cid])) {
      $out['deans'][$cid] = (string) $row['full_name'];
    }
  }
  foreach ($pdo->query("SELECT department_id, COUNT(*) n FROM users WHERE role = 'program_chair' AND is_active = 1 AND department_id IS NOT NULL GROUP BY department_id") as $row) {
    $out['chairs'][(int) $row['department_id']] = (int) $row['n'];
  }
  foreach ($pdo->query("SELECT college_id, COUNT(*) n FROM users WHERE is_active = 1 AND college_id IS NOT NULL AND role IN ('dean', 'program_chair') GROUP BY college_id") as $row) {
    $out['collegeAccounts'][(int) $row['college_id']] = (int) $row['n'];
  }
  return $out;
}

/** @param array{sections: array<int, int>, curricula: array<int, int>, drafts: array<int, int>, accounts: array<int, int>} $counts */
function org_program_usage(array $counts, int $programId): array
{
  return org_usage_row(
    (int) ($counts['sections'][$programId] ?? 0),
    (int) ($counts['curricula'][$programId] ?? 0),
    (int) ($counts['drafts'][$programId] ?? 0),
    (int) ($counts['accounts'][$programId] ?? 0)
  );
}

/**
 * Usage of a record that owns several programs, plus the accounts tied to it.
 *
 * @param array<int, array<string, mixed>> $programRows rows with a program_id
 * @return array{sections: int, curricula: int, drafts: int, accounts: int}
 */
function org_programs_usage(array $counts, array $programRows, int $accounts): array
{
  $sections = 0;
  $curricula = 0;
  $drafts = 0;
  foreach ($programRows as $row) {
    $one = org_program_usage($counts, (int) $row['program_id']);
    $sections += $one['sections'];
    $curricula += $one['curricula'];
    $drafts += $one['drafts'];
    $accounts += $one['accounts'];
  }
  return org_usage_row($sections, $curricula, $drafts, $accounts);
}

/** @return array{sections: int, curricula: int, drafts: int, accounts: int} */
function org_usage_row(int $sections, int $curricula, int $drafts, int $accounts): array
{
  return [
    'sections' => $sections,
    'curricula' => $curricula,
    'drafts' => $drafts,
    'accounts' => $accounts,
  ];
}

function org_usage_sentence(array $usage): string
{
  $parts = [];
  $sections = (int) ($usage['sections'] ?? 0);
  $curricula = (int) ($usage['curricula'] ?? 0);
  $drafts = (int) ($usage['drafts'] ?? 0);
  $accounts = (int) ($usage['accounts'] ?? 0);
  if ($sections > 0) {
    $parts[] = $sections . ($sections === 1 ? ' section' : ' sections');
  }
  if ($curricula > 0) {
    $parts[] = $curricula . ($curricula === 1 ? ' curriculum' : ' curricula');
  }
  if ($drafts > 0) {
    $parts[] = $drafts . ($drafts === 1 ? ' curriculum draft' : ' curriculum drafts');
  }
  if ($accounts > 0) {
    $parts[] = $accounts . ($accounts === 1 ? ' account' : ' accounts');
  }
  return implode(', ', $parts);
}

function org_program_usage_live(PDO $pdo, int $programId): array
{
  $counts = org_usage_counts($pdo);
  return org_program_usage($counts, $programId);
}

function add_college(PDO $pdo, string $name, string $code, bool $useDepartments): array
{
  $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
  if ($name === '') {
    throw new RuntimeException('Enter a name for the college.');
  }
  $stmt = $pdo->prepare('SELECT college_name FROM colleges WHERE college_name = ? LIMIT 1');
  $stmt->execute([$name]);
  if ($stmt->fetch()) {
    throw new RuntimeException('A college called "' . $name . '" already exists.');
  }
  $code = strtoupper(trim($code));
  if ($code === '') {
    $code = code_from_name($name);
  }
  $code = unique_code($pdo, 'colleges', 'college_code', $code, null, 'college_id');
  $ins = $pdo->prepare(
    'INSERT INTO colleges (college_code, college_name, use_departments, is_active) VALUES (?, ?, ?, 1)'
  );
  $ins->execute([$code, $name, $useDepartments ? 1 : 0]);
  $id = (int) $pdo->lastInsertId();
  if (!$useDepartments) {
    ensure_sole_program($pdo, $id, $name, $code);
  }
  return [
    'id' => public_id('c', $id),
    'name' => $name,
    'code' => $code,
    'useDepartments' => $useDepartments,
    'departments' => [],
  ];
}

function add_department(PDO $pdo, string $collegeId, string $name): array
{
  $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
  if ($name === '') {
    throw new RuntimeException('Enter a name for the department.');
  }
  $cid = parse_id($collegeId, 'c');
  if (!$cid) {
    throw new RuntimeException('Pick a college first.');
  }
  $col = $pdo->prepare('SELECT college_id, college_code, college_name FROM colleges WHERE college_id = ?');
  $col->execute([$cid]);
  $college = $col->fetch();
  if (!$college) {
    throw new RuntimeException('Pick a college first.');
  }
  $dup = $pdo->prepare(
    'SELECT department_name FROM departments WHERE college_id = ? AND department_name = ? AND is_active = 1 LIMIT 1'
  );
  $dup->execute([$cid, $name]);
  if ($dup->fetch()) {
    throw new RuntimeException('"' . $name . '" is already under this college.');
  }
  $pdo->prepare('UPDATE colleges SET use_departments = 1 WHERE college_id = ?')->execute([$cid]);
  $deptCode = unique_code(
    $pdo,
    'departments',
    'department_code',
    $college['college_code'] . '-' . code_from_name($name, 6),
    null,
    'department_id'
  );
  $insDept = $pdo->prepare(
    'INSERT INTO departments (college_id, department_code, department_name, is_active) VALUES (?, ?, ?, 1)'
  );
  $insDept->execute([$cid, $deptCode, $name]);
  $deptId = (int) $pdo->lastInsertId();
  $program = insert_program($pdo, $cid, $deptId, $name, (string) $college['college_code']);
  return [
    'id' => public_id('dept', $deptId),
    'name' => $name,
    'programs' => [$program],
  ];
}

function add_program(PDO $pdo, string $departmentId, string $name): array
{
  $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
  if ($name === '') {
    throw new RuntimeException('Enter a name for the program.');
  }
  $did = parse_id($departmentId, 'dept');
  if (!$did) {
    throw new RuntimeException('Pick a department first.');
  }
  $dept = $pdo->prepare(
    'SELECT d.department_id, d.college_id, c.college_code
     FROM departments d
     JOIN colleges c ON c.college_id = d.college_id
     WHERE d.department_id = ? AND d.is_active = 1'
  );
  $dept->execute([$did]);
  $row = $dept->fetch();
  if (!$row) {
    throw new RuntimeException('Pick a department first.');
  }
  $dup = $pdo->prepare(
    'SELECT program_id FROM programs WHERE department_id = ? AND program_name = ? AND is_active = 1 LIMIT 1'
  );
  $dup->execute([$did, $name]);
  if ($dup->fetch()) {
    throw new RuntimeException('"' . $name . '" is already under this department.');
  }
  return insert_program($pdo, (int) $row['college_id'], $did, $name, (string) $row['college_code']);
}

function insert_program(PDO $pdo, int $collegeId, int $departmentId, string $name, string $collegeCode): array
{
  $code = unique_code(
    $pdo,
    'programs',
    'program_code',
    $collegeCode . '-' . code_from_name($name, 6),
    null,
    'program_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO programs (college_id, department_id, program_code, program_name, represents_college, normal_years, is_active)
     VALUES (?, ?, ?, ?, 0, 4, 1)'
  );
  $ins->execute([$collegeId, $departmentId, $code, $name]);
  return [
    'id' => public_id('d', (int) $pdo->lastInsertId()),
    'name' => $name,
  ];
}

function direct_program_id(PDO $pdo, int $collegeId): int
{
  $stmt = $pdo->prepare(
    'SELECT program_id FROM programs
     WHERE college_id = ? AND represents_college = 1 AND is_active = 1
     LIMIT 1'
  );
  $stmt->execute([$collegeId]);
  $id = (int) $stmt->fetchColumn();
  if ($id > 0) {
    return $id;
  }
  $fallback = $pdo->prepare(
    'SELECT program_id FROM programs
     WHERE college_id = ? AND department_id IS NULL AND is_active = 1
     ORDER BY program_id
     LIMIT 1'
  );
  $fallback->execute([$collegeId]);
  return (int) $fallback->fetchColumn();
}

/**
 * uq_programs_sole allows one represents_college = 1 row per college, archived or not.
 * An archived direct program keeps that flag, so before another one is saved the
 * archived rows give the slot up. They stay archived (and listed as archived programs).
 */
function release_direct_program_slot(PDO $pdo, int $collegeId, int $exceptProgramId): void
{
  $pdo->prepare(
    'UPDATE programs
     SET represents_college = 0, department_id = NULL
     WHERE college_id = ? AND represents_college = 1 AND is_active = 0 AND program_id <> ?'
  )->execute([$collegeId, $exceptProgramId]);
}

function upsert_direct_program(PDO $pdo, int $collegeId, array $program, string $collegeCode): int
{
  $name = trim((string) ($program['name'] ?? ''));
  if ($name === '') {
    throw new RuntimeException('Enter a program name.');
  }
  $id = parse_id((string) ($program['id'] ?? ''), 'd');
  if (!$id) {
    $id = direct_program_id($pdo, $collegeId);
  }
  $dup = $pdo->prepare(
    'SELECT program_id FROM programs
     WHERE college_id = ? AND program_name = ? AND is_active = 1 AND program_id <> ?
     LIMIT 1'
  );
  $dup->execute([$collegeId, $name, $id ?: 0]);
  if ($dup->fetch()) {
    throw new RuntimeException('"' . $name . '" is already under this college.');
  }
  if ($id) {
    $exists = $pdo->prepare('SELECT program_id FROM programs WHERE program_id = ?');
    $exists->execute([$id]);
    if ($exists->fetch()) {
      release_direct_program_slot($pdo, $collegeId, $id);
      $pdo->prepare(
        'UPDATE programs
         SET college_id = ?, department_id = NULL, program_name = ?, represents_college = 1, archived_label = NULL, is_active = 1
         WHERE program_id = ?'
      )->execute([$collegeId, $name, $id]);
      return $id;
    }
  }
  release_direct_program_slot($pdo, $collegeId, 0);
  $code = unique_code(
    $pdo,
    'programs',
    'program_code',
    ($collegeCode !== '' ? $collegeCode : 'P') . '-' . code_from_name($name, 6),
    null,
    'program_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO programs (college_id, department_id, program_code, program_name, represents_college, normal_years, is_active)
     VALUES (?, NULL, ?, ?, 1, 4, 1)'
  );
  $ins->execute([$collegeId, $code, $name]);
  return (int) $pdo->lastInsertId();
}

function ensure_sole_program(PDO $pdo, int $collegeId, string $collegeName, string $collegeCode): int
{
  return upsert_direct_program($pdo, $collegeId, ['name' => $collegeName], $collegeCode);
}

function insert_department(PDO $pdo, int $collegeId, string $name, string $collegeCode): int
{
  $code = unique_code(
    $pdo,
    'departments',
    'department_code',
    $collegeCode . '-' . code_from_name($name, 6),
    null,
    'department_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO departments (college_id, department_code, department_name, is_active) VALUES (?, ?, ?, 1)'
  );
  $ins->execute([$collegeId, $code, $name]);
  return (int) $pdo->lastInsertId();
}

function org_keep_direct_program_when_departments_start(PDO $pdo, array &$seenPrograms, array $seenDepartments): void
{
  $rows = $pdo->query(
    'SELECT p.program_id, p.college_id
     FROM programs p
     JOIN colleges c ON c.college_id = p.college_id
     WHERE p.is_active = 1 AND c.is_active = 1 AND c.use_departments = 1
       AND (p.represents_college = 1 OR p.department_id IS NULL)'
  )->fetchAll();
  foreach ($rows as $row) {
    $pid = (int) $row['program_id'];
    if (isset($seenPrograms[$pid])) {
      continue;
    }
    $dept = $pdo->prepare(
      'SELECT department_id FROM departments
       WHERE college_id = ? AND is_active = 1
       ORDER BY department_id
       LIMIT 1'
    );
    $dept->execute([(int) $row['college_id']]);
    $deptId = (int) $dept->fetchColumn();
    if ($deptId < 1 || !isset($seenDepartments[$deptId])) {
      $seenPrograms[$pid] = true;
      continue;
    }
    $pdo->prepare(
      'UPDATE programs
       SET department_id = ?, represents_college = 0, is_active = 1, archived_label = NULL
       WHERE program_id = ?'
    )->execute([$deptId, $pid]);
    $seenPrograms[$pid] = true;
  }
}

/** Colleges, departments, and programs are sensitive: the Records Officer archives, only the University Registrar deletes. */
function org_require_registrar_to_delete(PDO $pdo): void
{
  $me = session_db_user($pdo);
  if (($me['role'] ?? '') !== 'super_admin') {
    throw new RuntimeException('Only the University Registrar can delete this. Archive it instead; it can be restored later.');
  }
}

function org_delete(PDO $pdo, string $action, string $publicId): void
{
  if ($action === 'deleteProgram') {
    $pid = parse_id($publicId, 'd');
    if (!$pid) {
      throw new RuntimeException('That program could not be found.');
    }
    $name = $pdo->prepare('SELECT program_name FROM programs WHERE program_id = ?');
    $name->execute([$pid]);
    $label = $name->fetchColumn();
    if ($label === false) {
      throw new RuntimeException('That program could not be found.');
    }
    $label = (string) $label;
    $why = org_usage_sentence(org_program_usage_live($pdo, $pid));
    if ($why !== '') {
      throw new RuntimeException($label . ' is still used by ' . $why . '. Archive it instead. Archiving hides it from new work and keeps the sections, curricula, and schedules.');
    }
    $pdo->prepare('DELETE FROM programs WHERE program_id = ?')->execute([$pid]);
    record_transaction($pdo, 'Delete', 'program', 'Deleted program ' . $label . '.');
    return;
  }
  if ($action === 'deleteDepartment') {
    $did = parse_id($publicId, 'dept');
    if (!$did) {
      throw new RuntimeException('That department could not be found.');
    }
    $name = $pdo->prepare('SELECT department_name FROM departments WHERE department_id = ?');
    $name->execute([$did]);
    $label = $name->fetchColumn();
    if ($label === false) {
      throw new RuntimeException('That department could not be found.');
    }
    $label = (string) $label;
    $usage = org_usage_row(0, 0, 0, 0);
    $programs = $pdo->prepare('SELECT program_id FROM programs WHERE department_id = ?');
    $programs->execute([$did]);
    foreach ($programs->fetchAll() as $row) {
      $one = org_program_usage_live($pdo, (int) $row['program_id']);
      $usage['sections'] += $one['sections'];
      $usage['curricula'] += $one['curricula'];
      $usage['drafts'] += $one['drafts'];
      $usage['accounts'] += $one['accounts'];
    }
    $chairs = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND is_active = 1 AND role = 'program_chair'");
    $chairs->execute([$did]);
    $usage['accounts'] += (int) $chairs->fetchColumn();
    $why = org_usage_sentence($usage);
    if ($why !== '') {
      throw new RuntimeException($label . ' is still used by ' . $why . '. Archive it instead. Archiving hides it from new work and keeps the sections, curricula, and schedules.');
    }
    $pdo->prepare('DELETE FROM programs WHERE department_id = ?')->execute([$did]);
    $pdo->prepare('DELETE FROM departments WHERE department_id = ?')->execute([$did]);
    record_transaction($pdo, 'Delete', 'department', 'Deleted department ' . $label . '.');
    return;
  }
  if ($action === 'deleteCollege') {
    $cid = parse_id($publicId, 'c');
    if (!$cid) {
      throw new RuntimeException('That college could not be found.');
    }
    $name = $pdo->prepare('SELECT college_name FROM colleges WHERE college_id = ?');
    $name->execute([$cid]);
    $label = $name->fetchColumn();
    if ($label === false) {
      throw new RuntimeException('That college could not be found.');
    }
    $label = (string) $label;
    $usage = org_usage_row(0, 0, 0, 0);
    $programs = $pdo->prepare('SELECT program_id FROM programs WHERE college_id = ?');
    $programs->execute([$cid]);
    foreach ($programs->fetchAll() as $row) {
      $one = org_program_usage_live($pdo, (int) $row['program_id']);
      $usage['sections'] += $one['sections'];
      $usage['curricula'] += $one['curricula'];
      $usage['drafts'] += $one['drafts'];
      $usage['accounts'] += $one['accounts'];
    }
    $people = $pdo->prepare("SELECT COUNT(*) FROM users WHERE college_id = ? AND is_active = 1 AND role IN ('dean', 'program_chair')");
    $people->execute([$cid]);
    $usage['accounts'] += (int) $people->fetchColumn();
    $why = org_usage_sentence($usage);
    if ($why !== '') {
      throw new RuntimeException($label . ' is still used by ' . $why . '. Archive it instead. Archiving hides it from new work and keeps the sections, curricula, and schedules.');
    }
    $pdo->prepare('DELETE FROM programs WHERE college_id = ?')->execute([$cid]);
    $pdo->prepare('DELETE FROM departments WHERE college_id = ?')->execute([$cid]);
    $pdo->prepare('DELETE FROM colleges WHERE college_id = ?')->execute([$cid]);
    record_transaction($pdo, 'Delete', 'college', 'Deleted college ' . $label . '.');
  }
}

function sync_org(PDO $pdo, array $doc): void
{
  $active = is_array($doc['active'] ?? null) ? $doc['active'] : [];
  $archived = is_array($doc['archivedColleges'] ?? null) ? $doc['archivedColleges'] : [];
  $archivedDepts = is_array($doc['archivedDeptOnly'] ?? null) ? $doc['archivedDeptOnly'] : [];

  $seenColleges = [];
  $seenPrograms = [];
  $seenDepartments = [];

  foreach ($active as $c) {
    $cid = upsert_college($pdo, $c, true);
    $seenColleges[$cid] = true;
    $useDept = !empty($c['useDepartments']);
    $depts = is_array($c['departments'] ?? null) ? $c['departments'] : [];
    if ($useDept) {
      foreach ($depts as $d) {
        [$deptId, $programIds] = upsert_department_with_programs($pdo, $cid, $d, $c, true);
        $seenDepartments[$deptId] = true;
        foreach ($programIds as $pid) {
          $seenPrograms[$pid] = true;
        }
      }
      if (!$depts) {
        $keep = direct_program_id($pdo, $cid);
        if ($keep) {
          $seenPrograms[$keep] = true;
        }
      }
    } else {
      $program = is_array($c['program'] ?? null) ? $c['program'] : null;
      $pname = trim((string) ($program['name'] ?? ''));
      if ($pname !== '') {
        $seenPrograms[upsert_direct_program($pdo, $cid, $program, (string) ($c['code'] ?? ''))] = true;
      } elseif (!array_key_exists('program', $c)) {
        $keep = direct_program_id($pdo, $cid);
        if ($keep) {
          $seenPrograms[$keep] = true;
        }
      }
    }
  }

  foreach ($archived as $c) {
    $cid = upsert_college($pdo, $c, false);
    $seenColleges[$cid] = true;
    $depts = is_array($c['departments'] ?? null) ? $c['departments'] : [];
    foreach ($depts as $d) {
      [$deptId, $programIds] = upsert_department_with_programs($pdo, $cid, $d, $c, false);
      $seenDepartments[$deptId] = true;
      foreach ($programIds as $pid) {
        $seenPrograms[$pid] = true;
      }
    }
  }

  foreach ($archivedDepts as $d) {
    $deptId = parse_id((string) ($d['id'] ?? ''), 'dept');
    if (!$deptId) {
      $deptId = parse_id((string) ($d['id'] ?? ''), 'd');
      if ($deptId) {
        $pdo->prepare('UPDATE programs SET is_active = 0, archived_label = ? WHERE program_id = ?')
          ->execute([(string) ($d['archivedLabel'] ?? ''), $deptId]);
        $seenPrograms[$deptId] = true;
      }
      continue;
    }
    $label = (string) ($d['archivedLabel'] ?? '');
    $pdo->prepare('UPDATE departments SET is_active = 0, archived_label = ? WHERE department_id = ?')
      ->execute([$label, $deptId]);
    $pdo->prepare('UPDATE programs SET is_active = 0, archived_label = ? WHERE department_id = ?')
      ->execute([$label, $deptId]);
    $seenDepartments[$deptId] = true;
    foreach ($pdo->query('SELECT program_id FROM programs WHERE department_id = ' . (int) $deptId)->fetchAll() as $row) {
      $seenPrograms[(int) $row['program_id']] = true;
    }
  }

  foreach (is_array($doc['archivedPrograms'] ?? null) ? $doc['archivedPrograms'] : [] as $program) {
    if (!is_array($program)) {
      continue;
    }
    $pid = parse_id((string) ($program['id'] ?? ''), 'd');
    if (!$pid) {
      continue;
    }
    $pdo->prepare('UPDATE programs SET is_active = 0, archived_label = ? WHERE program_id = ?')
      ->execute([(string) ($program['archivedLabel'] ?? ''), $pid]);
    $seenPrograms[$pid] = true;
  }

  org_keep_direct_program_when_departments_start($pdo, $seenPrograms, $seenDepartments);

  foreach ($pdo->query('SELECT college_id FROM colleges')->fetchAll() as $row) {
    $cid = (int) $row['college_id'];
    if (!isset($seenColleges[$cid])) {
      $pdo->prepare('UPDATE colleges SET is_active = 0 WHERE college_id = ?')->execute([$cid]);
    }
  }
  foreach ($pdo->query('SELECT program_id FROM programs')->fetchAll() as $row) {
    $pid = (int) $row['program_id'];
    if (!isset($seenPrograms[$pid])) {
      $pdo->prepare('UPDATE programs SET is_active = 0 WHERE program_id = ?')->execute([$pid]);
    }
  }
  foreach ($pdo->query('SELECT department_id FROM departments')->fetchAll() as $row) {
    $did = (int) $row['department_id'];
    if (!isset($seenDepartments[$did])) {
      $pdo->prepare('UPDATE departments SET is_active = 0 WHERE department_id = ?')->execute([$did]);
    }
  }
}

function upsert_department_with_programs(PDO $pdo, int $collegeId, array $d, array $college, bool $active): array
{
  $name = trim((string) ($d['name'] ?? ''));
  if ($name === '') {
    throw new RuntimeException('Enter a name for the department.');
  }
  $label = (string) ($d['archivedLabel'] ?? $college['archivedLabel'] ?? '');
  $deptId = parse_id((string) ($d['id'] ?? ''), 'dept');
  $collegeCode = (string) ($college['code'] ?? 'P');
  $known = false;
  if ($deptId) {
    $exists = $pdo->prepare('SELECT department_id FROM departments WHERE department_id = ?');
    $exists->execute([$deptId]);
    $known = (bool) $exists->fetch();
  }
  if (!$known) {
    $byName = $pdo->prepare(
      'SELECT department_id FROM departments WHERE college_id = ? AND department_name = ? ORDER BY is_active DESC, department_id LIMIT 1'
    );
    $byName->execute([$collegeId, $name]);
    $deptId = (int) $byName->fetchColumn();
    $known = $deptId > 0;
  }
  if ($known) {
    $pdo->prepare(
      'UPDATE departments SET college_id = ?, department_name = ?, archived_label = ?, is_active = ? WHERE department_id = ?'
    )->execute([$collegeId, $name, $active ? null : $label, $active ? 1 : 0, $deptId]);
  } else {
    $deptId = insert_department($pdo, $collegeId, $name, $collegeCode);
  }

  $programsProvided = array_key_exists('programs', $d);
  $programs = is_array($d['programs'] ?? null) ? $d['programs'] : [];
  if (!$programsProvided) {
    $existingPrograms = $pdo->prepare(
      'SELECT program_id, program_name FROM programs WHERE department_id = ? AND is_active = 1 ORDER BY program_name'
    );
    $existingPrograms->execute([$deptId]);
    foreach ($existingPrograms->fetchAll() as $existingProgram) {
      $programs[] = [
        'id' => public_id('d', (int) $existingProgram['program_id']),
        'name' => (string) $existingProgram['program_name'],
      ];
    }
  }
  if (!$programs && !$programsProvided) {
    $programs = [['id' => '', 'name' => $name]];
  }
  $ids = [];
  foreach ($programs as $p) {
    if (!is_array($p)) {
      continue;
    }
    $pname = trim((string) ($p['name'] ?? ''));
    if ($pname === '') {
      continue;
    }
    $pid = parse_id((string) ($p['id'] ?? ''), 'd');
    $programKnown = false;
    if ($pid) {
      $exists = $pdo->prepare('SELECT program_id FROM programs WHERE program_id = ?');
      $exists->execute([$pid]);
      $programKnown = (bool) $exists->fetch();
    }
    if (!$programKnown) {
      $byName = $pdo->prepare(
        'SELECT program_id FROM programs WHERE college_id = ? AND program_name = ? ORDER BY is_active DESC, program_id LIMIT 1'
      );
      $byName->execute([$collegeId, $pname]);
      $pid = (int) $byName->fetchColumn();
      $programKnown = $pid > 0;
    }
    if ($programKnown) {
      $pdo->prepare(
        'UPDATE programs SET college_id = ?, department_id = ?, program_name = ?, represents_college = 0, archived_label = ?, is_active = ?
         WHERE program_id = ?'
      )->execute([$collegeId, $deptId, $pname, $active ? null : $label, $active ? 1 : 0, $pid]);
      $ids[] = $pid;
      continue;
    }
    $created = insert_program($pdo, $collegeId, $deptId, $pname, $collegeCode);
    $ids[] = (int) parse_id($created['id'], 'd');
  }
  if (!$ids && !$programsProvided) {
    $created = insert_program($pdo, $collegeId, $deptId, $name, $collegeCode);
    $ids[] = (int) parse_id($created['id'], 'd');
  }
  return [$deptId, $ids];
}

function upsert_college(PDO $pdo, array $c, bool $active): int
{
  $name = trim((string) ($c['name'] ?? ''));
  $code = strtoupper(trim((string) ($c['code'] ?? '')));
  $useDept = !empty($c['useDepartments']);
  $label = (string) ($c['archivedLabel'] ?? '');
  $id = parse_id((string) ($c['id'] ?? ''), 'c');

  if ($id) {
    $exists = $pdo->prepare('SELECT college_id FROM colleges WHERE college_id = ?');
    $exists->execute([$id]);
    if ($exists->fetch()) {
      if ($code === '') {
        $code = code_from_name($name);
      }
      $code = unique_code($pdo, 'colleges', 'college_code', $code, $id, 'college_id');
      $pdo->prepare(
        'UPDATE colleges SET college_code = ?, college_name = ?, use_departments = ?, archived_label = ?, is_active = ?
         WHERE college_id = ?'
      )->execute([$code, $name, $useDept ? 1 : 0, $active ? null : $label, $active ? 1 : 0, $id]);
      return $id;
    }
  }

  if ($code === '') {
    $code = code_from_name($name);
  }
  $code = unique_code($pdo, 'colleges', 'college_code', $code, null, 'college_id');
  $ins = $pdo->prepare(
    'INSERT INTO colleges (college_code, college_name, use_departments, archived_label, is_active)
     VALUES (?, ?, ?, ?, ?)'
  );
  $ins->execute([$code, $name, $useDept ? 1 : 0, $active ? null : $label, $active ? 1 : 0]);
  return (int) $pdo->lastInsertId();
}

function upsert_program(PDO $pdo, int $collegeId, array $d, array $college, bool $represents, bool $active): int
{
  $name = trim((string) ($d['name'] ?? ''));
  $id = parse_id((string) ($d['id'] ?? ''), 'd');
  $label = (string) ($d['archivedLabel'] ?? $college['archivedLabel'] ?? '');

  if ($id) {
    $exists = $pdo->prepare('SELECT program_id FROM programs WHERE program_id = ?');
    $exists->execute([$id]);
    if ($exists->fetch()) {
      $pdo->prepare(
        'UPDATE programs SET college_id = ?, program_name = ?, represents_college = ?, archived_label = ?, is_active = ?
         WHERE program_id = ?'
      )->execute([$collegeId, $name, $represents ? 1 : 0, $active ? null : $label, $active ? 1 : 0, $id]);
      return $id;
    }
  }

  $code = unique_code(
    $pdo,
    'programs',
    'program_code',
    ((string) ($college['code'] ?? 'P')) . '-' . code_from_name($name, 6),
    null,
    'program_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO programs (college_id, program_code, program_name, represents_college, normal_years, archived_label, is_active)
     VALUES (?, ?, ?, ?, 4, ?, ?)'
  );
  $ins->execute([$collegeId, $code, $name, $represents ? 1 : 0, $active ? null : $label, $active ? 1 : 0]);
  return (int) $pdo->lastInsertId();
}

function org_audit_state(PDO $pdo): array
{
  $colleges = [];
  foreach ($pdo->query('SELECT college_id, college_code, college_name, use_departments, is_active FROM colleges') as $row) {
    $colleges[(int) $row['college_id']] = [
      'code' => (string) $row['college_code'],
      'name' => (string) $row['college_name'],
      'useDepartments' => (int) $row['use_departments'] === 1,
      'active' => (int) $row['is_active'] === 1,
    ];
  }
  $departments = [];
  foreach ($pdo->query('SELECT department_id, college_id, department_name, is_active FROM departments') as $row) {
    $departments[(int) $row['department_id']] = [
      'collegeId' => (int) $row['college_id'],
      'name' => (string) $row['department_name'],
      'active' => (int) $row['is_active'] === 1,
    ];
  }
  $programs = [];
  foreach ($pdo->query('SELECT program_id, college_id, department_id, program_name, represents_college, is_active FROM programs') as $row) {
    $programs[(int) $row['program_id']] = [
      'collegeId' => (int) $row['college_id'],
      'departmentId' => (int) ($row['department_id'] ?? 0),
      'name' => (string) $row['program_name'],
      'represents' => (int) $row['represents_college'] === 1,
      'active' => (int) $row['is_active'] === 1,
    ];
  }
  return ['colleges' => $colleges, 'departments' => $departments, 'programs' => $programs];
}

/** The PUT: save the whole document, log what changed, and archive curricula of newly archived programs. */
function org_save_document(PDO $pdo, array $doc): void
{
  $before = org_audit_state($pdo);
  sync_org($pdo, $doc);
  $after = org_audit_state($pdo);
  foreach (org_audit_changes($before, $after) as $change) {
    record_transaction($pdo, $change['action'], $change['area'], $change['summary']);
  }
  org_archive_curricula_of_archived_programs($pdo, $before, $after);
  org_restore_children_of_restored_colleges($pdo, $before, $after);
  org_restore_programs_of_restored_departments($pdo, $before, $after);
}

/**
 * Restoring a department brings back all of its programs too, including ones archived
 * on their own earlier; archive again whatever should stay unused. Curricula are not
 * restored. A department restored together with its college is handled there.
 */
function org_restore_programs_of_restored_departments(PDO $pdo, array $before, array $after): void
{
  $restore = $pdo->prepare('UPDATE programs SET is_active = 1, archived_label = NULL WHERE department_id = ? AND is_active = 0');
  foreach ($after['departments'] as $id => $dept) {
    $was = $before['departments'][$id] ?? null;
    if (!$was || $was['active'] || !$dept['active']) {
      continue;
    }
    $restore->execute([(int) $id]);
    $n = $restore->rowCount();
    if ($n > 0) {
      record_transaction(
        $pdo,
        'Unarchive',
        'department',
        'Restored ' . $n . ($n === 1 ? ' program' : ' programs') . ' under ' . org_department_name($after, (int) $id) . ' along with the department.'
      );
    }
  }
}

/**
 * Restoring a college brings back everything under it: all its departments and their
 * programs (or, for a college without departments, its own program), including ones that
 * were archived on their own earlier. The Records Officer or Registrar then archives
 * whatever should stay unused. Curricula are not restored (each is restored on its own).
 * Only the change itself triggers this, so later saves never undo an archive.
 */
function org_restore_children_of_restored_colleges(PDO $pdo, array $before, array $after): void
{
  foreach ($after['colleges'] as $id => $college) {
    $was = $before['colleges'][$id] ?? null;
    if (!$was || $was['active'] || !$college['active']) {
      continue;
    }
    $id = (int) $id;
    $departments = 0;
    $programs = 0;
    if ($college['useDepartments']) {
      $stmt = $pdo->prepare('UPDATE departments SET is_active = 1, archived_label = NULL WHERE college_id = ? AND is_active = 0');
      $stmt->execute([$id]);
      $departments = $stmt->rowCount();
      $stmt = $pdo->prepare(
        'UPDATE programs SET is_active = 1, archived_label = NULL
         WHERE is_active = 0 AND department_id IN (SELECT department_id FROM departments WHERE college_id = ?)'
      );
      $stmt->execute([$id]);
      $programs = $stmt->rowCount();
    } else {
      // one program stands for the whole college
      $active = $pdo->prepare('SELECT COUNT(*) FROM programs WHERE college_id = ? AND is_active = 1');
      $active->execute([$id]);
      if ((int) $active->fetchColumn() === 0) {
        $pick = $pdo->prepare(
          'SELECT program_id FROM programs
           WHERE college_id = ? AND is_active = 0 AND (represents_college = 1 OR department_id IS NULL)
           ORDER BY represents_college DESC, program_id
           LIMIT 1'
        );
        $pick->execute([$id]);
        $pid = (int) $pick->fetchColumn();
        if ($pid > 0) {
          $pdo->prepare('UPDATE programs SET is_active = 1, archived_label = NULL WHERE program_id = ?')->execute([$pid]);
          $programs = 1;
        }
      }
    }
    if ($departments > 0 || $programs > 0) {
      $parts = [];
      if ($departments > 0) {
        $parts[] = $departments . ($departments === 1 ? ' department' : ' departments');
      }
      if ($programs > 0) {
        $parts[] = $programs . ($programs === 1 ? ' program' : ' programs');
      }
      record_transaction(
        $pdo,
        'Unarchive',
        'college',
        'Restored ' . implode(' and ', $parts) . ' under ' . org_college_name($after, $id) . ' along with the college.'
      );
    }
  }
}

/**
 * A program that goes from active to archived (on its own, or because its department
 * or college was archived) takes its active curricula with it. Restoring the program
 * does not restore them: the Registrar or Records Officer unarchives each curriculum.
 * Only the change itself triggers this, so a curriculum restored later stays restored.
 */
function org_archive_curricula_of_archived_programs(PDO $pdo, array $before, array $after): void
{
  if (!schema_table_exists($pdo, 'curricula')) {
    return;
  }
  $archive = $pdo->prepare(
    "UPDATE curricula SET status = 'archived', updated_at = CURRENT_TIMESTAMP
     WHERE program_id = ? AND status = 'active'"
  );
  foreach ($after['programs'] as $id => $program) {
    $was = $before['programs'][$id] ?? null;
    if (!$was || !$was['active'] || $program['active']) {
      continue;
    }
    $archive->execute([(int) $id]);
    $n = $archive->rowCount();
    if ($n > 0) {
      $name = trim((string) $program['name']) !== '' ? (string) $program['name'] : 'a program';
      record_transaction(
        $pdo,
        'Archive',
        'curriculum',
        'Archived ' . $n . ($n === 1 ? ' curriculum' : ' curricula') . ' of ' . $name . ' because the program was archived.'
      );
    }
  }
}

function org_college_name(array $state, int $collegeId): string
{
  $row = $state['colleges'][$collegeId] ?? null;
  if (!$row) {
    return 'a college';
  }
  $name = trim((string) $row['name']);
  return $name !== '' ? $name : 'a college';
}

function org_department_name(array $state, int $departmentId): string
{
  $row = $state['departments'][$departmentId] ?? null;
  if (!$row) {
    return 'a department';
  }
  $name = trim((string) $row['name']);
  return $name !== '' ? $name : 'a department';
}

function org_audit_changes(array $before, array $after): array
{
  $out = [];
  $collegeArchived = [];
  $newColleges = [];
  foreach ($after['colleges'] as $id => $college) {
    $name = trim((string) $college['name']) !== '' ? (string) $college['name'] : 'a college';
    if (!isset($before['colleges'][$id])) {
      if ($college['active']) {
        $out[] = ['action' => 'Add', 'area' => 'college', 'summary' => 'Added college ' . $name . '.'];
        $newColleges[$id] = $name;
      }
      continue;
    }
    $was = $before['colleges'][$id];
    $wasName = trim((string) $was['name']) !== '' ? (string) $was['name'] : $name;
    if ($was['active'] && !$college['active']) {
      $out[] = ['action' => 'Archive', 'area' => 'college', 'summary' => 'Archived college ' . $wasName . '.'];
      $collegeArchived[$id] = true;
      continue;
    }
    if (!$was['active'] && $college['active']) {
      $out[] = ['action' => 'Unarchive', 'area' => 'college', 'summary' => 'Restored college ' . $name . '.'];
    }
    if (!$college['active']) {
      continue;
    }
    $parts = [];
    if ($wasName !== $name) {
      $parts[] = 'name ' . $wasName . ' to ' . $name;
    }
    if ((string) $was['code'] !== (string) $college['code']) {
      $parts[] = 'code ' . $was['code'] . ' to ' . $college['code'];
    }
    if ((bool) $was['useDepartments'] !== (bool) $college['useDepartments']) {
      $parts[] = $college['useDepartments'] ? 'now uses departments' : 'no longer uses departments';
    }
    if ($parts) {
      $out[] = ['action' => 'Edit', 'area' => 'college', 'summary' => 'Edited college ' . $wasName . ': ' . implode('; ', $parts) . '.'];
    }
  }

  $departmentArchived = [];
  $newDepartments = [];
  foreach ($after['departments'] as $id => $dept) {
    $name = trim((string) $dept['name']) !== '' ? (string) $dept['name'] : 'a department';
    $collegeName = org_college_name($after, (int) $dept['collegeId']);
    if (!isset($before['departments'][$id])) {
      if ($dept['active']) {
        $out[] = ['action' => 'Add', 'area' => 'department', 'summary' => 'Added department ' . $name . ' under ' . $collegeName . '.'];
        $newDepartments[$id] = $name;
      }
      continue;
    }
    $was = $before['departments'][$id];
    $wasName = trim((string) $was['name']) !== '' ? (string) $was['name'] : $name;
    $wasCollege = org_college_name($before, (int) $was['collegeId']);
    if ($was['active'] && !$dept['active']) {
      if (empty($collegeArchived[(int) $was['collegeId']]) && empty($collegeArchived[(int) $dept['collegeId']])) {
        $out[] = ['action' => 'Archive', 'area' => 'department', 'summary' => 'Archived department ' . $wasName . ' under ' . $wasCollege . '.'];
      }
      $departmentArchived[$id] = true;
      continue;
    }
    if (!$was['active'] && $dept['active']) {
      $out[] = ['action' => 'Unarchive', 'area' => 'department', 'summary' => 'Restored department ' . $name . ' under ' . $collegeName . '.'];
    }
    if (!$dept['active']) {
      continue;
    }
    $parts = [];
    if ($wasName !== $name) {
      $parts[] = 'name ' . $wasName . ' to ' . $name;
    }
    if ((int) $was['collegeId'] !== (int) $dept['collegeId']) {
      $parts[] = 'moved from ' . $wasCollege . ' to ' . $collegeName;
    }
    if ($parts) {
      $out[] = ['action' => 'Edit', 'area' => 'department', 'summary' => 'Edited department ' . $wasName . ': ' . implode('; ', $parts) . '.'];
    }
  }

  foreach ($after['programs'] as $id => $program) {
    $name = trim((string) $program['name']) !== '' ? (string) $program['name'] : 'a program';
    $collegeName = org_college_name($after, (int) $program['collegeId']);
    $deptName = (int) $program['departmentId'] > 0 ? org_department_name($after, (int) $program['departmentId']) : '';
    $place = $deptName !== '' ? $deptName . ', ' . $collegeName : $collegeName;
    if (!isset($before['programs'][$id])) {
      if (!$program['active']) {
        continue;
      }
      if ($program['represents'] && isset($newColleges[(int) $program['collegeId']]) && strcasecmp($name, $newColleges[(int) $program['collegeId']]) === 0) {
        continue;
      }
      if (!$program['represents'] && isset($newDepartments[(int) $program['departmentId']]) && strcasecmp($name, $newDepartments[(int) $program['departmentId']]) === 0) {
        continue;
      }
      $out[] = ['action' => 'Add', 'area' => 'program', 'summary' => 'Added program ' . $name . ' under ' . $place . '.'];
      continue;
    }
    $was = $before['programs'][$id];
    $wasName = trim((string) $was['name']) !== '' ? (string) $was['name'] : $name;
    $wasCollege = org_college_name($before, (int) $was['collegeId']);
    $wasDept = (int) $was['departmentId'] > 0 ? org_department_name($before, (int) $was['departmentId']) : '';
    $wasPlace = $wasDept !== '' ? $wasDept . ', ' . $wasCollege : $wasCollege;
    if ($was['active'] && !$program['active']) {
      $parentArchived = !empty($collegeArchived[(int) $was['collegeId']]) || !empty($collegeArchived[(int) $program['collegeId']]);
      $deptArchived = !empty($departmentArchived[(int) $was['departmentId']]) || !empty($departmentArchived[(int) $program['departmentId']]);
      if (!$parentArchived && !$deptArchived) {
        $out[] = ['action' => 'Archive', 'area' => 'program', 'summary' => 'Archived program ' . $wasName . ' under ' . $wasPlace . '.'];
      }
      continue;
    }
    if (!$was['active'] && $program['active']) {
      $out[] = ['action' => 'Unarchive', 'area' => 'program', 'summary' => 'Restored program ' . $name . ' under ' . $place . '.'];
    }
    if (!$program['active']) {
      continue;
    }
    $parts = [];
    if ($wasName !== $name) {
      $parts[] = 'name ' . $wasName . ' to ' . $name;
    }
    if ($wasPlace !== $place) {
      $parts[] = 'moved from ' . $wasPlace . ' to ' . $place;
    }
    if ($parts) {
      $out[] = ['action' => 'Edit', 'area' => 'program', 'summary' => 'Edited program ' . $wasName . ': ' . implode('; ', $parts) . '.'];
    }
  }
  return $out;
}
