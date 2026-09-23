<?php

namespace Tests\Feature\Billing;

use App\Models\ApplicationStatu;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ThermalPrintStylesTest extends TestCase
{
    /** @test */
    public function test_receipt_thermal_has_valid_zero_margin_page_and_responsive_print_width()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Hope Hospital';
        $site->contact_address = '123 Main St';
        $site->contact_phones = '08000000000';
        $site->logo = null;

        $html = View::make('admin.Accounts.receipt_thermal', [
            'site' => $site,
            'patientName' => 'John Doe',
            'patientFileNo' => 'HH-001',
            'isFamilyPayment' => false,
            'familyPatientNames' => null,
            'date' => '2026-09-23 10:00',
            'ref' => 'REF-001',
            'receiptDetails' => [],
            'totalDiscount' => 0,
            'totalPaid' => 1000,
            'amountInWords' => 'One Thousand Naira',
            'paymentType' => 'Cash',
            'notes' => '',
            'currentUserName' => 'Cashier',
            'thermalWidth' => '48mm',
        ])->render();

        $this->assertMatchesRegularExpression('/@page\s*\{\s*margin:\s*0;\s*size:\s*auto;\s*\}/', $html);
        $this->assertStringNotContainsString('auto; margin: 0;', $html);
        $this->assertStringContainsString('width: 100% !important;', $html);
        $this->assertStringContainsString('max-width: 48mm;', $html);
    }

    /** @test */
    public function test_invoice_thermal_has_valid_zero_margin_page_and_responsive_print_width()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Hope Hospital';
        $site->contact_address = '123 Main St';
        $site->contact_phones = '08000000000';
        $site->logo = null;

        $html = View::make('admin.Accounts.invoice_thermal', [
            'site' => $site,
            'patientName' => 'John Doe',
            'patientFileNo' => 'HH-001',
            'date' => '2026-09-23 10:00',
            'invoiceNo' => 'INV-001',
            'isProforma' => false,
            'hmoName' => null,
            'hmoNo' => null,
            'hasInsurance' => false,
            'invoiceDetails' => [],
            'subtotal' => 5000,
            'totalAmount' => 5000,
            'totalHmo' => 0,
            'totalHmoCoverage' => 0,
            'totalDiscount' => 0,
            'totalPayable' => 5000,
            'patientPayable' => 5000,
            'amountInWords' => 'Five Thousand Naira',
            'currentUserName' => 'Billing Clerk',
            'thermalWidth' => '48mm',
        ])->render();

        $this->assertMatchesRegularExpression('/@page\s*\{\s*margin:\s*0;\s*size:\s*auto;\s*\}/', $html);
        $this->assertStringNotContainsString('auto; margin: 0;', $html);
        $this->assertStringContainsString('width: 100% !important;', $html);
    }

    /** @test */
    public function test_admission_bill_thermal_has_valid_zero_margin_page_and_responsive_print_width()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Hope Hospital';
        $site->contact_address = '123 Main St';
        $site->contact_phones = '08000000000';
        $site->logo = null;

        $html = View::make('admin.Accounts.admission_bill_thermal', [
            'site' => $site,
            'billNo' => 'ADM-001',
            'date' => '2026-09-23 10:00',
            'admission' => [
                'patient_name' => 'John Doe',
                'patient_file_no' => 'HH-001',
                'admitted_date' => '2026-09-01',
                'discharge_date' => '2026-09-05',
                'los' => '4 days',
                'ward' => 'Ward A',
                'bed' => 'Bed 1',
                'status' => 'Discharged',
            ],
            'categories' => [],
            'totals' => [
                'gross' => 20000,
                'discount' => 0,
                'hmo' => 0,
                'paid' => 15000,
                'balance' => 5000,
            ],
            'currentUserName' => 'Accounts Clerk',
            'thermalWidth' => '48mm',
        ])->render();

        $this->assertMatchesRegularExpression('/@page\s*\{\s*margin:\s*0;\s*size:\s*auto;\s*\}/', $html);
        $this->assertStringNotContainsString('auto; margin: 0;', $html);
        $this->assertStringContainsString('width: 100% !important;', $html);
    }

    /** @test */
    public function test_deposit_receipt_thermal_has_valid_zero_margin_page_and_responsive_print_width()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Hope Hospital';
        $site->contact_address = '123 Main St';
        $site->contact_phones = '08000000000';
        $site->logo = null;

        $html = View::make('admin.Accounts.deposit_receipt_thermal', [
            'site' => $site,
            'patientName' => 'John Doe',
            'patientFileNo' => 'HH-001',
            'depositNumber' => 'DEP-001',
            'date' => '2026-09-23 10:00',
            'depositType' => 'General',
            'amount' => 50000,
            'amountInWords' => 'Fifty Thousand Naira',
            'paymentMethod' => 'Transfer',
            'bank' => null,
            'paymentReference' => null,
            'notes' => 'Admission deposit',
            'currentUserName' => 'Cashier',
            'receivedBy' => 'Cashier',
            'previousBalance' => 0,
            'newBalance' => 50000,
            'admissionNumber' => null,
            'thermalWidth' => '48mm',
        ])->render();

        $this->assertMatchesRegularExpression('/@page\s*\{\s*margin:\s*0;\s*size:\s*auto;\s*\}/', $html);
        $this->assertStringNotContainsString('auto; margin: 0;', $html);
        $this->assertStringContainsString('width: 100% !important;', $html);
    }

    /** @test */
    public function test_account_statement_thermal_has_valid_zero_margin_page_and_responsive_print_width()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Hope Hospital';
        $site->contact_address = '123 Main St';
        $site->contact_phones = '08000000000';

        $html = View::make('admin.Accounts.account_statement_thermal', [
            'site' => $site,
            'patientName' => 'John Doe',
            'patientFileNo' => 'HH-001',
            'patientHmo' => 'None',
            'dateFrom' => '2026-09-01',
            'dateTo' => '2026-09-23',
            'summary' => [
                'total_deposits' => 5000,
                'total_bills' => 10000,
                'total_payments' => 8000,
                'total_refunds' => 0,
                'total_withdrawals' => 0,
                'closing_balance' => 2000,
            ],
            'transactions' => collect([]),
            'currentUserName' => 'Accountant',
            'preparedBy' => 'Accountant',
            'thermalWidth' => '48mm',
        ])->render();

        $this->assertMatchesRegularExpression('/margin:\s*0;/', $html);
        $this->assertMatchesRegularExpression('/size:\s*auto;/', $html);
        $this->assertStringNotContainsString('auto; margin: 0;', $html);
        $this->assertStringContainsString('width: 100% !important;', $html);
    }

    /** @test */
    public function test_app_layout_contains_print_rules_hiding_sidebar_and_navbar()
    {
        $viewPath = resource_path('views/admin/layouts/app.blade.php');
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('@media print', $content);
        $this->assertStringContainsString('.ch-sidebar, .sidebar, .navbar, footer, .footer, .no-print', $content);
        $this->assertStringContainsString('.container-scroller, .page-body-wrapper, .main-panel, .content-wrapper', $content);
    }

    /** @test */
    public function test_services_rendered_view_contains_isolated_thermal_print_function()
    {
        $viewPath = resource_path('views/admin/encounters/services_rendered.blade.php');
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('printThermalServicesRendered', $content);
        $this->assertStringContainsString('sr-thermal-print', $content);
        $this->assertStringContainsString('body.thermal-mode .sr-container', $content);
        $this->assertStringNotContainsString('size: {{ $thermalWidth ?? getThermalPrinterWidth() }} auto;', $content);
        $this->assertStringContainsString('font-size: 11px !important;', $content);
        $this->assertStringContainsString('flex-wrap: wrap', $content);
        $this->assertStringContainsString('word-break: break-word', $content);
    }
}
