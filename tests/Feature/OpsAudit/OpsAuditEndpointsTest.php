<?php

namespace Tests\Feature\OpsAudit;

use Tests\TestCase;
use App\Models\User;

class OpsAuditEndpointsTest extends TestCase
{

    public static function auditTabsProvider()
    {
        $modules = [];
        $controllers = glob(__DIR__ . '/../../../app/Http/Controllers/OpsAudit/*Controller.php');
        
        foreach ($controllers as $controller) {
            if (basename($controller) === 'OpsAuditBaseController.php') continue;
            
            $module = strtolower(str_replace(['OpsAudit', 'Controller.php'], '', basename($controller)));
            $content = file_get_contents($controller);
            
            preg_match_all("/case\s+'([^']+)':/", $content, $matches);
            if (!empty($matches[1])) {
                $tabs = array_unique($matches[1]);
                foreach ($tabs as $tab) {
                    $modules["[{$module} -> {$tab}]"] = [$module, $tab];
                }
            }
        }
        return $modules;
    }

    /**
     * @dataProvider auditTabsProvider
     */
    public function test_ops_audit_ajax_data_endpoint($module, $tab)
    {
        $user = User::first();
        $this->actingAs($user);

        $uri = "/ops-audit/{$module}/data/{$tab}";
        
        $response = $this->getJson($uri . '?start=0&length=500');
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    /**
     * @dataProvider auditTabsProvider
     */
    public function test_ops_audit_print_endpoint($module, $tab)
    {
        $user = User::first();
        $this->actingAs($user);

        $uri = "/ops-audit/{$module}/data/{$tab}?action=print&tab={$tab}";
        
        $response = $this->get($uri, ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        
    }

    /**
     * @dataProvider auditTabsProvider
     */
    public function test_ops_audit_payment_filters($module, $tab)
    {
        $this->withoutExceptionHandling();
        $user = User::first();
        $this->actingAs($user);

        $uri = "/ops-audit/{$module}/data/{$tab}";
        
        $response = $this->getJson($uri . '?start=0&length=500&payment_method=CASH&cashier_id=1');
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}
