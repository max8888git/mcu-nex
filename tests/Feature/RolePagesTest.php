<?php

namespace Tests\Feature;

use Tests\TestCase;

class RolePagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_staff_roles_share_one_view_with_their_own_role_text(): void
    {
        $this->get('/admin/home-dashboard.html')
            ->assertOk()
            ->assertSee('<span class="chip">Records Officer</span>', false)
            ->assertDontSee('University Registrar</span>', false);

        $this->get('/super-admin/home-dashboard.html')
            ->assertOk()
            ->assertSee('<span class="chip">University Registrar</span>', false);
    }

    public function test_viewer_roles_share_one_view(): void
    {
        foreach (['viewing', 'viewing-with-approval', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/dashboard.html")->assertOk();
        }
    }

    public function test_role_specific_pages_still_win_over_shared_views(): void
    {
        // the VPAA's Approval (records) page was removed: its own stub sends old links to the Dashboard,
        // while Deans and Program Chairs keep the shared Requests page
        $this->get('/viewing-with-approval/viewer-requests.html')->assertOk()->assertSee("location.replace('dashboard.html')", false);
        $this->get('/viewing/viewer-requests.html')->assertOk()->assertSee('Request a schedule change');
        // the room schedule viewer was removed: the old link opens the dashboard
        $this->get('/viewing-with-approval/viewer.html')->assertOk()->assertSee('Redirecting');
        $this->get('/viewing-with-approval/viewer-rooms.html')->assertNotFound();
    }

    public function test_sidebar_shows_the_roles_links_and_highlights_the_current_section(): void
    {
        $this->get('/admin/data-management/facilities.html')
            ->assertOk()
            ->assertSee('id="app-nav"', false)
            ->assertSee('Transaction log')
            ->assertSee('aria-current="page"', false)
            ->assertSeeInOrder(['is-active', 'Data management'], false);

        // viewer roles get their own, shorter menu; VPAA approves instead of requesting
        $this->get('/viewing/dashboard.html')->assertOk()->assertDontSee('Transaction log')
            ->assertSee('Past timetables')->assertDontSee('final-report.html', false);
        $this->get('/viewing-with-approval/dashboard.html')->assertOk()->assertSee('Approved schedules')
            ->assertDontSee('Room schedule')->assertDontSee('viewer-requests.html', false)->assertDontSee('final-report.html', false);
    }

    public function test_every_role_has_a_my_account_page_and_only_super_admin_edits_it(): void
    {
        foreach (['admin', 'super-admin', 'viewing', 'viewing-with-approval', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/account.html")->assertOk()->assertSee('My account')->assertSee('data-profile-menu', false);
        }
        $this->get('/super-admin/account.html')->assertSee('id="maDetailsForm"', false);
        $this->get('/viewing/account.html')->assertDontSee('id="maDetailsForm"', false)->assertSee('Ask your Records Officer or the University Registrar');
    }

    public function test_rooms_available_is_one_page_and_old_filter_links_still_work(): void
    {
        $this->get('/super-admin/rooms-available.html')->assertOk()->assertSee('id="rfPage"', false)->assertDontSee('Request allocation')
            ->assertSee('id="rfShow"', false)->assertSee('Use current time')->assertDontSee('Right now');
        $this->get('/admin/rooms-available-filters.html')->assertOk()->assertSee("location.replace('rooms-available.html'", false);
        $this->getJson('/api/room-availability.php')->assertStatus(401);
    }

    public function test_create_schedule_has_the_colleges_panel_for_both_staff_roles(): void
    {
        $this->get('/super-admin/create-schedule.html')->assertOk()
            ->assertSee('id="schedColleges"', false)
            ->assertSee('data-actor="super_admin"', false)
            ->assertSee('For approval')
            ->assertSee('Generate settings')
            ->assertSee('id="schedFind"', false)
            ->assertSee('Needs attention');
        $this->get('/admin/create-schedule.html')->assertOk()
            ->assertSee('id="schedColleges"', false)
            ->assertSee('data-actor="admin"', false)
            ->assertSee('Sent for approval');
    }

    public function test_reports_is_gone_for_the_vpaa_deans_and_program_chairs(): void
    {
        // the Dashboard has this term; Past timetables (Deans, Program Chairs) / History (VPAA) the rest
        foreach (['viewing', 'viewing-program-head', 'viewing-with-approval'] as $role) {
            $this->get("/{$role}/final-report.html")->assertOk()->assertSee("location.replace('dashboard.html')", false);
        }
        foreach (['viewing', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/past-timetables.html")->assertOk()->assertSee('id="ctRoot" data-mode="past"', false);
            $this->get("/{$role}/dashboard.html")->assertSee('id="ctRoot" data-mode="current"', false);
            // parked (parked/department-report): the old link opens the Dashboard
            $this->get("/{$role}/department-report.html")->assertOk()->assertSee("location.replace('dashboard.html')", false);
        }
        $this->get('/viewing-with-approval/past-timetables.html')->assertOk()->assertSee("location.replace('history.html')", false);
        // the staff keep their Reports
        $this->get('/super-admin/final-report.html')->assertOk()->assertDontSee('Redirecting');
    }

    public function test_curriculum_pages_for_the_colleges_and_the_registrars(): void
    {
        foreach (['viewing', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/curriculum-builder.html")->assertOk()->assertSee('id="cbRoot"', false)->assertSee('curriculum-builder', false);
            $this->get("/{$role}/dashboard.html")->assertOk()->assertSee('Curriculum')
                ->assertSee('id="ctRoot"', false)->assertDontSee('class="dept-cards-section', false);
            $this->get("/{$role}/curriculum-builder.html")->assertSee('id="cbTemplate"', false);
        }
        $this->get('/viewing-program-head/dashboard.html')->assertSee('href="curriculum-builder.html" aria-label="New curriculum">+ New</a>', false);
        $this->get('/viewing/dashboard.html')->assertSee('+ Request');
        // the VPAA's dashboard keeps its approval card instead
        $this->get('/viewing-with-approval/dashboard.html')->assertOk()->assertDontSee('id="ctRoot"', false);
        foreach (['admin', 'super-admin'] as $role) {
            $this->get("/{$role}/curriculums/review.html")->assertOk()->assertSee('id="crRoot"', false)->assertSee('From the colleges');
            // one Curriculums page: read-only list + viewer; the old filter page and Data management → Curriculums redirect to it
            $this->get("/{$role}/curriculums/curriculums.html")->assertOk()->assertSee('id="clRoot"', false)->assertSee('href="review.html"', false);
            $this->get("/{$role}/curriculums/curriculum-filters.html")->assertOk()->assertSee("location.replace('curriculums.html')", false);
            $this->get("/{$role}/data-management/add-curriculum.html")->assertOk()->assertSee("location.replace('../curriculums/curriculums.html')", false);
            $this->get("/{$role}/data-management/sections.html")->assertOk()->assertDontSee('add-curriculum.html', false);
        }
    }

    public function test_vpaa_has_boxes_on_the_dashboard_plus_approved_schedules_and_history(): void
    {
        $this->get('/viewing-with-approval/dashboard.html')->assertOk()
            ->assertSee('id="vbRoot" data-mode="waiting"', false)->assertDontSee('vpaaHomeCard', false)
            ->assertSee('Approved schedules')->assertSee('History');
        $this->get('/viewing-with-approval/approved-schedules.html')->assertOk()->assertSee('data-mode="approved"', false);
        $this->get('/viewing-with-approval/history.html')->assertOk()->assertSee('data-mode="history"', false);
        // VPAA pages only
        $this->get('/viewing/history.html')->assertNotFound();
        $this->get('/viewing/approved-schedules.html')->assertNotFound();
    }

    public function test_staff_reports_have_final_report_finished_curriculums_and_past_terms(): void
    {
        foreach (['admin', 'super-admin'] as $role) {
            $this->get("/{$role}/final-report.html")->assertOk()
                ->assertDontSee('data-ws-tab="awaiting"', false)
                ->assertSee('href="finished-curriculums.html"', false)->assertSee('href="past-terms.html"', false);
            $this->get("/{$role}/finished-curriculums.html")->assertOk()->assertSee('id="fcRoot"', false)
                ->assertSeeInOrder(['is-active', 'Reports'], false);
            $this->get("/{$role}/past-terms.html")->assertOk()->assertSee('id="vbRoot" data-mode="history"', false);
        }
        // staff pages only
        $this->get('/viewing/finished-curriculums.html')->assertNotFound();
        $this->get('/viewing-with-approval/past-terms.html')->assertNotFound();
    }

    public function test_only_the_university_registrar_gets_start_next_term(): void
    {
        $this->get('/super-admin/data-management/sections.html')->assertOk()->assertSee('id="ntStart"', false);
        $this->get('/admin/data-management/sections.html')->assertOk()->assertDontSee('id="ntStart"', false);
    }

    public function test_pages_a_role_never_had_are_still_404(): void
    {
        $this->get('/viewing/viewer-rooms.html')->assertNotFound();
        $this->get('/viewing/home-dashboard.html')->assertNotFound();
        $this->get('/nobody/home-dashboard.html')->assertNotFound();
        $this->get('/admin')->assertNotFound();
    }
}
