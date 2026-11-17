<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendancePhotoStorage
{
    public function prepare(UploadedFile $file): array
    {
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['photo' => 'Photo processing is unavailable. Attendance was not recorded. Contact HR to restore the attendance service.']);
        }
        $bytes = $file->get();
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || min($info[0], $info[1]) < 240 || max($info[0], $info[1]) > 4096 || $info[0] * $info[1] > 8000000) {
            throw ValidationException::withMessages(['photo' => 'Capture a valid JPEG/PNG frame, at least 240 × 240 pixels, at most 4096 pixels per side and 8 megapixels.']);
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be decoded. Retake it with the live camera.']);
        }
        $scale = min(1, 1280 / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        ob_start();
        imagejpeg($canvas, null, 82);
        $normalized = ob_get_clean();

        return ['bytes' => $normalized, 'file_hash' => hash('sha256', $normalized), 'file_size' => strlen($normalized), 'image_width' => $width, 'image_height' => $height, 'original_format' => $info[2] === IMAGETYPE_JPEG ? 'jpeg' : 'png'];
    }

    public function store(array $photo): string
    {
        $path = now()->format('Y/m/d').'/'.Str::uuid().'.bin';
        Storage::disk('attendance')->put($path, Crypt::encryptString($photo['bytes']));

        return $path;
    }

    public function discard(string $path): void
    {
        Storage::disk('attendance')->delete($path);
    }
}
