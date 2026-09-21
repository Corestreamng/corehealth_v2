<?php

namespace Tests\Unit;

use Tests\TestCase;

class VersionedAssetHelperTest extends TestCase
{
    /**
     * Test versioned_asset generates a URL with mtime for existing assets.
     */
    public function testVersionedAssetWithExistingFile()
    {
        $url = versioned_asset('js/billing-core.js');

        $this->assertStringContainsString('js/billing-core.js', $url);
        $this->assertStringContainsString('?v=', $url);

        $expectedMtime = filemtime(public_path('js/billing-core.js'));
        $this->assertStringEndsWith('?v=' . $expectedMtime, $url);
    }

    /**
     * Test versioned_asset handles leading slashes cleanly.
     */
    public function testVersionedAssetHandlesLeadingSlash()
    {
        $url = versioned_asset('/js/billing-core.js');

        $this->assertStringContainsString('js/billing-core.js', $url);
        $this->assertStringContainsString('?v=', $url);
    }

    /**
     * Test versioned_asset gracefully falls back without throwing stat failed error for non-existent files.
     */
    public function testVersionedAssetHandlesNonExistentFileGracefully()
    {
        // This must not throw an ErrorException / PHP warning
        $url = versioned_asset('js/non-existent-sample-file-123456.js');

        $this->assertStringContainsString('js/non-existent-sample-file-123456.js', $url);
        $this->assertStringContainsString('?v=', $url);
    }
}
