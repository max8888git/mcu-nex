<?php
declare(strict_types=1);

/*
 * Reports → Finished curriculums (the registrars' "graveyard"): curriculums the Records
 * Officer or University Registrar archived because they don't run any more, each with its
 * subjects and the approved timetables of every term its sections were scheduled in.
 * Also lists active curriculums no section uses this term, as "ready to archive".
 * Archiving / restoring is PATCH on curricula.php (status). The University Registrar can
 * delete an archived one that was never used.
 */

require_once __DIR__ . '/bootstrap.php';

/** Only the Records Officer and the University Registrar open the archive. Returns the role. */
function curriculum_archive_role(PDO $pdo): string
{
  $me = session_db_user($pdo);
  $role = (string) ($me['role'] ?? '');
  if (!in_array($role, ['admin', 'super_admin'], true)) {
    throw new RuntimeException('Only the Records Officer and the University Registrar can open Finished curriculums.');
  }
  return $role;
}

/**
 * Why a curriculum can't be deleted, or null when it can. Deleting is for mistakes (a wrong upload,
 * a duplicate), so it has to be archived first and must never have been used: no section follows it,
 * and none of its classes is in an approved timetable. Anything that was used stays as the record of
 * those timetables. $termsUsed skips the (slow) lookup when the caller already has it.
 */
function curriculum_delete_block_reason(PDO $pdo, array $cur, ?int $termsUsed = null): ?string
{
  if (($cur['status'] ?? '') !== 'archived') {
    return 'Archive it first. Only an archived curriculum can be deleted.';
  }
  $id = (int) parse_id((string) $cur['id'], 'cur');
  if ($termsUsed === null) {
    $usage = curriculum_section_usage($pdo, null);
    $termsUsed = max($usage[$id]['terms'] ?? 0, count(curriculum_term_classes($pdo, $cur, list_curricula($pdo))));
  }
  if ($termsUsed > 0) {
    return 'It was used in ' . $termsUsed . ($termsUsed === 1 ? ' term' : ' terms')
      . ' (sections or an approved timetable follow it), so it stays as the record of that.';
  }
  $offerings = 0;
  $overrides = 0;
  if (schema_table_exists($pdo, 'class_offerings')) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM class_offerings o JOIN curriculum_courses cc ON cc.curriculum_course_id = o.curriculum_course_id WHERE cc.curriculum_id = ?');
    $q->execute([$id]);
    $offerings = (int) $q->fetchColumn();
  }
  if (schema_table_exists($pdo, 'section_subject_overrides')) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM section_subject_overrides s JOIN curriculum_courses cc ON cc.curriculum_course_id = s.curriculum_course_id WHERE cc.curriculum_id = ?');
    $q->execute([$id]);
    $overrides = (int) $q->fetchColumn();
  }
  if ($offerings > 0 || $overrides > 0) {
    return 'Classes or section changes still point at its subjects, so it stays as the record of them.';
  }
  return null;
}

/** Deletes an archived, never-used curriculum. Only the University Registrar. Returns what was deleted. */
function curriculum_delete(PDO $pdo, int $id): array
{
  $me = session_db_user($pdo);
  if (($me['role'] ?? '') !== 'super_admin') {
    throw new RuntimeException('Only the University Registrar can delete a curriculum. You can archive it instead.');
  }
  $cur = curriculum_by_id($pdo, $id);
  if (!$cur) {
    throw new RuntimeException('That curriculum was not found. It may already be deleted.');
  }
  $why = curriculum_delete_block_reason($pdo, $cur);
  if ($why !== null) {
    throw new RuntimeException('Can’t delete ' . $cur['name'] . '. ' . $why);
  }
  $subjects = count($cur['courses'] ?? []);
  // a review that produced it keeps its own copy of the subjects; it just stops pointing here
  if (schema_table_exists($pdo, 'curriculum_reviews')) {
    $pdo->prepare('UPDATE curriculum_reviews SET curriculum_id = NULL WHERE curriculum_id = ?')->execute([$id]);
  }
  $pdo->prepare('DELETE FROM curricula WHERE curriculum_id = ?')->execute([$id]);
  $where = trim((string) ($cur['departmentName'] ?? ''));
  record_transaction(
    $pdo,
    'Delete',
    'curriculum',
    'Deleted curriculum ' . $cur['name'] . ($where !== '' ? ' for ' . $where : '') . ' (effective ' . $cur['academicYear'] . ', '
      . $subjects . ($subjects === 1 ? ' subject' : ' subjects') . ').'
  );
  return ['id' => $cur['id'], 'name' => $cur['name'], 'subjects' => $subjects];
}

/** Sections per curriculum: this term's count, and how many terms it was used in. */
function curriculum_section_usage(PDO $pdo, ?int $currentTermId): array
{
  $out = [];
  if (!schema_table_exists($pdo, 'sections')) {
    return $out;
  }
  foreach ($pdo->query('SELECT curriculum_id, term_id, COUNT(*) n FROM sections GROUP BY curriculum_id, term_id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $cid = (int) $r['curriculum_id'];
    $out[$cid] ??= ['current' => 0, 'terms' => 0];
    $out[$cid]['terms']++;
    if ($currentTermId && (int) $r['term_id'] === $currentTermId) {
      $out[$cid]['current'] = (int) $r['n'];
    }
  }
  return $out;
}

function curriculum_archive_item(array $cur, array $usage): array
{
  $id = (int) parse_id((string) $cur['id'], 'cur');
  return [
    'id' => $cur['id'],
    'name' => $cur['name'],
    'programName' => $cur['departmentName'],
    'collegeName' => $cur['collegeName'],
    'academicYear' => $cur['academicYear'],
    'status' => $cur['status'],
    'courseCount' => count($cur['courses'] ?? []),
    'updatedAt' => $cur['updatedAt'],
    'sectionsThisTerm' => $usage[$id]['current'] ?? 0,
    'termsUsed' => $usage[$id]['terms'] ?? 0,
  ];
}

/** The list: archived curriculums, and active ones no section uses this term (sections or the approved timetable). */
function curriculum_finished_overview(PDO $pdo): array
{
  $role = curriculum_archive_role($pdo);
  $current = current_offering_term($pdo);
  $usage = curriculum_section_usage($pdo, $current ? (int) $current['termId'] : null);
  $archived = [];
  $notInUse = [];
  $inUse = 0;
  $all = list_curricula($pdo);
  $currentId = $current ? (int) $current['termId'] : 0;
  foreach ($all as $cur) {
    $item = curriculum_archive_item($cur, $usage);
    // the approved timetables count too (sections get cleared or rolled over)
    $terms = curriculum_term_classes($pdo, $cur, $all);
    $item['termsUsed'] = max($item['termsUsed'], count($terms));
    if ($cur['status'] === 'archived') {
      // why it can't be deleted (null when it can); only the University Registrar deletes
      $item['deleteBlocked'] = $role === 'super_admin' ? curriculum_delete_block_reason($pdo, $cur, $item['termsUsed']) : null;
    }
    foreach ($terms as $t) {
      if ($t['id'] === $currentId) {
        $item['sectionsThisTerm'] = max($item['sectionsThisTerm'], count(array_unique(array_map(static fn ($c) => (string) ($c['sectionId'] ?? ''), $t['classes']))));
      }
    }
    if ($cur['status'] === 'archived') {
      $archived[] = $item;
    } elseif ($item['sectionsThisTerm'] === 0) {
      $notInUse[] = $item;
    } else {
      $inUse++;
    }
  }
  $sort = static fn ($a, $b) => strcmp($a['collegeName'] . $a['programName'] . $a['academicYear'], $b['collegeName'] . $b['programName'] . $b['academicYear']);
  usort($archived, $sort);
  usort($notInUse, $sort);
  return [
    'role' => $role,
    'canArchive' => true,
    'canDelete' => $role === 'super_admin',
    'term' => $current ? ['schoolYear' => $current['schoolYear'], 'termCode' => $current['termCode'],
      'label' => term_offering_label($current['schoolYear'], $current['termCode'])] : null,
    'archived' => $archived,
    'notInUse' => $notInUse,
    'inUse' => $inUse,
  ];
}

/**
 * The approved classes that followed a curriculum, per term (newest first). A class carries
 * curriculumId from 2026-10-17 on; older ones are matched by their section, or, when the program
 * has only this one curriculum, by program (matched by name, since programs get re-created).
 * $onlyTermId limits it to one term.
 */
function curriculum_term_classes(PDO $pdo, array $cur, array $allCurricula, ?int $onlyTermId = null): array
{
  $id = (int) parse_id((string) $cur['id'], 'cur');
  $collegeId = (int) parse_id((string) $cur['collegeId'], 'c');
  $publicId = public_id('cur', $id);
  $sectionsByTerm = [];
  if (schema_table_exists($pdo, 'sections')) {
    $stmt = $pdo->prepare('SELECT term_id, section_id FROM sections WHERE curriculum_id = ?');
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $sectionsByTerm[(int) $r['term_id']][public_id('s', (int) $r['section_id'])] = true;
    }
  }
  $programId = (string) ($cur['departmentId'] ?? '');
  $onlyOne = $programId !== '' && count(array_filter($allCurricula, static fn ($c) => (string) ($c['departmentId'] ?? '') === $programId)) === 1;
  $activeIds = [];
  $act = $pdo->prepare('SELECT program_id FROM programs WHERE college_id = ? AND is_active = 1');
  $act->execute([$collegeId]);
  foreach ($act->fetchAll(PDO::FETCH_COLUMN) as $pid) {
    $activeIds[public_id('d', (int) $pid)] = true;
  }
  // a college with one active program (no departments): an old program row can only mean it
  $soleProgram = count($activeIds) === 1 ? (string) array_key_first($activeIds) : null;
  $sql = 'SELECT t.term_id, t.term_code, t.is_active, sy.label
     FROM terms t JOIN school_years sy ON sy.school_year_id = t.school_year_id'
    . ($onlyTermId ? ' WHERE t.term_id = ' . (int) $onlyTermId : '')
    . ' ORDER BY sy.label DESC, t.term_order DESC';
  $out = [];
  foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $termId = (int) $t['term_id'];
    $snap = function_exists('college_published_snapshot') ? college_published_snapshot($pdo, $termId, $collegeId) : null;
    if (!$snap) {
      continue;
    }
    $mine = $sectionsByTerm[$termId] ?? [];
    $all = array_values(array_filter($snap['classes'] ?? [], 'is_array'));
    if ($onlyOne && function_exists('college_tag_programs')) {
      [$all] = college_tag_programs($pdo, $collegeId, $all);
      if ($soleProgram !== null) {
        foreach ($all as &$c) {
          if (!isset($activeIds[(string) ($c['programId'] ?? '')])) {
            $c['programId'] = $soleProgram;
          }
        }
        unset($c);
      }
    }
    $classes = array_values(array_filter($all, static fn ($c) => ($c['curriculumId'] ?? null) === $publicId
      || isset($mine[(string) ($c['sectionId'] ?? '')])
      || ($onlyOne && empty($c['curriculumId']) && (string) ($c['programId'] ?? $c['departmentId'] ?? '') === $programId)));
    if ($classes) {
      $out[] = ['id' => $termId, 'label' => term_offering_label((string) $t['label'], (string) $t['term_code']),
        'current' => (int) $t['is_active'] === 1, 'classes' => $classes];
    }
  }
  return $out;
}

/** One curriculum with its subjects and the approved classes that followed it, per term. */
function curriculum_finished_detail(PDO $pdo, int $id): array
{
  $role = curriculum_archive_role($pdo);
  $cur = curriculum_by_id($pdo, $id);
  if (!$cur) {
    throw new RuntimeException('That curriculum was not found.');
  }
  return ['role' => $role, 'canArchive' => true, 'canDelete' => $role === 'super_admin', 'curriculum' => $cur,
    'terms' => curriculum_term_classes($pdo, $cur, list_curricula($pdo))];
}
