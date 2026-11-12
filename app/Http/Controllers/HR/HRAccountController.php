<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Administration;
use App\Services\AuditService;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HRAccountController extends Controller
{
    public function index(): View
    {
        return view('hr.accounts.index', ['accounts' => User::where('role_id', Role::HR)->orderBy('name')->paginate(15)]);
    }

    public function create(): View
    {
        return view('hr.accounts.create');
    }

    public function store(Request $request, Administration $administration, InvitationService $invitations, AuditService $audit): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254', 'unique:users,email'], 'role' => ['prohibited'], 'role_id' => ['prohibited'], 'password' => ['prohibited'], 'account_status' => ['prohibited']]);
        $issued = $administration->run(function () use ($data, $invitations, $audit) {
            $user = User::create([...$data, 'password' => Str::random(80)]);
            $user->forceFill(['role_id' => Role::HR])->save();
            $audit->record('hr.created', $user);

            return $invitations->issue($user);
        });

        return redirect()->route('hr.accounts.index')->with('status', $invitations->deliver($issued));
    }

    public function status(Request $request, User $hrAccount, Administration $administration, AccountService $accounts, InvitationService $invitations): RedirectResponse
    {
        abort_unless($hrAccount->isHr(), 403);
        $data = $request->validate(['action' => ['required', Rule::in(['deactivate', 'reactivate'])], 'confirmation' => ['accepted']]);
        $issued = $administration->run(function () use ($hrAccount, $data, $accounts) {
            $user = $hrAccount->fresh();
            if ($data['action'] === 'deactivate') {
                $accounts->deactivate($user);

                return null;
            }

            return $accounts->reactivate($user);
        });

        return back()->with('status', $issued ? $invitations->deliver($issued) : 'HR account status updated.');
    }

    public function resend(User $hrAccount, Administration $administration, InvitationService $invitations): RedirectResponse
    {
        abort_unless($hrAccount->isHr(), 403);
        $issued = $administration->run(fn () => $invitations->issue($hrAccount->fresh()));

        return back()->with('status', $invitations->deliver($issued));
    }
}
