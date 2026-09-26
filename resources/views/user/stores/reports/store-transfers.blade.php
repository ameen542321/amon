@extends('dashboard.app')

@section('title', 'تقرير النقل المخزني - ' . $store->name)

@section('content')
@php
    $statusLabels = ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'];
    $statusVariants = ['pending' => 'warning', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'danger'];
    $formatQuantity = fn ($item) => \App\Support\ProductQuantityFormatter::transferQuantity(
        $item->senderProduct,
        (float) $item->requested_quantity,
        (string) $item->unit_type
    );
@endphp
<div class="max-w-7xl mx-auto px-4 py-6 space-y-5 text-right" dir="rtl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="ui-title text-2xl font-bold">تقرير النقل المخزني</h1>
                <x-ui.help title="تقرير النقل المخزني" body="يعرض الصادر من هذا المتجر والوارد إليه حسب أيام العمل المحددة، مع المتجر المقابل والمنتجات والملاحظات وسبب الرفض." />
            </div>
            <p class="ui-text-soft mt-1">{{ $store->name }}</p>
        </div>
        <a href="{{ route('user.stores.reports.index', $store) }}" class="ui-btn ui-btn-secondary"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع للتقارير</a>
    </div>

    @if($errors->any())
        <div class="ui-alert ui-alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('user.stores.reports.store-transfers', $store) }}" class="ui-card p-4 grid grid-cols-1 gap-3 md:grid-cols-4 md:items-end">
        <label>
            <span class="ui-label">من</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="ui-input" required>
        </label>
        <label>
            <span class="ui-label">إلى</span>
            <input type="date" name="to" value="{{ $filters['to'] }}" min="{{ $filters['from'] }}" class="ui-input" required>
        </label>
        <label>
            <span class="ui-label">الحالة</span>
            <select name="status" class="ui-input">
                <option value="">كل الحالات</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <button class="ui-btn ui-btn-primary flex-1">عرض التقرير</button>
            <a href="{{ route('user.stores.reports.store-transfers', $store) }}" class="ui-btn ui-btn-secondary">إعادة</a>
        </div>
    </form>

    <div class="flex justify-end">
        <a href="{{ route('user.stores.reports.store-transfers.pdf', ['store' => $store, 'from' => $filters['from'], 'to' => $filters['to'], 'status' => $filters['status']]) }}" class="ui-btn ui-btn-info">
            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> تحميل PDF
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="ui-card p-4"><span class="ui-text-soft">إجمالي النقل</span><strong class="block ui-title text-xl mt-1">{{ $summary['total'] }}</strong></div>
        <div class="ui-card p-4"><span class="ui-text-soft">الصادر</span><strong class="block ui-title text-xl mt-1">{{ $summary['outgoing'] }}</strong></div>
        <div class="ui-card p-4"><span class="ui-text-soft">الوارد</span><strong class="block ui-title text-xl mt-1">{{ $summary['incoming'] }}</strong></div>
        <div class="ui-card p-4"><span class="ui-text-soft">المرفوض</span><strong class="block ui-status-danger text-xl mt-1">{{ $summary['rejected'] }}</strong></div>
    </div>

    <div class="space-y-4">
        @forelse($transfers as $transfer)
            @php
                $isOutgoing = (int) $transfer->sender_store_id === (int) $store->id;
                $otherStore = $isOutgoing ? $transfer->receiverStore : $transfer->senderStore;
                $reportDate = $isOutgoing
                    ? $transfer->request_business_date
                    : ($transfer->action_business_date ?? $transfer->request_business_date);
            @endphp
            <article class="ui-card p-5 space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="ui-title font-bold">طلب #{{ $transfer->id }}</h2>
                            <x-ui.badge :variant="$isOutgoing ? 'info' : 'success'">{{ $isOutgoing ? 'صادر' : 'وارد' }}</x-ui.badge>
                            <x-ui.badge :variant="$statusVariants[$transfer->status] ?? 'info'">{{ $statusLabels[$transfer->status] ?? $transfer->status }}</x-ui.badge>
                        </div>
                        <p class="ui-text-soft mt-2">{{ $isOutgoing ? 'مرسل إلى' : 'وارد من' }}: <strong class="ui-title">{{ $otherStore?->name ?: 'متجر غير متاح' }}</strong></p>
                    </div>
                    <div class="ui-frame-row">
                        <span class="ui-text-soft">التاريخ</span>
                        <strong class="ui-title">{{ $reportDate?->format('Y-m-d') ?: 'غير مسجل' }}</strong>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="ui-card-muted p-3">
                        <span class="ui-text-caption ui-text-soft">يوم عمل الإرسال</span>
                        <strong class="block ui-title mt-1">{{ $transfer->request_business_date?->format('Y-m-d') ?: 'غير مسجل' }}</strong>
                    </div>
                    <div class="ui-card-muted p-3">
                        <span class="ui-text-caption ui-text-soft">يوم عمل الإجراء</span>
                        <strong class="block ui-title mt-1">{{ $transfer->action_business_date?->format('Y-m-d') ?: 'لم يعالج بعد' }}</strong>
                    </div>
                </div>

                @if($transfer->notes)
                    <div class="ui-alert ui-alert-info"><strong>الملاحظات:</strong> {{ $transfer->notes }}</div>
                @endif
                @if($transfer->status === 'rejected')
                    <div class="ui-alert ui-alert-danger"><strong>سبب الرفض:</strong> {{ $transfer->rejection_reason ?: 'لم يسجل سبب' }}</div>
                @endif

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>المنتج</th><th>الكمية</th><th>منتج المتجر المستلم</th></tr></thead>
                        <tbody>
                            @foreach($transfer->items as $item)
                                <tr>
                                    <td>{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }}</td>
                                    <td>{{ $formatQuantity($item) }}</td>
                                    <td>{{ $item->receiverProduct?->name ?: ($transfer->status === 'completed' ? 'منتج غير متاح' : 'لم يربط بعد') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="ui-empty-state">لا توجد عمليات نقل ضمن الفترة والحالة المحددتين.</div>
        @endforelse
    </div>

    {{ $transfers->links() }}
</div>
@endsection
