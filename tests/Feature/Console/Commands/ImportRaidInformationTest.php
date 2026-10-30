<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\Addon\BuildExportFile;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\RaidInformation\ResolveChapterChannels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ImportRaidInformationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([BuildExportFile::class, AttachAvatarToUser::class, ResolveChapterChannels::class]);

        $this->directory = storage_path('framework/testing/raid-information-import');
        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_imports_the_chapters_in_order(): void
    {
        $this->writeFolder(['introduction', 'trash', 'bosses'], [
            'introduction' => "# Welcome\r\nHello",
            'trash' => "## Trash\nSee <#111>",
            'bosses' => "## Bosses\nKill them  \n\n",
        ]);

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('Imported 3 chapters.')
            ->assertSuccessful();

        $disk = Storage::disk('raid-information');
        $this->assertSame("# Welcome\nHello", $disk->get('introduction.md'));
        $this->assertSame("## Trash\nSee <#111>", $disk->get('trash.md'));
        $this->assertSame("## Bosses\nKill them", $disk->get('bosses.md'));
        $this->assertSame(['introduction', 'trash', 'bosses'], json_decode($disk->get('chapters.json'), true));
        Queue::assertPushed(ResolveChapterChannels::class, 1);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_refuses_when_the_disk_already_holds_chapters(): void
    {
        Storage::disk('raid-information')->put('existing.md', '## Existing');
        $this->writeFolder(['introduction'], ['introduction' => '# Welcome']);

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('The raid-information disk already holds chapters. Nothing was imported.')
            ->assertFailed();

        Storage::disk('raid-information')->assertMissing('introduction.md');
    }

    #[Test]
    #[Group('validation')]
    public function it_imports_nothing_when_a_chapter_is_over_the_limit(): void
    {
        $this->writeFolder(['introduction', 'long'], [
            'introduction' => '# Welcome',
            'long' => "## Long\n".str_repeat('a', 2100),
        ]);

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('long:')
            ->assertFailed();

        $this->assertSame([], Storage::disk('raid-information')->allFiles());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_imports_nothing_when_a_listed_file_is_missing(): void
    {
        $this->writeFolder(['introduction', 'missing'], ['introduction' => '# Welcome']);

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('missing')
            ->assertFailed();

        $this->assertSame([], Storage::disk('raid-information')->allFiles());
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_an_introduction(): void
    {
        $this->writeFolder(['bosses'], ['bosses' => '## Bosses']);

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('introduction')
            ->assertFailed();

        $this->assertSame([], Storage::disk('raid-information')->allFiles());
    }

    #[Test]
    public function it_imports_a_duplicated_slug_once(): void
    {
        $this->writeFolder(['introduction', 'bosses', 'bosses'], [
            'introduction' => '# Welcome',
            'bosses' => '## Bosses',
        ]);

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('Imported 2 chapters.')
            ->assertSuccessful();

        $disk = Storage::disk('raid-information');
        $this->assertSame('## Bosses', $disk->get('bosses.md'));
        $this->assertSame(['introduction', 'bosses'], json_decode($disk->get('chapters.json'), true));
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidSlugs')]
    public function it_imports_nothing_when_a_slug_is_invalid(string $slug): void
    {
        $this->writeFolder(['introduction', $slug], ['introduction' => '# Welcome']);

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('Invalid slug: '.json_encode($slug))
            ->assertFailed();

        $this->assertSame([], Storage::disk('raid-information')->allFiles());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSlugs(): array
    {
        return [
            'path traversal' => ['../outside'],
            'uppercase' => ['Bosses'],
        ];
    }

    #[Test]
    #[Group('error-handling')]
    public function it_fails_when_chapters_json_is_missing(): void
    {
        File::put("{$this->directory}/introduction.md", '# Welcome');

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain("chapters.json was not found in {$this->directory}.")
            ->assertFailed();

        $this->assertSame([], Storage::disk('raid-information')->allFiles());
    }

    #[Test]
    #[Group('validation')]
    public function it_fails_when_chapters_json_is_not_a_list(): void
    {
        File::put("{$this->directory}/chapters.json", json_encode(['first' => 'introduction']));
        File::put("{$this->directory}/introduction.md", '# Welcome');

        $this->artisan('raid-information:import', ['directory' => $this->directory])
            ->expectsOutputToContain('chapters.json must hold a list of slugs.')
            ->assertFailed();

        $this->assertSame([], Storage::disk('raid-information')->allFiles());
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, string>  $order
     */
    private function writeFolder(array $order, array $files): void
    {
        File::put("{$this->directory}/chapters.json", json_encode($order));

        foreach ($files as $slug => $content) {
            File::put("{$this->directory}/{$slug}.md", $content);
        }
    }
}
