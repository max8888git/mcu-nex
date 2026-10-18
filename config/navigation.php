<?php

/*
 * Sidebar navigation for each role folder, rendered by <x-app-nav>.
 *
 * Each item: label, page (link target, relative to the role folder, without
 * .html), icon (key in components/app-nav-icon), match (pages that highlight
 * the item; a trailing * matches a folder) and optional badge (filled in by
 * resources/js/legacy/app-nav.js).
 */

$staff = [
    'Overview' => [
        ['label' => 'Dashboard', 'page' => 'home-dashboard', 'icon' => 'dashboard', 'match' => ['home-dashboard', 'dashboard']],
    ],
    'Scheduling' => [
        ['label' => 'Create schedule', 'page' => 'create-schedule', 'icon' => 'calendar', 'match' => ['create-schedule']],
        ['label' => 'Rooms available', 'page' => 'rooms-available', 'icon' => 'door', 'match' => ['rooms-available', 'rooms-available-filters', 'data-management/rooms-available-filters']],
        ['label' => 'Reports', 'page' => 'final-report', 'icon' => 'report', 'match' => ['final-report', 'finished-curriculums', 'past-terms', 'report-schedules', 'report-schedule-room']],
    ],
    'Academic data' => [
        ['label' => 'Data management', 'page' => 'data-management/college-departments', 'icon' => 'building', 'match' => ['data-management', 'data-management/*']],
        ['label' => 'Curriculums', 'page' => 'curriculums/curriculums', 'icon' => 'book', 'match' => ['curriculums/*', 'data-management/add-curriculum']],
    ],
    'Administration' => [
        ['label' => 'Requests', 'page' => 'requests', 'icon' => 'inbox', 'match' => ['requests'], 'badge' => 'pending-requests'],
        ['label' => 'Account management', 'page' => 'account-management', 'icon' => 'user-cog', 'match' => ['account-management', 'account-create', 'users']],
        ['label' => 'Transaction log', 'page' => 'logs', 'icon' => 'history', 'match' => ['logs']],
    ],
];

// Deans and Program Chairs: this term's approved timetable on the Dashboard, earlier terms in
// Past timetables, the curriculums they make, and schedule-change requests.
$maker = [
    'Overview' => [
        ['label' => 'Dashboard', 'page' => 'dashboard', 'icon' => 'dashboard', 'match' => ['dashboard']],
    ],
    'Schedules' => [
        ['label' => 'Curriculum', 'page' => 'curriculum-builder', 'icon' => 'book', 'match' => ['curriculum-builder']],
        ['label' => 'Past timetables', 'page' => 'past-timetables', 'icon' => 'history', 'match' => ['past-timetables']],
    ],
    'Requests' => [
        ['label' => 'Requests', 'page' => 'viewer-requests', 'icon' => 'inbox', 'match' => ['viewer-requests']],
    ],
];

// VPAA: approves per college on the Dashboard; Approved schedules (retract) and History.
// (Room schedule, Reports and the Approval records page were removed 2026-10-14.)
$vpaa = [
    'Overview' => [
        ['label' => 'Dashboard', 'page' => 'dashboard', 'icon' => 'dashboard', 'match' => ['dashboard']],
    ],
    'Schedules' => [
        ['label' => 'Approved schedules', 'page' => 'approved-schedules', 'icon' => 'check', 'match' => ['approved-schedules']],
        ['label' => 'History', 'page' => 'history', 'icon' => 'history', 'match' => ['history']],
    ],
];

return [
    'admin' => $staff,
    'super-admin' => $staff,
    'viewing' => $maker,
    'viewing-program-head' => $maker,
    'viewing-with-approval' => $vpaa,
];
