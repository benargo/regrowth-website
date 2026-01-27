<?php

namespace App\Support\Warcraft;

use Illuminate\Support\Collection;

class Classes extends Collection
{
    public const WARRIOR = 1;

    public const PALADIN = 2;

    public const HUNTER = 3;

    public const ROGUE = 4;

    public const PRIEST = 5;

    public const SHAMAN = 7;

    public const MAGE = 8;

    public const WARLOCK = 9;

    public const DRUID = 11;

    public function __construct(?array $items = null)
    {
        parent::__construct($items ?? [
            self::WARRIOR => [
                'id' => self::WARRIOR,
                'name' => 'Warrior',
                'specialisations' => [
                    ['name' => 'Arms', 'role' => 'Melee DPS'],
                    ['name' => 'Fury', 'role' => 'Melee DPS'],
                    ['name' => 'Protection', 'role' => 'Tank'],
                ],
            ],
            self::PALADIN => [
                'id' => self::PALADIN,
                'name' => 'Paladin',
                'specialisations' => [
                    ['name' => 'Holy', 'role' => 'Healer'],
                    ['name' => 'Protection', 'role' => 'Tank'],
                    ['name' => 'Retribution', 'role' => 'Melee DPS'],
                ],
            ],
            self::HUNTER => [
                'id' => self::HUNTER,
                'name' => 'Hunter',
                'specialisations' => [
                    ['name' => 'Beast Mastery', 'role' => 'Ranged DPS'],
                    ['name' => 'Marksmanship', 'role' => 'Ranged DPS'],
                    ['name' => 'Survival', 'role' => 'Ranged DPS'],
                ],
            ],
            self::ROGUE => [
                'id' => self::ROGUE,
                'name' => 'Rogue',
                'specialisations' => [
                    ['name' => 'Assassination', 'role' => 'Melee DPS'],
                    ['name' => 'Combat', 'role' => 'Melee DPS'],
                    ['name' => 'Subtlety', 'role' => 'Melee DPS'],
                ],
            ],
            self::PRIEST => [
                'id' => self::PRIEST,
                'name' => 'Priest',
                'specialisations' => [
                    ['name' => 'Discipline', 'role' => 'Healer'],
                    ['name' => 'Holy', 'role' => 'Healer'],
                    ['name' => 'Shadow', 'role' => 'Ranged DPS'],
                ],
            ],
            self::SHAMAN => [
                'id' => self::SHAMAN,
                'name' => 'Shaman',
                'specialisations' => [
                    ['name' => 'Elemental', 'role' => 'Ranged DPS'],
                    ['name' => 'Enhancement', 'role' => 'Melee DPS'],
                    ['name' => 'Restoration', 'role' => 'Healer'],
                ],
            ],
            self::MAGE => [
                'id' => self::MAGE,
                'name' => 'Mage',
                'specialisations' => [
                    ['name' => 'Arcane', 'role' => 'Ranged DPS'],
                    ['name' => 'Fire', 'role' => 'Ranged DPS'],
                    ['name' => 'Frost', 'role' => 'Ranged DPS'],
                ],
            ],
            self::WARLOCK => [
                'id' => self::WARLOCK,
                'name' => 'Warlock',
                'specs' => [
                    ['name' => 'Affliction', 'role' => 'Ranged DPS'],
                    ['name' => 'Demonology', 'role' => 'Ranged DPS'],
                    ['name' => 'Destruction', 'role' => 'Ranged DPS'],
                ],
            ],
            self::DRUID => [
                'id' => self::DRUID,
                'name' => 'Druid',
                'specialisations' => [
                    ['name' => 'Balance', 'role' => 'Ranged DPS'],
                    ['name' => 'Feral', 'role' => 'Melee DPS'],
                    ['name' => 'Feral', 'role' => 'Tank'],
                    ['name' => 'Restoration', 'role' => 'Healer'],
                ],
            ],
        ]);
    }

    /**
     * Get classes that have a spec matching the given role.
     */
    public function getByRole(string $role): static
    {
        return $this->filter(function ($class) use ($role) {
            return collect($class['specs'])->contains('role', $role);
        });
    }

    /**
     * Get specialisations by role.
     */
    public function getSpecsByRole(string $role): static
    {
        $filtered = $this->map(function ($class) use ($role) {
            $specs = collect($class['specialisations'])->filter(function ($spec) use ($role) {
                return $spec['role'] === $role;
            })->values()->all();

            if (empty($specs)) {
                return null;
            }

            return [
                'id' => $class['id'],
                'name' => $class['name'],
                'specialisations' => $specs,
            ];
        })->filter();

        return new static($filtered->all());
    }

    /**
     * Get all available roles.
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        return ['Tank', 'Healer', 'Melee DPS', 'Ranged DPS'];
    }
}
