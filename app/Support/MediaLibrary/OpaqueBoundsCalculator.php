<?php

namespace App\Support\MediaLibrary;

/**
 * Finds the vertical extent of non-transparent pixels within an image.
 *
 * Blizzard's `main-raw` character renders sit on a fixed-size transparent
 * canvas, but the character's silhouette fills a different fraction of that
 * canvas per race/pose — a Tauren stands much taller in-frame than a Gnome.
 * Sizing these renders by canvas height alone (e.g. a fixed CSS height on an
 * `object-contain` image) therefore produces wildly inconsistent apparent
 * character heights. This calculator locates where the actual artwork
 * starts and ends, as fractions of the canvas height, so callers can scale
 * by *visible* character height instead.
 */
class OpaqueBoundsCalculator
{
    /**
     * Row sample step, in pixels. Scanning every row of a 1600x1200 render
     * is unnecessary for a bounding box — a coarse vertical sample is stable
     * because character artwork spans hundreds of contiguous rows.
     */
    private const ROW_STEP = 4;

    /**
     * Column sample step, in pixels.
     */
    private const COLUMN_STEP = 4;

    /**
     * Alpha value (0-127, GD's scale) below which a pixel counts as opaque.
     * GD alpha is inverted: 0 is fully opaque, 127 is fully transparent.
     */
    private const OPAQUE_ALPHA_THRESHOLD = 127;

    /**
     * @return array{top: float, bottom: float}|null Fractions (0-1) of the
     *                                               image height marking the first and last rows containing an opaque
     *                                               pixel, or null when the bytes are not a decodable image or contain
     *                                               no opaque pixels at all.
     */
    public function calculate(string $imageBytes): ?array
    {
        $image = @imagecreatefromstring($imageBytes);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $firstOpaqueRow = null;
        $lastOpaqueRow = null;

        for ($y = 0; $y < $height; $y += self::ROW_STEP) {
            if (! $this->rowHasOpaquePixel($image, $y, $width)) {
                continue;
            }

            $firstOpaqueRow ??= $y;
            $lastOpaqueRow = $y;
        }

        if ($firstOpaqueRow === null || $lastOpaqueRow === null) {
            return null;
        }

        return [
            'top' => $firstOpaqueRow / $height,
            'bottom' => ($lastOpaqueRow + 1) / $height,
        ];
    }

    private function rowHasOpaquePixel(\GdImage $image, int $y, int $width): bool
    {
        for ($x = 0; $x < $width; $x += self::COLUMN_STEP) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;

            if ($alpha < self::OPAQUE_ALPHA_THRESHOLD) {
                return true;
            }
        }

        return false;
    }
}
