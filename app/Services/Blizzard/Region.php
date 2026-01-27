<?php

namespace App\Services\Blizzard;

enum Region: string
{
    case EU = 'eu';
    case US = 'us';
    case KR = 'kr';
    case TW = 'tw';

    /**
     * Get the API base URL for this region.
     */
    public function apiUrl(): string
    {
        return "https://{$this->value}.api.blizzard.com";
    }

    /**
     * Get the OAuth token URL for this region.
     */
    public function tokenUrl(): string
    {
        return "https://{$this->value}.battle.net/oauth/token";
    }

    /**
     * Get the supported locales for this region.
     *
     * @return array<string>
     */
    public function locales(): array
    {
        return match ($this) {
            self::EU => ['en_GB', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'pl_PL', 'pt_PT', 'ru_RU'],
            self::US => ['en_US', 'pt_BR', 'es_MX'],
            self::KR => ['ko_KR'],
            self::TW => ['zh_TW'],
        };
    }

    /**
     * Get the default locale for this region.
     */
    public function defaultLocale(): string
    {
        return $this->locales()[0];
    }

    /**
     * Check if a locale is valid for this region.
     */
    public function supportsLocale(string $locale): bool
    {
        return in_array($locale, $this->locales(), true);
    }
}
