<?php

namespace App\Services;

use App\Models\SupervisorSignature;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupervisorSignatureService
{
    public function __construct(private WorkflowTransaction $transactions, private AuditService $audit) {}

    public function save(User $actor, UploadedFile $upload): void
    {
        $png = $this->normalize($upload);
        $path = 'supervisor-signatures/'.Str::uuid().'.bin';
        try {
            $this->transactions->run($actor, function (User $supervisor) use ($path, $png): void {
                abort_unless($supervisor->isSupervisor(), 403);
                $previous = SupervisorSignature::activeFor($supervisor)->first();
                $version = (int) SupervisorSignature::where('supervisor_user_id', $supervisor->id)->max('version') + 1;
                $registeredAt = now();
                Storage::disk('leave')->put($path, Crypt::encryptString($png));
                $previous?->update(['revoked_at' => $registeredAt]);
                $signature = SupervisorSignature::create([
                    'supervisor_user_id' => $supervisor->id,
                    'storage_path' => $path,
                    'version' => $version,
                    'registered_at' => $registeredAt,
                ]);
                $this->audit->record($previous ? 'signature.replaced' : 'signature.registered', $signature, ['signature_version' => $version, 'previous_version' => $previous?->version]);
            });
        } catch (Throwable $exception) {
            try {
                if (! SupervisorSignature::where('storage_path', $path)->exists()) {
                    Storage::disk('leave')->delete($path);
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }
    }

    public function bytes(SupervisorSignature $signature): string
    {
        return Crypt::decryptString(Storage::disk('leave')->get($signature->storage_path));
    }

    private function normalize(UploadedFile $upload): string
    {
        $bytes = $upload->get();
        $size = @getimagesizefromstring($bytes);
        if (! $size || ! in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || max($size[0], $size[1]) > 2400 || min($size[0], $size[1]) < 20) {
            throw ValidationException::withMessages(['signature' => 'Upload a valid PNG or JPEG between 20 and 2400 pixels on each side.']);
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['signature' => 'The signature image cannot be decoded.']);
        }
        imagepalettetotruecolor($image);
        imagesavealpha($image, true);
        ob_start();
        try {
            if (! imagepng($image)) {
                throw ValidationException::withMessages(['signature' => 'The signature image could not be prepared.']);
            }

            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
