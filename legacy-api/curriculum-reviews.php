<?php
declare(strict_types=1);

/**
 * Curriculums made by the colleges and checked by the registrars.
 *
 *   Program Chair (or the Dean, for a college without departments) drafts
 *   → sends to the Dean → Dean approves or sends back (note required)
 *   → Dean sends to the Registrar → Records Officer or University Registrar
 *   accepts (it becomes the active curriculum used for sectioning) or sends
 *   back (note required). After acceptance the Registrar can ask for a
 *   revision, which opens a new version; the accepted one stays active until
 *   the revision is accepted.
 *
 * Drafts live in curriculum_reviews (courses as JSON). The live tables
 * (curricula, curriculum_courses) only change when a version is accepted,
 * through save_curriculum() in curricula.php, the same path the CSV upload uses.
 *
 * GET  curriculum-reviews.php?mine=1     programs and versions this Dean / Program Chair handles
 * GET  curriculum-reviews.php?inbox=1    what the registrars review
 * GET  curriculum-reviews.php?id=rv-1    one version with its courses and checks
 * POST curriculum-reviews.php { action, ... }   see curriculum_review_action()
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/curricula.php';

const CURRICULUM_REVIEW_OPEN = ['draft', 'pending_dean', 'dean_approved', 'pending_registrar'];

function run_curriculum_reviews_request(): void
{
  $pdo = db();
  ensure_curriculum_reviews_schema($pdo);
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $me = curriculum_review_user($pdo);
  if (!$me) {
    json_error('Log in again to continue.', 401);
  }

  if ($method === 'GET') {
    try {
      if (!empty($_GET['id'])) {
        json_ok(['review' => curriculum_review_full($pdo, curriculum_review_row($pdo, (int) parse_id((string) $_GET['id'], 'rv')), $me)]);
      }
      if (!empty($_GET['inbox'])) {
        json_ok(curriculum_review_inbox($pdo, $me));
      }
      json_ok(curriculum_review_mine($pdo, $me));
    } catch (Throwable $e) {
      registrar_reraise($e);
      json_error($e->getMessage(), 400);
    }
  }

  if ($method === 'POST') {
    $body = json_input();
    $action = (string) ($body['action'] ?? '');
    try {
      $pdo->beginTransaction();
      $out = curriculum_review_action($pdo, $me, $action, $body);
      $pdo->commit();
      json_ok($out);
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
  run_curriculum_reviews_request();
}

function ensure_curriculum_reviews_schema(PDO $pdo): void
{
  // CREATE / ALTER commit an open transaction in MySQL, so only when missing (called before any transaction)
  if (schema_table_exists($pdo, 'curriculum_reviews')) {
    // what was sent back (subjects as they were + notes on single subjects), for "what changed" and the notes
    if (!schema_column_exists($pdo, 'curriculum_reviews', 'return_json')) {
      $pdo->exec('ALTER TABLE curriculum_reviews ADD COLUMN return_json LONGTEXT NULL AFTER last_return_at');
    }
    // a draft's own name ("With new electives"), so several drafts can be told apart
    if (!schema_column_exists($pdo, 'curriculum_reviews', 'name')) {
      $pdo->exec('ALTER TABLE curriculum_reviews ADD COLUMN name VARCHAR(80) NULL AFTER version');
    }
    return;
  }
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS curriculum_reviews (
      review_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      program_id INT NOT NULL,
      academic_year VARCHAR(9) NOT NULL,
      version INT NOT NULL DEFAULT 1,
      name VARCHAR(80) NULL,
      status ENUM('draft','pending_dean','dean_approved','pending_registrar','accepted','superseded') NOT NULL DEFAULT 'draft',
      courses_json LONGTEXT NULL,
      notes TEXT NULL,
      revision_of INT NULL,
      curriculum_id INT NULL,
      created_by INT NULL,
      updated_by INT NULL,
      sent_dean_at DATETIME NULL,
      dean_by INT NULL,
      dean_at DATETIME NULL,
      sent_registrar_at DATETIME NULL,
      registrar_by INT NULL,
      registrar_at DATETIME NULL,
      last_return_from ENUM('dean','registrar') NULL,
      last_return_note TEXT NULL,
      last_return_at DATETIME NULL,
      return_json LONGTEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_curriculum_reviews_program (program_id, academic_year),
      KEY idx_curriculum_reviews_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

// ----- who is asking -----

/** Signed-in user with role, college and department (from the session, never the request). */
function curriculum_review_user(PDO $pdo): ?array
{
  $u = session_db_user($pdo);
  if (!$u) {
    return null;
  }
  $stmt = $pdo->prepare('SELECT department_id FROM users WHERE user_id = ?');
  $stmt->execute([(int) $u['user_id']]);
  $u['department_id'] = (int) ($stmt->fetchColumn() ?: 0);
  $u['user_id'] = (int) $u['user_id'];
  $u['college_id'] = (int) ($u['college_id'] ?? 0);
  return $u;
}

function curriculum_is_registrar(array $me): bool
{
  return in_array($me['role'], ['admin', 'super_admin'], true);
}

/** Programs a Dean or Program Chair may write curriculums for: program_id => row. */
function curriculum_programs_for(PDO $pdo, array $me): array
{
  $sql = 'SELECT p.program_id, p.program_name, p.department_id, p.represents_college, c.college_id, c.college_name, c.use_departments
          FROM programs p
          JOIN colleges c ON c.college_id = p.college_id
          LEFT JOIN departments d ON d.department_id = p.department_id
          WHERE p.is_active = 1 AND c.is_active = 1 AND (d.department_id IS NULL OR d.is_active = 1)';
  $params = [];
  if ($me['role'] === 'dean') {
    $sql .= ' AND p.college_id = ?';
    $params[] = $me['college_id'];
  } elseif ($me['role'] === 'program_chair') {
    $sql .= ' AND c.use_departments = 1 AND (p.department_id = ?
               OR p.program_id IN (SELECT program_id FROM user_programs WHERE user_id = ?))';
    $params[] = $me['department_id'];
    $params[] = $me['user_id'];
  } elseif (!curriculum_is_registrar($me)) {
    return [];
  }
  $sql .= ' ORDER BY c.college_name, p.program_name';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $out = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $out[(int) $row['program_id']] = $row;
  }
  return $out;
}

function curriculum_program(PDO $pdo, int $programId): array
{
  $stmt = $pdo->prepare(
    'SELECT p.program_id, p.program_name, p.department_id, p.represents_college, c.college_id, c.college_name, c.use_departments
     FROM programs p JOIN colleges c ON c.college_id = p.college_id WHERE p.program_id = ?'
  );
  $stmt->execute([$programId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    throw new RuntimeException('That program was not found.');
  }
  return $row;
}

/** Program Chair for a college with departments; the Dean writes for colleges without. */
function curriculum_may_write(PDO $pdo, array $me, int $programId): bool
{
  return isset(curriculum_programs_for($pdo, $me)[$programId]);
}

// ----- rows -----

function curriculum_review_row(PDO $pdo, int $id): array
{
  if ($id < 1) {
    throw new RuntimeException('Curriculum version not found.');
  }
  $stmt = $pdo->prepare('SELECT * FROM curriculum_reviews WHERE review_id = ?');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    throw new RuntimeException('Curriculum version not found.');
  }
  return $row;
}

function curriculum_review_courses(array $row): array
{
  $list = json_decode((string) ($row['courses_json'] ?? ''), true);
  return is_array($list) ? array_values($list) : [];
}

function curriculum_user_names(PDO $pdo, array $ids): array
{
  $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
  if (!$ids) {
    return [];
  }
  $stmt = $pdo->prepare('SELECT user_id, full_name FROM users WHERE user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
  $stmt->execute($ids);
  $out = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $out[(int) $u['user_id']] = (string) $u['full_name'];
  }
  return $out;
}

/** The draft's name, or "Draft N" for one made before names. */
function curriculum_review_name(array $row): string
{
  $name = trim((string) ($row['name'] ?? ''));
  return $name !== '' ? $name : 'Draft ' . (int) $row['version'];
}

/** A cleaned name for a draft (1–80 characters). */
function curriculum_clean_name(string $name, string $fallback): string
{
  $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
  return $name === '' ? $fallback : mb_substr($name, 0, 80);
}

/** Made by a Dean (not a Program Chair)? */
function curriculum_made_by_dean(PDO $pdo, array $row): bool
{
  if (empty($row['created_by'])) {
    return false;
  }
  $stmt = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
  $stmt->execute([(int) $row['created_by']]);
  return (string) $stmt->fetchColumn() === 'dean';
}

/** A Dean's own draft: kept beside the Program Chair's version, never sent anywhere on its own. */
function curriculum_is_dean_draft(PDO $pdo, array $row): bool
{
  return (string) $row['status'] === 'draft' && curriculum_made_by_dean($pdo, $row);
}

function curriculum_review_public(PDO $pdo, array $row, ?array $program = null): array
{
  $program = $program ?: curriculum_program($pdo, (int) $row['program_id']);
  $courses = curriculum_review_courses($row);
  $names = curriculum_user_names($pdo, [$row['created_by'], $row['updated_by'], $row['dean_by'], $row['registrar_by']]);
  $units = 0.0;
  foreach ($courses as $c) {
    $units += (float) ($c['lec'] ?? 0) + (float) ($c['lab'] ?? 0);
  }
  return [
    'id' => public_id('rv', (int) $row['review_id']),
    'programId' => public_id('d', (int) $row['program_id']),
    'programName' => (string) $program['program_name'],
    'collegeId' => public_id('c', (int) $program['college_id']),
    'collegeName' => (string) $program['college_name'],
    'academicYear' => (string) $row['academic_year'],
    'version' => (int) $row['version'],
    'name' => curriculum_review_name($row),
    'status' => (string) $row['status'],
    'notes' => (string) ($row['notes'] ?? ''),
    'courseCount' => count($courses),
    'totalUnits' => round($units, 2),
    'revisionOf' => $row['revision_of'] ? public_id('rv', (int) $row['revision_of']) : null,
    'curriculumId' => $row['curriculum_id'] ? public_id('cur', (int) $row['curriculum_id']) : null,
    'createdBy' => $names[(int) $row['created_by']] ?? '',
    'madeByDean' => curriculum_made_by_dean($pdo, $row),
    'deanDraft' => curriculum_is_dean_draft($pdo, $row),
    'updatedBy' => $names[(int) $row['updated_by']] ?? '',
    'deanBy' => $names[(int) $row['dean_by']] ?? '',
    'registrarBy' => $names[(int) $row['registrar_by']] ?? '',
    'sentDeanAt' => $row['sent_dean_at'],
    'deanAt' => $row['dean_at'],
    'sentRegistrarAt' => $row['sent_registrar_at'],
    'registrarAt' => $row['registrar_at'],
    'lastReturnFrom' => $row['last_return_from'],
    'lastReturnNote' => $row['last_return_note'],
    'lastReturnAt' => $row['last_return_at'],
    'updatedAt' => $row['updated_at'],
  ];
}

/** One version with courses, checks, what this user may do, and the other versions of the program. */
function curriculum_review_full(PDO $pdo, array $row, array $me): array
{
  $program = curriculum_program($pdo, (int) $row['program_id']);
  if (!curriculum_is_registrar($me) && !curriculum_may_write($pdo, $me, (int) $row['program_id'])) {
    throw new RuntimeException('You can’t open curriculums for this program.');
  }
  if ($me['role'] === 'program_chair' && curriculum_is_dean_draft($pdo, $row)) {
    throw new RuntimeException('That’s the Dean’s own draft.');
  }
  $courses = curriculum_review_courses($row);
  $out = curriculum_review_public($pdo, $row, $program);
  $out['courses'] = $courses;
  $out['problems'] = curriculum_problems($pdo, $courses);
  $out['can'] = curriculum_review_can($pdo, $me, $row, $program);
  $out['hasDepartments'] = (int) $program['use_departments'] === 1;
  // the last send-back: subjects as they were then, and notes on single subjects
  $returned = json_decode((string) ($row['return_json'] ?? ''), true);
  $out['returned'] = is_array($returned) ? $returned : null;
  $hist = $pdo->prepare('SELECT * FROM curriculum_reviews WHERE program_id = ? ORDER BY review_id DESC');
  $hist->execute([(int) $row['program_id']]);
  $rows = $hist->fetchAll(PDO::FETCH_ASSOC);
  // the Dean's own drafts are theirs; nobody else lists them
  if ($me['role'] !== 'dean') {
    $rows = array_values(array_filter($rows, fn ($r) => !curriculum_is_dean_draft($pdo, $r)));
  }
  $out['history'] = array_map(fn ($r) => curriculum_review_public($pdo, $r, $program), $rows);
  return $out;
}

/** What the signed-in user may do with this version right now. */
function curriculum_review_can(PDO $pdo, array $me, array $row, array $program): array
{
  $st = (string) $row['status'];
  $role = $me['role'];
  $mine = curriculum_may_write($pdo, $me, (int) $row['program_id']);
  $dean = $role === 'dean' && $mine;
  $chair = $role === 'program_chair' && $mine;
  $reg = curriculum_is_registrar($me);
  $deanDraft = curriculum_is_dean_draft($pdo, $row);
  return [
    // the Dean can still edit after approving, until it's sent to the Registrar
    'edit' => ($chair && $st === 'draft' && !$deanDraft) || ($dean && in_array($st, ['draft', 'pending_dean', 'dean_approved'], true)),
    'discard' => (($chair && !$deanDraft) || $dean) && $st === 'draft',
    'rename' => ($chair && $st === 'draft' && !$deanDraft) || ($dean && in_array($st, ['draft', 'pending_dean', 'dean_approved'], true)),
    'sendToDean' => $chair && $st === 'draft' && !$deanDraft,
    'deanDecide' => $dean && $st === 'pending_dean',
    // the Dean may send their own draft, or approve and send in one step
    'sendToRegistrar' => $dean && in_array($st, ['draft', 'pending_dean', 'dean_approved'], true),
    'takeBack' => $dean && $st === 'pending_registrar',
    'registrarDecide' => $reg && $st === 'pending_registrar',
    'requestRevision' => $reg && $st === 'accepted',
  ];
}

// ----- the checks (same on the page and on the server) -----

function curriculum_code_key(string $code): string
{
  return strtoupper(preg_replace('/\s+/', ' ', trim($code)) ?? '');
}

function curriculum_term_order(int $year, string $term): int
{
  return $year * 10 + ($term === 'summer' ? 3 : ((int) $term === 2 ? 2 : 1));
}

function curriculum_term_label(int $year, string $term): string
{
  $ord = [1 => '1st', 2 => '2nd', 3 => '3rd'][$year] ?? ($year . 'th');
  return $ord . ' year, ' . ($term === 'summer' ? 'summer' : ((int) $term === 2 ? '2nd semester' : '1st semester'));
}

/** Room types the form may use (names exactly as in Data management). */
function curriculum_room_type_names(PDO $pdo): array
{
  if (!schema_table_exists($pdo, 'room_types')) {
    return [];
  }
  return array_map('strval', $pdo->query('SELECT room_type_name FROM room_types WHERE is_active = 1 ORDER BY room_type_name')->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Problems in a list of courses: errors stop sending, warnings don't.
 * Each: { level: 'error'|'warning', row: index or null, message }.
 */
function curriculum_problems(PDO $pdo, array $courses): array
{
  $types = array_map('strtolower', curriculum_room_type_names($pdo));
  $out = [];
  $add = static function (string $level, ?int $row, string $msg) use (&$out) {
    $out[] = ['level' => $level, 'row' => $row, 'message' => $msg];
  };
  if (!$courses) {
    $add('error', null, 'Add at least one subject.');
    return $out;
  }
  $byCode = [];
  $byTitle = [];
  $termUnits = [];
  foreach ($courses as $i => $c) {
    $code = curriculum_code_key((string) ($c['code'] ?? ''));
    $title = trim((string) ($c['title'] ?? ''));
    $label = $code !== '' ? $code : 'Row ' . ($i + 1);
    if ($code === '') {
      $add('error', $i, ($title !== '' ? $title : 'Row ' . ($i + 1)) . ' has no course code.');
    }
    if ($title === '') {
      $add('error', $i, $label . ' has no course title.');
    }
    if ($code !== '') {
      if (isset($byCode[$code])) {
        $add('error', $i, $code . ' is listed twice. Each course code can be used once.');
      } else {
        $byCode[$code] = $i;
      }
    }
    $lec = $c['lec'] ?? 0;
    $lab = $c['lab'] ?? 0;
    if (!is_numeric($lec) || !is_numeric($lab) || (float) $lec < 0 || (float) $lab < 0 || (float) $lec > 12 || (float) $lab > 12) {
      $add('error', $i, $label . ': lecture and lab units must be numbers from 0 to 12.');
    } elseif ((float) $lec + (float) $lab <= 0) {
      $add('error', $i, $label . ' has 0 units. Give it lecture or lab units.');
    }
    $lecRoom = trim((string) ($c['lecRoom'] ?? ''));
    $labRoom = trim((string) ($c['labRoom'] ?? ''));
    if ((float) $lec > 0 && $lecRoom === '') {
      $add('error', $i, $label . ' has lecture units but no lecture room type.');
    }
    if ((float) $lab > 0 && $labRoom === '') {
      $add('error', $i, $label . ' has lab units but no lab room type.');
    }
    foreach ([$lecRoom, $labRoom] as $t) {
      if ($t !== '' && $types && !in_array(strtolower($t), $types, true)) {
        $add('error', $i, $label . ': room type "' . $t . '" isn’t in Data management → Room types.');
      }
    }
    $year = (int) ($c['year'] ?? 0);
    $term = (string) ($c['term'] ?? '');
    if ($year < 1 || $year > 8 || !in_array($term, ['1', '2', 'summer'], true)) {
      $add('error', $i, $label . ' needs a year level (1–8) and a term (1st, 2nd, or summer).');
    } else {
      $k = curriculum_term_order($year, $term);
      $termUnits[$k] = ($termUnits[$k] ?? 0) + (float) $lec + (float) $lab;
    }
    if ($title !== '') {
      $byTitle[strtolower($title)][] = $code;
    }
  }
  // "CCS 101L" typed as its own subject next to CCS 101
  foreach ($byCode as $code => $i) {
    if (preg_match('/^(.*\d)\s*L$/', $code, $m)) {
      $base = curriculum_code_key($m[1]);
      if (isset($byCode[$base])) {
        $add('error', $i, $code . ' is the lab of ' . $base . '. Put the lab units on ' . $base . ' instead; the lab gets the “L” code automatically.');
      }
    }
  }
  // prerequisites must be earlier in the curriculum
  foreach ($courses as $i => $c) {
    $code = curriculum_code_key((string) ($c['code'] ?? ''));
    $here = curriculum_term_order((int) ($c['year'] ?? 0), (string) ($c['term'] ?? ''));
    foreach (array_slice((array) ($c['prereqs'] ?? []), 0, 3) as $p) {
      $p = curriculum_code_key((string) $p);
      if ($p === '' || curriculum_not_a_course($p)) {
        continue;
      }
      $pi = $byCode[$p] ?? (preg_match('/^(.*\d)\s*L$/', $p, $m) ? ($byCode[curriculum_code_key($m[1])] ?? null) : null);
      if ($pi === null) {
        $add('error', $i, ($code ?: 'Row ' . ($i + 1)) . ': prerequisite ' . $p . ' isn’t in this curriculum.');
        continue;
      }
      $there = curriculum_term_order((int) ($courses[$pi]['year'] ?? 0), (string) ($courses[$pi]['term'] ?? ''));
      if ($there >= $here) {
        $add('error', $i, ($code ?: 'Row ' . ($i + 1)) . ': prerequisite ' . $p . ' must be in an earlier term.');
      }
    }
  }
  foreach ($byTitle as $title => $codes) {
    $codes = array_values(array_unique(array_filter($codes)));
    if (count($codes) > 1) {
      $add('warning', null, 'Same title under different codes: ' . implode(', ', $codes) . '.');
    }
  }
  foreach ($termUnits as $k => $u) {
    if ($u > 30) {
      $add('warning', null, curriculum_term_label(intdiv($k, 10), [1 => '1', 2 => '2', 3 => 'summer'][$k % 10]) . ' has ' . $u . ' units, which is a heavy load.');
    }
  }
  return $out;
}

/** Prerequisites that aren't subjects: "3rd year standing", "None", or a "**" placeholder. */
function curriculum_not_a_course(string $p): bool
{
  return strpos($p, 'STANDING') !== false || (bool) preg_match('#^(\*+|-+|NONE|N/A)$#', $p);
}

function curriculum_has_errors(array $problems): bool
{
  foreach ($problems as $p) {
    if ($p['level'] === 'error') {
      return true;
    }
  }
  return false;
}

/** Notes on single subjects sent with a send-back: { "CCS 101": "Lab should be 2 units." }, only for subjects in the version. */
function curriculum_row_notes($raw, array $courses): array
{
  $codes = [];
  foreach ($courses as $c) {
    $codes[curriculum_code_key((string) ($c['code'] ?? ''))] = true;
  }
  $out = [];
  foreach (is_array($raw) ? $raw : [] as $code => $note) {
    $code = curriculum_code_key((string) $code);
    $note = trim(preg_replace('/\s+/', ' ', (string) $note) ?? '');
    if ($code !== '' && $note !== '' && isset($codes[$code]) && count($out) < 150) {
      $out[$code] = mb_substr($note, 0, 500);
    }
  }
  return $out;
}

/** What a send-back keeps: who, when, the subjects as they were, and the subject notes. */
function curriculum_return_json(PDO $pdo, array $row, string $from, array $notes): string
{
  // the database's clock, like the other dates on a version
  return json_encode(['from' => $from, 'at' => (string) $pdo->query('SELECT NOW()')->fetchColumn(), 'courses' => curriculum_review_courses($row), 'notes' => (object) $notes],
    JSON_UNESCAPED_UNICODE);
}

/** A send-back needs a note, or notes on single subjects (then the general note says so). */
function curriculum_return_note(string $note, array $rowNotes, string $missing): string
{
  if ($note !== '') {
    return $note;
  }
  if (!$rowNotes) {
    throw new RuntimeException($missing);
  }
  return 'See the notes on ' . count($rowNotes) . (count($rowNotes) === 1 ? ' subject.' : ' subjects.');
}

/** Clean what the form sent into the stored course shape. */
function curriculum_clean_courses($raw): array
{
  $out = [];
  foreach (is_array($raw) ? $raw : [] as $c) {
    if (!is_array($c)) {
      continue;
    }
    $prereqs = [];
    foreach (array_slice((array) ($c['prereqs'] ?? []), 0, 3) as $p) {
      $p = curriculum_code_key((string) $p);
      if ($p !== '') {
        $prereqs[] = $p;
      }
    }
    $term = (string) ($c['term'] ?? '1');
    $out[] = [
      'code' => curriculum_code_key((string) ($c['code'] ?? '')),
      'title' => trim(preg_replace('/\s+/', ' ', (string) ($c['title'] ?? '')) ?? ''),
      'lec' => is_numeric($c['lec'] ?? null) ? round((float) $c['lec'], 2) : 0,
      'lab' => is_numeric($c['lab'] ?? null) ? round((float) $c['lab'], 2) : 0,
      'year' => (int) ($c['year'] ?? 1),
      'term' => in_array($term, ['1', '2', 'summer'], true) ? $term : '1',
      'lecRoom' => trim((string) ($c['lecRoom'] ?? '')),
      'labRoom' => trim((string) ($c['labRoom'] ?? '')),
      'prereqs' => $prereqs,
      'elective' => !empty($c['elective']),
    ];
  }
  return $out;
}

/** Courses of an accepted/live curriculum in the form's shape. */
function curriculum_courses_from_live(PDO $pdo, int $curriculumId): array
{
  $cur = curriculum_by_id($pdo, $curriculumId);
  if (!$cur) {
    return [];
  }
  $elective = [];
  if (schema_table_exists($pdo, 'curriculum_courses')) {
    $st = $pdo->prepare('SELECT course_id, is_elective FROM curriculum_courses WHERE curriculum_id = ?');
    $st->execute([$curriculumId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $elective[curriculum_code_key((string) $r['course_id'])] = (int) $r['is_elective'] === 1;
    }
  }
  return curriculum_clean_courses(array_map(static fn ($c) => [
    'code' => $c['course_id'],
    'title' => $c['course_title'],
    'lec' => $c['lecture_units'],
    'lab' => $c['lab_units'],
    'year' => $c['year_level'],
    'term' => $c['semester'],
    'lecRoom' => $c['lecture_room_type'],
    'labRoom' => $c['lab_room_type'],
    'prereqs' => array_values(array_filter([$c['prerequisite_1'], $c['prerequisite_2'], $c['prerequisite_3']])),
    'elective' => $elective[curriculum_code_key((string) $c['course_id'])] ?? false,
  ], $cur['courses']));
}

function curriculum_valid_year(string $year): string
{
  $year = trim($year);
  if (!preg_match('/^(\d{4})-(\d{4})$/', $year, $m) || (int) $m[2] !== (int) $m[1] + 1) {
    throw new RuntimeException('Enter the school year it starts, for example 2026-2027.');
  }
  return $year;
}

/** Only one version of a program is on its way to the Registrar at a time. */
function curriculum_assert_none_on_the_way(PDO $pdo, array $row, string $role = ''): void
{
  $stmt = $pdo->prepare(
    "SELECT version, status FROM curriculum_reviews WHERE program_id = ? AND review_id <> ?
       AND status IN ('pending_dean','dean_approved','pending_registrar') ORDER BY review_id DESC LIMIT 1"
  );
  $stmt->execute([(int) $row['program_id'], (int) $row['review_id']]);
  if ($other = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $where = ['pending_dean' => $role === 'dean' ? 'is waiting for your approval' : 'is waiting for the Dean', 'dean_approved' => 'is approved and ready for the Registrar',
      'pending_registrar' => 'is with the Registrar'][$other['status']];
    throw new RuntimeException('Version ' . $other['version'] . ' ' . $where . '. Only one version goes to the Registrar at a time, so finish that one first.');
  }
}

function curriculum_next_version(PDO $pdo, int $programId): int
{
  $stmt = $pdo->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM curriculum_reviews WHERE program_id = ?');
  $stmt->execute([$programId]);
  return (int) $stmt->fetchColumn();
}

/** Active (live) curriculums of a program, newest first. */
function curriculum_live_for_program(PDO $pdo, int $programId): array
{
  $stmt = $pdo->prepare("SELECT curriculum_id, academic_year, curriculum_name FROM curricula WHERE program_id = ? AND status = 'active' ORDER BY academic_year DESC, curriculum_id DESC");
  $stmt->execute([$programId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ----- lists -----

function curriculum_review_mine(PDO $pdo, array $me): array
{
  if (!in_array($me['role'], ['dean', 'program_chair'], true)) {
    throw new RuntimeException('Only deans and program chairs make curriculums here.');
  }
  $programs = [];
  foreach (curriculum_programs_for($pdo, $me) as $pid => $p) {
    $rows = $pdo->prepare('SELECT * FROM curriculum_reviews WHERE program_id = ? ORDER BY review_id DESC');
    $rows->execute([$pid]);
    $live = curriculum_live_for_program($pdo, $pid);
    $versions = $rows->fetchAll(PDO::FETCH_ASSOC);
    if ($me['role'] === 'program_chair') {
      $versions = array_values(array_filter($versions, fn ($r) => !curriculum_is_dean_draft($pdo, $r)));
    }
    $programs[] = [
      'id' => public_id('d', $pid),
      'name' => (string) $p['program_name'],
      'collegeName' => (string) $p['college_name'],
      'hasDepartments' => (int) $p['use_departments'] === 1,
      'live' => array_map(static fn ($l) => ['id' => public_id('cur', (int) $l['curriculum_id']), 'academicYear' => $l['academic_year'], 'name' => $l['curriculum_name']], $live),
      'versions' => array_map(fn ($r) => curriculum_review_public($pdo, $r, $p), $versions),
    ];
  }
  return ['role' => $me['role'], 'programs' => $programs, 'roomTypes' => curriculum_room_type_names($pdo)];
}

function curriculum_review_inbox(PDO $pdo, array $me): array
{
  if (!curriculum_is_registrar($me)) {
    throw new RuntimeException('Only the registrars review curriculums.');
  }
  $pending = $pdo->query("SELECT * FROM curriculum_reviews WHERE status = 'pending_registrar' ORDER BY sent_registrar_at, review_id")->fetchAll(PDO::FETCH_ASSOC);
  $recent = $pdo->query("SELECT * FROM curriculum_reviews WHERE status IN ('accepted','superseded','draft') AND registrar_at IS NOT NULL ORDER BY registrar_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
  $accepted = $pdo->query("SELECT * FROM curriculum_reviews WHERE status = 'accepted' ORDER BY registrar_at DESC")->fetchAll(PDO::FETCH_ASSOC);
  $elsewhere = $pdo->query("SELECT * FROM curriculum_reviews WHERE status IN ('draft','pending_dean','dean_approved') ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
  $map = fn ($rows) => array_map(fn ($r) => curriculum_review_public($pdo, $r), $rows);
  return [
    'role' => $me['role'],
    'pending' => $map($pending),
    'recent' => $map($recent),
    'accepted' => $map($accepted),
    'inProgress' => $map($elsewhere),
    'roomTypes' => curriculum_room_type_names($pdo),
  ];
}

// ----- notifications -----

function curriculum_chair_ids(PDO $pdo, array $program): array
{
  $stmt = $pdo->prepare(
    "SELECT DISTINCT u.user_id FROM users u
     LEFT JOIN user_programs up ON up.user_id = u.user_id
     WHERE u.role = 'program_chair' AND u.is_active = 1
       AND (u.department_id = ? OR up.program_id = ?)"
  );
  $stmt->execute([(int) ($program['department_id'] ?? 0), (int) $program['program_id']]);
  return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function curriculum_notify(PDO $pdo, array $program, array $to, string $title, string $body): void
{
  $ids = [];
  foreach ($to as $who) {
    if ($who === 'dean') {
      $ids = array_merge($ids, user_ids_by_role($pdo, 'dean', (int) $program['college_id']));
    } elseif ($who === 'chair') {
      $ids = array_merge($ids, curriculum_chair_ids($pdo, $program));
    } elseif ($who === 'registrars') {
      $ids = array_merge($ids, user_ids_by_role($pdo, 'admin'), user_ids_by_role($pdo, 'super_admin'));
    }
  }
  $page = in_array('registrars', $to, true) && count($to) === 1 ? 'curriculum-review' : 'curriculum-builder';
  notify_user_ids($pdo, $ids, 'curriculum', $title, $body, $page, 'Curriculum');
}

// ----- actions -----

function curriculum_save_row(PDO $pdo, int $id, array $fields, int $userId): void
{
  $fields['updated_by'] = $userId;
  $sets = [];
  $vals = [];
  foreach ($fields as $k => $v) {
    if ($v === 'NOW()') {
      $sets[] = $k . ' = NOW()';
    } else {
      $sets[] = $k . ' = ?';
      $vals[] = $v;
    }
  }
  $vals[] = $id;
  $pdo->prepare('UPDATE curriculum_reviews SET ' . implode(', ', $sets) . ' WHERE review_id = ?')->execute($vals);
}

function curriculum_review_action(PDO $pdo, array $me, string $action, array $body): array
{
  $uid = $me['user_id'];
  $note = trim((string) ($body['note'] ?? ''));

  if ($action === 'create') {
    if (!in_array($me['role'], ['dean', 'program_chair'], true)) {
      throw new RuntimeException('Only deans and program chairs make curriculums.');
    }
    $programId = (int) parse_id((string) ($body['programId'] ?? ''), 'd');
    if (!curriculum_may_write($pdo, $me, $programId)) {
      throw new RuntimeException('You can’t make curriculums for that program.');
    }
    $program = curriculum_program($pdo, $programId);
    if ($me['role'] === 'program_chair' && (int) $program['use_departments'] !== 1) {
      throw new RuntimeException('In this college the Dean makes the curriculum.');
    }
    // any number of drafts; only one version goes on to the Dean / Registrar at a time (curriculum_assert_none_on_the_way)
    $year = curriculum_valid_year((string) ($body['academicYear'] ?? ''));
    $courses = [];
    $from = (string) ($body['from'] ?? 'blank');
    if ($from === 'live') {
      $courses = curriculum_courses_from_live($pdo, (int) parse_id((string) ($body['sourceId'] ?? ''), 'cur'));
    } elseif ($from === 'version') {
      $src = curriculum_review_row($pdo, (int) parse_id((string) ($body['sourceId'] ?? ''), 'rv'));
      if ((int) $src['program_id'] !== $programId) {
        throw new RuntimeException('Copy from a version of the same program.');
      }
      $courses = curriculum_review_courses($src);
    } elseif ($from === 'import') {
      $courses = curriculum_clean_courses($body['courses'] ?? []);
    }
    $version = curriculum_next_version($pdo, $programId);
    $pdo->prepare('INSERT INTO curriculum_reviews (program_id, academic_year, version, name, status, courses_json, created_by, updated_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$programId, $year, $version, curriculum_clean_name((string) ($body['name'] ?? ''), 'Draft ' . $version), 'draft',
        json_encode($courses, JSON_UNESCAPED_UNICODE), $uid, $uid]);
    $id = (int) $pdo->lastInsertId();
    record_transaction($pdo, 'Add', 'curriculum', 'Started a curriculum draft for ' . $program['program_name'] . ' ' . $year . '.');
    return ['review' => curriculum_review_full($pdo, curriculum_review_row($pdo, $id), $me)];
  }

  if ($action === 'registrar_decide' || $action === 'send_to_registrar') {
    // several at once
    $ids = array_values(array_unique(array_filter(array_map(static fn ($v) => (int) parse_id((string) $v, 'rv'), (array) ($body['ids'] ?? [$body['id'] ?? ''])))));
    if (!$ids) {
      throw new RuntimeException('Pick at least one curriculum.');
    }
    if (count($ids) > 1) {
      unset($body['rowNotes']);
    }
    $done = [];
    foreach ($ids as $id) {
      $done[] = curriculum_review_one($pdo, $me, $action, curriculum_review_row($pdo, $id), $body, $note);
    }
    return ['reviews' => $done];
  }

  $row = curriculum_review_row($pdo, (int) parse_id((string) ($body['id'] ?? ''), 'rv'));
  return ['review' => curriculum_review_one($pdo, $me, $action, $row, $body, $note)];
}

function curriculum_review_one(PDO $pdo, array $me, string $action, array $row, array $body, string $note): array
{
  $uid = $me['user_id'];
  $id = (int) $row['review_id'];
  $program = curriculum_program($pdo, (int) $row['program_id']);
  $can = curriculum_review_can($pdo, $me, $row, $program);
  $what = $program['program_name'] . ' ' . $row['academic_year'] . ' (version ' . $row['version'] . ', “' . curriculum_review_name($row) . '”)';
  $problems = static fn () => curriculum_problems($pdo, curriculum_review_courses(curriculum_review_row($pdo, $id)));

  switch ($action) {
    case 'save': {
      if (!$can['edit']) {
        throw new RuntimeException('This version can’t be edited right now.');
      }
      $year = curriculum_valid_year((string) ($body['academicYear'] ?? $row['academic_year']));
      // untouched blank rows are dropped; a row with anything in it needs a course code
      $courses = array_values(array_filter(curriculum_clean_courses($body['courses'] ?? []),
        static fn ($c) => $c['code'] !== '' || $c['title'] !== '' || $c['prereqs']));
      foreach ($courses as $c) {
        if ($c['code'] === '') {
          throw new RuntimeException('A subject has no course code' . ($c['title'] !== '' ? ' (' . $c['title'] . ')' : '') . '. Add the code before saving.');
        }
      }
      curriculum_save_row($pdo, $id, [
        'academic_year' => $year,
        'courses_json' => json_encode($courses, JSON_UNESCAPED_UNICODE),
        'notes' => trim((string) ($body['notes'] ?? $row['notes'] ?? '')),
      ], $uid);
      break;
    }
    case 'rename': {
      if (!$can['rename']) {
        throw new RuntimeException('This version can’t be renamed right now.');
      }
      $name = curriculum_clean_name((string) ($body['name'] ?? ''), '');
      if ($name === '') {
        throw new RuntimeException('Give the draft a name.');
      }
      curriculum_save_row($pdo, $id, ['name' => $name], $uid);
      record_transaction($pdo, 'Edit', 'curriculum', 'Renamed ' . $what . ' to “' . $name . '”.');
      break;
    }
    case 'discard': {
      if (!$can['discard']) {
        throw new RuntimeException('Only a draft can be deleted.');
      }
      $pdo->prepare('DELETE FROM curriculum_reviews WHERE review_id = ?')->execute([$id]);
      record_transaction($pdo, 'Delete', 'curriculum', 'Deleted the draft of ' . $what . '.');
      return ['id' => public_id('rv', $id), 'deleted' => true];
    }
    case 'send_to_dean': {
      if (!$can['sendToDean']) {
        throw new RuntimeException('Only a Program Chair’s draft can be sent to the Dean.');
      }
      if (curriculum_has_errors($problems())) {
        throw new RuntimeException('Fix the problems in the checklist before sending.');
      }
      curriculum_assert_none_on_the_way($pdo, $row, $me['role']);
      curriculum_save_row($pdo, $id, ['status' => 'pending_dean', 'sent_dean_at' => 'NOW()'], $uid);
      curriculum_notify($pdo, $program, ['dean'], 'A curriculum is waiting for your approval',
        $me['full_name'] . ' sent ' . $what . ' for your approval.');
      record_transaction($pdo, 'Submit', 'curriculum', 'Sent ' . $what . ' to the Dean.');
      break;
    }
    case 'dean_decide': {
      if (!$can['deanDecide']) {
        throw new RuntimeException('This curriculum isn’t waiting for your approval.');
      }
      if (!empty($body['accept'])) {
        if (curriculum_has_errors($problems())) {
          throw new RuntimeException('This version still has problems. Fix them or send it back.');
        }
        $fields = ['status' => 'dean_approved', 'dean_by' => $uid, 'dean_at' => 'NOW()',
          'last_return_from' => null, 'last_return_note' => null, 'last_return_at' => null];
        // a Registrar's send-back record stays until the Registrar decides (they see what changed)
        if ((json_decode((string) ($row['return_json'] ?? ''), true)['from'] ?? '') !== 'registrar') {
          $fields['return_json'] = null;
        }
        curriculum_save_row($pdo, $id, $fields, $uid);
        curriculum_notify($pdo, $program, ['chair'], 'The Dean approved your curriculum', 'The Dean approved ' . $what . '.');
        record_transaction($pdo, 'Approve', 'curriculum', 'Approved ' . $what . '.');
      } else {
        $rowNotes = curriculum_row_notes($body['rowNotes'] ?? [], curriculum_review_courses($row));
        $note = curriculum_return_note($note, $rowNotes, 'Say what the Program Chair should fix.');
        curriculum_save_row($pdo, $id, ['status' => 'draft', 'dean_by' => $uid, 'dean_at' => 'NOW()',
          'last_return_from' => 'dean', 'last_return_note' => $note, 'last_return_at' => 'NOW()',
          'return_json' => curriculum_return_json($pdo, $row, 'dean', $rowNotes)], $uid);
        curriculum_notify($pdo, $program, ['chair'], 'The Dean sent your curriculum back',
          'The Dean sent ' . $what . ' back. ' . notify_note_snippet($note));
        record_transaction($pdo, 'Reject', 'curriculum', 'Sent ' . $what . ' back to the Program Chair. Note: ' . mb_substr($note, 0, 180));
      }
      break;
    }
    case 'send_to_registrar': {
      if (!$can['sendToRegistrar']) {
        throw new RuntimeException($what . ' can’t be sent to the Registrar yet' . ((string) $row['status'] === 'pending_dean' ? '. Approve it first.' : '.'));
      }
      if (curriculum_has_errors($problems())) {
        throw new RuntimeException($what . ' still has problems in the checklist. Fix them before sending.');
      }
      curriculum_assert_none_on_the_way($pdo, $row, $me['role']);
      $fields = ['status' => 'pending_registrar', 'sent_registrar_at' => 'NOW()',
        'last_return_from' => null, 'last_return_note' => null, 'last_return_at' => null];
      if (empty($row['dean_by'])) {
        $fields['dean_by'] = $uid;
        $fields['dean_at'] = 'NOW()';
      }
      curriculum_save_row($pdo, $id, $fields, $uid);
      curriculum_notify($pdo, $program, ['registrars'], 'A curriculum is waiting for your review',
        'The Dean of ' . $program['college_name'] . ' sent ' . $what . ' for review.');
      record_transaction($pdo, 'Submit', 'curriculum', 'Sent ' . $what . ' to the Registrar.');
      break;
    }
    case 'take_back': {
      if (!$can['takeBack']) {
        throw new RuntimeException('This curriculum isn’t waiting for the Registrar.');
      }
      curriculum_save_row($pdo, $id, ['status' => 'dean_approved'], $uid);
      record_transaction($pdo, 'Retract', 'curriculum', 'Took back ' . $what . ' from the Registrar.');
      break;
    }
    case 'registrar_decide': {
      if (!curriculum_is_registrar($me)) {
        throw new RuntimeException('Only the registrars accept curriculums.');
      }
      if ((string) $row['status'] !== 'pending_registrar') {
        throw new RuntimeException($what . ' isn’t waiting for review (someone may have already decided).');
      }
      if (!empty($body['accept'])) {
        $courses = curriculum_review_courses($row);
        if (curriculum_has_errors(curriculum_problems($pdo, $courses))) {
          throw new RuntimeException($what . ' has problems in the checklist. Send it back so the college can fix them.');
        }
        $saved = save_curriculum($pdo, [
          'meta' => [
            'departmentId' => public_id('d', (int) $program['program_id']),
            'departmentName' => (string) $program['program_name'],
            'academicYear' => (string) $row['academic_year'],
            'sourceFileName' => 'From ' . $program['college_name'] . ' (version ' . $row['version'] . ')',
          ],
          'courses' => array_map(static fn ($c) => [
            'course_id' => $c['code'],
            'course_title' => $c['title'],
            'lecture_units' => $c['lec'],
            'lab_units' => $c['lab'],
            'year_level' => $c['year'],
            'semester' => $c['term'],
            'lecture_room_type' => $c['lecRoom'],
            'lab_room_type' => $c['labRoom'],
            'prerequisite_1' => $c['prereqs'][0] ?? '',
            'prerequisite_2' => $c['prereqs'][1] ?? '',
            'prerequisite_3' => $c['prereqs'][2] ?? '',
            'is_elective' => !empty($c['elective']),
          ], $courses),
          'replace' => true,
        ]);
        $curId = (int) parse_id((string) ($saved['curriculum']['id'] ?? ''), 'cur');
        $pdo->prepare("UPDATE curriculum_reviews SET status = 'superseded' WHERE program_id = ? AND status = 'accepted' AND review_id <> ?")
          ->execute([(int) $row['program_id'], $id]);
        curriculum_save_row($pdo, $id, ['status' => 'accepted', 'registrar_by' => $uid, 'registrar_at' => 'NOW()', 'curriculum_id' => $curId ?: null,
          'last_return_from' => null, 'last_return_note' => null, 'last_return_at' => null, 'return_json' => null], $uid);
        curriculum_notify($pdo, $program, ['dean', 'chair'], 'Your curriculum was accepted',
          ($me['role'] === 'super_admin' ? 'The University Registrar' : 'The Records Officer') . ' accepted ' . $what . '. It’s now the active curriculum.');
        record_transaction($pdo, 'Approve', 'curriculum', 'Accepted ' . $what . '. It’s now the active curriculum.');
      } else {
        $rowNotes = curriculum_row_notes($body['rowNotes'] ?? [], curriculum_review_courses($row));
        $note = curriculum_return_note($note, $rowNotes, 'Say what the college should fix.');
        curriculum_save_row($pdo, $id, ['status' => 'draft', 'registrar_by' => $uid, 'registrar_at' => 'NOW()',
          'last_return_from' => 'registrar', 'last_return_note' => $note, 'last_return_at' => 'NOW()',
          'return_json' => curriculum_return_json($pdo, $row, 'registrar', $rowNotes)], $uid);
        curriculum_notify($pdo, $program, ['dean', 'chair'], 'The Registrar sent your curriculum back',
          'The Registrar sent ' . $what . ' back. ' . notify_note_snippet($note));
        record_transaction($pdo, 'Reject', 'curriculum', 'Sent ' . $what . ' back to ' . $program['college_name'] . '. Note: ' . mb_substr($note, 0, 180));
      }
      break;
    }
    case 'request_revision': {
      if (!$can['requestRevision']) {
        throw new RuntimeException('Only an accepted curriculum can be sent for revision.');
      }
      $rowNotes = curriculum_row_notes($body['rowNotes'] ?? [], curriculum_review_courses($row));
      $note = curriculum_return_note($note, $rowNotes, 'Say what needs to change.');
      $version = curriculum_next_version($pdo, (int) $row['program_id']);
      $pdo->prepare('INSERT INTO curriculum_reviews (program_id, academic_year, version, name, status, courses_json, revision_of, created_by, updated_by,
                       last_return_from, last_return_note, last_return_at, return_json)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)')
        ->execute([(int) $row['program_id'], $row['academic_year'], $version, 'Revision of version ' . $row['version'], 'draft',
          $row['courses_json'], $id, (int) ($row['created_by'] ?: $uid), $uid, 'registrar', $note,
          curriculum_return_json($pdo, $row, 'registrar', $rowNotes)]); // still prepared by the college
      $newId = (int) $pdo->lastInsertId();
      curriculum_notify($pdo, $program, ['dean', 'chair'], 'The Registrar asked for a curriculum revision',
        'The Registrar asked for changes to ' . $what . '. A new version is ready to edit; the accepted one stays active until the revision is accepted. ' . notify_note_snippet($note));
      record_transaction($pdo, 'Edit', 'curriculum', 'Asked ' . $program['college_name'] . ' to revise ' . $what . '. Note: ' . mb_substr($note, 0, 180));
      return curriculum_review_public($pdo, curriculum_review_row($pdo, $newId), $program);
    }
    default:
      throw new RuntimeException('Unknown curriculum action.');
  }
  $fresh = curriculum_review_row($pdo, $id);
  return curriculum_is_registrar($me) && $action === 'registrar_decide'
    ? curriculum_review_public($pdo, $fresh, $program)
    : curriculum_review_full($pdo, $fresh, $me);
}
