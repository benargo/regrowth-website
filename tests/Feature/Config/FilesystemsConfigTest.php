<?php

namespace Tests\Feature\Config;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class FilesystemsConfigTest extends TestCase
{
    #[Test]
    public function the_s3_disk_writes_public_objects_with_an_immutable_cache_header(): void
    {
        $this->assertSame('public', config('filesystems.disks.s3.visibility'));
        $this->assertTrue(config('filesystems.disks.s3.report'));
        $this->assertSame('public, max-age=31536000, immutable', config('filesystems.disks.s3.options.CacheControl'));
    }

    #[Test]
    public function media_and_the_blizzard_mirror_use_the_s3_disk_under_test(): void
    {
        $this->assertSame('s3', config('media-library.disk_name'));
        $this->assertSame('s3', config('services.blizzard.filesystem'));
    }
}
