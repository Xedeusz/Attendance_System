<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'profile_photo_path'])]
class User extends Authenticatable
{
    protected $attributes = ['role_id' => Role::EMPLOYEE, 'account_status' => 'pending_activation', 'session_version' => 1];

    public function isHr(): bool
    {
        return (int) $this->role_id === Role::HR;
    }

    public function isSupervisor(): bool
    {
        return (int) $this->role_id === Role::SUPERVISOR;
    }

    public function isEmployee(): bool
    {
        return (int) $this->role_id === Role::EMPLOYEE;
    }

    public function workspaceRoute(): string
    {
        return match ((int) $this->role_id) {
            Role::HR => 'hr.dashboard',
            Role::SUPERVISOR => 'supervisor.leave.index',
            default => 'employee.dashboard',
        };
    }

    public function roleLabel(): string
    {
        return match ((int) $this->role_id) {
            Role::HR => 'HR',
            Role::SUPERVISOR => 'Supervisor',
            default => 'Employee',
        };
    }

    /** @return array{url: ?string, initials: string} */
    public function avatarData(): array
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = $words ? mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '') : '?';
        if ($this->employee) {
            $initials = mb_substr($this->employee->first_name, 0, 1).mb_substr($this->employee->last_name, 0, 1);
        }

        return ['url' => $this->profile_photo_path ? (auth()->id() !== $this->id && $this->employee ? route('employee.profile.photo', [$this->employee, hash('sha256', $this->profile_photo_path)]) : route('profile.photo', hash('sha256', $this->profile_photo_path))) : null, 'initials' => mb_strtoupper($initials)];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function supervisorDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'supervisor_department_id');
    }

    public function canAuthenticate(): bool
    {
        return in_array((int) $this->role_id, [Role::HR, Role::EMPLOYEE, Role::SUPERVISOR], true) && $this->account_status === 'active' && (! $this->employee || $this->employee->employment_status === 'active');
    }

    public function sendPasswordResetNotification($token): void
    {
        if ($this->canAuthenticate()) {
            $this->notify(new ResetPassword($token));
        }
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'role_id' => 'integer',
            'session_version' => 'integer',
        ];
    }
}
