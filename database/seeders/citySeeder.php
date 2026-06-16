<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\City;

class citySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = ['Dhaka', 'Chittagong'];
        
        foreach ($cities as $name) {
            City::create(['name' => $name]);
        }
    }
}
