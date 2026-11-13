<?php

namespace Tests\Unit\Policies;

use App\Enums\GuildRosterManager\UploadSource;
use App\Models\DesktopClient;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use App\Policies\DesktopSyncPolicy;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('auth')]
class DesktopSyncPolicyTest extends TestCase
{
    private DesktopSyncPolicy $policy;

    private User $owner;

    private DesktopClient $desktopClient;

    private Upload $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DesktopSyncPolicy;
        $this->owner = (new User)->forceFill(['id' => 11]);
        $this->desktopClient = DesktopClient::factory()->make(['user_id' => '11']);
        $this->sync = Upload::factory()->staging($this->desktopClient)->make(['user_id' => '11', 'game_version_id' => 1]);
    }

    #[Test]
    public function write_sync_allows_the_owner_on_the_same_device(): void
    {
        $this->assertTrue($this->policy->writeSync($this->owner, $this->sync, $this->desktopClient));
    }

    #[Test]
    #[Group('authorization')]
    public function write_sync_denies_the_owner_on_another_device(): void
    {
        $otherDevice = DesktopClient::factory()->make(['user_id' => '11']);

        $this->assertFalse($this->policy->writeSync($this->owner, $this->sync, $otherDevice));
    }

    #[Test]
    #[Group('authorization')]
    public function write_sync_denies_another_user(): void
    {
        $other = (new User)->forceFill(['id' => 12]);

        $this->assertFalse($this->policy->writeSync($other, $this->sync, $this->desktopClient));
    }

    #[Test]
    #[Group('authorization')]
    public function write_sync_denies_a_manual_upload(): void
    {
        $this->sync->source = UploadSource::Manual;

        $this->assertFalse($this->policy->writeSync($this->owner, $this->sync, $this->desktopClient));
    }

    #[Test]
    public function upload_resolves_the_desktop_sync_policy(): void
    {
        $this->assertInstanceOf(DesktopSyncPolicy::class, Gate::getPolicyFor(Upload::class));
    }
}
