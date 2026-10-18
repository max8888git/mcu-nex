# Curriculums from the colleges

For backend review. Program Chairs (or the Dean, in a college without
departments) make curriculums, the Dean approves, the Records Officer or the
University Registrar accepts. An accepted curriculum becomes the active one
(`curricula` / `curriculum_courses`) and is used for sectioning.

## Flow and statuses

`draft` → `pending_dean` → `dean_approved` → `pending_registrar` → `accepted`.
Sending back (note required) returns it to `draft` with `last_return_from`
(`dean` or `registrar`). A newer accepted version marks the older one
`superseded`. The Dean may also send a draft straight to the Registrar
(approve and send in one step). "Request a revision" on an accepted version
opens a new `draft` version; the accepted one stays active until the revision
is accepted.

## Files

- `legacy-api/curriculum-reviews.php` (new): everything above. Table
  `curriculum_reviews` is created on first use (courses stored as JSON until
  accepted). Roles are taken from the signed-in user, never the request.
  - Program Chair: programs in their department (`users.department_id`) or
    listed in `user_programs`, in colleges with departments.
  - Dean: every active program in their college.
  - Records Officer / University Registrar: review all.
- `app/Http/Controllers/CurriculumReviewController.php`, `routes/api.php`
  (`api/curriculum-reviews.php`), `app/Services/DomainCatalog.php` (loads the file).
- `legacy-api/curricula.php`: `replace_courses()` now also stores `is_elective`.
  Accepting calls the existing `save_curriculum(..., replace)` — the same path
  as the CSV upload.
- `legacy-api/bootstrap.php`: notification links for `curriculum-builder` /
  `curriculum-review`; `db()` reconnects if the configured database changes
  (only matters in the test suite).
- `tests/Feature/CurriculumReviewTest.php`: the whole flow on a copy of the
  database (`mcu_nex_curr_test`).

Backups of the changed legacy files are in `_archive/baseline-2026-10-11/`.

## Requests (api/curriculum-reviews.php)

- `GET ?mine=1` — Dean / Program Chair: their programs, active curriculums, versions, room types.
- `GET ?inbox=1` — registrars: waiting, at the colleges, accepted.
- `GET ?id=rv-N` — one version with courses, checks (`problems`), allowed actions (`can`), history.
- `POST` `action`: `create` (from blank, the active curriculum, a version, or imported rows),
  `save`, `discard`, `send_to_dean`, `dean_decide` (`accept`, `note`),
  `send_to_registrar` (`ids`), `take_back`, `registrar_decide` (`ids`, `accept`, `note`),
  `request_revision` (`note`).

## Checks (server and page use the same rules)

Errors block sending: missing code/title, duplicate code, a separate
"CCS 101L" row next to CCS 101 (the lab code is added automatically), units
outside 0–12 or 0 total, missing lecture/lab room type, a room type not in
Data management, a prerequisite that isn't in the curriculum or isn't in an
earlier term. Prerequisites like "3rd year standing" or "**" are allowed.
Warnings: same title under different codes, more than 30 units in a term.

## Front end

`roles/viewer/curriculum-builder` (Deans, Program Chairs; side menu
"Curriculum"), `roles/staff/curriculums/review` ("From the colleges" tab on
Curriculums), `resources/js/legacy/curriculum-form.js` (shared form, checks,
CSV, print), `resources/js/curriculum-pdf.js` (real PDF with jsPDF 4.2.1 +
jspdf-autotable 5.0.8, new npm dependencies, 0 audit issues).

## Form updates (2026-10-13)

- **CSV template**: "Download CSV template" on the Curriculum page, plus a "What should the CSV file look like?" guide in the Start dialog. It's a filled-in example in the same columns the import reads (`McuCurriculumForm.csvTemplate`). A new **Elective** column (Yes or blank) is read by `CurriculumStore` (`is_elective`) and written by Download CSV.
- **Course code required to save**: the page and the server (`save`) both refuse a row with something typed in but no course code. Rows left untouched after "+ Add subject" are dropped on save.
- **Prerequisites are a dropdown**: "— None", a year standing up to the subject's own year (stored as `3RD YEAR STANDING`, already allowed by the checks), or a subject from an earlier term. Up to 3, shown as chips. Retyping a subject's code updates the prerequisites that point to it.

## Dean's own draft, autosave, CSV by semester (2026-10-14)

- **Two versions at once**: the Program Chair works on one version per program. The Dean can keep **one draft of their own** beside it (`curriculum_is_dean_draft`: status draft, created by a dean). When the Dean approves the Program Chair's version, that one goes on to the Registrar, and the Dean's draft stays saved as a draft.
- **One on the way at a time**: `send_to_dean` / `send_to_registrar` refuse while another version of the program is waiting for the Dean, approved, or with the Registrar (`curriculum_assert_none_on_the_way`).
- The Program Chair doesn't see, open or edit the Dean's own drafts. A Program Chair's create still refuses when their version is in progress. The Dean can still edit after approving, until it's sent to the Registrar.
- The approval strip on the Curriculum page shows only when the college has Program Chairs (departments).
- **Autosave**: while editing, the form saves to the server 3 seconds after the last change, without redrawing. It's skipped when a subject has no course code. The copy on the computer stays as a backup.
- **CSV by semester**: Download CSV and the template put a blank line and a heading ("1st Year - 2nd Semester") before each term. `CurriculumStore` skips heading rows, and uses them for Year Level / Semester when a row leaves those blank. Year standings are written "3rd Year Standing".
- Program Chair dashboard: the "+ Request" button is now "+ New", which opens the Curriculum page.

## Compare, copy, what changed, subject notes, Excel (2026-10-14)

- **Compare…** (Dean / Program Chair page and the registrars' review page): differences between this version and another one of the same program: only in one, only in the other, and different fields (`McuCurriculumForm.diff`, `compareDialog`). The Dean's hint on the Program Chair's version has "Compare with your draft".
- **Copy subjects…** (while editing): tick whole terms or single subjects from another version. A subject with the same code is replaced (`copyDialog`, `form.merge`).
- **Send-back record**: new column `curriculum_reviews.return_json`, added by `ensure_curriculum_reviews_schema` when missing. It holds `{ from: dean|registrar, at, courses, notes }`. It's written on every send-back (and on a revision request) and returned as `review.returned`.
  - When the version comes back, the Dean (pending_dean) or the registrars (pending_registrar) see "N changes since it was sent back", rows marked New / Changed, removed subjects listed, and whether each subject note was handled.
  - It's cleared when the Dean approves (unless the Registrar sent it back) and when the Registrar accepts.
- **Notes on single subjects**: reviewers click **Note** on a row. The notes go with the send-back (`rowNotes: { code: note }`, only codes in the version, up to 500 characters each) and show on that subject's row for the people fixing it. With subject notes, the general note may be left out; it then reads "See the notes on N subjects." Bulk send-backs carry no subject notes. The Dean's own drafts are hidden from everyone but the Dean.
- **Excel**: "Excel template" (and the Start dialog) give an .xlsx file. It has set column widths, a frozen headings row, a purple heading per term, dropdowns (Year Level, Semester, room types, Elective), number checks on units, and a "How to fill" sheet. The import takes .xlsx as well as .csv. `resources/js/curriculum-excel.js` loads ExcelJS only when used; it's a separate 930 kB chunk. `CurriculumStore.parseGrid` reads the rows.
- npm: `exceljs@4.4.0`, with an `overrides` entry pinning `uuid@^11.1.1` (npm audit: 0 vulnerabilities).

## Several named drafts (2026-10-14)

- A program can have **any number of drafts**. The Program Chair and the Dean each keep their own; the Dean's are hidden from everyone else. Each draft has a **name** (new column `curriculum_reviews.name`, added automatically; the default is "Draft N", and a revision is named "Revision of version N").
- `create` takes `name`; the new action `rename` (`{ id, name }`) works while the person may edit the draft. Names show on the program card, on the version, in the registrars' list, and in notifications ("version 3, “With new electives”").
- The program card lists the version **on its way** (with the Dean, approved, or with the Registrar) and then the person's **drafts**, each with Open / Rename / Send to Dean (Program Chair) or Send to Registrar (Dean). Sending is locked while another version is on its way (`curriculum_assert_none_on_the_way`); the other drafts stay saved.
- The Start dialog asks for a name and can copy any earlier version. The Dean's bulk send takes one per program.
- The old one-draft limits (and `curriculum_open_version`) are gone. A revision request no longer waits for other drafts.
