<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\AttendanceMatrixData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class AttendanceMatrixDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function to_array_emits_the_columns_and_rows_the_matrix_page_reads(): void
    {
        $this->assertSame(
            ['columns' => [], 'rows' => []],
            (new AttendanceMatrixData(columns: collect(), rows: collect()))->toArray(),
        );
    }
}
