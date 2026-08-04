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
                'name' => 'Vegetable Khichdi Thali',
                'summary' => 'Comforting khichdi with mutton curry, mashed vegetables, and fresh kachumber salad.',
                'price' => 350,
                'kitchen_commission' => 35,
                'display_order' => 1,
                'image' => 'menu-1.jpg',
            ],
            [
                'name' => 'Traditional Vegetarian Thali',
                'summary' => 'Basmati rice with yellow dal, potato sabzi, mixed vegetable curry, and green bean stir-fry.',
                'price' => 320,
                'kitchen_commission' => 32,
                'display_order' => 2,
                'image' => 'menu-2.jpg',
            ],
            [
                'name' => 'Royal Indian Thali',
                'summary' => 'A grand platter with meat curry, dal tadka, mixed vegetable curries, and spiced potatoes.',
                'price' => 450,
                'kitchen_commission' => 45,
                'display_order' => 3,
                'image' => 'menu-3.jpg',
            ],
            [
                'name' => 'Chicken Curry Thali',
                'summary' => 'Steamed rice with rich chicken curry, mixed sabzi, yellow dal, and spiced potatoes.',
                'price' => 420,
                'kitchen_commission' => 42,
                'display_order' => 4,
                'image' => 'menu-4.jpg',
            ],
            [
                'name' => 'Bengali Fish Thali',
                'summary' => 'Fish curry with steamed rice, mixed vegetables, yellow dal, and spiced mashed potatoes.',
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
