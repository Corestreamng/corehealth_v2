<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

class CssVariableProviderTest extends TestCase
{
    public function test_dynamic_hospital_primary_and_maternity_pink_css_in_layout()
    {
        $layoutContent = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

        $this->assertStringContainsString('--hospital-primary:', $layoutContent);
        $this->assertStringContainsString('--primary-color:', $layoutContent);
        $this->assertStringContainsString('--hospital-secondary:', $layoutContent);
        $this->assertStringContainsString('--maternity-pink: #e91e8a;', $layoutContent);
        $this->assertStringContainsString('--maternity-pink-rgb: 233, 30, 138;', $layoutContent);
    }

    public function test_maternity_workbench_css_defines_and_uses_unique_pink()
    {
        $maternityCss = file_get_contents(public_path('css/maternity-workbench.css'));

        $this->assertStringContainsString('--maternity-pink: #e91e8a;', $maternityCss);
        $this->assertStringContainsString('--maternity-pink-rgb: 233, 30, 138;', $maternityCss);
        $this->assertStringContainsString('--maternity-pink-dark: #ad1457;', $maternityCss);
        $this->assertStringContainsString('background: var(--maternity-pink', $maternityCss);
        $this->assertStringContainsString('background: linear-gradient(135deg, var(--maternity-pink', $maternityCss);
    }

    public function test_billing_workbench_retains_hospital_primary_theme()
    {
        $billingCss = file_get_contents(public_path('css/billing-workbench.css'));

        $this->assertStringContainsString('var(--hospital-primary', $billingCss);
        $this->assertStringContainsString('.panel-header', $billingCss);
    }
}
