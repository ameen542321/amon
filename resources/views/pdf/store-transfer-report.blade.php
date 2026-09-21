<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير النقل المخزني - {{ $store->name }}</title>
    <style>
        body { font-family: 'Cairo', 'DejaVu Sans', sans-serif; direction: rtl; color: #0f172a; font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 6px; }
        .meta { margin-bottom: 14px; color: #334155; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td { border: 1px solid #cbd5e1; padding: 7px; text-align: center; }
        .transfer { border: 1px solid #cbd5e1; margin-bottom: 12px; padding: 9px; }
        .transfer-title { font-size: 13px; font-weight: bold; margin-bottom: 5px; }
        .details { margin: 4px 0; }
        .danger { color: #b91c1c; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 7px; }
        table.items th, table.items td { border: 1px solid #cbd5e1; padding: 6px; text-align: right; }
        table.items th { background: #eef3f8; }
    </style>
</head>
<body>
@php
    $statusLabels = ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'];
    $formatQuantity = fn ($item) => \App\Support\ProductQuantityFormatter::transferQuantity($item->senderProduct, (float) $item->requested_quantity, (string) $item->unit_type);
@endphp
<h1>تقرير النقل المخزني — {{ $store->name }}</h1>
<div class="meta">الفترة: {{ $filters['from'] }} إلى {{ $filters['to'] }} — الحالة: {{ $filters['status'] ? ($statusLabels[$filters['status']] ?? $filters['status']) : 'كل الحالات' }}</div>
<table class="summary"><tr><td>الإجمالي<br><strong>{{ $summary['total'] }}</strong></td><td>الصادر<br><strong>{{ $summary['outgoing'] }}</strong></td><td>الوارد<br><strong>{{ $summary['incoming'] }}</strong></td><td>المرفوض<br><strong>{{ $summary['rejected'] }}</strong></td></tr></table>

@forelse($transfers as $transfer)
    @php
        $isOutgoing = (int) $transfer->sender_store_id === (int) $store->id;
        $otherStore = $isOutgoing ? $transfer->receiverStore : $transfer->senderStore;
        $reportDate = $isOutgoing ? $transfer->request_business_date : ($transfer->action_business_date ?? $transfer->request_business_date);
    @endphp
    <div class="transfer">
        <div class="transfer-title">طلب #{{ $transfer->id }} — {{ $isOutgoing ? 'صادر إلى' : 'وارد من' }} {{ $otherStore?->name ?: 'متجر غير متاح' }} — {{ $statusLabels[$transfer->status] ?? $transfer->status }}</div>
        <div class="details">التاريخ: {{ $reportDate?->format('Y-m-d') ?: 'غير مسجل' }} | الإرسال: {{ $transfer->request_business_date?->format('Y-m-d') ?: 'غير مسجل' }} | الإجراء: {{ $transfer->action_business_date?->format('Y-m-d') ?: 'لم يعالج بعد' }}</div>
        @if($transfer->notes)<div class="details"><strong>الملاحظات:</strong> {{ $transfer->notes }}</div>@endif
        @if($transfer->status === 'rejected')<div class="details danger"><strong>سبب الرفض:</strong> {{ $transfer->rejection_reason ?: 'لم يسجل سبب' }}</div>@endif
        <table class="items">
            <thead><tr><th>المنتج</th><th>الكمية والوحدة</th><th>منتج المتجر المستلم</th></tr></thead>
            <tbody>
            @foreach($transfer->items as $item)
                <tr><td>{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }}</td><td>{{ $formatQuantity($item) }}</td><td>{{ $item->receiverProduct?->name ?: 'لم يربط' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p>لا توجد عمليات نقل ضمن الفترة المحددة.</p>
@endforelse
</body>
</html>
