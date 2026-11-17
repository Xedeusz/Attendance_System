<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfilePhotoService
{
    public function __construct(private AuditService $audit) {}

    public function update(User $actor, ?UploadedFile $file): User
    {
        $path = null;
        if ($file) {
            if (! extension_loaded('gd')) {
                throw ValidationException::withMessages(['photo' => 'Photo processing is unavailable. Please try again later.']);
            }
            $bytes = $file->get();
            $size = @getimagesizefromstring($bytes);
            if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || min($size[0], $size[1]) < 32 || max($size[0], $size[1]) > 4096 || $size[0] * $size[1] > 8000000) {
                throw ValidationException::withMessages(['photo' => 'Use a valid JPG, PNG or WebP image between 32 and 4096 pixels per side (maximum 8 megapixels).']);
            }
            $source = @imagecreatefromstring($bytes);
            if (! $source) {
                throw ValidationException::withMessages(['photo' => 'Please upload a valid image.']);
            }
            $scale = min(1, 512 / max($size[0], $size[1]));
            $width = max(1, (int) round($size[0] * $scale));
            $height = max(1, (int) round($size[1] * $scale));
            $canvas = imagecreatetruecolor($width, $height);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
            ob_start();
            imagejpeg($canvas, null, 85);
            $normalized = ob_get_clean();
            imagedestroy($canvas);
            imagedestroy($source);
            $path = 'profile-photos/'.Str::uuid().'.jpg';
            if (! Storage::disk('local')->put($path, $normalized)) {
                try {
                    $this->discard($path);
                } catch (Throwable $cleanupFailure) {
                    report($cleanupFailure);
                }
                throw new \RuntimeException('Profile photo storage failed.');
            }
        }
        try {
            return DB::transaction(function () use ($actor, $path) {
                $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->isEmployee() && $user->employee && $user->canAuthenticate(), 403);
                $old = $user->profile_photo_path;
                $user->forceFill(['profile_photo_path' => $path])->save();
                $this->audit->record($path ? 'profile.photo_updated' : 'profile.photo_removed', $user);
                if ($old) {
                    DB::afterCommit(function () use ($old) {
                        try {
                            $this->discard($old);
                        } catch (Throwable $failure) {
                            report($failure);
                        }
                    });
                }

                return $user;
            });
        } catch (Throwable $failure) {
            if ($path) {
                try {
                    $this->discard($path);
                } catch (Throwable $cleanupFailure) {
                    report($cleanupFailure);
                }
            }throw $failure;
        }
    }

    private function discard(string $path): void
    {
        if (preg_match('~^profile-photos/[a-f0-9-]{36}\.jpg$~D', $path)) {
            if (! Storage::disk('local')->delete($path)) {
                throw new \RuntimeException('Profile photo cleanup failed.');
            }
        }
    }
}
