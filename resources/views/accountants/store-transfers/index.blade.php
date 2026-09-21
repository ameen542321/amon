@extends('dashboard.app')
@section('title', 'النقل المخزني')
@section('content')
@php
    $formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    $transferQuantity = fn ($item) => \App\Support\ProductQuantityFormatter::transferQuantity(
        $item->senderProduct,
        (float) $item->requested_quantity,
        (string) $item->unit_type
    );
@endphp
<div class="max-w-7xl mx-auto space-y-6 px-4 py-6 sm:px-6" dir="rtl" data-store-transfer-system>
    <header class="ui-card p-5 sm:p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <span class="ui-status-info-bg ui-status-info flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"><i class="fa-solid fa-right-left" aria-hidden="true"></i></span>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black ui-title">النقل المخزني</h1>
                        <x-ui.help title="النقل المخزني" body="ابدأ من الوارد الذي يحتاج إجراء، وطابق كل منتج قبل القبول، ثم تابع الطلبات الصادرة." />
                    </div>
                    <p class="ui-text-soft mt-1">إدارة الوارد والصادر حسب يوم عمل المحاسبة للمتجر.</p>
                </div>
            </div>
            <a href="{{ route('accountant.transfers.create') }}" class="ui-btn ui-btn-primary w-full md:w-auto"><i class="fa-solid fa-plus" aria-hidden="true"></i> إرسال نقل جديد</a>
        </div>
        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="ui-frame-row"><span class="ui-text-soft">وارد يحتاج إجراء</span><strong class="ui-title">{{ $incoming->total() }}</strong></div>
            <div class="ui-frame-row"><span class="ui-text-soft">صادر قيد الانتظار</span><strong class="ui-title">{{ $outgoingPending->total() }}</strong></div>
            <div class="ui-frame-row"><span class="ui-text-soft">صادر مكتمل</span><strong class="ui-status-success">{{ $outgoingCompleted->total() }}</strong></div>
        </div>
    </header>

    @php($statusLabels = ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'])
    <nav class="grid grid-cols-2 gap-2 ui-card p-3 sm:flex sm:flex-wrap" aria-label="تصفية طلبات النقل">
        <a href="{{ route('accountant.transfers.index') }}" class="ui-btn inline-flex items-center justify-center px-4 py-2 text-sm {{ empty($status) ? 'ui-btn-primary' : 'ui-btn-secondary' }}">الكل</a>
        @foreach($statusLabels as $value => $label)
            <a href="{{ route('accountant.transfers.index', ['status' => $value]) }}" class="ui-btn inline-flex items-center justify-center px-4 py-2 text-sm {{ ($status ?? null) === $value ? 'ui-btn-primary' : 'ui-btn-secondary' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($errors->any())
        <div class="rounded-xl border ui-border ui-status-danger-bg p-4 ui-status-danger">{{ $errors->first() }}</div>
    @endif

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3"><div><h2 class="text-xl font-black ui-title">الوارد بحاجة إلى إجراء</h2><p class="ui-text-soft ui-text-caption mt-1">طابق المنتجات ثم اقبل الطلب أو ارفضه مع توضيح السبب.</p></div><span class="ui-badge ui-badge-warning">{{ $incoming->total() }} طلب</span></div>
        @forelse($incoming as $transfer)
            <article class="ui-card p-5 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                    <div>
                        <p class="ui-title font-black">طلب #{{ $transfer->id }}</p>
                        <p class="ui-text-soft text-sm mt-1">من: {{ $transfer->senderStore?->name }} — الحالة: {{ ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'][$transfer->status] ?? $transfer->status }}</p>
                        <p class="ui-text-soft ui-text-caption mt-1">يوم عمل الإرسال: {{ $transfer->request_business_date?->format('Y-m-d') ?: 'غير مسجل' }}</p>
                        @if($transfer->notes)
                            <p class="ui-status-warning ui-text-caption mt-2 ui-status-warning-bg border ui-status-warning-border rounded-lg px-3 py-2">ملاحظة الطلب: {{ $transfer->notes }}</p>
                        @endif
                    </div>
                </div>

                @if($transfer->status === 'pending')
                    <form method="POST" action="{{ route('accountant.transfers.approve', $transfer->id) }}" class="space-y-4">
                        @csrf
                        <div>
                            <div class="ui-frame-row"><span class="ui-text-soft font-bold">تاريخ الاستلام والإضافة للمخزون</span><strong class="ui-title">{{ $currentBusinessDate }}</strong></div>
                        </div>
                @endif
                <div class="grid grid-cols-1 gap-4">
                    @foreach($transfer->items as $item)
                        <div class="ui-card-muted p-4 space-y-3">
                            <p class="ui-title font-bold">{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }}</p>
                            <p class="ui-text-soft ui-text-caption">الكمية: {{ $transferQuantity($item) }}</p>
                            @if($item->receiverProduct)
                                <p class="ui-status-success text-sm">تمت إضافته إلى: {{ $item->receiverProduct->name }}</p>
                            @endif

                            @if($transfer->status === 'pending')
                                    <x-store-transfers.product-picker
                                        :item="$item"
                                        label="اختر المنتج المقابل في متجرك"
                                        note="إذا لم تجد المنتج، أنشئه أولاً ثم عد للموافقة." />
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($transfer->status === 'pending')
                        <button class="ui-btn ui-btn-success w-full px-4 py-3">موافقة واستلام جميع البنود</button>
                    </form>
                    <button type="button" data-ui-show="reject-transfer-{{ $transfer->id }}" data-ui-scroll-lock class="ui-btn ui-btn-danger w-full px-4 py-3">رفض طلب النقل</button>
                    <x-store-transfers.rejection-modal
                        modal-id="reject-transfer-{{ $transfer->id }}"
                        :action="route('accountant.transfers.reject', $transfer->id)"
                        :current-business-date="$currentBusinessDate" />
                @endif
            </article>
        @empty
            <div class="ui-card p-8 text-center ui-text-soft">لا توجد بضاعة واردة.</div>
        @endforelse
        {{ $incoming->appends(['status' => $status])->links() }}
    </section>

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3"><div><h2 class="text-xl font-black ui-title">الصادر قيد الانتظار</h2><p class="ui-text-soft ui-text-caption mt-1">طلبات خُصمت من المخزون وتنتظر قرار المتجر المستلم.</p></div><span class="ui-badge ui-badge-info">{{ $outgoingPending->total() }} طلب</span></div>
        @forelse($outgoingPending as $transfer)
            <article class="ui-card p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="ui-title font-bold">طلب #{{ $transfer->id }} إلى {{ $transfer->receiverStore?->name }}</p>
                    <p class="ui-text-soft text-sm">الحالة: {{ ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'][$transfer->status] ?? $transfer->status }}</p>
                    <p class="ui-text-soft ui-text-caption">يوم عمل الإرسال: {{ $transfer->request_business_date?->format('Y-m-d') ?: 'غير مسجل' }}</p>
                </div>
                @if($transfer->status === 'pending')
                    <form method="POST" action="{{ route('accountant.transfers.cancel', $transfer->id) }}" data-ui-confirm="سيتم إلغاء النقل وإرجاع الكمية لمتجرك." data-ui-confirm-title="تأكيد إلغاء النقل">
                    @csrf
                    <span class="ui-inline-frame ui-text-caption">يوم عمل الإلغاء: {{ $currentBusinessDate }}</span>
                        <button class="ui-btn ui-btn-danger px-4 py-2">إلغاء</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="ui-card p-8 text-center ui-text-soft">لا توجد بضاعة صادرة.</div>
        @endforelse
        {{ $outgoingPending->appends(request()->except('outgoing_pending_page'))->links() }}
    </section>

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3"><div><h2 class="text-xl font-black ui-title">الصادر المكتمل</h2><p class="ui-text-soft ui-text-caption mt-1">طلبات استلمها المتجر الآخر وأضيفت إلى مخزونه.</p></div><span class="ui-badge ui-badge-success">{{ $outgoingCompleted->total() }} طلب</span></div>
        @forelse($outgoingCompleted as $transfer)
            <article class="ui-card p-5">
                <p class="ui-title font-bold">طلب #{{ $transfer->id }} إلى {{ $transfer->receiverStore?->name }}</p>
                <p class="ui-status-success ui-text-caption mt-1">مكتمل</p>
                <p class="ui-text-soft ui-text-caption mt-1">الإرسال: {{ $transfer->request_business_date?->format('Y-m-d') ?: 'غير مسجل' }} — الاستلام والإضافة: {{ $transfer->action_business_date?->format('Y-m-d') ?: 'غير مسجل' }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($transfer->items as $item)
                        <span class="ui-card-muted px-3 py-2 ui-text-caption">{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }} — {{ $transferQuantity($item) }}</span>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="ui-card p-8 text-center ui-text-soft">لا توجد طلبات صادرة مكتملة.</div>
        @endforelse
        {{ $outgoingCompleted->appends(request()->except('outgoing_completed_page'))->links() }}
    </section>
</div>
@endsection
