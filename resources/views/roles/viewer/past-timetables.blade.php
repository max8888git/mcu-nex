{{-- Deans and Program Chairs: approved timetables of earlier terms (the Dashboard has this term's). --}}
@include('partials.simple-page', [
    'chip' => $role === 'viewing-program-head' ? 'Program Chair' : 'Dean',
    'css' => ['college-timetable'],
    'js' => ['college-timetable'],
    'root' => '<section class="ct-card" id="ctRoot" data-mode="past" aria-label="Past timetables"></section>',
    'title' => 'Past timetables',
    'intro' => 'Your college’s approved timetables from earlier terms. This term’s is on the Dashboard; when a new term starts, it moves here.',
])
