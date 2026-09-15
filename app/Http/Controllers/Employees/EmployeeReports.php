<?php

namespace App\Http\Controllers\Employees;

use App\Support\ArabicPdf as PDF;
use App\Models\Debt;
use App\Traits\FindPersonTrait;
use App\Services\EmployeeLogService;
use Illuminate\Support\Carbon;
use App\Models\Employee;
use App\Services\Employees\EmployeePayrollService;
use App\Services\Employees\EmployeeHistoricalStoreService;
use App\Models\EmployeeLog;
use App\Models\Store;

/**
 * --------------------------------------------------------------------------
 * EmployeeReports
 * --------------------------------------------------------------------------
 * هذا الملف مسؤول عن:
 * - تصدير تقارير PDF الخاصة بالموظف أو المحاسب
 * - يعتمد على FindPersonTrait لتحديد نوع الشخص (موظف / محاسب)
 * --------------------------------------------------------------------------
 */
class EmployeeReports
{
    use FindPersonTrait;

    /**
     * ----------------------------------------------------------------------
     * تصدير تقرير PDF باستخدام mPDF الداعم للعربية
     * ----------------------------------------------------------------------
     * - يقوم بجلب بيانات الشخص (موظف أو محاسب)
     * - يجمع جميع العمليات المرتبطة به
     * - ينشئ ملف PDF جاهز للتحميل
     * ----------------------------------------------------------------------
     */
    public static function exportPdf($id)
    {
        // إنشاء نسخة من الكلاس لاستخدام الـ trait
        $self = new self();

        // جلب الموظف أو المحاسب
        $person = $self->findPerson($id);

        $person->loadMissing(['store.user', 'accountant']);

        // الشهر المستهدف يأتي من فلتر صفحة العمليات، مع fallback محافظ للسلوك القديم.
        $requestedMonth = request()->query('month');
        $reportMonth = preg_match('/^\d{4}-\d{2}$/', (string) $requestedMonth) === 1
            ? Carbon::createFromFormat('Y-m-d', $requestedMonth . '-01')
            : (Carbon::now()->day <= 3 ? Carbon::now()->subMonthNoOverflow() : Carbon::now());
        $reportMonthKey = $reportMonth->format('Y-m');
        $monthStart = $reportMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $reportMonth->copy()->endOfMonth()->toDateString();
        $periodStart = Carbon::parse($monthStart)->startOfDay();
        $periodEnd = Carbon::parse($monthEnd)->endOfDay();

        // حسابات المديونية
        $remainingDebt = $person->debts()->sum('amount');

        // نعتمد على تاريخ العملية/الشفت، وليس تاريخ الإدخال، مع fallback موحد للسجلات القديمة.
        $debtOperations = $person->debts()
            ->with(['addedBy', 'store'])
            ->betweenOperationDates($monthStart, $monthEnd)
            ->orderBy('date')
            ->get();

        $collectedThisMonth = $debtOperations->where('amount', '<', 0)->sum('amount');
        $addedThisMonth = $debtOperations->where('amount', '>', 0)->sum('amount');

        // تقرير الموظف يعرض عمليات الشهر كاملة عبر كل المتاجر المرتبطة بالموظف، حتى لو تم نقله أثناء الشهر.
        $withdrawals = $person->withdrawals()
            ->with(['addedBy', 'store'])
            ->betweenAccountingDates($periodStart, $periodEnd)
            ->orderBy('business_date')
            ->orderBy('date')
            ->get();

        $absences = $person->absences()
            ->with(['addedBy', 'store'])
            ->betweenOperationDates($monthStart, $monthEnd)
            ->orderBy('date')
            ->get();

        $payrollService = app(EmployeePayrollService::class);
        $historicalSalary = $person instanceof Employee
            ? $payrollService->salaryAtPeriodEnd($person, $periodEnd)
            : (float) ($person->salary ?? 0);
        if ($person instanceof Employee) {
            $salaryInfo = $payrollService->salaryInfoWithSalaryChanges($person, $periodStart, $periodEnd);
        } else {
            $salaryInfo = [
                'payable_salary' => $historicalSalary,
                'worked_days' => $periodStart->daysInMonth,
                'suspended_days' => 0,
            ];
        }
        $absencePenalty = ($historicalSalary / max(1, $periodStart->daysInMonth)) * $absences->count();
        $salaryNet = max(0, (float) $salaryInfo['payable_salary'] - (float) $withdrawals->sum('amount') - $absencePenalty);

        $creditSalesPending = $person->creditSales()
            ->with(['addedBy', 'store'])
            ->betweenOperationDates($monthStart, $monthEnd)
            ->where('status', 'pending')
            ->orderBy('date')
            ->get();

        $creditSalesCollected = $person->creditSales()
            ->where('status', 'deducted')
            ->where(function ($query) use ($monthStart, $monthEnd, $reportMonthKey) {
                $query->where('deducted_month', $reportMonthKey)
                    ->orWhereBetween('date', [$monthStart, $monthEnd]);
            })
            ->with(['addedBy', 'store'])
            ->orderBy('date')
            ->get();

        // سجل العمل الكامل لا يقيد بشهر التقرير؛ أما الأرقام المالية أعلاه فتبقى للشهر المختار فقط.
        $transfers = EmployeeLog::withTrashed()
            ->where('person_id', $person->id)
            ->where('person_type', get_class($person))
            ->where('action_name', 'employee_transferred')
            ->orderBy('created_at')
            ->get();
        $transferStoreIds = $transfers->flatMap(fn (EmployeeLog $transfer) => [
            (int) data_get($transfer->meta, 'old_store_id'),
            (int) data_get($transfer->meta, 'new_store_id'),
        ])->filter()->unique();
        $transferStoreNames = Store::withTrashed()->whereIn('id', $transferStoreIds)->pluck('name', 'id');
        $transfers->each(function (EmployeeLog $transfer) use ($transferStoreNames): void {
                $names = $transferStoreNames;
                $transfer->setAttribute('old_store_name', $names[(int) data_get($transfer->meta, 'old_store_id')] ?? '—');
                $transfer->setAttribute('new_store_name', $names[(int) data_get($transfer->meta, 'new_store_id')] ?? '—');
            });

        $assignmentSegments = collect();
        if ($person instanceof Employee) {
            $assignmentSegments = app(EmployeeHistoricalStoreService::class)
                ->assignmentSegmentsForEmployee($person, $periodStart, $periodEnd);
            $segmentStoreNames = Store::withTrashed()
                ->whereIn('id', $assignmentSegments->pluck('store_id')->filter()->unique())
                ->pluck('name', 'id');
            $assignmentSegments = $assignmentSegments->map(function (array $segment) use ($segmentStoreNames): array {
                $start = Carbon::parse($segment['start'])->startOfDay();
                $end = Carbon::parse($segment['end'])->startOfDay();

                return $segment + [
                    'store_name' => $segmentStoreNames[(int) $segment['store_id']] ?? 'متجر محذوف',
                    'days' => $start->diffInDays($end) + 1,
                ];
            });
        }

        $emptySections = collect([
            'السحوبات' => $withdrawals->isEmpty(),
            'الغيابات' => $absences->isEmpty(),
            'المديونيات والتحصيلات' => $debtOperations->isEmpty(),
            'البيع الآجل غير المحصل' => $creditSalesPending->isEmpty(),
            'البيع الآجل المحصل' => $creditSalesCollected->isEmpty(),
            'سجل النقل بين المتاجر' => $transfers->isEmpty(),
        ])->filter()->keys()->values();

        // تجهيز البيانات للعرض داخل الـ PDF
        $data = [
            'person'               => $person,
            'report_month'         => $reportMonthKey,
            'withdrawals'          => $withdrawals,
            'withdrawals_total'    => (float) $withdrawals->sum('amount'),
            'absences'             => $absences,
            'absences_count'       => $absences->count(),
            'absence_penalty'      => $absencePenalty,
            'salary_payable'       => (float) $salaryInfo['payable_salary'],
            'historical_salary'    => $historicalSalary,
            'salary_worked_days'   => (int) ($salaryInfo['worked_days'] ?? $periodStart->daysInMonth),
            'salary_total_days'    => (int) $periodStart->daysInMonth,
            'salary_net'           => $salaryNet,
            'debts'                => $debtOperations, // العمليات كاملة
            'remainingDebt'        => $remainingDebt,  // الرصيد النهائي
            'addedThisMonth'       => $addedThisMonth, // مجموع الإضافات
            'collectedThisMonth'   => abs($collectedThisMonth), // التحصيل الشهري (موجب)
            'creditSalesPending'   => $creditSalesPending,
            'creditSalesCollected' => $creditSalesCollected,
            'transfers'             => $transfers,
            'assignmentSegments'    => $assignmentSegments,
            'creditPendingTotal'    => (float) $creditSalesPending->sum('remaining_amount'),
            'emptySections'        => $emptySections,
            'created_by'           => auth()->user(),
        ];

        // تسجيل عملية التصدير
        EmployeeLogService::add(
            $person,
            'report_exported',
            "تم تصدير تقرير PDF للموظف/المحاسب {$person->name} لشهر {$reportMonthKey}"
        );

        // إنشاء ملف PDF
        $pdf = PDF::loadView('pdf.employee-pdf', $data)
            ->setPaper('a4')
            ->setOption('encoding', 'UTF-8');

        // قرار مالي مثبت: تصدير PDF لا يحذف ولا يصفر أي عملية.
        // التقرير أصبح لقطة قراءة فقط؛ التحصيل أو التسوية تتم من شاشات العمليات المخصصة.
        session()->flash(
            'success',
            "تم تصدير التقرير بنجاح دون حذف أو تصفير أي بيانات للشهر {$reportMonthKey}."
        );

        return $pdf->download("تقرير {$reportMonthKey} - {$person->name}.pdf");
    }

    /**
     * 2026-05-16: دالة توافق مؤقتة لأي استدعاء قديم ما زال يستخدم اسم exportSnappy.
     */
    public static function exportSnappy($id)
    {
        return self::exportPdf($id);
    }
}
