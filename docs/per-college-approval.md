# Per-college timetable approval (server side)

For backend review. Colleges move through approval one at a time (or several
at once): Records Officer → University Registrar → VPAA → published.

## Files

- `legacy-api/schedule-colleges.php` (new): everything below.
- `legacy-api/schedule.php`: loads the new file; `GET ?colleges=1` returns the
  status overview; per-college actions are dispatched before the existing
  ones; after the existing whole-campus approval actions the per-college rows
  are rebuilt from the term-wide state.
- `legacy-api/schedule-workspaces.php`: Generate keeps locked colleges; edits
  to a locked college's classes are refused.
- `legacy-api/bootstrap.php`: `ensure_notifications_schema` skips its
  `CREATE TABLE IF NOT EXISTS` when the table exists. In MySQL that statement
  silently commits the open transaction, so every action that sent a
  notification ended its own transaction early (the change saved, then the
  request failed with "There is no active transaction"). This affected the
  existing approval buttons too.
- `tests/Feature/CollegeApprovalTest.php`: the whole flow on a copy of the
  database (`mcu_nex_sched_test`).

Backups of the three changed legacy files are in `_archive/baseline-2026-10-11/`.

## Data

New table `term_college_approvals` (created on first use), one row per
term and college: `status` (draft, pending_super_admin, pending_vpaa,
published), that college's copy at each step (`sa_json`, `vpaa_json`,
`published_json`), `is_locked`, unlock reason/time, and the last send-back
note.

The existing `term_schedule_approvals` columns and `schedule_copies` slots
stay the source for every page that reads them: after each per-college
action they are rebuilt as "all colleges at that step combined"
(`term_from_college_rows`). Reports, the viewer pages and Rooms available
need no changes.

## Actions (POST api/schedule.php)

All take `schoolYear`, `termCode` and `colleges: ["c-2", ...]`. Roles are
checked against the signed-in user, not the request's `actor`.

Statuses: `draft` (Records Officer working on it) → `pending_super_admin`
(sent, waiting for the University Registrar) → `with_registrar` (the
Registrar approved it or built it, and is finishing it) → `pending_vpaa` →
`published` (locked). `with_registrar` was added to the `status` enum; the
column is altered automatically on the first request.

| Action | Who | Effect |
| --- | --- | --- |
| `college_submit_to_registrar` | Records Officer | Their working timetable's copy of each college (complete, not locked, not already with the Registrar) → `pending_super_admin` |
| `college_retract` | Records Officer / University Registrar | Records Officer: `pending_super_admin` → `draft`. Registrar: `pending_vpaa` → `with_registrar` |
| `college_registrar_decide` `accept: true` | University Registrar | `pending_super_admin` → `with_registrar`; the Records Officer's copy replaces the Registrar's own version of those colleges in their working timetable, to check or edit |
| `college_registrar_decide` `accept: true, keepMine: true` | University Registrar | `pending_super_admin` → `with_registrar`, keeping the Registrar's own version of those colleges (they must have one); the Records Officer is told |
| `college_registrar_decide` `accept: false` (`note` required) | University Registrar | `pending_super_admin` or `with_registrar` → `draft`, back to the Records Officer |
| `college_send_to_vpaa` | University Registrar | From their working timetable (`draft` they built, or `with_registrar`) → `pending_vpaa`. Also works while `pending_super_admin`: the Registrar's own version goes instead and the Records Officer's request is closed (they're told). If the Registrar changed the Records Officer's version, the Records Officer is told |
| `college_vpaa_decide` (`accept`, `note`) | VPAA | Approve → `published`, locked. Send back (note required) → `with_registrar` |
| `college_unlock` (`note` required) | University Registrar | `published` → `with_registrar`, unlocked; logged. The published copy stays live until a corrected one is approved |
| `college_relock` | University Registrar | Lock an unlocked college again without changes |

Before the VPAA publishes, each college is checked for room double-bookings
against the other colleges' published timetables. If the clash is with a
college whose new version is also waiting, the message says to approve them
together.

## Lock

`apply_college_locks_to_generate`: a full Generate becomes "schedule the rest"
with the locked colleges' published classes as the already-placed classes,
so their rooms and times are blocked and they aren't moved.
`assert_edit_target_not_locked` / `assert_locked_colleges_unchanged`: manual
edits to a locked college's classes are refused.

## Known issue (not changed)

The existing schedule actions (`submit_to_vpaa`, `vpaa_decide`, …) trust the
`actor` field sent by the page instead of the signed-in user's role, so any
request could act as the VPAA. The new per-college actions check the session;
the old ones should too.

## Room rules (legacy-api/room-rules.php)

Data management → Facilities already stores which colleges may use a room
(owner college or "shared by" colleges), a floor (`floor_college_access`) or
a whole building (`exclusive_college_id`). The most specific setting wins.
The auto-scheduler now only places a class in rooms its college may use
(`placement_room_choices`, `assign_rooms_to_groups`, `group_from_members`).
Rooms sent to the page carry `colleges` (empty = any), so editing by hand
warns and asks "Save anyway" — the override.

The editable working timetable now always gets today's room list
(`apply_workspace_to_payload`), not the one saved inside a copy, so room
types and rules are current.

## Compare and clashes (front end)

On For approval, "Compare with my version" (schedule-colleges.js) loads the
submitted copy and the Registrar's working timetable, lists the classes whose
day, time or room differ, and the room or student clashes the Records
Officer's version would cause with the Registrar's other colleges. Choices:
Use the Records Officer's (approve), Keep mine (`keepMine`), Send back. When
using the Records Officer's version with clashes, the Registrar picks who
moves; the Working timetable tab then unschedules those classes and places
them again in free rooms (schedule-page.js `resolveApprovalClashes`, using
the existing `unschedule_class` and `add_meeting` actions), re-checking until
nothing clashes. No new server action for the moving.

## Dean / Program Chair dashboard (2026-10-13)

- `GET api/schedule.php?college_final=1[&term=ID]` → `college_final_for_viewer()` (schedule-colleges.php). Read-only. The college and programs come from the session. Returns the college's approved copy (`published_json`) for the current offering, or a chosen term that has one, plus its status. A Program Chair gets only their programs' classes. Each class is tagged `programId`, matched by name as well, because a saved copy can point at an older program row.
- When the VPAA approves colleges, each college's Dean and Program Chairs get "Your college's timetable is approved", which links to their dashboard (`notification_href_for_role` page `college-timetable`).
- Front end: `resources/js/legacy/college-timetable.js` + `css/legacy/college-timetable.css`, shown on `roles/viewer/dashboard` for Dean and Program Chair. It replaces the old hardcoded CAS department cards. There's a filter by program, search, and Download CSV / PDF (`window.McuTimetablePdf` in `resources/js/curriculum-pdf.js`) / Print.

## VPAA: boxes, Approved schedules, History, retract (2026-10-14)

- **Dashboard** (`roles/viewer/dashboard`, VPAA): one box per college waiting for approval (`resources/js/legacy/vpaa-board.js`, `css/legacy/vpaa-board.css`, mode `waiting`). Click a box to open a pop-up with the college's timetable: a program filter, search, sections, and Download PDF. From there: **Approve and publish**, or **Send back** (a note is required); both are the existing `college_vpaa_decide`. Tick several boxes to approve together. The other colleges are listed under the waiting ones with their status. The old single card and the dead CAS department-card script are removed.
- **Approved schedules** (`pages/viewing-with-approval/approved-schedules`, mode `approved`): this term's approved colleges. **Retract approval** needs a note → new action `college_vpaa_retract`. It sets status `with_registrar` (unlocked, `last_reject_from = vpaa`), notifies the registrars and writes to the Transaction log. The approved copy stays in use until a corrected one is approved. Retracted colleges show under "Being updated".
- **History** (`pages/viewing-with-approval/history`, mode `history`): every term with an approved timetable. Pick a term, then open a college (read-only). Terms approved before the per-college approval are read from `term_schedule_approvals.published_json`.
- New GET requests on `schedule.php` (VPAA and registrars only):
  - `college_copy=1&college=c-N&which=pending|published[&term=ID]` → `college_copy_for_review()`. Classes are tagged with `programId` / `programName`.
  - `vpaa_history=1` → `vpaa_history()`.
- `schedule.php?colleges=1` with no year now uses the current offering.
- Sidebar (config/navigation.php, VPAA): Dashboard · Approved schedules · History · Room schedule · Reports · Approval. There's a new `check` icon.
- (later the same day) **Retract approval** is also in the Dashboard pop-up for approved colleges (not in History). When a college's approved copy is still in use but the registrars are already preparing a new version, the pop-up explains why there's nothing to retract.
- **Room schedule removed** from the VPAA (sidebar item gone; `pages/viewing-with-approval/viewer` and `viewer-rooms` moved to `parked/room-schedule/`). `viewing-with-approval/viewer.html` falls back to the redirect stub, which opens the Dashboard.
- Page titles fixed: "VPAA – …", "Dean – …", "Program Chair – …" (the VPAA was "Viewing (approval)", Deans were "CAS").
- The VPAA's timetable notifications now open the Dashboard, or Approved schedules for `ws=final` links, instead of Reports (`notification_href_for_role`).

## One place for each timetable (2026-10-14)

- **Deans and Program Chairs**: the Dashboard shows **this term's** approved timetable only (`college_final=1&scope=current`). **Past timetables** (new `roles/viewer/past-timetables`, `scope=past`) has every earlier approved term, so when the term changes the old one moves there. **Reports was removed** for them. Their menu: Dashboard · Curriculum · Past timetables · Requests.
- **VPAA**: Reports and the Approval records page were removed. Menu: Dashboard · Approved schedules · History.
- The removed pages (`roles/viewer/final-report`, `pages/viewing-with-approval/viewer-requests`) are now redirect stubs to the Dashboard, so old links and bookmarks still work. The originals are in `parked/`. The staff keep their Reports.
- Notifications: the VPAA's always open the Dashboard (or Approved schedules); Deans' and Program Chairs' timetable notifications open the Dashboard.
- `partials/simple-page.blade.php` is the shared layout for Approved schedules, History and Past timetables.

## Working timetable: colleges leave when sent, come back with the note (2026-10-17)

- **Who has which colleges** (`college_in_working_for`): the Records Officer works on **drafts**. The University Registrar works on **drafts, what the Records Officer sent (pending_super_admin), and what's with them (with_registrar)**. Every other college is "away".
- **Sending takes it out** (`college_take_out_of_working`), and a copy goes into the sender's **saves**, named "*College* – sent to the University Registrar / to the VPAA, *date time*":
  - Records Officer → University Registrar (`college_submit_to_registrar`)
  - University Registrar → VPAA (`college_send_to_vpaa`)
  - locking an unlocked college again (`college_relock`; the copy is named "locked again (your edits)")
- **Coming back puts it in again** (`college_put_in_working`, now for either actor), with the note shown at the top of the Working timetable:
  - the Records Officer takes back their request → their Working timetable
  - the University Registrar takes back from the VPAA → theirs
  - the University Registrar sends back to the Records Officer → the Records Officer's (the Registrar's own copy if they were working on it; it leaves the Registrar's)
  - the VPAA sends back → the University Registrar's
  - the VPAA retracts, or the University Registrar unlocks → the approved copy goes into the University Registrar's (the approved one stays in use until a corrected one is approved)
- **Generate** (`apply_college_locks_to_generate`): away colleges' sections are skipped (`skipCollegeIds` → `build_schedule_jobs`). Their current copy (sent / with the Registrar / waiting for the VPAA / approved) is handed in as already-placed classes, so **their rooms stay booked**. The result never brings them back (`college_working_classes_only`).
- A Working timetable from before this change is tidied the first time it's opened (`apply_workspace_to_payload`).
- **Page** (`schedule-colleges.js` → `#schedWorkingNotes`): "*College* is back in your Working timetable. The VPAA sent it back (date): *note*", a "Not in your Working timetable: …" line, and "All colleges are approved for this term" (with a link to Reports) when nothing is left.
- Tests: `test_a_sent_college_leaves_the_working_timetable_and_comes_back_with_the_note`.

## Reports: Final report, Finished curriculums, Past terms (2026-10-17)

- **The "Awaiting the VPAA's approval" tab is gone** from staff Reports (`schedule-page.js`: the report page only has `final`). What waits for the VPAA is on Create schedule → For approval. Old `final-report.html?ws=awaiting` notification links for the registrars now open Create schedule → For approval (`notification_href_for_role`).
- **Finished curriculums** (`roles/staff/finished-curriculums`, `resources/js/legacy/curriculum-archive.js` + `css/legacy/curriculum-archive.css`, server `legacy-api/curriculum-archive.php` via `curricula.php?finished=1` / `?finished_id=cur-N`):
  - The "graveyard": archived curriculums, each with its **subjects** and the **approved schedules of every term its sections were scheduled in** (by term, then section).
  - **Not used this term**: active curriculums no section follows in the current term, with **Archive**.
  - The **Records Officer** and **University Registrar** archive and restore (`canArchive`); only the University Registrar deletes (`canDelete`) an archived curriculum that was never used. Archiving uses `PATCH curricula.php {status}`, which is logged. The Curriculums page: **Upload CSV** only for the University Registrar (older curriculums from before the system), no Archive/Restore there (a note links to Finished curriculums). Deans and Program Chairs can still discard their own drafts (curriculum-reviews.php).
  - From now on every class records **`curriculumId`** (`classes_from_placed_groups`, `list_scheduled_classes`). Older approved copies are matched by section; when the sections are gone (cleared or rolled over), by program, if that program has only this one curriculum (programs matched by name, and in a college with one active program an old program row counts as it). `curriculum_term_classes` does the matching for both the detail and the list, so "in use this term" and "scheduled in N terms" also count the approved timetables, not only sections (2026-10-17).
- **Past terms** (`roles/staff/past-terms`): the approved timetables of every term by college. It reuses `vpaa-board.js` in `history` mode (view and Download PDF).
- `partials/simple-page.blade.php` takes optional `$tabs` (the top tab links).

## Start next term (2026-10-17)

- **Data management → Sections → Offering term → "Start next term…"**, for the **University Registrar** only (page `next-term.js` + `css/legacy/next-term.css`; server `legacy-api/term-rollover.php` via `POST sections.php` actions `nextTermPreview`, `nextTermCreate`, `nextTermUndo`).
- **Preview** (nothing saved). The next term is worked out from the current one: 1st → 2nd; 2nd → next school year's 1st, or Summer; Summer → next 1st.
  - 1st → 2nd: every section as is.
  - 2nd → Summer: only sections whose curriculum has summer subjects for that year level.
  - Next school year: each section **moves up a year and keeps its curriculum**, and the program's last year (`programs.normal_years`, default 4) **graduates**. From Summer it starts from the 2nd semester's sections, which have every year level. New 1st-years are typed per program and get the program's **newest active** curriculum (split into sections of up to 40).
  - Warnings: a section following an archived curriculum; a program with no active curriculum; the target term already has sections.
  - In the dialog you can change counts and section numbers, remove or add sections, and change a year level's curriculum. Retake sections aren't carried over.
- **Create**: archives the current term (`archive_offering`), starts the next (`set_current_offering`), and makes each program/year with `save_section_plan`, so the plans and Transaction log entries match making them by hand. Logged as "Started *next* from *current*: N sections created." The table `term_rollovers` is added automatically.
- **Undo** (`rollover_undoable`): available until anything is scheduled in the new term (no class offerings and no working timetable with classes). It removes the new term's sections and makes the previous term current again. Shown as a note under the button.
- Test: `tests/Feature/TermRolloverTest.php` (its own database copy, `mcu_nex_term_test`).

## Notes on a class: Program Chair → Dean → Registrar's office (2026-10-17)

- On the Dean/Program Chair **Dashboard** (this term's approved timetable, `college-timetable.js`), every placed class row has a **Note** button. The note (a topic: day/time, room, split/add meeting, merge, unmerge, partition, remove, other, plus text) is a normal request through `requests.php` (`action: create`, one class id). Nothing new on the server side.
- **Program Chair**: the note goes to the Dean first (`pending_dean`). A panel above the timetable lists their latest notes with where each one is and the replies (the Dean's answer, the Dean's note when forwarding, the Records Officer's reply). Rows with an open note say "Note sent".
- **Dean**: the panel lists the Program Chairs' notes waiting for them, with **Forward to the Registrar** (`forward`, optional note → `pending_admin`, the Records Officer's Requests page) or **Answer instead** (`hold`, an answer is required; it isn't forwarded). A Dean's own notes go straight to the Records Officer.
- The Requests page still has everything (the panel links to it).
- Server fix: `request_class_choices` (bootstrap.php) matches a Program Chair's classes to their programs by name too (`college_tag_programs`), because an approved copy can point at an older program row; before, the chair could get no classes to pick.
- Test: `tests/Feature/ClassNotesTest.php` (copy of the database, `mcu_nex_notes_test`).

## Final report: approved timetable per college (2026-10-17)

- Reports → Final report keeps its old look and its by-section / by-room / by-class views over the whole campus. Above it, a row per college (`resources/js/legacy/final-report.js`, `css/legacy/final-report.css`, data from `schedule.php?colleges=1`): approved on which date and how many classes; a college whose new version is still with the registrars or the VPAA says the approved one stays in use until then; a college with nothing approved says "Not approved yet".
- Clicking a college sets the College filter (click again for all colleges). Colleges with nothing approved are marked "(not approved yet)" in the filter, and picking one says so instead of only "No sections match".
- The old campus-wide "VPAA published — Official copy…" banner is hidden on the Final report (`schedule-page.js` `syncWorkspaceUi`), since the row per college replaces it. The row isn't printed.
- The "Awaiting the VPAA's approval" tab stays removed from Reports; registrars' old `final-report.html?ws=awaiting` notification links open Create schedule → For approval, the VPAA's open the Dashboard.
