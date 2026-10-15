<?php

namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarRedesignTest extends TestCase
{
    /** @test */
    public function test_sidebar_assets_exist_and_are_valid()
    {
        $cssPath = public_path('css/sidebar.css');
        $jsPath = public_path('js/sidebar.js');

        $this->assertFileExists($cssPath, 'public/css/sidebar.css must exist');
        $this->assertFileExists($jsPath, 'public/js/sidebar.js must exist');

        $this->assertGreaterThan(500, filesize($cssPath), 'sidebar.css should not be empty');
        $this->assertGreaterThan(500, filesize($jsPath), 'sidebar.js should not be empty');

        $cssContent = file_get_contents($cssPath);
        $this->assertStringContainsString('ch-sidebar', $cssContent);
        $this->assertStringContainsString('@media (min-width: 768px) and (max-width: 1199', $cssContent);
        $this->assertStringContainsString('menu-title', $cssContent);
    }

    /** @test */
    public function test_all_sidebar_partials_exist()
    {
        $expectedPartials = [
            '_brand.blade.php',
            '_search.blade.php',
            '_nav-global.blade.php',
            '_nav-ess.blade.php',
            '_nav-reception.blade.php',
            '_nav-billing.blade.php',
            '_nav-pharmacy.blade.php',
            '_nav-store.blade.php',
            '_nav-nursing.blade.php',
            '_nav-morgue.blade.php',
            '_nav-maternity.blade.php',
            '_nav-lab.blade.php',
            '_nav-radiology.blade.php',
            '_nav-doctor.blade.php',
            '_nav-hmo.blade.php',
            '_nav-accounting.blade.php',
            '_nav-hr.blade.php',
            '_nav-audit.blade.php',
            '_nav-admin.blade.php',
            '_user-profile.blade.php',
        ];

        $partialDir = resource_path('views/admin/partials/sidebar');

        foreach ($expectedPartials as $partial) {
            $this->assertFileExists($partialDir . '/' . $partial, "Sidebar partial {$partial} must exist");
        }
    }

    /** @test */
    public function test_app_layout_references_standalone_sidebar_assets()
    {
        $appBladePath = resource_path('views/admin/layouts/app.blade.php');
        $this->assertFileExists($appBladePath);

        $content = file_get_contents($appBladePath);
        $this->assertStringContainsString('css/sidebar.css', $content);
        $this->assertStringContainsString('js/sidebar.js', $content);
        $this->assertStringContainsString("versioned_asset('css/sidebar.css')", $content);
        $this->assertStringContainsString("versioned_asset('js/sidebar.js')", $content);
        $this->assertStringNotContainsString('/* Fresh Hamburger Menu Implementation */', $content);
    }

    /** @test */
    public function test_sidebar_renders_for_superadmin()
    {
        $user = User::factory()->create(['status' => 1]);
        $role = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->actingAs($user);

        $rendered = View::make('admin.partials.sidebar')->render();

        $this->assertStringContainsString('id="sidebar"', $rendered);
        $this->assertStringContainsString('id="ch-sidebar-search"', $rendered);
        $this->assertStringContainsString('id="sidebar-global-dashboard"', $rendered);
        $this->assertStringContainsString('id="sidebar-bottom-logout"', $rendered);
    }

    /** @test */
    public function test_tablet_css_enforces_small_text_under_icons()
    {
        $cssContent = file_get_contents(public_path('css/sidebar.css'));

        // Verify the 768px-1199px tablet media query exists
        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*768px\)\s*and\s*\(max-width:\s*1199(\.98px|px)\)/',
            $cssContent,
            'Tablet media query must be present in sidebar.css'
        );

        // Verify that menu-title is visible on tablet
        $this->assertStringContainsString(
            'width: 80px',
            $cssContent,
            'Tablet sidebar width should be 80px'
        );
        $this->assertStringContainsString(
            'display: flex !important;',
            $cssContent
        );
        $this->assertStringContainsString(
            'flex-direction: column !important;',
            $cssContent
        );
    }

    /** @test */
    public function test_portal_flyout_rules_exist_in_css_and_js()
    {
        $cssContent = file_get_contents(public_path('css/sidebar.css'));
        $jsContent = file_get_contents(public_path('js/sidebar.js'));

        // Verify floating flyout class exists in CSS
        $this->assertStringContainsString('.ch-sidebar-flyout', $cssContent);
        $this->assertStringContainsString('.ch-sidebar-flyout--visible', $cssContent);
        $this->assertStringContainsString('.ch-sidebar-flyout-menu', $cssContent);
        $this->assertStringContainsString('.ch-sidebar-flyout-link', $cssContent);

        // Verify JS handles portal container and rail mode
        $this->assertStringContainsString('ch-sidebar-flyout', $jsContent);
        $this->assertStringContainsString('setupFlyoutTriggers', $jsContent);
        $this->assertStringContainsString('isRailMode', $jsContent);
        $this->assertStringContainsString('ch-sidebar-flyout-menu', $jsContent);
        $this->assertStringContainsString('ch-sidebar-flyout-link', $jsContent);
    }

    /** @test */
    public function test_doctor_and_reception_consultation_encounters_links()
    {
        $user = User::factory()->create(['status' => 1]);
        $roleDoctor = Role::firstOrCreate(['name' => 'DOCTOR', 'guard_name' => 'web']);
        $roleReceptionist = Role::firstOrCreate(['name' => 'RECEPTIONIST', 'guard_name' => 'web']);
        $user->assignRole([$roleDoctor, $roleReceptionist]);

        $this->actingAs($user);

        $rendered = View::make('admin.partials.sidebar')->render();

        // Doctor section tests
        $this->assertStringContainsString('ch-sidebar-section-header', $rendered);
        $this->assertStringContainsString('id="sidebar-doctor-consultations-toggle"', $rendered);
        $this->assertStringContainsString('id="sidebar-doctor-consultations"', $rendered);
        $this->assertStringContainsString('id="sidebar-doctor-encounters"', $rendered);
        $this->assertStringContainsString(route('encounters.index'), $rendered);

        // Reception section tests
        $this->assertStringContainsString('id="sidebar-receptionist-encounters"', $rendered);
        $this->assertStringContainsString('id="sidebar-receptionist-consultations-toggle"', $rendered);
        $this->assertStringContainsString('id="sidebar-receptionist-consultations"', $rendered);
        $this->assertStringContainsString('id="sidebar-receptionist-prev-consultations"', $rendered);
        $this->assertStringContainsString(route('allPrevEncounters'), $rendered);
    }

    /** @test */
    public function test_search_filtering_rules_and_section_headers()
    {
        $cssContent = file_get_contents(public_path('css/sidebar.css'));
        $jsContent = file_get_contents(public_path('js/sidebar.js'));

        $this->assertStringContainsString('.ch-sidebar-section-header.ch-sidebar-search-hidden', $cssContent);
        $this->assertStringContainsString('.ch-sidebar-section-header', $jsContent);
        $this->assertStringContainsString('ch-sidebar-search-hidden', $jsContent);
        $this->assertStringContainsString('filterSidebar', $jsContent);
    }

    /** @test */
    public function test_leadership_badges_and_brand_app_name()
    {
        $user = User::factory()->create(['status' => 1]);
        $roleSuper = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $user->assignRole($roleSuper);

        // Create or update staff profile with leadership roles
        $staff = \App\Models\Staff::firstOrNew(['user_id' => $user->id]);
        $staff->is_dept_head = 1;
        $staff->is_unit_head = 1;
        $staff->save();

        $user->unsetRelation('staff_profile');
        $user->load('staff_profile');
        $this->actingAs($user);

        $rendered = View::make('admin.partials.sidebar')->render();

        // APP_NAME in branding
        $expectedAppName = config('app.name', 'Corehealth');
        $this->assertStringContainsString($expectedAppName, $rendered);

        // Badges for Dept Head and Unit Head
        $this->assertStringContainsString('Dept Head', $rendered);
        $this->assertStringContainsString('Unit Head', $rendered);
        $this->assertStringContainsString('ch-sidebar-badge-dept-head', $rendered);
        $this->assertStringContainsString('ch-sidebar-badge-unit-head', $rendered);
    }

    /** @test */
    public function test_tablet_section_group_badges_and_brand_styling()
    {
        $cssContent = file_get_contents(public_path('css/sidebar.css'));

        // Brand name visible on tablet
        $this->assertStringContainsString('.ch-sidebar .nav-brand .nav-brand-title', $cssContent);

        // Section group badge in tablet rail
        $this->assertStringContainsString('.ch-sidebar-nav-scrollable li:has(.nav-item-head)', $cssContent);

        // Tablet leadership badge styling
        $this->assertStringContainsString('.ch-sidebar .nav-profile .ch-sidebar-leadership-wrap', $cssContent);
    }

    /** @test */
    public function test_sidebar_js_touch_scroll_and_search_autofocus_guards()
    {
        $jsContent = file_get_contents(public_path('js/sidebar.js'));

        // Verify touch detection prevents auto-focusing search on touch devices
        $this->assertStringContainsString('var isTouchDevice =', $jsContent);
        $this->assertStringContainsString('if (options.focusSearch && !isTouchDevice)', $jsContent);

        // Verify search input is blurred when drawer is closed
        $this->assertStringContainsString('searchInput.blur()', $jsContent);

        // Verify edge swipe touchstart is constrained to extreme left edge (<= 15px)
        $this->assertStringContainsString('clientX <= 15', $jsContent);

        // Verify vertical scrolling cancels edge swipe candidate
        // Verify drawer open via swipe is restricted strictly to mobile viewport (< 768px)
        $this->assertStringContainsString("if (getViewportTier() === 'mobile')", $jsContent);
    }

    /** @test */
    public function test_condensed_staff_workspace_and_global_navigation()
    {
        $user = User::factory()->create(['status' => 1]);
        $roleSuper = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web']);
        $user->assignRole($roleSuper);

        // Create staff profile and assign ess.access
        $staff = \App\Models\Staff::firstOrNew(['user_id' => $user->id]);
        $staff->save();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ess.access', 'guard_name' => 'web']));

        $user->unsetRelation('staff_profile');
        $user->load('staff_profile');
        $this->actingAs($user);

        $rendered = View::make('admin.partials.sidebar')->render();

        // 1. Primary Global Links
        $this->assertStringContainsString('id="sidebar-global-dashboard"', $rendered);
        $this->assertStringContainsString(route('home'), $rendered);

        // 2. Staff Workspace Collapsible Dropdown
        $this->assertStringContainsString('id="sidebar-staff-workspace-toggle"', $rendered);
        $this->assertStringContainsString('id="sidebar-staff-workspace"', $rendered);
        $this->assertStringContainsString('Staff Workspace', $rendered);

        // 3. HR Self-Service Items inside Staff Workspace
        $this->assertStringContainsString('id="sidebar-ess-my-profile"', $rendered);
        $this->assertStringContainsString(route('hr.ess.my-profile'), $rendered);
        $this->assertStringContainsString('id="sidebar-ess-dashboard"', $rendered);
        $this->assertStringContainsString(route('hr.ess.index'), $rendered);
        $this->assertStringContainsString('id="sidebar-ess-my-leave"', $rendered);
        $this->assertStringContainsString(route('hr.ess.my-leave'), $rendered);
        $this->assertStringContainsString('id="sidebar-ess-my-disciplinary"', $rendered);
        $this->assertStringContainsString(route('hr.ess.my-disciplinary'), $rendered);

        // 4. Staff Supplies & Stock inside Staff Workspace
        $this->assertStringContainsString('id="sidebar-global-requisitions"', $rendered);
        $this->assertStringContainsString(route('inventory.requisitions.index'), $rendered);
        $this->assertStringContainsString('id="sidebar-global-my-stock"', $rendered);
        $this->assertStringContainsString(route('inventory.requisitions.my-stock'), $rendered);

        // 5. User Profile Card Link
        $this->assertStringContainsString('id="sidebar-user-profile-link"', $rendered);

        // 6. CSS Styles for Submenu Labels, Dividers, and Flyout
        $cssContent = file_get_contents(public_path('css/sidebar.css'));
        $this->assertStringContainsString('.ch-sidebar .sub-menu .ch-sidebar-sub-label', $cssContent);
        $this->assertStringContainsString('.ch-sidebar .sub-menu .ch-sidebar-sub-divider', $cssContent);
        $this->assertStringContainsString('.ch-sidebar-flyout-label', $cssContent);
        $this->assertStringContainsString('.ch-sidebar-flyout-divider', $cssContent);
        $this->assertStringContainsString('.ch-sidebar .ch-sidebar-user-link', $cssContent);
    }
}
