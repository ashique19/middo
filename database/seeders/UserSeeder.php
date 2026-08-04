<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('12345678');
        $dhakaId = City::where('name', 'Dhaka')->value('id');
        $gulshanId = Area::where('name', 'Gulshan')->value('id');
        $bananiId = Area::where('name', 'Banani')->value('id');
        $baridharaId = Area::where('name', 'Baridhara')->value('id');
        $mirpurId = Area::where('name', 'Mirpur')->value('id');

        User::create([
            'first_name' => 'Admin User',
            'last_name' => 'Admin',
            'email' => 'admin@middo.com',
            'mobile' => '01310123451',
            'password' => $password,
            'role_id' => Role::where('name', 'admin')->value('id'),
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        User::create([
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Middo Demo Corp',
            'email' => 'corporate@middo.com',
            'mobile' => '01310123452',
            'password' => $password,
            'role_id' => Role::where('name', 'corporate')->value('id'),
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 50000,
            'address' => 'House 12, Road 5, Gulshan',
            'city_id' => $dhakaId,
            'area_id' => $gulshanId,
        ]);

        $kitchenRoleId = Role::where('name', 'kitchen')->value('id');

        $activeKitchens = [
            ['first_name' => 'Gulshan', 'last_name' => 'Kitchen', 'email' => 'kitchen@middo.com', 'mobile' => '01310123453', 'area_id' => $gulshanId, 'address' => 'Road 45, Gulshan Ave'],
            ['first_name' => 'Banani', 'last_name' => 'Kitchen', 'email' => 'kitchen2@middo.com', 'mobile' => '01310123456', 'area_id' => $bananiId, 'address' => 'Block C, Banani'],
            ['first_name' => 'Mohakhali', 'last_name' => 'Kitchen', 'email' => 'kitchen3@middo.com', 'mobile' => '01310123457', 'area_id' => $gulshanId, 'address' => 'Mohakhali DOHS'],
            ['first_name' => 'Baridhara', 'last_name' => 'Kitchen', 'email' => 'kitchen4@middo.com', 'mobile' => '01310123458', 'area_id' => $baridharaId, 'address' => 'Baridhara Diplomatic Zone'],
            ['first_name' => 'Dhanmondi', 'last_name' => 'Kitchen', 'email' => 'kitchen5@middo.com', 'mobile' => '01310123459', 'area_id' => $mirpurId, 'address' => 'Road 27, Dhanmondi'],
        ];

        foreach ($activeKitchens as $index => $kitchen) {
            $tier = match ($index) {
                0 => 'gold',
                1 => 'silver',
                2 => 'platinum',
                default => 'silver',
            };
            $slots = match ($tier) {
                'gold' => 2,
                'platinum' => 3,
                default => 1,
            };

            User::create([
                'first_name' => $kitchen['first_name'],
                'last_name' => $kitchen['last_name'],
                'email' => $kitchen['email'],
                'mobile' => $kitchen['mobile'],
                'password' => $password,
                'role_id' => $kitchenRoleId,
                'status' => 'active',
                'is_mobile_verified' => true,
                'address' => $kitchen['address'],
                'city_id' => $dhakaId,
                'area_id' => $kitchen['area_id'],
                'kitchen_tier' => $tier,
                'allowed_open_groups' => $slots,
            ]);
        }

        // Pending kitchen signups → Admin > Kitchens > Onboarding
        $pendingKitchens = [
            ['first_name' => 'Uttara', 'last_name' => 'Kitchen', 'email' => 'pending.kitchen1@middo.com', 'mobile' => '01310123501', 'area_id' => $mirpurId, 'address' => 'Sector 7, Uttara'],
            ['first_name' => 'Mirpur', 'last_name' => 'Kitchen', 'email' => 'pending.kitchen2@middo.com', 'mobile' => '01310123502', 'area_id' => $mirpurId, 'address' => 'Mirpur 10 Roundabout'],
            ['first_name' => 'Motijheel', 'last_name' => 'Kitchen', 'email' => 'pending.kitchen3@middo.com', 'mobile' => '01310123503', 'area_id' => $gulshanId, 'address' => 'Dilkhusha, Motijheel'],
            ['first_name' => 'Bashundhara', 'last_name' => 'Kitchen', 'email' => 'pending.kitchen4@middo.com', 'mobile' => '01310123504', 'area_id' => $baridharaId, 'address' => 'Block B, Bashundhara R/A'],
            ['first_name' => 'Wari', 'last_name' => 'Kitchen', 'email' => 'pending.kitchen5@middo.com', 'mobile' => '01310123505', 'area_id' => $mirpurId, 'address' => 'Rankin Street, Wari'],
            ['first_name' => 'Tejgaon', 'last_name' => 'Kitchen', 'email' => 'pending.kitchen6@middo.com', 'mobile' => '01310123506', 'area_id' => $bananiId, 'address' => 'Industrial Area, Tejgaon'],
            ['first_name' => 'Farmgate', 'last_name' => 'Kitchen', 'email' => 'pending.kitchen7@middo.com', 'mobile' => '01310123507', 'area_id' => $bananiId, 'address' => 'Green Road, Farmgate'],
        ];

        foreach ($pendingKitchens as $kitchen) {
            User::create([
                'first_name' => $kitchen['first_name'],
                'last_name' => $kitchen['last_name'],
                'email' => $kitchen['email'],
                'mobile' => $kitchen['mobile'],
                'password' => $password,
                'role_id' => $kitchenRoleId,
                'status' => 'pending',
                'is_mobile_verified' => false,
                'address' => $kitchen['address'],
                'city_id' => $dhakaId,
                'area_id' => $kitchen['area_id'],
            ]);
        }

        $deliveryRoleId = Role::where('name', 'delivery')->value('id');

        $riders = [
            ['first_name' => 'Rahim', 'last_name' => 'Uddin', 'email' => 'delivery@middo.com', 'mobile' => '01310123454'],
            ['first_name' => 'Karim', 'last_name' => 'Ahmed', 'email' => 'delivery2@middo.com', 'mobile' => '01310123460'],
            ['first_name' => 'Jamal', 'last_name' => 'Hossain', 'email' => 'delivery3@middo.com', 'mobile' => '01310123461'],
        ];

        foreach ($riders as $rider) {
            User::create([
                'first_name' => $rider['first_name'],
                'last_name' => $rider['last_name'],
                'email' => $rider['email'],
                'mobile' => $rider['mobile'],
                'password' => $password,
                'role_id' => $deliveryRoleId,
                'status' => 'active',
                'is_mobile_verified' => true,
            ]);
        }

        User::create([
            'first_name' => 'Operation User',
            'last_name' => 'Operation',
            'email' => 'operations@middo.com',
            'mobile' => '01310123455',
            'password' => $password,
            'role_id' => Role::where('name', 'operation')->value('id'),
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);
    }
}
