<?php

namespace Tests\Unit\Support\MediaLibrary;

use App\Support\MediaLibrary\OpaqueBoundsCalculator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('media')]
class OpaqueBoundsCalculatorTest extends TestCase
{
    #[Test]
    public function it_finds_the_vertical_bounds_of_opaque_pixels(): void
    {
        // 10x100 canvas, opaque only between y=20 and y=79 (inclusive).
        $png = $this->canvasWithOpaqueBand(width: 10, height: 100, opaqueFrom: 20, opaqueTo: 79);

        $bounds = (new OpaqueBoundsCalculator)->calculate($png);

        $this->assertNotNull($bounds);
        $this->assertEqualsWithDelta(0.20, $bounds['top'], 0.05);
        $this->assertEqualsWithDelta(0.80, $bounds['bottom'], 0.05);
    }

    #[Test]
    public function it_returns_null_for_a_fully_transparent_image(): void
    {
        $png = $this->canvasWithOpaqueBand(width: 10, height: 100, opaqueFrom: null, opaqueTo: null);

        $bounds = (new OpaqueBoundsCalculator)->calculate($png);

        $this->assertNull($bounds);
    }

    #[Test]
    public function it_returns_null_for_bytes_that_are_not_a_decodable_image(): void
    {
        $bounds = (new OpaqueBoundsCalculator)->calculate('NOT-AN-IMAGE');

        $this->assertNull($bounds);
    }

    // ==================== helpers ====================

    /**
     * Builds a PNG with transparent rows everywhere except an opaque band
     * between `$opaqueFrom` and `$opaqueTo` (inclusive, 0-indexed). Passing
     * null for both leaves the whole canvas transparent.
     */
    private function canvasWithOpaqueBand(int $width, int $height, ?int $opaqueFrom, ?int $opaqueTo): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        if ($opaqueFrom !== null && $opaqueTo !== null) {
            $opaque = imagecolorallocatealpha($image, 255, 0, 0, 0);
            imagefilledrectangle($image, 0, $opaqueFrom, $width - 1, $opaqueTo, $opaque);
        }

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();

        return $bytes;
    }
}
