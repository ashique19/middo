<?php

namespace Database\Seeders;

use App\Support\StaffNavSync;
use Illuminate\Database\Seeder;

class NavSeeder extends Seeder
{
    /**
     * Seed desktop sidebar navs from the canonical section map.
     */
    public function run(): void
    {
        StaffNavSync::syncAll();
    }
}
