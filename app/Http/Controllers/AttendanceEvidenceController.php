<?php

namespace App\Http\Controllers;

use App\Models\AttendancePhotoEvidence;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttendanceEvidenceController extends Controller
{
    public function show(AttendancePhotoEvidence $photo): Response
    {
        Gate::authorize('view', $photo->event->record);
        abort_if($photo->purged_at || ! $photo->storage_path || ! Storage::disk('attendance')->exists($photo->storage_path), 410, 'This evidence is no longer available under the retention policy.');

        return response(Crypt::decryptString(Storage::disk('attendance')->get($photo->storage_path)), 200, ['Content-Type' => 'image/jpeg', 'Content-Disposition' => 'inline; filename="attendance-'.$photo->id.'.jpg"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
