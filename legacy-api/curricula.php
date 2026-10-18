<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function run_curricula_request(): void
{
$pdo = db();
ensure_curriculum_hours_writable($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  // Reports → Finished curriculums (curriculum-archive.php)
  if (!empty($_GET['finished']) || !empty($_GET['finished_id'])) {
    try {
      json_ok(!empty($_GET['finished_id'])
        ? curriculum_finished_detail($pdo, (int) parse_id((string) $_GET['finished_id'], 'cur'))
        : curriculum_finished_overview($pdo));
    } catch (Throwable $e) {
      registrar_reraise($e);
      json_error($e->getMessage(), 400);
    }
  }
  $id = parse_id((string) ($_GET['id'] ?? ''), 'cur');
  if ($id) {
    $row = curriculum_by_id($pdo, $id);
    if (!$row) {
      json_error('Curriculum not found', 404);
    }
    json_ok(['curriculum' => $row]);
  }
  json_ok(['curricula' => list_curricula($pdo)]);
}

if ($method === 'POST' || $method === 'PUT') {
  $body = json_input();
  try {
    $pdo->beginTransaction();
    $saved = save_curriculum($pdo, $body);
    if (empty($saved['conflict']) && !empty($saved['curriculum']) && is_array($saved['curriculum'])) {
      $cur = $saved['curriculum'];
      $where = trim((string) ($cur['departmentName'] ?? ''));
      $courses = is_array($cur['courses'] ?? null) ? count($cur['courses']) : 0;
      $courseWord = $courses === 1 ? '1 course' : $courses . ' courses';
      $place = $where !== '' ? ' for ' . $where : '';
      if (!empty($saved['replaced'])) {
        record_transaction(
          $pdo,
          'Edit',
          'curriculum',
          'Replaced curriculum ' . $cur['name'] . $place . ' (' . $courseWord . ').'
        );
      } else {
        record_transaction(
          $pdo,
          'Add',
          'curriculum',
          'Added curriculum ' . $cur['name'] . $place . ' (' . $courseWord . ').'
        );
      }
    }
    $pdo->commit();
    json_ok($saved);
  } catch (Throwable $e) {
    registrar_reraise($e);
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_error($e->getMessage(), 400);
  }
}

if ($method === 'PATCH') {
  $body = json_input();
  $id = parse_id((string) ($body['id'] ?? $_GET['id'] ?? ''), 'cur');
  if (!$id) {
    json_error('Curriculum id is required');
  }
  $before = curriculum_by_id($pdo, $id);
  if (!$before) {
    json_error('Curriculum not found', 404);
  }
  try {
    $pdo->beginTransaction();
    $hasCourses = array_key_exists('courses', $body);
    if (!$hasCourses && isset($body['status'])) {
      curriculum_archive_role($pdo);
      $status = (string) $body['status'] === 'archived' ? 'archived' : 'active';
      $pdo->prepare('UPDATE curricula SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE curriculum_id = ?')
        ->execute([$status, $id]);
      if ($before['status'] !== $status) {
        record_transaction(
          $pdo,
          $status === 'archived' ? 'Archive' : 'Unarchive',
          'curriculum',
          ($status === 'archived' ? 'Archived curriculum ' : 'Restored curriculum ') . $before['name'] . '.'
        );
      }
    } else {
      replace_courses($pdo, $id, $body['courses'] ?? []);
      $pdo->prepare('UPDATE curricula SET updated_at = CURRENT_TIMESTAMP WHERE curriculum_id = ?')->execute([$id]);
      $count = is_array($body['courses'] ?? null) ? count($body['courses']) : 0;
      record_transaction(
        $pdo,
        'Edit',
        'curriculum',
        'Edited courses on curriculum ' . $before['name'] . ' (' . $count . ' courses).'
      );
    }
    $pdo->commit();
    json_ok(['curriculum' => curriculum_by_id($pdo, $id)]);
  } catch (Throwable $e) {
    registrar_reraise($e);
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_error($e->getMessage(), 400);
  }
}

// curriculums are records: archived first (Reports → Finished curriculums). The University Registrar can delete
// an archived one that was never used (a wrong upload, a duplicate); see curriculum_delete in curriculum-archive.php
if ($method === 'DELETE') {
  $id = parse_id((string) ($_GET['id'] ?? ''), 'cur');
  if (!$id) {
    json_error('Curriculum id is required');
  }
  try {
    $pdo->beginTransaction();
    $deleted = curriculum_delete($pdo, $id);
    $pdo->commit();
    json_ok(['deleted' => $deleted]);
  } catch (Throwable $e) {
    registrar_reraise($e);
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_error($e->getMessage(), 400);
  }
}

json_error('Unsupported method', 405);
}

if (!defined('REGISTRAR_LIBRARY')) {
  run_curricula_request();
}

function list_curricula(PDO $pdo): array
{
  $ids = $pdo->query('SELECT curriculum_id FROM curricula ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_COLUMN);
  $out = [];
  foreach ($ids as $id) {
    $row = curriculum_by_id($pdo, (int) $id);
    if ($row) {
      $out[] = $row;
    }
  }
  return $out;
}

function curriculum_by_id(PDO $pdo, int $id): ?array
{
  $stmt = $pdo->prepare(
    'SELECT cu.curriculum_id, cu.program_id, cu.curriculum_name, cu.academic_year, cu.source_file_name,
            cu.status, cu.created_at, cu.updated_at,
            p.program_name, p.represents_college, p.program_id,
            c.college_id, c.college_name
     FROM curricula cu
     JOIN programs p ON p.program_id = cu.program_id
     JOIN colleges c ON c.college_id = p.college_id
     WHERE cu.curriculum_id = ?'
  );
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  if (!$row) {
    return null;
  }

  $courses = $pdo->prepare(
    'SELECT curriculum_course_id, course_id, course_title, lecture_units, lab_units, total_units, total_hours_week, total_hours_sem,
            weeks_in_term, year_level, term_code, lecture_room_type, lab_room_type,
            prerequisite_1, prerequisite_2, prerequisite_3
     FROM curriculum_courses
     WHERE curriculum_id = ?
     ORDER BY year_level, term_order, display_order, course_id'
  );
  $courses->execute([$id]);
  $list = [];
  foreach ($courses as $co) {
    $list[] = [
      'id' => public_id('cc', (int) $co['curriculum_course_id']),
      'course_id' => $co['course_id'],
      'course_title' => $co['course_title'],
      'lecture_units' => (float) $co['lecture_units'],
      'lab_units' => (float) $co['lab_units'],
      'total_units' => (float) $co['total_units'],
      'total_hours_week' => (float) $co['total_hours_week'],
      'total_hours_sem' => (float) $co['total_hours_sem'],
      'weeks_in_term' => (int) $co['weeks_in_term'],
      'year_level' => (int) $co['year_level'],
      'semester' => $co['term_code'],
      'lecture_room_type' => $co['lecture_room_type'] ?: '',
      'lab_room_type' => $co['lab_room_type'] ?: '',
      'prerequisite_1' => $co['prerequisite_1'] ?: '',
      'prerequisite_2' => $co['prerequisite_2'] ?: '',
      'prerequisite_3' => $co['prerequisite_3'] ?: '',
    ];
  }

  return [
    'id' => public_id('cur', (int) $row['curriculum_id']),
    'collegeId' => public_id('c', (int) $row['college_id']),
    'collegeName' => $row['college_name'],
    'departmentId' => public_id('d', (int) $row['program_id']),
    'departmentName' => trim((string) ($row['program_name'] ?? '')) !== ''
      ? (string) $row['program_name']
      : (string) ($row['college_name'] ?? ''),
    'name' => $row['curriculum_name'],
    'academicYear' => $row['academic_year'],
    'sourceFileName' => $row['source_file_name'] ?: '',
    'status' => ($row['status'] === 'archived') ? 'archived' : 'active',
    'createdAt' => date('c', strtotime((string) $row['created_at'])),
    'updatedAt' => date('c', strtotime((string) $row['updated_at'])),
    'courses' => $list,
  ];
}

function resolve_program_id(PDO $pdo, array $meta): int
{
  $collegeId = parse_id((string) ($meta['collegeId'] ?? ''), 'c');
  $departmentId = parse_id((string) ($meta['departmentId'] ?? ''), 'd');
  if ($departmentId) {
    $stmt = $pdo->prepare('SELECT program_id FROM programs WHERE program_id = ?');
    $stmt->execute([$departmentId]);
    $id = $stmt->fetchColumn();
    if (!$id) {
      throw new RuntimeException('That department was not found.');
    }
    return (int) $id;
  }
  if (!$collegeId) {
    throw new RuntimeException('A college is required.');
  }
  $stmt = $pdo->prepare(
    'SELECT program_id FROM programs WHERE college_id = ? AND represents_college = 1 LIMIT 1'
  );
  $stmt->execute([$collegeId]);
  $id = $stmt->fetchColumn();
  if ($id) {
    return (int) $id;
  }
  $col = $pdo->prepare('SELECT college_name, college_code FROM colleges WHERE college_id = ?');
  $col->execute([$collegeId]);
  $college = $col->fetch();
  if (!$college) {
    throw new RuntimeException('That college was not found.');
  }
  $code = unique_code($pdo, 'programs', 'program_code', $college['college_code'], null, 'program_id');
  $ins = $pdo->prepare(
    'INSERT INTO programs (college_id, program_code, program_name, represents_college, normal_years, is_active)
     VALUES (?, ?, ?, 1, 4, 1)'
  );
  $ins->execute([$collegeId, $code, $college['college_name']]);
  return (int) $pdo->lastInsertId();
}

function save_curriculum(PDO $pdo, array $body): array
{
  $meta = $body['meta'] ?? $body;
  $courses = $body['courses'] ?? [];
  $replace = !empty($body['replace']);
  $year = trim((string) ($meta['academicYear'] ?? ''));
  if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $m) || (int) $m[2] !== (int) $m[1] + 1) {
    throw new RuntimeException('Enter a valid academic year, for example 2026-2027.');
  }
  $programId = resolve_program_id($pdo, $meta);
  ensure_school_year($pdo, $year);

  $existing = $pdo->prepare('SELECT curriculum_id FROM curricula WHERE program_id = ? AND academic_year = ?');
  $existing->execute([$programId, $year]);
  $curId = $existing->fetchColumn();

  if ($curId && !$replace) {
    return [
      'ok' => false,
      'conflict' => curriculum_by_id($pdo, (int) $curId),
    ];
  }

  $name = trim((string) ($meta['departmentName'] ?? $meta['collegeName'] ?? 'Curriculum')) . ' ' . $year;
  $file = (string) ($meta['sourceFileName'] ?? '');
  $startYear = (int) $m[1];

  if ($curId) {
    $pdo->prepare(
      'UPDATE curricula SET curriculum_name = ?, effective_year = ?, source_file_name = ?, status = ? WHERE curriculum_id = ?'
    )->execute([$name, $startYear, $file, 'active', (int) $curId]);
    $id = (int) $curId;
    $replaced = true;
  } else {
    $ins = $pdo->prepare(
      'INSERT INTO curricula (program_id, curriculum_name, academic_year, effective_year, status, source_file_name)
       VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$programId, $name, $year, $startYear, 'active', $file]);
    $id = (int) $pdo->lastInsertId();
    $replaced = false;
  }

  replace_courses($pdo, $id, $courses);
  return [
    'replaced' => $replaced,
    'curriculum' => curriculum_by_id($pdo, $id),
  ];
}

function curriculum_course_hours(float $lec, float $lab, string $term, $week, $semHours): array
{
  $weeks = $term === 'summer' ? 6.0 : 18.0;
  $computedWeek = round((($lec * 1.0 + $lab * 3.0) * 18.0) / $weeks, 2);
  $computedSem = round(($lec * 1.0 + $lab * 3.0) * 18.0, 2);
  $fileWeek = (float) $week;
  if ($fileWeek > 0) {
    $fileSem = (float) $semHours;
    return [
      'week' => round($fileWeek, 2),
      'sem' => $fileSem > 0 ? round($fileSem, 2) : round($fileWeek * $weeks, 2),
    ];
  }
  return ['week' => $computedWeek, 'sem' => $computedSem];
}

function replace_courses(PDO $pdo, int $curriculumId, array $courses): void
{
  $pdo->prepare('DELETE FROM curriculum_courses WHERE curriculum_id = ?')->execute([$curriculumId]);
  $ins = $pdo->prepare(
    'INSERT INTO curriculum_courses (
       curriculum_id, year_level, term_code, course_id, course_title,
       lecture_units, lab_units, lecture_room_type, lab_room_type,
       prerequisite_1, prerequisite_2, prerequisite_3, display_order,
       total_hours_week, total_hours_sem, is_elective
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );

  $order = 0;
  $inserted = [];
  foreach ($courses as $c) {
    $code = trim((string) ($c['course_id'] ?? $c['course_code'] ?? ''));
    $title = trim((string) ($c['course_title'] ?? ''));
    if ($code === '' || $title === '') {
      continue;
    }
    $term = (string) ($c['semester'] ?? '1');
    if ($term !== '1' && $term !== '2' && $term !== 'summer') {
      $term = '1';
    }
    $year = (int) ($c['year_level'] ?? 1);
    if ($year < 1 || $year > 8) {
      $year = 1;
    }
    $lec = (float) ($c['lecture_units'] ?? 0);
    $lab = (float) ($c['lab_units'] ?? 0);
    $hours = curriculum_course_hours(
      $lec,
      $lab,
      $term,
      $c['total_hours_week'] ?? 0,
      $c['total_hours_sem'] ?? 0
    );
    $p1 = trim((string) ($c['prerequisite_1'] ?? '')) ?: null;
    $p2 = trim((string) ($c['prerequisite_2'] ?? '')) ?: null;
    $p3 = trim((string) ($c['prerequisite_3'] ?? '')) ?: null;
    $lecType = trim((string) ($c['lecture_room_type'] ?? ''));
    $lecType = $lecType !== '' ? canonical_room_type_name($lecType) : null;
    $labType = trim((string) ($c['lab_room_type'] ?? ''));
    $labType = $labType !== '' ? canonical_room_type_name($labType) : null;
    $ins->execute([
      $curriculumId, $year, $term, $code, $title, $lec, $lab,
      $lecType, $labType, $p1, $p2, $p3, $order++,
      $hours['week'], $hours['sem'], !empty($c['is_elective']) ? 1 : 0,
    ]);
    $rowId = (int) $pdo->lastInsertId();
    $inserted[$code] = ['id' => $rowId, 'course' => $c, 'p1' => $p1, 'p2' => $p2, 'p3' => $p3, 'lecType' => $lecType, 'labType' => $labType];
  }

  $prereq = $pdo->prepare(
    'INSERT IGNORE INTO course_prerequisites (curriculum_course_id, prereq_course_id, slot) VALUES (?, ?, ?)'
  );
  $req = $pdo->prepare(
    'INSERT IGNORE INTO course_room_requirements (curriculum_course_id, component, room_type_id, priority) VALUES (?, ?, ?, 1)'
  );

  foreach ($inserted as $row) {
    foreach ([1 => $row['p1'], 2 => $row['p2'], 3 => $row['p3']] as $slot => $code) {
      if ($code && isset($inserted[$code]) && $inserted[$code]['id'] !== $row['id']) {
        $prereq->execute([$row['id'], $inserted[$code]['id'], $slot]);
      }
    }
    if ($row['lecType']) {
      $typeId = find_room_type_id($pdo, $row['lecType']);
      if ($typeId) {
        $req->execute([$row['id'], 'lecture', $typeId]);
      }
    }
    if ($row['labType']) {
      $typeId = find_room_type_id($pdo, $row['labType']);
      if ($typeId) {
        $req->execute([$row['id'], 'laboratory', $typeId]);
      }
    }
  }
}
