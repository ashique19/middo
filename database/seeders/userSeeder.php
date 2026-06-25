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

        $kitchen = \App\Models\User::create([
            'first_name' => 'Kitchen User',
            'last_name' => 'Kitchen',
            'email' => 'kitchen@middo.com',
            'mobile' => '01310123453',
            'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'role_id' => \App\Models\Role::where('name', 'kitchen')->first()->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $delivery = \App\Models\User::create([
            'first_name' => 'Delivery User',
            'last_name' => 'Delivery',
            'email' => 'delivery@middo.com',
            'mobile' => '01310123454',
            'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'role_id' => \App\Models\Role::where('name', 'delivery')->first()->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

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
