<?php

namespace Database\Seeders;

use App\Models\MiddoBox;
use Illuminate\Database\Seeder;

class MiddoBoxTestSeeder extends Seeder
{
    public function run(): void
    {
        MiddoBox::generateBatch(20);
    }
}
