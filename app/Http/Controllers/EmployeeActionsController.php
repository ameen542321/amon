<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\EmployeeLogService;
use App\Domain\EmployeeOperations\Exceptions\EmployeeOperationException;
use App\Domain\EmployeeOperations\Policies\EmployeeOperationAccessPolicy;
use App\Domain\EmployeeOperations\Queries\FindEmployeeOperationPerson;
use App\Domain\EmployeeOperations\Services\EmployeeOperationService;
use App\Domain\EmployeeOperations\ViewModels\EmployeeOperationPageViewModel;

class EmployeeActionsController extends Controller
{
    /**
     * عرض صفحة العمليات
     */
    public function index($id)
    {
        $person = $this->findPerson($id);
        $this->authorizePerson($person);

        $returnTo = $this->safeReturnTo(request()->query('return_to')) ?? route('user.employees.index');

        return view('employees.actions', app(EmployeeOperationPageViewModel::class)->forPerson($person, $returnTo, request()->query('month')));
    }

    /**
     * حفظ عملية السحب
     */
    public function storeWithdrawal(Request $request, $id)
    {
        $person = $this->findPerson($id);
        $this->authorizePerson($person);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $employeeOperationService = app(EmployeeOperationService::class);

        try {
            $employeeOperationService->recordWithdrawal(
                $person,
                $validated,
                $employeeOperationService->actorFromCurrentAuth()
            );
        } catch (EmployeeOperationException $exception) {
            return back()->withErrors(['duplicate' => $exception->getMessage()]);
        }

        return back()->with('success', 'تم إضافة السحب بنجاح');
    }

    public function updateWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        $person = $this->authorizeWithdrawalAccess($withdrawal);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], [
            'amount' => 'مبلغ السحب',
            'date' => 'تاريخ السحب',
            'description' => 'وصف السحب',
        ]);

        DB::transaction(function () use ($withdrawal, $person, $validated): void {
            $lockedWithdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);
            $oldData = $lockedWithdrawal->only(['amount', 'date', 'business_date', 'description']);
            $operationDate = \Carbon\Carbon::parse($validated['date'])->toDateString();
            $previousAccountingDate = optional($lockedWithdrawal->business_date ?? $lockedWithdrawal->date)->toDateString();

            $lockedWithdrawal->update([
                'amount' => $validated['amount'],
                'date' => $operationDate,
                'business_date' => $operationDate,
                // نحافظ على ربط الإقفال عند تعديل المبلغ/الوصف فقط، ونفك الربط إذا نقل المالك العملية إلى يوم آخر.
                'daily_balance_id' => $operationDate === $previousAccountingDate ? $lockedWithdrawal->daily_balance_id : null,
                'month' => \Carbon\Carbon::parse($operationDate)->format('Y-m'),
                'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            ]);

            EmployeeLogService::add(
                $person,
                'withdrawal_owner_updated',
                'عدّل المالك السحب رقم #'.$lockedWithdrawal->id.' إلى '.number_format((float) $validated['amount'], 2).' ريال.',
                $validated['amount'],
                [
                    'withdrawal_id' => $lockedWithdrawal->id,
                    'old_data' => $oldData,
                    'new_data' => $lockedWithdrawal->only(['amount', 'date', 'business_date', 'description']),
                    'operation_date' => $operationDate,
                ]
            );
        });

        return back()->with('success', 'تم تعديل السحب بنجاح وتسجيل التغيير في سجل الموظف.');
    }

    public function destroyWithdrawal(Withdrawal $withdrawal)
    {
        $person = $this->authorizeWithdrawalAccess($withdrawal);

        DB::transaction(function () use ($withdrawal, $person): void {
            $lockedWithdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            EmployeeLogService::add(
                $person,
                'withdrawal_owner_deleted',
                'حذف المالك السحب رقم #'.$lockedWithdrawal->id.' بقيمة '.number_format((float) $lockedWithdrawal->amount, 2).' ريال.',
                $lockedWithdrawal->amount,
                [
                    'withdrawal_id' => $lockedWithdrawal->id,
                    'deleted_data' => $lockedWithdrawal->only(['amount', 'date', 'business_date', 'description']),
                    'operation_date' => optional($lockedWithdrawal->business_date ?? $lockedWithdrawal->date)->toDateString(),
                ]
            );

            $lockedWithdrawal->delete();
        });

        return back()->with('success', 'تم حذف السحب وتسجيل الحذف في سجل الموظف.');
    }


    /**
     * حفظ عملية الغياب
     */
    public function storeAbsence(Request $request, $id)
    {
        $person = $this->findPerson($id);
        $this->authorizePerson($person);

        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $employeeOperationService = app(EmployeeOperationService::class);

        try {
            $employeeOperationService->recordAbsence(
                $person,
                $validated,
                $employeeOperationService->actorFromCurrentAuth(),
                ['notify_store_owner' => auth('accountant')->check()]
            );
        } catch (EmployeeOperationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تسجيل الغياب بنجاح');
    }

    /**
     * حفظ المديونية
     */
public function storeDebt(Request $request, $id)
{
    $person = $this->findPerson($id);
    $this->authorizePerson($person);

    $validated = $request->validate([
        'amount'      => 'required|numeric|min:0.01',
        'description' => 'nullable|string|max:255',
        'date'        => 'required|date',
    ]);

    $employeeOperationService = app(EmployeeOperationService::class);

    try {
        $employeeOperationService->recordDebt(
            $person,
            $validated,
            $employeeOperationService->actorFromCurrentAuth(),
            ['notify_store_owner' => auth('accountant')->check()]
        );
    } catch (EmployeeOperationException $exception) {
        return back()->withErrors(['duplicate' => $exception->getMessage()]);
    }

    return back()->with('success', 'تم إضافة المديونية بنجاح');
}


public function collectPartial(Request $request, $debtId)
{
    $validated = $request->validate([
        'amount' => ['required', 'numeric', 'gt:0'],
        'payment_method' => ['nullable', 'in:cash,card,mixed'],
        'cash_amount' => ['nullable', 'numeric', 'min:0'],
        'card_amount' => ['nullable', 'numeric', 'min:0'],
    ]);

    $debt = Debt::findOrFail($debtId);
    $person = $this->authorizeDebtAccess($debt);

    try {
        app(EmployeeOperationService::class)->collectDebt(
            $debt,
            (float) $validated['amount'],
            app(EmployeeOperationService::class)->actorFromCurrentAuth(),
            [
                'use_shift_gap_date' => true,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'cash_amount' => (float) ($validated['cash_amount'] ?? 0),
                'card_amount' => (float) ($validated['card_amount'] ?? 0),
            ]
        );
    } catch (EmployeeOperationException $exception) {
        return back()->with('error', $exception->getMessage());
    }

    return back()->with('success', 'تم التحصيل الجزئي بنجاح');
}

public function collectFull(Request $request, $debtId)
{
    $validated = $request->validate([
        'payment_method' => ['nullable', 'in:cash,card,mixed'],
        'cash_amount' => ['nullable', 'numeric', 'min:0'],
        'card_amount' => ['nullable', 'numeric', 'min:0'],
    ]);

    $debt = Debt::findOrFail($debtId);
    $this->authorizeDebtAccess($debt);

    if ($debt->amount <= 0) {
        return back()->with('error', 'لا توجد مديونية لتسديدها.');
    }

    try {
        app(EmployeeOperationService::class)->collectDebt(
            $debt,
            (float) $debt->amount,
            app(EmployeeOperationService::class)->actorFromCurrentAuth(),
            [
                'full' => true,
                'use_shift_gap_date' => true,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'cash_amount' => (float) ($validated['cash_amount'] ?? 0),
                'card_amount' => (float) ($validated['card_amount'] ?? 0),
            ]
        );
    } catch (EmployeeOperationException $exception) {
        return back()->with('error', $exception->getMessage());
    }

    return back()->with('success', 'تم التحصيل الكامل بنجاح');
}

public function updateDebt(Request $request, $debtId)
{
    $debt = Debt::findOrFail($debtId);
    $person = $this->authorizeDebtAccess($debt);

    if ((float) $debt->amount <= 0 || $debt->status === Debt::STATUS_DEDUCTED || $debt->collections()->exists()) {
        return back()->with('error', 'يمكن تعديل المديونية الأصلية القائمة فقط قبل تسجيل أي تحصيل عليها.');
    }

    $validated = $request->validate([
        'amount' => 'required|numeric|min:0.01',
        'description' => 'nullable|string|max:255',
        'date' => 'required|date',
    ]);

    $debt->update([
        'amount' => $validated['amount'],
        'description' => trim((string) ($validated['description'] ?? '')) !== '' ? trim((string) $validated['description']) : null,
        'date' => $validated['date'],
        'month' => \Carbon\Carbon::parse($validated['date'])->format('Y-m'),
    ]);

    \App\Services\EmployeeLogService::add(
        $person,
        'debt_updated',
        'تعديل مديونية إلى ' . $validated['amount'] . ' ريال',
        $validated['amount'],
        [
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'operation_date' => $validated['date'],
        ]
    );

    return back()->with('success', 'تم تعديل المديونية بنجاح');
}


    private function authorizeDebtAccess(Debt $debt)
    {
        $person = $debt->person;

        if (!$person) {
            abort(404, 'لم يتم العثور على صاحب المديونية');
        }

        $this->authorizePerson($person);

        return $person;
    }

    private function authorizeWithdrawalAccess(Withdrawal $withdrawal)
    {
        $person = $withdrawal->person;

        if (! $person) {
            abort(404, 'لم يتم العثور على صاحب السحب');
        }

        $this->authorizePerson($person);

        return $person;
    }

    private function safeReturnTo(?string $returnTo): ?string
    {
        if (!$returnTo) {
            return null;
        }

        if (str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $targetHost = parse_url($returnTo, PHP_URL_HOST);

        return $targetHost && $targetHost === $appHost ? $returnTo : null;
    }

    /**
     * إيجاد موظف أو محاسب ضمن نطاق عمليات الموظفين.
     */
    private function findPerson($id)
    {
        return app(FindEmployeeOperationPerson::class)->findOrFail($id);
    }

    /**
     * حماية المستخدم حسب المتجر من خلال سياسة نطاق عمليات الموظفين.
     */
    private function authorizePerson($person): void
    {
        app(EmployeeOperationAccessPolicy::class)->authorizePerson($person);
    }
}
