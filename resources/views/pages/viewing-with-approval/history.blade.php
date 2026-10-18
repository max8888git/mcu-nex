@include('partials.simple-page', [
    'chip' => 'VPAA',
    'css' => ['schedule-colleges', 'college-timetable', 'vpaa-board'],
    'js' => ['vpaa-board'],
    'root' => '<section class="vb-card" id="vbRoot" data-mode="history" aria-live="polite"></section>',
    'title' => 'History',
    'intro' => 'Approved timetables from this and past terms. Pick a term, then open a college to see its timetable.',
])
