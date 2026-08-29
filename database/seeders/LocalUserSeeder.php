<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class LocalUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $id = config("auth.local_users.{$definition['config']}");

            if (blank($id)) {
                continue;
            }

            $user = User::updateOrCreate(
                ['id' => $id],
                [
                    'username' => $definition['username'],
                    'discriminator' => $definition['discriminator'],
                    'nickname' => $definition['nickname'],
                    'is_admin' => $definition['is_admin'],
                ]
            );

            $user->discordRoles()->sync($definition['roles']);
        }
    }

    /**
     * The test users to provision, one per Discord role plus a site admin.
     *
     * @return list<array{
     *     config: string,
     *     username: string,
     *     discriminator: string,
     *     nickname: string,
     *     roles: list<string>,
     *     is_admin: bool,
     * }>
     */
    protected function definitions(): array
    {
        return [
            [
                'config' => 'officer',
                'username' => 'local-officer',
                'discriminator' => '0000',
                'nickname' => 'Local Officer',
                'roles' => ['829021769448816691'],
                'is_admin' => false,
            ],
            [
                'config' => 'loot_councillor',
                'username' => 'local-loot-councillor',
                'discriminator' => '0000',
                'nickname' => 'Local Loot Councillor',
                'roles' => ['1467994755953852590'],
                'is_admin' => false,
            ],
            [
                'config' => 'raider',
                'username' => 'local-raider',
                'discriminator' => '0000',
                'nickname' => 'Local Raider',
                'roles' => ['1265247017215594496', '829022020301094922'],
                'is_admin' => false,
            ],
            [
                'config' => 'member',
                'username' => 'local-member',
                'discriminator' => '0000',
                'nickname' => 'Local Member',
                'roles' => ['829022020301094922'],
                'is_admin' => false,
            ],
            [
                'config' => 'guest',
                'username' => 'local-guest',
                'discriminator' => '0000',
                'nickname' => 'Local Guest',
                'roles' => ['829022292590985226'],
                'is_admin' => false,
            ],
            [
                'config' => 'admin',
                'username' => 'local-admin',
                'discriminator' => '0000',
                'nickname' => 'Local Admin',
                'roles' => [],
                'is_admin' => true,
            ],
        ];
    }
}
