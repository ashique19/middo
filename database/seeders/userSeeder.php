<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class userSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = \App\Models\User::create([
            'first_name' => 'Admin User',
            'last_name' => 'Admin',
            'email' => 'admin@middo.com',
            'mobile' => '01310123451',
            'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'role_id' => \App\Models\Role::where('name', 'admin')->first()->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $corporate = \App\Models\User::create([
            'first_name' => 'Corporate User',
            'last_name' => 'Corporate', 
            'email' => 'corporate@middo.com',
            'mobile' => '01310123452',
            'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'role_id' => \App\Models\Role::where('name', 'corporate')->first()->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $kitchenRoleId = \App\Models\Role::where('name', 'kitchen')->first()->id;

        $kitchens = [
            ['first_name' => 'Gulshan', 'last_name' => 'Kitchen', 'email' => 'kitchen@middo.com', 'mobile' => '01310123453'],
            ['first_name' => 'Banani', 'last_name' => 'Kitchen', 'email' => 'kitchen2@middo.com', 'mobile' => '01310123456'],
            ['first_name' => 'Mohakhali', 'last_name' => 'Kitchen', 'email' => 'kitchen3@middo.com', 'mobile' => '01310123457'],
            ['first_name' => 'Baridhara', 'last_name' => 'Kitchen', 'email' => 'kitchen4@middo.com', 'mobile' => '01310123458'],
            ['first_name' => 'Dhanmondi', 'last_name' => 'Kitchen', 'email' => 'kitchen5@middo.com', 'mobile' => '01310123459'],
        ];

        foreach ($kitchens as $kitchen) {
            \App\Models\User::create([
                'first_name' => $kitchen['first_name'],
                'last_name' => $kitchen['last_name'],
                'email' => $kitchen['email'],
                'mobile' => $kitchen['mobile'],
                'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
                'role_id' => $kitchenRoleId,
                'status' => 'active',
                'is_mobile_verified' => true,
            ]);
        }

        $deliveryRoleId = \App\Models\Role::where('name', 'delivery')->first()->id;

        $riders = [
            ['first_name' => 'Rahim', 'last_name' => 'Uddin', 'email' => 'delivery@middo.com', 'mobile' => '01310123454'],
            ['first_name' => 'Karim', 'last_name' => 'Ahmed', 'email' => 'delivery2@middo.com', 'mobile' => '01310123460'],
            ['first_name' => 'Jamal', 'last_name' => 'Hossain', 'email' => 'delivery3@middo.com', 'mobile' => '01310123461'],
        ];

        foreach ($riders as $rider) {
            \App\Models\User::create([
                'first_name' => $rider['first_name'],
                'last_name' => $rider['last_name'],
                'email' => $rider['email'],
                'mobile' => $rider['mobile'],
                'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
                'role_id' => $deliveryRoleId,
                'status' => 'active',
                'is_mobile_verified' => true,
            ]);
        }

        $operations = \App\Models\User::create([
            'first_name' => 'Operation User',
            'last_name' => 'Operation',
            'email' => 'operations@middo.com',
            'mobile' => '01310123455',
            'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'role_id' => \App\Models\Role::where('name', 'operation')->first()->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

    }
}
