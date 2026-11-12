<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function dashboard(Request $request): View
    {
        $preview = $request->query('view', 'admin');
        abort_unless(in_array($preview, ['admin', 'hr', 'sub-admin', 'employee'], true), 404);

        // UI previews contain no domain data and grant no role or privilege.
        return view('dashboard', ['preview' => $preview]);
    }

    public function module(string $module): View
    {
        abort_unless(array_key_exists($module, config('workspace.modules')), 404);

        return view('workspace.module', ['module' => $module, 'definition' => config('workspace.modules.'.$module)]);
    }
}
