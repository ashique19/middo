<?php

namespace App\Support;

use App\Models\KitchenRatingLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class KitchenRating
{
    public const MIN = 0;

    public const MAX = 10;

    /** @var list<string> */
    public const FORBIDDEN = ['kitchen_rating', 'kitchen_rating_note'];

    /**
     * Kitchen, ops, and rider clients must not send rating fields.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    public static function changeErrors(array $input): array
    {
        $errors = [];
        foreach (self::FORBIDDEN as $field) {
            if (array_key_exists($field, $input)) {
                $errors[$field] = ['Kitchen rating can only be changed by an admin.'];
            }
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'kitchen_rating' => ['nullable', 'integer', 'between:'.self::MIN.','.self::MAX],
            'kitchen_rating_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Persist the current rating and note. Writes a history row when either value changes.
     */
    public static function apply(User $kitchen, ?int $rating, ?string $note, ?int $actorId = null): bool
    {
        $note = self::normalizeNote($note);
        $oldRating = $kitchen->kitchen_rating;
        $oldNote = self::normalizeNote($kitchen->kitchen_rating_note);

        if ($oldRating === $rating && $oldNote === $note) {
            return false;
        }

        $kitchen->kitchen_rating = $rating;
        $kitchen->kitchen_rating_note = $note;
        $kitchen->save();

        KitchenRatingLog::query()->create([
            'kitchen_id' => $kitchen->id,
            'actor_id' => $actorId ?? Auth::id(),
            'old_rating' => $oldRating,
            'new_rating' => $rating,
            'note' => $note,
            'created_at' => now(),
        ]);

        return true;
    }

    public static function normalizeNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }
}
