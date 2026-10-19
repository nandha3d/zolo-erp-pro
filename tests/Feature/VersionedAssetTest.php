<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionedAssetTest extends TestCase
{
    public function test_public_asset_version_changes_when_the_file_changes(): void
    {
        $path = 'js/asset-test-'.bin2hex(random_bytes(8)).'.js';
        $file = public_path($path);
        try {
            file_put_contents($file, 'one');
            $first = versioned_asset($path);
            $this->assertStringContainsString('?v='.filemtime($file).'-3', $first);
            file_put_contents($file, 'changed');
            clearstatcache(true, $file);
            $this->assertNotSame($first, versioned_asset($path));
        } finally {
            if (is_file($file)) unlink($file);
        }
    }

    public function test_missing_files_and_business_documents_do_not_receive_asset_versions(): void
    {
        $this->assertSame(asset('js/missing-test-file.js'), versioned_asset('js/missing-test-file.js'));
        $this->assertSame(asset('documents/sale/private.pdf'), versioned_asset('documents/sale/private.pdf'));
    }
}
