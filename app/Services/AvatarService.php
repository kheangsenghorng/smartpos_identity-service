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
     *
     * Pipeline Steps:
     * 1. Resize: Proportional scaling if dimensions exceed maxWidth/maxHeight.
     * 2. Compress / Re-encode: Converts to optimized WebP format with quality control.
     * 3. Cache: Applies long-term public Cache-Control headers for CDN and browser caching.
     */
    public function uploadAvatar(
        User $user,
        UploadedFile $file,
        ?string $disk = null,
        int $quality = 80,
        int $maxWidth = 512,
        int $maxHeight = 512
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

        // Step 1: Resize — Reduce image dimensions if exceeding maximum limits
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
            $targetWidth = max(1, (int) round($origWidth * $ratio));
            $targetHeight = max(1, (int) round($origHeight * $ratio));

            $resizedImage = imagescale($image, $targetWidth, $targetHeight);
            if ($resizedImage !== false) {
                imagedestroy($image);
                $image = $resizedImage;
            }
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Step 2: Compress / re-encode — Reduce file size to optimized WebP format
        ob_start();
        $success = imagewebp($image, null, $quality);
        $webpContent = ob_get_clean();

        imagedestroy($image);

        if (!$success || $webpContent === false) {
            throw new RuntimeException('Failed to process image into WebP format.');
        }

        // Step 3: Cache — Store with public CDN / browser Cache-Control directives
        $path = 'avatars/' . Str::uuid() . '.webp';
        Storage::disk($activeDisk)->put($path, $webpContent, [
            'visibility' => 'public',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

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
