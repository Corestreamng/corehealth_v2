<?php

namespace Tests\Feature\Billing;

use App\Models\ApplicationStatu;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ReceiptPrescriptionDoseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (function_exists('clearAppSettingsCache')) {
            clearAppSettingsCache();
        }
        if (function_exists('appsettings')) {
            appsettings(null, true);
        }
    }

    /** @test */
    public function test_receipt_a4_displays_dose_when_enabled_and_available()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Test Hospital';
        $site->hos_color = '#0a6cf2';
        $site->show_prescription_dose_on_receipt = true;

        $receiptDetails = [
            [
                'type' => 'Product',
                'name' => 'Paracetamol 500mg',
                'price' => 200,
                'qty' => 2,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'amount_paid' => 400,
                'dose' => '500mg | PO | TDS | 3 days | Qty: 6',
            ],
            [
                'type' => 'Service',
                'name' => 'Consultation',
                'price' => 1500,
                'qty' => 1,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'amount_paid' => 1500,
                'dose' => null,
            ],
        ];

        $html = View::make('admin.Accounts.receipt_a4', [
            'site' => $site,
            'patientName' => 'John Doe',
            'patientFileNo' => 'FILE-001',
            'isFamilyPayment' => false,
            'familyPatientNames' => null,
            'date' => '2026-09-21 17:00',
            'ref' => 'REF-12345',
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => 0,
            'totalPaid' => 1900,
            'amountInWords' => 'One Thousand Nine Hundred Naira',
            'paymentType' => 'CASH',
            'notes' => '',
            'currentUserName' => 'Cashier One',
        ])->render();

        $this->assertStringContainsString('Paracetamol 500mg', $html);
        $this->assertStringContainsString('500mg | PO | TDS | 3 days | Qty: 6', $html);
        $this->assertStringContainsString('Dose:', $html);
    }

    /** @test */
    public function test_receipt_a4_hides_dose_when_config_is_disabled()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Test Hospital';
        $site->hos_color = '#0a6cf2';
        $site->show_prescription_dose_on_receipt = false;

        $receiptDetails = [
            [
                'type' => 'Product',
                'name' => 'Paracetamol 500mg',
                'price' => 200,
                'qty' => 2,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'amount_paid' => 400,
                'dose' => '500mg | PO | TDS | 3 days | Qty: 6',
            ],
        ];

        $html = View::make('admin.Accounts.receipt_a4', [
            'site' => $site,
            'patientName' => 'John Doe',
            'patientFileNo' => 'FILE-001',
            'isFamilyPayment' => false,
            'familyPatientNames' => null,
            'date' => '2026-09-21 17:00',
            'ref' => 'REF-12345',
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => 0,
            'totalPaid' => 400,
            'amountInWords' => 'Four Hundred Naira',
            'paymentType' => 'CASH',
            'notes' => '',
            'currentUserName' => 'Cashier One',
        ])->render();

        $this->assertStringContainsString('Paracetamol 500mg', $html);
        $this->assertStringNotContainsString('500mg | PO | TDS | 3 days | Qty: 6', $html);
    }

    /** @test */
    public function test_receipt_thermal_displays_dose_when_enabled_and_available()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Test Hospital';
        $site->show_prescription_dose_on_receipt = true;

        $receiptDetails = [
            [
                'type' => 'Product',
                'name' => 'Amoxicillin 500mg',
                'price' => 500,
                'qty' => 1,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'amount_paid' => 500,
                'dose' => '2 caps BD x 5 days',
            ],
        ];

        $html = View::make('admin.Accounts.receipt_thermal', [
            'site' => $site,
            'patientName' => 'Jane Smith',
            'patientFileNo' => 'FILE-002',
            'isFamilyPayment' => false,
            'familyPatientNames' => null,
            'date' => '2026-09-21 17:00',
            'ref' => 'REF-99999',
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => 0,
            'totalPaid' => 500,
            'amountInWords' => 'Five Hundred Naira',
            'paymentType' => 'POS',
            'notes' => '',
            'currentUserName' => 'Cashier One',
            'thermalWidth' => '80mm',
        ])->render();

        $this->assertStringContainsString('Amoxicillin 500mg', $html);
        $this->assertStringContainsString('2 caps BD x 5 days', $html);
        $this->assertStringContainsString('Dose/Freq:', $html);
    }

    /** @test */
    public function test_receipt_thermal_hides_dose_when_config_is_disabled()
    {
        $site = new ApplicationStatu();
        $site->site_name = 'Test Hospital';
        $site->show_prescription_dose_on_receipt = false;

        $receiptDetails = [
            [
                'type' => 'Product',
                'name' => 'Amoxicillin 500mg',
                'price' => 500,
                'qty' => 1,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'amount_paid' => 500,
                'dose' => '2 caps BD x 5 days',
            ],
        ];

        $html = View::make('admin.Accounts.receipt_thermal', [
            'site' => $site,
            'patientName' => 'Jane Smith',
            'patientFileNo' => 'FILE-002',
            'isFamilyPayment' => false,
            'familyPatientNames' => null,
            'date' => '2026-09-21 17:00',
            'ref' => 'REF-99999',
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => 0,
            'totalPaid' => 500,
            'amountInWords' => 'Five Hundred Naira',
            'paymentType' => 'POS',
            'notes' => '',
            'currentUserName' => 'Cashier One',
            'thermalWidth' => '80mm',
        ])->render();

        $this->assertStringContainsString('Amoxicillin 500mg', $html);
        $this->assertStringNotContainsString('2 caps BD x 5 days', $html);
    }

    /** @test */
    public function test_hospital_config_updates_prescription_dose_on_receipt_setting()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $config = ApplicationStatu::first();
        if (!$config) {
            $config = ApplicationStatu::create([
                'site_name' => 'CoreHealth Clinic',
                'show_prescription_dose_on_receipt' => true,
            ]);
        }

        // Test updating with toggle checked (value = 1)
        $response = $this->put(route('hospital-config.update'), [
            'site_name' => 'Updated Clinic Name',
            'show_prescription_dose_on_receipt' => '1',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 302]);
        $config->refresh();
        $this->assertTrue((bool) $config->show_prescription_dose_on_receipt);

        // Test updating with toggle unchecked (absent in payload)
        $response2 = $this->put(route('hospital-config.update'), [
            'site_name' => 'Updated Clinic Name 2',
        ]);

        $this->assertContains($response2->getStatusCode(), [200, 302]);
        $config->refresh();
        $this->assertFalse((bool) $config->show_prescription_dose_on_receipt);
    }

    /** @test */
    public function test_billing_workbench_print_receipt_includes_dose_from_product_request()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        // Ensure config has dose enabled
        $config = ApplicationStatu::first();
        if ($config) {
            $config->show_prescription_dose_on_receipt = true;
            $config->save();
        }
        clearAppSettingsCache();
        appsettings(null, true);

        $patient = Patient::factory()->create();
        $product = Product::firstOrCreate(
            ['product_name' => 'Ciprofloxacin 500mg'],
            ['user_id' => $user->id, 'staff_user_id' => $user->id, 'category_id' => 1, 'status' => 1]
        );

        $payment = Payment::create([
            'payment_type' => 'CASH',
            'total' => 1200,
            'total_discount' => 0,
            'reference_no' => 'PAY-TEST-' . rand(1000, 9999),
            'user_id' => $user->id,
            'patient_id' => $patient->id,
        ]);

        $posr = ProductOrServiceRequest::create([
            'user_id' => $patient->user_id,
            'staff_user_id' => $user->id,
            'patient_id' => $patient->id,
            'product_id' => $product->id,
            'payment_id' => $payment->id,
            'qty' => 1,
            'amount' => 1200,
            'payable_amount' => 1200,
            'discount' => 0,
        ]);

        ProductRequest::create([
            'product_request_id' => $posr->id,
            'product_id' => $product->id,
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'dose' => '1 tab BD x 5 days',
            'qty' => 10,
            'status' => 1,
        ]);

        $response = $this->postJson(route('billing.print-receipt'), [
            'patient_id' => $patient->id,
            'payment_ids' => [$payment->id],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('receipt_a4', $data);
            $this->assertArrayHasKey('receipt_thermal', $data);
            $this->assertStringContainsString('Ciprofloxacin 500mg', $data['receipt_a4']);
            $this->assertStringContainsString('1 tab BD x 5 days', $data['receipt_a4']);
            $this->assertStringContainsString('1 tab BD x 5 days', $data['receipt_thermal']);
        }
    }
}
