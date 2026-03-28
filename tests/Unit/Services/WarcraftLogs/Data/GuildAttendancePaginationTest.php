<?php

namespace Tests\Unit\Services\WarcraftLogs\Data;

use App\Services\WarcraftLogs\Data\GuildAttendancePagination;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildAttendancePaginationTest extends TestCase
{
    private function samplePaginationData(): array
    {
        return [
            'data' => [
                [
                    'code' => 'abc123',
                    'players' => [
                        ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                    ],
                    'startTime' => 1700000000000,
                    'zone' => null,
                ],
            ],
            'total' => 25,
            'per_page' => 10,
            'current_page' => 1,
            'from' => 1,
            'to' => 10,
            'last_page' => 3,
            'has_more_pages' => true,
        ];
    }

    #[Test]
    public function it_converts_to_length_aware_paginator(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->samplePaginationData());

        $paginator = $pagination->toLengthAwarePaginator('/attendance');

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(25, $paginator->total());
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(1, $paginator->currentPage());
        $this->assertCount(1, $paginator->items());
    }

    #[Test]
    public function it_uses_provided_path(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->samplePaginationData());

        $paginator = $pagination->toLengthAwarePaginator('/custom/path');

        $this->assertStringContainsString('/custom/path', $paginator->path());
    }

    #[Test]
    public function it_falls_back_to_slash_when_no_path_and_no_request(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->samplePaginationData());

        $paginator = $pagination->toLengthAwarePaginator();

        $this->assertSame('/', $paginator->path());
    }

    #[Test]
    public function it_creates_from_array_with_correct_structure(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->samplePaginationData());

        $this->assertSame(25, $pagination->total);
        $this->assertSame(10, $pagination->perPage);
        $this->assertSame(1, $pagination->currentPage);
        $this->assertSame(1, $pagination->from);
        $this->assertSame(10, $pagination->to);
        $this->assertSame(3, $pagination->lastPage);
        $this->assertTrue($pagination->hasMorePages);
        $this->assertCount(1, $pagination->data);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $pagination = GuildAttendancePagination::fromArray($this->samplePaginationData());

        $result = $pagination->toArray();

        $this->assertSame(25, $result['total']);
        $this->assertSame(10, $result['perPage']);
        $this->assertSame(1, $result['currentPage']);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
    }
}
