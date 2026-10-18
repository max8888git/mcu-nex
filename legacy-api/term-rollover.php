<?php
declare(strict_types=1);

/*
 * Start next term (Data management → Sections, University Registrar): carries the current
 * term's sections over to the next term, with a preview to edit first.
 *   1st sem → 2nd sem:            every section as is (year level, students, curriculum)
 *   2nd sem → Summer:             only sections whose curriculum has summer subjects for that year
 *   2nd sem / Summer → next 1st:  every section moves up a year and keeps its curriculum; the last
 *                                 year level graduates; new 1st-years use the newest active curriculum
 * Create archives the current term, starts the next one and makes the sections (via
 * save_section_plan, so plans and the Transaction log are the same as making them by hand).
 * Undo works until anything is scheduled in the new term.
 */

require_once __DIR__ . '/bootstrap.php';

function ensure_term_rollover_schema(PDO $pdo): void
{
  // CREATE TABLE commits an open transaction, so only when missing (called before any transaction)
  if (schema_table_exists($pdo, 'term_rollovers')) {
    return;
  }
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS term_rollovers (
      rollover_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      from_term_id INT NOT NULL,
      to_term_id INT NOT NULL,
      sections_created INT NOT NULL DEFAULT 0,
      created_by INT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      undone_at DATETIME NULL,
      KEY idx_term_rollovers_to (to_term_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function rollover_require_registrar(PDO $pdo): array
{
  $me = session_db_user($pdo);
  if (!$me || (string) $me['role'] !== 'super_admin') {
    throw new RuntimeException('Only the University Registrar starts the next term.');
  }
  return $me;
}

function rollover_next_school_year(string $label): string
{
  [$a, $b] = array_map('intval', explode('-', parse_school_year_label($label)));
  return ($a + 1) . '-' . ($b + 1);
}

/** Where the current term can go next: [{schoolYear, termCode, label}]. */
function rollover_targets(array $current): array
{
  $sy = (string) $current['schoolYear'];
  $next = rollover_next_school_year($sy);
  $t = static fn (string $y, string $c) => ['schoolYear' => $y, 'termCode' => $c, 'label' => term_offering_label($y, $c)];
  return match ((string) $current['termCode']) {
    '1' => [$t($sy, '2')],
    '2' => [$t($next, '1'), $t($sy, 'summer')],
    default => [$t($next, '1')],
  };
}

function rollover_term_id(PDO $pdo, string $schoolYear, string $termCode, bool $create): int
{
  $stmt = $pdo->prepare('SELECT t.term_id FROM terms t JOIN school_years sy ON sy.school_year_id = t.school_year_id WHERE sy.label = ? AND t.term_code = ?');
  $stmt->execute([$schoolYear, $termCode]);
  $id = (int) $stmt->fetchColumn();
  if ($id || !$create) {
    return $id;
  }
  return ensure_term($pdo, ensure_school_year($pdo, $schoolYear), $termCode);
}

/** Year levels with summer subjects, per curriculum: [curriculum_id => [year => true]]. */
function rollover_summer_years(PDO $pdo): array
{
  $out = [];
  foreach ($pdo->query("SELECT DISTINCT curriculum_id, year_level FROM curriculum_courses WHERE term_code = 'summer'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $out[(int) $r['curriculum_id']][(int) $r['year_level']] = true;
  }
  return $out;
}

/** The newest active curriculum of each active program: [program_id => [id, name]]. */
function rollover_newest_curricula(PDO $pdo): array
{
  $out = [];
  foreach ($pdo->query(
    "SELECT cu.curriculum_id, cu.program_id, cu.curriculum_name, cu.academic_year
     FROM curricula cu JOIN programs p ON p.program_id = cu.program_id
     WHERE cu.status = 'active' AND p.is_active = 1
     ORDER BY cu.academic_year DESC, cu.curriculum_id DESC"
  )->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $out[(int) $r['program_id']] ??= ['id' => public_id('cur', (int) $r['curriculum_id']), 'name' => (string) $r['curriculum_name']];
  }
  return $out;
}

/** What Create would make for the chosen target, plus warnings. Nothing is saved. */
function rollover_preview(PDO $pdo, array $body): array
{
  rollover_require_registrar($pdo);
  $current = current_offering_term($pdo);
  if (!$current) {
    throw new RuntimeException('There is no current term. Start one in Offering term first.');
  }
  $targets = rollover_targets($current);
  $pick = null;
  foreach ($targets as $t) {
    if ($t['schoolYear'] === (string) ($body['schoolYear'] ?? '') && $t['termCode'] === (string) ($body['termCode'] ?? '')) {
      $pick = $t;
    }
  }
  $pick ??= $targets[0];
  $newYear = $pick['schoolYear'] !== $current['schoolYear'];

  // the sections to start from: the current term's (from summer to a new year: the 2nd semester's,
  // which has every year level)
  $sourceTermId = (int) $current['termId'];
  if ($newYear && (string) $current['termCode'] === 'summer') {
    $second = rollover_term_id($pdo, (string) $current['schoolYear'], '2', false);
    if ($second && term_section_count($pdo, $second) > 0) {
      $sourceTermId = $second;
    }
  }
  $stmt = $pdo->prepare(
    "SELECT s.section_id, s.program_id, s.curriculum_id, s.year_level, s.section_code, s.student_count,
            p.program_name, p.normal_years, c.college_name, cu.curriculum_name, cu.status AS curriculum_status
     FROM sections s
     JOIN programs p ON p.program_id = s.program_id
     JOIN colleges c ON c.college_id = p.college_id
     JOIN curricula cu ON cu.curriculum_id = s.curriculum_id
     WHERE s.term_id = ? AND s.is_retake = 0 AND p.is_active = 1
     ORDER BY c.college_name, p.program_name, s.year_level, s.section_code"
  );
  $stmt->execute([$sourceTermId]);
  $summerYears = $pick['termCode'] === 'summer' ? rollover_summer_years($pdo) : [];
  $sections = [];
  $graduating = [];
  $skipped = 0;
  $warnings = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $year = (int) $r['year_level'];
    $years = max(1, (int) ($r['normal_years'] ?: 4));
    if ($newYear) {
      if ($year >= $years) {
        $graduating[] = ['programName' => (string) $r['program_name'], 'yearLevel' => $year, 'code' => (string) $r['section_code'], 'students' => (int) $r['student_count']];
        continue;
      }
      $year++;
    } elseif ($pick['termCode'] === 'summer' && empty($summerYears[(int) $r['curriculum_id']][$year])) {
      $skipped++;
      continue;
    }
    $item = [
      'programId' => public_id('d', (int) $r['program_id']),
      'programName' => (string) $r['program_name'],
      'collegeName' => (string) $r['college_name'],
      'yearLevel' => $year,
      'code' => (string) $r['section_code'],
      'students' => (int) $r['student_count'],
      'curriculumId' => public_id('cur', (int) $r['curriculum_id']),
      'curriculumName' => (string) $r['curriculum_name'],
    ];
    if ((string) $r['curriculum_status'] === 'archived') {
      $warnings[] = $r['program_name'] . ' ' . $year . '-' . $r['section_code'] . ' follows an archived curriculum (' . $r['curriculum_name'] . '). Restore it or pick another.';
    }
    $sections[] = $item;
  }

  // new 1st-years (a new school year only): every active program, with its newest active curriculum
  $firstYears = [];
  if ($newYear && $pick['termCode'] === '1') {
    $newest = rollover_newest_curricula($pdo);
    foreach ($pdo->query(
      'SELECT p.program_id, p.program_name, c.college_name FROM programs p JOIN colleges c ON c.college_id = p.college_id
       WHERE p.is_active = 1 AND c.is_active = 1 ORDER BY c.college_name, p.program_name'
    )->fetchAll(PDO::FETCH_ASSOC) as $p) {
      $cur = $newest[(int) $p['program_id']] ?? null;
      $firstYears[] = ['programId' => public_id('d', (int) $p['program_id']), 'programName' => (string) $p['program_name'],
        'collegeName' => (string) $p['college_name'], 'students' => 0, 'curriculumId' => $cur['id'] ?? null, 'curriculumName' => $cur['name'] ?? ''];
      if (!$cur) {
        $warnings[] = $p['program_name'] . ' has no active curriculum, so it can’t get new 1st-year sections.';
      }
    }
  }

  // curriculums each program may use (the preview's dropdowns)
  $choices = [];
  foreach ($pdo->query("SELECT curriculum_id, program_id, curriculum_name, academic_year FROM curricula WHERE status = 'active' ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $choices[public_id('d', (int) $c['program_id'])][] = ['id' => public_id('cur', (int) $c['curriculum_id']), 'name' => (string) $c['curriculum_name']];
  }

  $toId = rollover_term_id($pdo, $pick['schoolYear'], $pick['termCode'], false);
  if ($toId && term_section_count($pdo, $toId) > 0) {
    $warnings[] = $pick['label'] . ' already has sections, so it can’t be started from here.';
  }
  return [
    'from' => ['schoolYear' => $current['schoolYear'], 'termCode' => $current['termCode'], 'label' => term_offering_label($current['schoolYear'], $current['termCode'])],
    'targets' => $targets,
    'target' => $pick,
    'newYear' => $newYear,
    'sections' => $sections,
    'firstYears' => $firstYears,
    'graduating' => $graduating,
    'summerSkipped' => $skipped,
    'curricula' => (object) $choices,
    'warnings' => array_values(array_unique($warnings)),
  ];
}

/** Archive the current term, start the target one, and make the reviewed sections. */
function rollover_create(PDO $pdo, array $body): array
{
  $me = rollover_require_registrar($pdo);
  $preview = rollover_preview($pdo, $body);
  $target = $preview['target'];
  if ($target['schoolYear'] !== (string) ($body['schoolYear'] ?? '') || $target['termCode'] !== (string) ($body['termCode'] ?? '')) {
    throw new RuntimeException('Pick the next term again; that one can’t follow the current term.');
  }
  $existing = rollover_term_id($pdo, $target['schoolYear'], $target['termCode'], false);
  if ($existing && term_section_count($pdo, $existing) > 0) {
    throw new RuntimeException($target['label'] . ' already has sections, so it can’t be started from here.');
  }

  // group the reviewed sections: one plan per program, year level (one curriculum each)
  $groups = [];
  foreach ((array) ($body['sections'] ?? []) as $s) {
    $students = max(0, (int) ($s['students'] ?? 0));
    $year = (int) ($s['yearLevel'] ?? 0);
    $code = trim((string) ($s['code'] ?? ''));
    $prog = (string) ($s['programId'] ?? '');
    $cur = (string) ($s['curriculumId'] ?? '');
    if ($prog === '' || $cur === '' || $code === '' || $year < 1 || $year > 6) {
      continue;
    }
    $key = $prog . '|' . $year;
    $groups[$key] ??= ['programId' => $prog, 'yearLevel' => $year, 'curriculumId' => $cur, 'sections' => []];
    if ($groups[$key]['curriculumId'] !== $cur) {
      throw new RuntimeException('Every section of one program and year level must follow the same curriculum.');
    }
    $groups[$key]['sections'][] = ['code' => $code, 'studentCount' => $students];
  }
  $firstYears = [];
  foreach ((array) ($body['firstYears'] ?? []) as $f) {
    $students = (int) ($f['students'] ?? 0);
    if ($students > 0 && !empty($f['curriculumId']) && !empty($f['programId'])) {
      if (isset($groups[(string) $f['programId'] . '|1'])) {
        throw new RuntimeException('A program has both carried-over and new 1st-year sections. Keep one.');
      }
      $firstYears[] = ['programId' => (string) $f['programId'], 'curriculumId' => (string) $f['curriculumId'], 'students' => $students];
    }
  }
  if (!$groups && !$firstYears) {
    throw new RuntimeException('There are no sections to create. Add students to at least one section.');
  }

  // the current term ends; the next one begins
  $from = current_offering_term($pdo);
  archive_offering($pdo, []);
  set_current_offering($pdo, ['schoolYear' => $target['schoolYear'], 'termCode' => $target['termCode']]);
  $toId = rollover_term_id($pdo, $target['schoolYear'], $target['termCode'], true);
  $termBody = ['schoolYear' => $target['schoolYear'], 'termCode' => $target['termCode']];

  $made = 0;
  foreach ($groups as $g) {
    $total = array_sum(array_column($g['sections'], 'studentCount'));
    if ($total < 1) {
      continue; // a year level with no students left isn't made
    }
    $saved = save_section_plan($pdo, $termBody + ['departmentId' => $g['programId'], 'yearLevel' => $g['yearLevel'],
      'curriculumId' => $g['curriculumId'], 'studentCount' => $total, 'sections' => $g['sections']]);
    $made += (int) ($saved['savedCount'] ?? 0);
  }
  foreach ($firstYears as $f) {
    $saved = save_section_plan($pdo, $termBody + ['departmentId' => $f['programId'], 'yearLevel' => 1,
      'curriculumId' => $f['curriculumId'], 'studentCount' => $f['students']]);
    $made += (int) ($saved['savedCount'] ?? 0);
  }

  $pdo->prepare('INSERT INTO term_rollovers (from_term_id, to_term_id, sections_created, created_by) VALUES (?, ?, ?, ?)')
    ->execute([(int) $from['termId'], $toId, $made, (int) $me['user_id']]);
  record_transaction($pdo, 'Add', 'section', 'Started ' . $target['label'] . ' from ' . term_offering_label($from['schoolYear'], $from['termCode'])
    . ': ' . $made . ($made === 1 ? ' section' : ' sections') . ' created.');
  return ['started' => $target, 'sectionsCreated' => $made];
}

/** The last Start next term, while it can still be undone (nothing scheduled in the new term yet). */
function rollover_undoable(PDO $pdo): ?array
{
  if (!schema_table_exists($pdo, 'term_rollovers')) {
    return null;
  }
  $current = current_offering_term($pdo);
  if (!$current) {
    return null;
  }
  $stmt = $pdo->prepare('SELECT * FROM term_rollovers WHERE to_term_id = ? AND undone_at IS NULL ORDER BY rollover_id DESC LIMIT 1');
  $stmt->execute([(int) $current['termId']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    return null;
  }
  $toId = (int) $row['to_term_id'];
  if (schema_table_exists($pdo, 'class_offerings')) {
    $n = $pdo->prepare('SELECT COUNT(*) FROM class_offerings WHERE term_id = ?');
    $n->execute([$toId]);
    if ((int) $n->fetchColumn() > 0) {
      return null;
    }
  }
  $copies = $pdo->prepare("SELECT payload FROM schedule_copies WHERE term_id = ? AND kind = 'workspace'");
  $copies->execute([$toId]);
  foreach ($copies->fetchAll(PDO::FETCH_COLUMN) as $payload) {
    $snap = json_decode((string) $payload, true);
    if (!empty($snap['classes'])) {
      return null;
    }
  }
  $from = $pdo->prepare('SELECT t.term_code, sy.label FROM terms t JOIN school_years sy ON sy.school_year_id = t.school_year_id WHERE t.term_id = ?');
  $from->execute([(int) $row['from_term_id']]);
  $f = $from->fetch(PDO::FETCH_ASSOC);
  return ['id' => (int) $row['rollover_id'], 'sections' => (int) $row['sections_created'], 'at' => $row['created_at'],
    'from' => $f ? ['schoolYear' => (string) $f['label'], 'termCode' => (string) $f['term_code'], 'label' => term_offering_label((string) $f['label'], (string) $f['term_code'])] : null];
}

/** Remove the sections it made and make the previous term current again. */
function rollover_undo(PDO $pdo): array
{
  $me = rollover_require_registrar($pdo);
  $undo = rollover_undoable($pdo);
  if (!$undo || !$undo['from']) {
    throw new RuntimeException('There’s nothing to undo: classes were already scheduled in this term, or it wasn’t started with Start next term.');
  }
  $current = current_offering_term($pdo);
  clear_active_sections($pdo);
  unarchive_offering($pdo, ['schoolYear' => $undo['from']['schoolYear'], 'termCode' => $undo['from']['termCode']]);
  $pdo->prepare('UPDATE term_rollovers SET undone_at = NOW() WHERE rollover_id = ?')->execute([$undo['id']]);
  record_transaction($pdo, 'Delete', 'section', 'Undid Start next term: removed ' . term_offering_label($current['schoolYear'], $current['termCode'])
    . ' and made ' . $undo['from']['label'] . ' current again.');
  return ['undone' => true, 'current' => $undo['from'], 'by' => (int) $me['user_id']];
}
