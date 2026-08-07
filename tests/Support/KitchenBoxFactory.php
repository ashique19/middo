<?php

namespace Tests\Support;

use App\Models\MiddoBox;
use App\Models\User;

class KitchenBoxFactory
{
    /**
     * @return list<MiddoBox>
     */
    public static function seedSendable(User $kitchen, int $count): array
    {
        $boxes = [];
        for ($i = 0; $i < $count; $i++) {
            $boxes[] = MiddoBox::create([
                'qr_code_id' => 'MB-TEST-'.$kitchen->id.'-'.uniqid().'-'.$i,
                'box_model_type' => 'standard_insulated',
                'asset_status' => 'active',
                'kitchen_id' => $kitchen->id,
                'held_by_user_id' => $kitchen->id,
                'total_uses_count' => 0,
            ]);
        }

        return $boxes;
    }
}
