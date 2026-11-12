<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['action' => ['nullable', 'string', 'max:80']]);

        return view('hr.audit', ['logs' => AuditLog::with('actor')->when($data['action'] ?? null, fn ($query, $action) => $query->where('action', $action))->latest('id')->paginate(25)->withQueryString()]);
    }
}
