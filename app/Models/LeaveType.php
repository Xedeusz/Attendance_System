<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'requires_document', 'affects_balance', 'allows_half_day', 'is_active', 'duration_policy'];

    protected function casts(): array
    {
        return ['requires_document' => 'boolean', 'affects_balance' => 'boolean', 'allows_half_day' => 'boolean', 'is_active' => 'boolean'];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class)->orderByDesc('effective_from');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    /** @return Collection<int, LeaveType> */
    public static function applicationTypes(): Collection
    {
        return self::query()
            ->with('policies')
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (LeaveType $type): string => Str::lower(trim($type->name)))
            ->map(fn (Collection $duplicates): LeaveType => $duplicates
                ->sort(function (LeaveType $left, LeaveType $right): int {
                    $balancePriority = (int) $right->affects_balance <=> (int) $left->affects_balance;

                    return $balancePriority !== 0 ? $balancePriority : $left->id <=> $right->id;
                })
                ->first())
            ->sortBy(fn (LeaveType $type): string => Str::lower($type->name))
            ->values();
    }

    public function isCanonicalApplicationType(): bool
    {
        return self::applicationTypes()->contains(fn (LeaveType $type): bool => $type->is($this));
    }
}
