<?php

namespace App\Support;

use App\Models\User;

class KitchenActivation
{
    /**
     * Activate a kitchen and copy tier default allowed quantity when unset.
     */
    public static function activate(User $kitchen): User
    {
        $tier = KitchenTier::normalize($kitchen->kitchen_tier);
        $allowed = $kitchen->allowed_open_groups;

        if ($allowed === null) {
            $allowed = MiddoSettings::defaultAllowedOpenGroupsForTier($tier);
        }

        $kitchen->update([
            'status' => 'active',
            'kitchen_tier' => $tier,
            'allowed_open_groups' => (int) $allowed,
        ]);

        return $kitchen->fresh();
    }

    /**
     * Reset a kitchen's allowed_open_groups to the current Settings default for its tier.
     */
    public static function resetAllowedOpenGroupsToTierDefault(User $kitchen): User
    {
        $tier = KitchenTier::normalize($kitchen->kitchen_tier);

        $kitchen->update([
            'kitchen_tier' => $tier,
            'allowed_open_groups' => MiddoSettings::defaultAllowedOpenGroupsForTier($tier),
        ]);

        return $kitchen->fresh();
    }
}
