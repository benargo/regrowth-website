<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AsBinaryColor implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value ? bin2hex($value) : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_int($value)) {
            return hex2bin(str_pad(dechex($value), 6, '0', STR_PAD_LEFT));
        }

        if (is_array($value)) {
            return $this->fromRgbComponents((int) $value[0], (int) $value[1], (int) $value[2]);
        }

        if (is_string($value)) {
            // RGB / RGBA comma-separated string, e.g. "34,110,115" or "34, 110, 115, 255"
            if (str_contains($value, ',')) {
                $parts = array_map('trim', explode(',', $value));
                if (count($parts) < 3) {
                    throw new InvalidArgumentException("Invalid color value: {$value}");
                }

                return $this->fromRgbComponents((int) $parts[0], (int) $parts[1], (int) $parts[2]);
            }

            // Hex string with optional leading #
            $hex = ltrim($value, '#');
            if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
                throw new InvalidArgumentException("Invalid color value: {$value}");
            }

            return hex2bin($hex);
        }

        throw new InvalidArgumentException('Invalid color value type: '.gettype($value));
    }

    private function fromRgbComponents(int $r, int $g, int $b): string
    {
        if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255) {
            throw new InvalidArgumentException('RGB values must be between 0 and 255.');
        }

        return hex2bin(sprintf('%02x%02x%02x', $r, $g, $b));
    }
}
