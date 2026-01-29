<?php

namespace App\Enums;

enum DiscordRole: string
{
    /**
     * Discord Role IDs mapped to role names.
     */
    case Officer = '829021769448816691';
    case Raider = '1265247017215594496';
    case Member = '829022020301094922';
    case Guest = '829022292590985226';

    /**
     * Get the role name by its ID.
     */
    public static function getRoleNameById(string $roleId): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->value === $roleId) {
                return $case->name;
            }
        }

        return null;
    }

    /**
     * Get all role IDs.
     *
     * @return array<string>
     */
    public static function getAllRoleIds(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get role hierarchy (highest to lowest).
     *
     * @return array<string, string>
     */
    public static function getRoleHierarchy(): array
    {
        return [
            self::Officer->value => 'Officer',
            self::Raider->value => 'Raider',
            self::Member->value => 'Member',
            self::Guest->value => 'Guest',
        ];
    }
}
