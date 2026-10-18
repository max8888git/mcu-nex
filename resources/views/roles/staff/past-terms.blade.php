{{-- Reports → Past terms (vpaa-board.js, mode "history"): approved timetables of every term, by college. --}}
@include('partials.simple-page', [
    'chip' => $role === 'super-admin' ? 'University Registrar' : 'Records Officer',
    'css' => ['schedule-colleges', 'college-timetable', 'vpaa-board'],
    'js' => ['vpaa-board'],
    'tabs' => '<a class="topbar-tab" href="final-report.html">Final report</a><a class="topbar-tab" href="finished-curriculums.html">Finished curriculums</a><span class="topbar-tab topbar-tab--current" aria-current="page">Past terms</span>',
    'root' => '<section class="vb-card" id="vbRoot" data-mode="history" aria-live="polite"></section>',
    'title' => 'Past terms',
    'intro' => 'Approved timetables from this and earlier terms. Pick a term, then open a college to see its timetable and download it.',
])
