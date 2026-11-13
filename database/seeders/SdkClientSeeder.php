<?php

namespace Database\Seeders;

use App\Interoperability\Models\SdkClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SdkClientSeeder extends Seeder
{
    /**
     * Seed SDK partner client credentials for testing and partner sandbox.
     */
    public function run(): void
    {
        $clients = [
            [
                'client_id' => 'partner-emr-dev',
                'client_secret' => Hash::make('partner_dev_secret_2026'),
                'name' => 'Partner EMR Developer Sandbox',
                'organization' => 'Partner Healthcare Organization',
                'allowed_scopes' => ['*'],
                'rate_limit_rpm' => 300,
                'is_active' => true,
            ],
            [
                'client_id' => 'partner-emr-prod',
                'client_secret' => Hash::make('partner_prod_secret_2026'),
                'name' => 'Partner EMR Production Node',
                'organization' => 'Partner Healthcare Organization',
                'allowed_scopes' => ['scribe:process', 'scribe:sign', 'interop:compositions'],
                'rate_limit_rpm' => 600,
                'is_active' => true,
            ],
            [
                'client_id' => 'ehealth-africa-dev',
                'client_secret' => Hash::make('ehealth_dev_secret_2026'),
                'name' => 'Partner Deployment Sandbox',
                'organization' => 'CoreHealth Partner Network',
                'allowed_scopes' => ['*'],
                'rate_limit_rpm' => 300,
                'is_active' => true,
            ],
        ];

        foreach ($clients as $clientData) {
            SdkClient::updateOrCreate(
                ['client_id' => $clientData['client_id']],
                $clientData
            );
        }
    }
}
