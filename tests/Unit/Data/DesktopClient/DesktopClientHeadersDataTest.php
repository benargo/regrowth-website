<?php

namespace Tests\Unit\Data\DesktopClient;

use App\Data\DesktopClient\DesktopClientHeadersData;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class DesktopClientHeadersDataTest extends TestCase
{
    private const string CLIENT_ID = '9f1c2b3a-4d5e-4f60-8a7b-1c2d3e4f5a6b';

    #[Test]
    #[Group('happy-path')]
    public function it_reads_the_client_id_and_trimmed_name_from_the_headers(): void
    {
        $headers = DesktopClientHeadersData::tryFromRequest($this->request(self::CLIENT_ID, '  Gaming PC  '));

        $this->assertNotNull($headers);
        $this->assertSame(self::CLIENT_ID, $headers->id);
        $this->assertSame('Gaming PC', $headers->name);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_accepts_a_name_of_exactly_one_hundred_characters(): void
    {
        $name = str_repeat('é', 100);

        $headers = DesktopClientHeadersData::tryFromRequest($this->request(self::CLIENT_ID, $name));

        $this->assertSame($name, $headers?->name);
    }

    #[Test]
    #[Group('error-handling')]
    #[DataProvider('invalidHeadersProvider')]
    public function it_returns_null_for_missing_or_invalid_headers(?string $id, ?string $name): void
    {
        $this->assertNull(DesktopClientHeadersData::tryFromRequest($this->request($id, $name)));
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function invalidHeadersProvider(): array
    {
        return [
            'missing id' => [null, 'Gaming PC'],
            'id that is not a uuid' => ['not-a-uuid', 'Gaming PC'],
            'missing name' => [self::CLIENT_ID, null],
            'blank name' => [self::CLIENT_ID, '   '],
            'name over one hundred characters' => [self::CLIENT_ID, str_repeat('a', 101)],
        ];
    }

    // ==================== helpers ====================

    private function request(?string $id, ?string $name): Request
    {
        $request = Request::create('/');

        if ($id !== null) {
            $request->headers->set('X-Desktop-Client-Id', $id);
        }

        if ($name !== null) {
            $request->headers->set('X-Desktop-Client-Name', $name);
        }

        return $request;
    }
}
