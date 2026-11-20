<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PrivacyNotice extends Model
{
    use HasFactory;

    protected $fillable = ['version', 'title', 'content', 'contact_email', 'requires_acknowledgement', 'effective_at'];

    protected static function booted(): void
    {
        static::updating(function (self $notice): void {
            if ($notice->getOriginal('published_at') !== null && array_diff(array_keys($notice->getDirty()), ['archived_at', 'updated_at'])) {
                throw new LogicException('Published privacy notice content is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'effective_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'requires_acknowledgement' => 'boolean',
        ];
    }

    public static function current(bool $lock = false): ?self
    {
        if (! config('privacy.enabled')) {
            return null;
        }

        return static::query()
            ->whereNotNull('published_at')
            ->whereNull('archived_at')
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    public static function pendingFor(User $user): bool
    {
        $notice = static::current();

        return $notice?->requires_acknowledgement && ! $notice->acknowledgedBy($user);
    }

    public function draftFingerprint(): string
    {
        return hash('sha256', serialize([
            $this->version,
            $this->title,
            $this->content,
            $this->contact_email,
            $this->requires_acknowledgement,
            $this->effective_at->toIso8601String(),
        ]));
    }

    /** @param Builder<PrivacyNotice> $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('effective_at', '<=', now());
    }

    /** @return HasMany<PrivacyNoticeAcknowledgement, $this> */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(PrivacyNoticeAcknowledgement::class);
    }

    public function acknowledgedBy(User $user): bool
    {
        return $this->acknowledgements()->whereBelongsTo($user)->exists();
    }
}
