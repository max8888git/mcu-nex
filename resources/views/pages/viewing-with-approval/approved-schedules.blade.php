@include('partials.simple-page', [
    'chip' => 'VPAA',
    'css' => ['schedule-colleges', 'college-timetable', 'vpaa-board'],
    'js' => ['vpaa-board'],
    'root' => '<section class="vb-card" id="vbRoot" data-mode="approved" aria-live="polite"></section>',
    'title' => 'Approved schedules',
    'intro' => 'The colleges you approved this term. Open one to see its timetable, or retract the approval with a note to send it back to the University Registrar.',
])
