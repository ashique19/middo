<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $dhaka = City::where('name', 'Dhaka')->first();
        $chittagong = City::where('name', 'Chittagong')->first();

        $areas = [
            ['name' => 'Mirpur', 'city_id' => $dhaka->id],
            ['name' => 'Gulshan', 'city_id' => $dhaka->id],
            ['name' => 'Banani', 'city_id' => $dhaka->id],
            ['name' => 'Baridhara', 'city_id' => $dhaka->id],
            ['name' => 'Cantonment', 'city_id' => $dhaka->id],
            ['name' => 'Chittagong sadar', 'city_id' => $chittagong->id],
            ['name' => 'Bayezid', 'city_id' => $chittagong->id],
            ['name' => 'Halishahar', 'city_id' => $chittagong->id],
        ];

        foreach ($areas as $area) {
            Area::create($area);
        }
    }
}