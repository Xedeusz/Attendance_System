<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePhotoRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function store(ProfilePhotoRequest $request, ProfilePhotoService $photos): JsonResponse
    {
        $user = $photos->update($request->user(), $request->file('photo'));

        return response()->json(['avatar' => $user->avatarData(), 'message' => 'Profile picture updated successfully.']);
    }

    public function destroy(Request $request, ProfilePhotoService $photos): JsonResponse
    {
        abort_unless($request->user()->isEmployee() && $request->user()->employee, 403);
        $request->validate(['user_id' => ['prohibited'], 'employee_id' => ['prohibited'], 'profile_photo_path' => ['prohibited']]);
        $user = $photos->update($request->user(), null);

        return response()->json(['avatar' => $user->avatarData(), 'message' => 'Profile picture removed successfully.']);
    }

    public function show(Request $request, string $version): StreamedResponse
    {
        return $this->photo($request->user()->fresh(), $version);
    }

    public function employee(Employee $employee, string $version): StreamedResponse
    {
        Gate::authorize('view', $employee);

        return $this->photo($employee->user, $version);
    }

    private function photo(User $user, string $version): StreamedResponse
    {
        $path = $user->profile_photo_path;
        abort_unless($path && hash_equals(hash('sha256', $path), $version) && preg_match('~^profile-photos/[a-f0-9-]{36}\.jpg$~D', $path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'profile.jpg', ['Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
