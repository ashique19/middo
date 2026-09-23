<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class KitchenVerification
{
    public const DISK = 'public';

    public const MAX_EDGE = 960;

    public const JPEG_QUALITY = 52;

    /** @var array<string, string> */
    public const SLOTS = [
        'nid_front' => 'nid_front_path',
        'nid_back' => 'nid_back_path',
        'selfie' => 'profile_photo_path',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'nid_number' => ['nullable', 'string', 'regex:/^\d{10,17}$/'],
            'nid_front' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'nid_back' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'selfie' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_nid_front' => ['sometimes', 'boolean'],
            'remove_nid_back' => ['sometimes', 'boolean'],
            'remove_selfie' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'nid_number.regex' => 'NID number must be 10 to 17 digits.',
            'nid_front.image' => 'NID front must be a photo.',
            'nid_back.image' => 'NID back must be a photo.',
            'selfie.image' => 'Chef selfie must be a photo.',
            'nid_front.max' => 'NID front must be 8 MB or smaller.',
            'nid_back.max' => 'NID back must be 8 MB or smaller.',
            'selfie.max' => 'Chef selfie must be 8 MB or smaller.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files
     */
    public static function apply(User $user, array $data, array $files = []): void
    {
        if (array_key_exists('nid_number', $data)) {
            $number = trim((string) ($data['nid_number'] ?? ''));
            $user->nid_number = $number === '' ? null : $number;
        }

        foreach (self::SLOTS as $slot => $column) {
            if (! empty($data['remove_'.$slot])) {
                self::deleteStored($user->{$column});
                $user->{$column} = null;
            }

            $file = $files[$slot] ?? null;
            if ($file instanceof UploadedFile) {
                self::deleteStored($user->{$column});
                $user->{$column} = self::storeCompressed($file, (int) $user->id, $slot);
            }
        }

        $user->save();
    }

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        $url = $disk->url($path);

        if (! $disk->exists($path)) {
            return $url;
        }

        return $url.'?v='.$disk->lastModified($path);
    }

    public static function deleteStored(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public static function purge(User $user): void
    {
        foreach (self::SLOTS as $column) {
            self::deleteStored($user->{$column});
            $user->{$column} = null;
        }
    }

    public static function storeCompressed(UploadedFile $file, int $userId, string $slot): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->decodePath($file->getRealPath());
        $image->scaleDown(width: self::MAX_EDGE, height: self::MAX_EDGE);
        $encoded = $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY, strip: true));

        $filename = str_replace('_', '-', $slot).'.jpg';
        $path = 'kitchen-verification/'.$userId.'/'.$filename;
        Storage::disk(self::DISK)->put($path, (string) $encoded);

        return $path;
    }
}
