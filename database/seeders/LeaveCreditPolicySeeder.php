<?php

namespace Database\Seeders;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveCreditPolicySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definitions = [
            ['code' => 'VACATION', 'name' => 'Vacation Leave', 'days' => 15, 'basis' => 'annual', 'day_basis' => 'working_days', 'document' => false, 'half_day' => true],
            ['code' => 'SICK', 'name' => 'Sick Leave', 'days' => 15, 'basis' => 'annual', 'day_basis' => 'working_days', 'document' => false, 'half_day' => true],
            ['code' => 'BIRTHDAY', 'name' => 'Birthday Leave', 'days' => 1, 'basis' => 'annual', 'day_basis' => 'working_days', 'document' => false, 'half_day' => false],
            ['code' => 'PATERNITY', 'name' => 'Paternity Leave', 'days' => 7, 'basis' => 'event', 'day_basis' => 'calendar_days', 'document' => true, 'half_day' => false],
            ['code' => 'MATERNITY', 'name' => 'Maternity Leave', 'days' => 105, 'basis' => 'event', 'day_basis' => 'calendar_days', 'document' => true, 'half_day' => false],
        ];

        foreach ($definitions as $definition) {
            $type = LeaveType::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['basis'] === 'event'
                        ? 'Event-based entitlement verified and granted by HR under the effective policy.'
                        : 'Annual leave-credit entitlement under the effective company policy.',
                    'requires_document' => $definition['document'],
                    'affects_balance' => true,
                    'allows_half_day' => $definition['half_day'],
                    'is_active' => true,
                    'duration_policy' => $definition['day_basis'] === 'working_days' ? 'exclude_nonworking_holidays' : 'requested_days',
                ],
            );

            LeavePolicy::updateOrCreate(
                ['leave_type_id' => $type->id, 'effective_from' => '2026-01-01'],
                [
                    'effective_until' => null,
                    'allocation_basis' => $definition['basis'],
                    'default_days' => $definition['days'],
                    'day_basis' => $definition['day_basis'],
                    'carryover_limit' => 0,
                    'proration_rule' => 'none',
                    'eligibility_rules' => $definition['code'] === 'MATERNITY'
                        ? ['standard_days' => 105, 'solo_parent_days' => 120, 'miscarriage_or_emergency_termination_days' => 60]
                        : null,
                    'is_active' => true,
                ],
            );
        }
    }
}
