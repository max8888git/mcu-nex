# Parked pages

Pages taken out of the running system that **might come back**. Laravel never loads anything
in this folder: it isn't under `resources/views`, so no URL reaches it. Each page's old URL now
opens the Dashboard (a small redirect stub in its place), so old links and bookmarks still work.

(`_archive/` is different: it's git-ignored and holds backups and pages we're sure are gone,
like Faculty loading.)

| Folder | What it was | Parked | Replaced by |
|---|---|---|---|
| `department-report/` | Dean / Program Chair per-program report. It was opened from the old hardcoded CAS department cards on their Dashboard (cs / it / emc / psych) | 2026-10-14 | Dashboard (this term's approved timetable, filter by program) and Past timetables |
| `viewer-reports/` | Reports → Final report for the VPAA, Deans and Program Chairs (`roles/viewer/final-report`), including the VPAA's old Colleges approve panel | 2026-10-14 | VPAA: Dashboard boxes, Approved schedules, History. Deans / Program Chairs: Dashboard, Past timetables |
| `vpaa-approval-page/` | The VPAA's "Approval" page: a log of timetable sends and send-backs (`pages/viewing-with-approval/viewer-requests`) | 2026-10-14 | The notes on the VPAA's boxes; the Transaction log (staff) |
| `room-schedule/` | The VPAA's Room schedule viewer (`pages/viewing-with-approval/viewer`, `viewer-rooms`) | 2026-10-14 | Not needed for the VPAA (staff have Rooms available) |

## Taking one back

1. Move the file back to the path in the table, replacing the redirect stub that's there now
   (for `room-schedule`, delete `viewer-rooms`' stub too if one was added).
2. Add its sidebar item again in `config/navigation.php`.
3. Run `php artisan view:clear` and the tests (`php artisan test`). `tests/Feature/RolePagesTest.php`
   checks that these pages are redirects, so update those lines.
