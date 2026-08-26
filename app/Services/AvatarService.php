<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AvatarService
{
    /**
     * Resolve the active storage disk.
     */
    public function disk(?string $disk = null): string
    {
        return $disk ?: config('filesystems.default', 'public');
    }

    /**
     * Process and store avatar image file as WebP format.
     * Accepts JPEG, PNG, GIF, or WebP uploaded files.
     */
    public function uploadAvatar(
        User $user,
        UploadedFile $file,
        ?string $disk = null,
        int $quality = 80
    ): string {
        $activeDisk = $this->disk($disk);
        $this->deleteAvatarFile($user, $activeDisk);

        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            throw new RuntimeException('Failed to read uploaded image content.');
        }

        $image = @imagecreatefromstring($content);
        if ($image === false) {
            throw new RuntimeException('Invalid image payload or unsupported format.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        $success = imagewebp($image, null, $quality);
        $webpContent = ob_get_clean();

        imagedestroy($image);

        if (!$success || $webpContent === false) {
            throw new RuntimeException('Failed to process image into WebP format.');
        }

        $path = 'avatars/' . Str::uuid() . '.webp';
        Storage::disk($activeDisk)->put($path, $webpContent);

        $user->update([
            'avatar' => $path,
        ]);

        return $path;
    }

    /**
     * Remove existing avatar file and clear database attribute.
     */
    public function removeAvatar(User $user, ?string $disk = null): bool
    {
        $activeDisk = $this->disk($disk);
        $this->deleteAvatarFile($user, $activeDisk);

        $user->update([
            'avatar' => null,
        ]);

        return true;
    }

    /**
     * Helper to delete physical avatar file if exists.
     */
    protected function deleteAvatarFile(User $user, ?string $disk = null): void
    {
        $activeDisk = $this->disk($disk);
        if ($user->avatar && Storage::disk($activeDisk)->exists($user->avatar)) {
            Storage::disk($activeDisk)->delete($user->avatar);
        }
    }
}
