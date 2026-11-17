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
#[Hidden(['password', 'remember_token'])]
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
