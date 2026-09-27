<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Cairo', 'DejaVu Sans', Arial, sans-serif; direction: rtl; font-size: 12px; color: #172033; margin: 0; padding: 10px; }
        .header { border: 1px solid #d7deea; border-radius: 8px; padding: 10px; background: #f8fafc; margin-bottom: 10px; }
        .brand { font-size: 20px; font-weight: 800; color: #0f766e; }
        .title { font-size: 16px; font-weight: 800; margin: 5px 0; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .meta td { padding: 5px; border: 1px solid #e5e7eb; }
        .summary { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
        .summary td { border: 1px solid #d8dee9; padding: 8px; text-align: center; }
        .summary strong { color: #0f766e; font-size: 14px; }
        .section-title { margin: 14px 0 8px; padding: 8px 10px; border-right: 4px solid #0f766e; background: #f1f5f9; font-size: 14px; font-weight: 800; }
        .transfer { border: 1px solid #d7deea; border-radius: 8px; margin-bottom: 12px; padding: 9px; page-break-inside: avoid; }
        .transfer-title { color: #0f766e; font-size: 13px; font-weight: 800; margin-bottom: 5px; }
        .details { margin: 4px 0; line-height: 1.7; }
        .danger { color: #b91c1c; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 7px; }
        table.items th { background: #0f766e; color: #fff; padding: 8px; border: 1px solid #0f766e; font-size: 11px; text-align: center; }
        table.items td { padding: 8px; border: 1px solid #d8dee9; text-align: center; font-size: 11px; }
        .text-right { text-align: right; }
        .document-notes { margin-top: 20px; }
        .document-alert { margin-bottom: 15px; padding: 12px 15px; border: 1px solid #ef4444; border-radius: 8px; background: #fef2f2; }
        .document-alert-title { margin: 0 0 8px; color: #b91c1c; font-size: 13px; font-weight: bold; }
        .document-alert-list { margin: 0; padding-right: 20px; color: #7f1d1d; font-size: 11px; line-height: 1.8; }
        .document-help { padding: 12px 15px; border: 1px solid #22c55e; border-radius: 8px; background: #f0fdf4; }
        .document-help-title { margin: 0 0 8px; color: #15803d; font-size: 13px; font-weight: bold; }
        .document-help-list { margin: 0; padding-right: 20px; color: #166534; font-size: 11px; line-height: 1.8; }
        .signatures { margin-top: 20px; width: 100%; }
        .signatures td { width: 50%; padding: 10px; text-align: center; }
        .footer { margin-top: 20px; text-align: center; color: #64748b; font-size: 10px; }
    </style>
</head>
<body>
@php
    $statusLabels = ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'];
    $formatQuantity = fn ($item) => \App\Support\ProductQuantityFormatter::transferQuantity($item->senderProduct, (float) $item->requested_quantity, (string) $item->unit_type);
    $formatDate = fn ($date) => $date?->format('d-m-Y') ?: 'غير مسجل';
    $groups = [
        'outgoing' => ['title' => 'الطلبات الصادرة', 'items' => $transfers->filter(fn ($transfer) => $transfer->status !== 'rejected' && (int) $transfer->sender_store_id === (int) $store->id)],
        'incoming' => ['title' => 'الطلبات الواردة', 'items' => $transfers->filter(fn ($transfer) => $transfer->status !== 'rejected' && (int) $transfer->receiver_store_id === (int) $store->id)],
        'rejected' => ['title' => 'الطلبات المرفوضة', 'items' => $transfers->where('status', 'rejected')],
    ];
@endphp
<div class="header">
    <div class="brand">CARLED</div>
    <div class="title">تقرير النقل المخزني</div>
    <table class="meta">
        <tr><td>المتجر: {{ $store->name }}</td><td>من: {{ \Carbon\Carbon::parse($filters['from'])->format('d-m-Y') }}</td><td>إلى: {{ \Carbon\Carbon::parse($filters['to'])->format('d-m-Y') }}</td></tr>
    </table>
</div>
<table class="summary"><tr><td>الإجمالي<br><strong>{{ $summary['total'] }}</strong></td><td>الصادر<br><strong>{{ $summary['outgoing'] }}</strong></td><td>الوارد<br><strong>{{ $summary['incoming'] }}</strong></td><td>المرفوض<br><strong>{{ $summary['rejected'] }}</strong></td></tr></table>

@if($transfers->isEmpty())
    <p>لا توجد عمليات نقل ضمن الفترة المحددة.</p>
@else
@foreach($groups as $group)
    @if($group['items']->isNotEmpty())
        <div class="section-title">{{ $group['title'] }} — {{ $group['items']->count() }}</div>
        @foreach($group['items'] as $transfer)
            @php
                $isOutgoing = (int) $transfer->sender_store_id === (int) $store->id;
                $otherStore = $isOutgoing ? $transfer->receiverStore : $transfer->senderStore;
                $sentDate = $transfer->request_business_date ?? $transfer->created_at;
                $receivedDate = $transfer->action_business_date ?? $transfer->completed_at ?? $transfer->acted_at;
                $notes = $transfer->status === 'rejected'
                    ? 'سبب الرفض: '.($transfer->rejection_reason ?: 'لم يسجل سبب')
                    : ($transfer->notes ?: 'لا توجد ملاحظات');
            @endphp
            <div class="transfer">
                <div class="transfer-title">{{ $transfer->id }} — {{ $otherStore?->name ?: 'متجر غير متاح' }}</div>
                <div class="details">تاريخ الإرسال: {{ $formatDate($sentDate) }} | تاريخ الاستلام: {{ $transfer->status === 'completed' ? $formatDate($receivedDate) : 'لم يستلم بعد' }}</div>
                <table class="items">
                    <thead><tr><th>المنتج</th><th>الكمية والوحدة</th><th>الملاحظات</th><th>الحالة</th></tr></thead>
                    <tbody>
                    @foreach($transfer->items as $item)
                        <tr><td class="text-right">{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }}</td><td>{{ $formatQuantity($item) }}</td><td class="text-right">{{ $notes }}</td><td>{{ $statusLabels[$transfer->status] ?? $transfer->status }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
@endforeach
@endif

<table class="summary"><tr><td>قيمة الصادرات<br><strong>{{ number_format($summary['outgoing_cost'], 2) }}</strong></td><td>قيمة الواردات<br><strong>{{ number_format($summary['incoming_cost'], 2) }}</strong></td></tr></table>

<table class="signatures"><tr><td>توقيع المالك: ..........................</td><td>توقيع مسؤول المتجر: ..........................</td></tr></table>

<div class="document-notes">
    <div class="document-alert">
        <h4 class="document-alert-title">تنبيه هام:</h4>
        <ul class="document-alert-list">
            <li>هذه الوثيقة سجل لعمليات النقل المحفوظة في النظام ويجب الحفاظ عليها.</li>
            <li>يعتمد التقرير على أيام العمل المسجلة، وليس وقت إنشاء السجل الفعلي.</li>
            <li>قيمة الصادرات والواردات محسوبة بسعر التكلفة المحفوظ وقت إنشاء طلب النقل، وليس بسعر البيع.</li>
            <li>عند عدم توفر يوم عمل مسجل، يعرض التقرير تاريخ تنفيذ العملية الفعلي كقيمة بديلة.</li>
            <li>راجع أسباب الرفض والملاحظات وربط المنتج المستلم قبل اعتماد الوثيقة.</li>
            <li>أي تعديل يدوي على النسخة المطبوعة لا يغير بيانات النظام.</li>
        </ul>
    </div>
    <div class="document-help">
        <h4 class="document-help-title">تنبيهات وتوضيح للترتيب:</h4>
        <ul class="document-help-list">
            <li>يعرض التقرير النقل الصادر أولًا، ثم النقل الوارد، ثم العمليات المرفوضة.</li>
            <li>تاريخ الإرسال والاستلام يعتمد يوم العمل المسجل، ويستخدم تاريخ العملية الفعلي فقط عند غياب يوم العمل.</li>
            <li>يعرض جدول كل طلب المنتج والكمية مع نوع الوحدة والملاحظات وحالة الطلب.</li>
            <li>تظهر الملاحظات وسبب الرفض مع الطلب المرتبط بهما.</li>
        </ul>
    </div>
</div>
<div class="footer">تم إنشاء التقرير آليًا بواسطة CARLED</div>
</body>
</html>
