<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\IsNewerEvidence;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class IsNewerEvidenceTest extends TestCase
{
    #[Test]
    #[DataProvider('evidenceTimes')]
    public function it_compares_the_evidence_time_with_the_stored_time(?string $evidenceAt, ?string $storedAt, bool $expected): void
    {
        $result = IsNewerEvidence::run(
            $evidenceAt === null ? null : CarbonImmutable::parse($evidenceAt),
            $storedAt === null ? null : CarbonImmutable::parse($storedAt),
        );

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{?string, ?string, bool}>
     */
    public static function evidenceTimes(): array
    {
        return [
            'no evidence' => [null, '2026-11-01 12:00:00', false],
            'no evidence and nothing stored' => [null, null, false],
            'nothing stored' => ['2026-11-01 12:00:00', null, true],
            'later evidence' => ['2026-11-01 12:00:01', '2026-11-01 12:00:00', true],
            'equal times' => ['2026-11-01 12:00:00', '2026-11-01 12:00:00', false],
            'earlier evidence' => ['2026-11-01 11:59:59', '2026-11-01 12:00:00', false],
        ];
    }
}
