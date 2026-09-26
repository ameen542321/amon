@extends('dashboard.app')
@section('title', 'إدخال الجرد')
@section('content')
@php
    // تتغير البصمة بعد أي حفظ ناجح في الخادم، فلا تعيد مسودة المتصفح القديمة فوق البيانات الأحدث.
    $browserDraftVersion = hash('sha256', $session->items->map(fn ($item) => [
        $item->id,
        $item->accountant_quantity,
        $item->unit_type,
        $item->accountant_note,
        $item->accountant_updated_at?->format('Y-m-d H:i:s.u'),
    ])->toJson());
@endphp
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <h1 class="ui-title text-2xl font-bold">{{ $session->referenceCode() }}</h1>
            <x-ui.help title="إدخال نتيجة الجرد" body="عدّ كل منتج فعليًا ثم أدخل الكمية والوحدة. يسجل النظام اليوم ووقت التعديل تلقائيًا، ولا يعرض كمية النظام أثناء العد." />
        </div>
        <a class="ui-btn ui-btn-secondary" href="{{ route('accountant.inventory-counts.index') }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    @if(session('success'))<div class="ui-alert ui-alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="ui-alert ui-alert-danger" role="alert"><strong>تعذر إكمال العملية:</strong> {{ $errors->first() }}</div>@endif
    <div class="flex items-center gap-2">
        <x-ui.badge variant="info"><span data-inventory-count-draft-status>تُحفظ مسودة الجرد محليًا في هذا الجهاز</span></x-ui.badge>
        <x-ui.help title="حفظ الكميات" body="يحفظ المتصفح ما تكتبه في قاعدة IndexedDB المحلية لتستكمل الجرد عند العودة. المسودة لا تُرسل تلقائيًا ولا تغيّر المخزون؛ اضغط حفظ جميع الكميات عند توفر الإنترنت لتثبيتها في الخادم." />
    </div>

    <form method="POST" action="{{ route('accountant.inventory-counts.items.bulk-update', $session) }}" class="space-y-4"
          data-inventory-count-form
          data-inventory-count-storage-key="accountant:{{ auth('accountant')->id() }}:store:{{ $session->store_id }}:inventory-count:{{ $session->id }}"
          data-inventory-count-legacy-storage-key="inventory-count:{{ auth('accountant')->id() }}:{{ $session->id }}"
          data-inventory-count-account-scope="accountant:{{ auth('accountant')->id() }}"
          data-inventory-count-store-id="{{ $session->store_id }}"
          data-inventory-count-version="{{ $browserDraftVersion }}">
        @csrf
        @method('PUT')
    @foreach($session->items as $item)
        @php
            $unitOptions = $item->product?->product_type === 'fractional'
                ? ['roll' => 'رول', 'meter' => 'متر']
                : ($item->product?->is_splittable
                    ? ['kit' => 'طقم', 'piece' => 'حبة']
                    : ['piece' => 'حبة']);
        @endphp
        <div class="ui-card p-4 space-y-3" data-inventory-count-item data-items-per-unit="{{ (int) ($item->product?->items_per_unit ?? 0) }}">
            <div>
                <h2 class="ui-title text-lg font-bold">{{ $item->product_name_snapshot }}</h2>
                @if($item->product?->is_splittable && (int) $item->product->items_per_unit > 0)
                    <p class="mt-1 ui-text-caption ui-text-soft">مكونات المنتج: الطقم الواحد يحتوي على {{ (int) $item->product->items_per_unit }} حبة.</p>
                @elseif($item->product?->product_type === 'fractional' && (float) $item->product->roll_length > 0)
                    <p class="mt-1 ui-text-caption ui-text-soft">مكونات المنتج: الرول الواحد يحتوي على {{ rtrim(rtrim(number_format((float) $item->product->roll_length, 3, '.', ''), '0'), '.') }} متر.</p>
                @endif
                @if(in_array($item->decision, ['returned', 'recounted']))<p class="ui-status-warning mt-2">أعاده المالك: {{ $item->owner_adjustment_reason }}</p>@endif
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label>
                    <span class="ui-label">الكمية الفعلية</span>
                    <input class="ui-input" type="number" min="0" step="0.001" name="items[{{ $item->id }}][accountant_quantity]" value="{{ $item->accountant_quantity }}" placeholder="مثال: 12" required data-inventory-count-quantity>
                    @if($item->product?->is_splittable && (int) $item->product->items_per_unit > 0)
                        <span class="block mt-1 ui-text-caption ui-status-danger" data-inventory-count-breakdown aria-live="polite"></span>
                    @endif
                </label>
                <div>
                    <div class="ui-label inline-flex items-center gap-2">الوحدة <x-ui.help title="اختيار وحدة العد" body="تظهر الوحدات المناسبة للمنتج فقط. إذا كان المنتج طقمًا واخترت الحبة، يحول النظام رصيد الأطقم إلى حبات عند المقارنة مع المحافظة على الكمية التي أدخلتها." /></div>
                    <select class="ui-input" name="items[{{ $item->id }}][unit_type]" aria-label="وحدة جرد {{ $item->product_name_snapshot }}" data-inventory-count-unit>
                        @foreach($unitOptions as $value => $label)
                            <option value="{{ $value }}" @selected($item->unit_type === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label class="block">
                <span class="ui-label">ملاحظة (اختياري)</span>
                <input class="ui-input" name="items[{{ $item->id }}][accountant_note]" value="{{ $item->accountant_note }}" placeholder="مثال: منتج مكسور، تالف، رجيع، استهلاك، الاسم بحاجة للتغيير">
            </label>
            @if($item->accountant_updated_at)<p class="ui-text-caption">آخر حفظ: {{ $item->accountant_updated_at->format('Y-m-d H:i') }} — اليوم: {{ $item->count_business_date?->format('Y-m-d') }}</p>@endif
        </div>
    @endforeach
        <div class="flex items-center gap-2">
            <button class="ui-btn ui-btn-secondary flex-1">حفظ جميع الكميات</button>
            <x-ui.help title="حفظ جميع الكميات" body="يحفظ هذا الزر كميات كل المنتجات ووحداتها وملاحظاتها معًا، ويمكن تعديلها وإعادة الحفظ قبل الإرسال." />
        </div>
    </form>

    @php($allItemsSaved = $session->items->isNotEmpty() && $session->items->every(fn ($item) => $item->accountant_quantity !== null && $item->decision !== 'returned'))
    @unless($allItemsSaved)<div class="ui-alert ui-alert-danger"><strong>الإرسال غير جاهز:</strong> توجد كمية غير محفوظة.</div>@endunless
    <form method="POST" action="{{ route('accountant.inventory-counts.submit', $session) }}"
          data-ui-confirm="سيتم إرسال جميع نتائج الجرد المحفوظة إلى صاحب المتجر للمراجعة، وسيتوقف تعديلها حتى يعيد لك منتجًا. هل تريد المتابعة؟"
          data-ui-confirm-title="إرسال نتائج الجرد للمالك"
          data-ui-confirm-busy="جارٍ إرسال النتائج...">
        @csrf
        <div class="flex items-center gap-2">
            <button class="ui-btn ui-btn-primary flex-1" @disabled(! $allItemsSaved)>{{ $allItemsSaved ? 'إرسال النتائج للمالك' : 'احفظ كميات المنتجات أولًا' }}</button>
            <x-ui.help title="إرسال النتائج" body="يرسل الكميات المحفوظة ولقطات المقارنة إلى المالك، ثم يتوقف التعديل إلا إذا أعاد المالك منتجًا لإعادة الجرد." />
        </div>
    </form>
</div>
@endsection
