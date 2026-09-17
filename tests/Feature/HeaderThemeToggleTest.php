<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Theme Test Tenant',
            'slug' => 'theme-test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Theme Test User',
            'email' => 'themetest@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_header_renders_dark_and_light_mode_buttons_with_correct_classes_and_icons(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'theme-test-tenant')
            ->get('/dashboard');

        $response->assertOk();

        // Check container
        $response->assertSee('dark-light-theme', false);

        // Check dark mode button with moon icon
        $response->assertSee('header-dark-mode-btn', false);
        $response->assertSee('dark-button', false);
        $response->assertSee('feather-moon', false);

        // Check light mode button with sun icon
        $response->assertSee('header-light-mode-btn', false);
        $response->assertSee('light-button', false);
        $response->assertSee('feather-sun', false);
    }

    public function test_layout_includes_anti_flicker_head_initialization_script(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'theme-test-tenant')
            ->get('/dashboard');

        $response->assertOk();

        // Verify head script immediately inspects savedSkin and adds app-skin-dark to documentElement
        $response->assertSee("localStorage.getItem('app-skin-dark')", false);
        $response->assertSee("document.documentElement.classList.add('app-skin-dark')", false);
    }

    public function test_layout_includes_css_and_javascript_toggle_handlers(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'theme-test-tenant')
            ->get('/dashboard');

        $response->assertOk();

        // Verify default CSS hides light button before JS or dark mode class is applied
        $response->assertSee('.dark-light-theme .light-button', false);

        // Verify click event listeners for dark and light buttons
        $response->assertSee("$(document).on('click', '.dark-button'", false);
        $response->assertSee("$(document).on('click', '.light-button'", false);
        $response->assertSee("localStorage.setItem('app-skin-dark', 'app-skin-dark')", false);
        $response->assertSee("localStorage.setItem('app-skin-dark', 'app-skin-light')", false);
    }

    public function test_erp_css_contains_dark_mode_rules_for_common_components_and_single_panel(): void
    {
        $css = file_get_contents(public_path('assets/css/erp.css'));

        // Single panel dark mode
        $this->assertStringContainsString('html.app-skin-dark .erp-single-panel', $css);

        // Pagination dark mode
        $this->assertStringContainsString('html.app-skin-dark .erp-pagination', $css);

        // Filter dropdown dark mode
        $this->assertStringContainsString('html.app-skin-dark .erp-filter-dropdown', $css);

        // Button component dark mode
        $this->assertStringContainsString('html.app-skin-dark .btn.btn-animated.btn-light', $css);

        // Icon button dark mode
        $this->assertStringContainsString('html.app-skin-dark .erp-icon-btn--transparent-dark', $css);

        // Action dropdown dark mode
        $this->assertStringContainsString('html.app-skin-dark .action-dropdown-btn', $css);

        // Odoo form component dark mode
        $this->assertStringContainsString('html.app-skin-dark .odoo-sheet', $css);
        $this->assertStringContainsString('html.app-skin-dark .odoo-form-control', $css);

        // Sidebar dropdown dark mode
        $this->assertStringContainsString('html.app-skin-dark .nxl-navigation', $css);
        $this->assertStringContainsString('html.app-skin-dark .nxl-navigation .nxl-submenu', $css);

        // Workflow guide dark mode
        $this->assertStringContainsString('html.app-skin-dark .erp-workflow-guide-box', $css);

        // Horizontal tabs dark mode
        $this->assertStringContainsString('html.app-skin-dark .erp-horizontal-tabs', $css);

        // Vertical tabs dark mode
        $this->assertStringContainsString('html.app-skin-dark .erp-vertical-tabs', $css);

        // Production order content scroll container dark mode
        $this->assertStringContainsString('html.app-skin-dark .production-main-content-scroll', $css);
    }

    public function test_common_blade_components_render_dark_mode_styles(): void
    {
        // Render components followed by @stack('styles') so pushed styles are emitted
        $renderedHtml = \Illuminate\Support\Facades\Blade::render(
            '<div>
                <x-ui.pagination :currentPage="1" :totalPages="5" :totalResults="50" />
                <x-ui.filter label="Filter"><div>Filter Content</div></x-ui.filter>
                <x-ui.button variant="light">Test Button</x-ui.button>
                <x-ui.icon-btn variant="transparent-dark" icon="feather-search" />
                <x-ui.action-dropdown :viewUrl="route(\'dashboard\')"><li>Item</li></x-ui.action-dropdown>
                <x-ui.odoo-form-ui type="sheet"><div>Sheet Content</div></x-ui.odoo-form-ui>
                <x-ui.workflow-guide title="Workflow Guide">Test guide text</x-ui.workflow-guide>
                <x-ui.horizontal-tabs id="testHTabs" :tabs="[[\'id\' => \'tab1\', \'label\' => \'Tab 1\']]" />
                <x-ui.vertical-tabs id="testVTabs" :tabs="[[\'id\' => \'vtab1\', \'label\' => \'VTab 1\']]" />
            </div>
            @stack("styles")'
        );

        $this->assertStringContainsString('html.app-skin-dark .erp-pagination', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .erp-filter-dropdown', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .btn.btn-animated.btn-light', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .erp-icon-btn--transparent-dark', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .action-dropdown-btn', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .odoo-sheet', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .erp-workflow-guide-box', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .erp-horizontal-tabs', $renderedHtml);
        $this->assertStringContainsString('html.app-skin-dark .erp-vertical-tabs', $renderedHtml);
    }

    public function test_production_workflow_strip_supports_dark_mode(): void
    {
        $productionCss = file_get_contents(public_path('assets/css/production.css'));
        $erpCss = file_get_contents(public_path('assets/css/erp.css'));

        $this->assertStringContainsString('html.app-skin-dark .production-workflow-card', $productionCss);
        $this->assertStringContainsString('html.app-skin-dark .production-flow-tab-item.future-step', $productionCss);
        $this->assertStringContainsString('html.app-skin-dark .production-flow-tab-item.passed-step', $productionCss);
        $this->assertStringContainsString('html.app-skin-dark .production-flow-tab-item.active-step', $productionCss);

        $this->assertStringContainsString('html.app-skin-dark .production-workflow-card', $erpCss);
        $this->assertStringContainsString('html.app-skin-dark .production-flow-tab-item.future-step', $erpCss);
    }
}
