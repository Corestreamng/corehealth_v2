<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProbeTmpTest extends TestCase
{
    public function test_probe()
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole('SUPERADMIN');
        \Laravel\Sanctum\Sanctum::actingAs($u);
        $paths = explode("\n", trim(file_get_contents('/tmp/probe/paths.txt')));
        $out = [];
        foreach ($paths as $p) {
            $r = $this->get($p, ['X-Requested-With' => 'XMLHttpRequest','Accept' => 'application/json']);
            $ct = $r->headers->get('content-type');
            $body = $r->getContent();
            $j = json_decode($body, true);
            $sample = (isset($j['data'][0]) && isset($j['current_page'])) ? substr(json_encode($j['data'][0]), 0, 900) : (isset($j['data'][0]) ? substr(json_encode(array_map(fn ($v) => is_string($v) ? strip_tags(substr($v, 0, 50)) : $v, $j['data'][0])), 0, 900) : (isset($j) && is_array($j) ? substr(json_encode($j), 0, 900) : ''));
            $keys = $sample . ' || ' . (isset($j['exception']) ? $j['exception'] . ': ' . substr($j['message'] ?? '', 0, 160) . ' @' . basename($j['file']) . ':' . $j['line'] . ' ' : '') . (is_array($j) ? implode(',', array_slice(array_keys($j), 0, 12)) : substr(strip_tags($body), 0, 60));
            $out[] = $p . ' => ' . $r->status() . ' ' . (str_contains((string)$ct, 'json') ? 'json' : 'html') . ' [' . $keys . ']';
        }
        file_put_contents('/tmp/probe/out.txt', implode("\n", $out));
        $this->assertTrue(true);
    }
}
