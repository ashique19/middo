<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Soft Khichuri Meal',
                'summary' => 'Soft moong khichuri with begun bhaji, aloo bhorta, and fresh salad — classic comfort lunch.',
                'price' => 350,
                'kitchen_commission' => 35,
                'display_order' => 1,
                'image' => 'menu-1.jpg',
            ],
            [
                'name' => 'Niramish Bhaat Plate',
                'summary' => 'Steamed rice with moong daal, aloo bhaji, mixed shobji, and salad — light vegetarian office meal.',
                'price' => 320,
                'kitchen_commission' => 32,
                'display_order' => 2,
                'image' => 'menu-2.jpg',
            ],
            [
                'name' => 'Special Mangsho Bhaat',
                'summary' => 'Gorur mangsho curry with rice, daal, mixed shobji, and spiced aloo — a hearty Bangla lunch.',
                'price' => 450,
                'kitchen_commission' => 45,
                'display_order' => 3,
                'image' => 'menu-3.jpg',
            ],
            [
                'name' => 'Murgir Curry Bhaat',
                'summary' => 'Home-style chicken curry with steamed rice, moong daal, mixed shobji, and aloo bhaji.',
                'price' => 420,
                'kitchen_commission' => 42,
                'display_order' => 4,
                'image' => 'menu-4.jpg',
            ],
            [
                'name' => 'Machher Jhol Bhaat',
                'summary' => 'Light machher jhol with steamed rice, daal, shobji, and aloo bhorta — everyday Bangla fish meal.',
                'price' => 400,
                'kitchen_commission' => 40,
                'display_order' => 5,
                'image' => 'menu-5.jpg',
            ],
        ];

        foreach ($items as $data) {
            $thumbnail = 'img/menu/'.$data['image'];

            if (! file_exists(public_path($thumbnail))) {
                $this->command?->warn("Skipping {$data['name']}: {$thumbnail} not found.");

                continue;
            }

            MenuItem::create([
                'name' => $data['name'],
                'summary' => $data['summary'],
                'price' => $data['price'],
                'kitchen_commission' => $data['kitchen_commission'],
                'delivery_commission' => max(20, (int) round($data['kitchen_commission'] * 0.8)),
                'thumbnail' => $thumbnail,
                'is_featured' => true,
                'is_homepage' => $data['display_order'] <= 3,
                'display_order' => $data['display_order'],
            ]);
        }
    }
}
