<?php

namespace App\Support\Api;

use App\Models\Accountant;
use App\Models\Employee;

final class PeopleResource
{
    public static function employee(Employee $employee, bool $includeContact): array
    {
        $data = [
            'id' => (int) $employee->id,
            'store_id' => (int) $employee->store_id,
            'name' => (string) $employee->name,
            'status' => (string) ($employee->status ?? 'active'),
            'has_login_account' => $employee->activeAccountant !== null,
            'created_at' => $employee->created_at?->toIso8601String(),
            'updated_at' => $employee->updated_at?->toIso8601String(),
        ];

        if ($includeContact) {
            $data['phone'] = $employee->phone;
        }

        return $data;
    }

    public static function accountant(Accountant $accountant, bool $includeContact): array
    {
        $data = [
            'id' => (int) $accountant->id,
            'store_id' => $accountant->store_id !== null ? (int) $accountant->store_id : null,
            'employee_id' => $accountant->employee_id !== null ? (int) $accountant->employee_id : null,
            'name' => (string) $accountant->name,
            'status' => (string) $accountant->status,
            'created_at' => $accountant->created_at?->toIso8601String(),
            'updated_at' => $accountant->updated_at?->toIso8601String(),
        ];

        if ($includeContact) {
            $data['email'] = $accountant->email;
            $data['phone'] = $accountant->phone;
        }

        return $data;
    }
}
