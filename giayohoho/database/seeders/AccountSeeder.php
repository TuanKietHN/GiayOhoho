<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = '123456';

    public function run(): void
    {
        foreach ($this->accounts() as $account) {
            $this->seedAccount($account);
        }
    }

    private function seedAccount(array $seed): void
    {
        $now = now();
        $roleId = DB::table('roles')->where('name', $seed['role'])->value('id');

        DB::table('accounts')->updateOrInsert(
            ['email' => $seed['email']],
            [
                'name' => $seed['first_name'].' '.$seed['last_name'],
                'username' => $seed['username'],
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'email_verified' => true,
                'email_verified_at' => $now,
                'status' => 'ACTIVE',
                'locked' => false,
                'first_name' => $seed['first_name'],
                'last_name' => $seed['last_name'],
                'phone_number' => $seed['phone_number'],
                'birth_of_date' => $seed['birth_of_date'],
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        $accountId = DB::table('accounts')->where('email', $seed['email'])->value('id');
        if ($accountId && $roleId) {
            DB::table('account_roles')->updateOrInsert([
                'account_id' => $accountId,
                'role_id' => $roleId,
            ]);
        }

        DB::table('profiles')->updateOrInsert(
            ['account_id' => $accountId],
            [
                'dtype' => $seed['profile_type'],
                'first_name' => $seed['first_name'],
                'last_name' => $seed['last_name'],
                'phone_number' => $seed['phone_number'],
                'birth_of_date' => $seed['birth_of_date'],
                'created_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function accounts(): array
    {
        return [
            [
                'username' => 'admin',
                'email' => 'admin@ohgiay.vn',
                'first_name' => 'Admin',
                'last_name' => 'OhGiay',
                'phone_number' => '0900000000',
                'birth_of_date' => '1990-01-01',
                'role' => 'ADMIN',
                'profile_type' => 'AdminProfile',
            ],
            [
                'username' => 'tuankiethn',
                'email' => 'tuankiethn@ohgiay.vn',
                'first_name' => 'Tuan Kiet',
                'last_name' => 'Nguyen',
                'phone_number' => '0901111222',
                'birth_of_date' => '1998-05-05',
                'role' => 'CUSTOMER',
                'profile_type' => 'CustomerProfile',
            ],
            [
                'username' => 'kiet',
                'email' => 'kiet@ohgiay.vn',
                'first_name' => 'Kiet',
                'last_name' => 'Truong',
                'phone_number' => '0902222333',
                'birth_of_date' => '1999-06-06',
                'role' => 'CUSTOMER',
                'profile_type' => 'CustomerProfile',
            ],
        ];
    }
}
