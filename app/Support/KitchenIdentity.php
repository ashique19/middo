<?php

namespace App\Support;

use App\Models\User;

class KitchenIdentity
{
    /** @var list<string> */
    public const LOCKED = ['first_name', 'last_name', 'mobile', 'address'];

    /**
     * Reject kitchen attempts to change name, phone, or address.
     * Matching values are allowed so older clients can resubmit the current profile.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    public static function changeErrors(User $user, array $input): array
    {
        if (! $user->isKitchen()) {
            return [];
        }

        $errors = [];
        foreach (self::LOCKED as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            if (self::normalize($input[$field]) === self::normalize($user->getAttribute($field))) {
                continue;
            }

            $errors[$field] = [self::message($field)];
        }

        return $errors;
    }

    public static function message(string $field): string
    {
        return match ($field) {
            'mobile' => 'Kitchen phone number can only be changed by an admin.',
            'address' => 'Kitchen address can only be changed by an admin.',
            default => 'Kitchen name can only be changed by an admin.',
        };
    }

    private static function normalize(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
