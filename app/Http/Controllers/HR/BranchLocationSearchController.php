<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BranchLocationSearchController extends Controller
{
    public function __invoke(Request $request, GeocodingService $geocoding): JsonResponse
    {
        abort_unless($request->user()?->isHr(), 403);
        $request->merge(['query' => is_string($request->input('query')) ? preg_replace('/\\s+/u', ' ', trim($request->input('query'))) : $request->input('query')]);
        $data = $request->validate(['query' => ['required', 'string', 'min:3', 'max:200', 'regex:/[\\pL\\pN]/u']], [
            'query.*' => 'Enter a valid location to search.',
        ]);
        if (! $geocoding->configured()) {
            return response()->json(['message' => 'Location search is not configured. Contact the system administrator.'], 503);
        }
        try {
            return response()->json(['results' => $geocoding->search(mb_strtolower($data['query']))]);
        } catch (RuntimeException $exception) {
            $status = in_array($exception->getCode(), [429, 504], true) ? $exception->getCode() : 503;

            return response()->json(['message' => match ($status) {
                429 => 'Location search is temporarily limited. Please try again later.',
                504 => 'Location search timed out. Please try again.',
                default => 'Location search is temporarily unavailable. You can still select a location directly on the map.',
            }], $status);
        }
    }
}
