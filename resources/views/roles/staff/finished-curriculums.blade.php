{{-- Reports → Finished curriculums (curriculum-archive.js): the curriculums that don't run any more, with their schedules. --}}
@include('partials.simple-page', [
    'chip' => $role === 'super-admin' ? 'University Registrar' : 'Records Officer',
    'css' => ['college-timetable', 'curriculum-form', 'schedule-colleges', 'curriculum-archive'],
    'js' => ['curriculum-form', 'curriculum-archive'],
    'tabs' => '<a class="topbar-tab" href="final-report.html">Final report</a><span class="topbar-tab topbar-tab--current" aria-current="page">Finished curriculums</span><a class="topbar-tab" href="past-terms.html">Past terms</a>',
    'root' => '<section class="vb-card" id="fcRoot" aria-live="polite"></section>',
    'title' => 'Finished curriculums',
    'intro' => $role === 'super-admin'
        ? 'Curriculums that don’t run any more, with their subjects and the schedules of every term they were used in. Archive one here once its last batch has graduated. One that nothing ever used (a wrong upload, a duplicate) can be deleted.'
        : 'Curriculums that don’t run any more, with their subjects and the schedules of every term they were used in. Archive one here once its last batch has graduated. The University Registrar can delete one that nothing ever used.',
])
