<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttendanceHoliday extends Model
{
    use HasFactory;

    public const TYPES = [
        'regular' => 'Regular holiday',
        'special_non_working' => 'Special non-working day',
        'special_working' => 'Special working day',
        'local' => 'Local holiday',
        'company' => 'Company-declared holiday',
    ];

    public const STATUSES = ['active' => 'Active', 'cancelled' => 'Cancelled', 'superseded' => 'Superseded'];

    protected $fillable = ['holiday_import_id', 'updated_by', 'created_by', 'name', 'holiday_date', 'type', 'status', 'work_expected', 'description', 'declaration', 'proclamation_number', 'source_url', 'scope', 'locations', 'identity_hash'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date', 'work_expected' => 'boolean', 'locations' => 'array'];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function appliesTo(?int $branchId, ?string $location): bool
    {
        return match ($this->scope) {
            'company' => true,
            'branches' => $branchId !== null && $this->branches->contains('id', $branchId),
            'locations' => $location !== null && $this->coversLocation($location),
        };
    }

    private function coversLocation(string $location): bool
    {
        $coverage = $this->locations ?? [];
        [, $province] = explode(' | ', $location, 2) + [1 => ''];

        return in_array($location, $coverage, true) || ($province !== '' && in_array('province:'.$province, $coverage, true));
    }

    public function coverageLabel(): string
    {
        return match ($this->scope) {
            'company' => 'Company-wide',
            'branches' => $this->branches->pluck('branch_name')->join(', '),
            'locations' => collect($this->locations)->map(fn (string $location): string => mb_convert_case(str_replace(['province:', ' | '], ['Province: ', ', '], $location), MB_CASE_TITLE))->join('; '),
        };
    }

    public function snapshot(): array
    {
        return [...$this->only(['id', 'name', 'type', 'work_expected', 'declaration', 'proclamation_number', 'source_url']), 'holiday_date' => $this->holiday_date->toDateString()];
    }
}
