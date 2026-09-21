@props(['store', 'stores', 'products', 'action', 'backUrl', 'title', 'currentBusinessDate'])

@php
    $productRows = $products->map(fn ($product) => [
        'id' => (string) $product->id,
        'name' => $product->name,
        'quantity' => (float) $product->quantity,
        'product_type' => $product->product_type,
        'is_splittable' => (bool) $product->is_splittable,
    ])->values();
    $oldItems = collect(old('items', []))->map(fn ($item) => [
        'sender_product_id' => (string) ($item['sender_product_id'] ?? ''),
        'quantity' => $item['quantity'] ?? '',
        'unit_type' => $item['unit_type'] ?? 'unit',
    ])->values();
@endphp

<div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-5" dir="rtl"
     data-store-transfer-system
     x-data="storeTransferBuilder(@js($productRows), @js($oldItems))">
    <header class="ui-card p-5 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="ui-status-info-bg ui-status-info flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"><i class="fa-solid fa-right-left" aria-hidden="true"></i></span>
                    <div>
                        <h1 class="ui-title text-2xl font-black">{{ $title }}</h1>
                        <p class="ui-text-soft mt-1">من {{ $store->name }} إلى متجر آخر تابع للمالك نفسه</p>
                    </div>
                    <x-ui.help variant="warning" title="كيف يعمل النقل؟" body="يخصم النظام الكمية من المتجر المرسل عند إرسال الطلب في يوم عمله المفتوح، ثم يضيفها إلى المتجر المستلم بعد مطابقة المنتجات وقبول الطلب في يوم عمل المتجر المستلم." />
                </div>
            </div>
            <a href="{{ $backUrl }}" class="ui-btn ui-btn-secondary"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع للنقل المخزني</a>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="ui-frame-row"><span class="ui-badge ui-badge-info">1</span><span class="ui-text-soft">حدد المتجر المستلم</span></div>
            <div class="ui-frame-row"><span class="ui-badge ui-badge-info">2</span><span class="ui-text-soft">أضف المنتجات والكميات</span></div>
            <div class="ui-frame-row"><span class="ui-badge ui-badge-success">3</span><span class="ui-text-soft">راجع ثم أرسل الطلب</span></div>
        </div>
    </header>

    @if ($errors->any())
        <div class="ui-alert ui-alert-danger" role="alert">
            <strong class="block mb-2">تعذر إنشاء طلب النقل:</strong>
            <ul class="list-disc list-inside space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf

        <section class="ui-card p-4 sm:p-6 space-y-4">
            <div class="flex items-center gap-3">
                <span class="ui-badge ui-badge-info">الوجهة</span>
                <div><h2 class="ui-title text-lg font-bold">بيانات النقل</h2><p class="ui-text-soft ui-text-caption mt-1">يحدد النظام يوم العمل تلقائيًا، واختر أنت المتجر المستلم.</p></div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="ui-card-muted p-4">
                    <span class="ui-text-soft font-bold">يوم عمل الإرسال</span>
                    <strong class="ui-title block text-xl mt-2">{{ $currentBusinessDate }}</strong>
                    <span class="ui-text-caption ui-text-muted block mt-1">اليوم المفتوح للمتجر المرسل</span>
                </div>
                <label class="ui-card-muted p-4">
                    <span class="ui-label">المتجر المستلم</span>
                    <select name="receiver_store_id" required class="ui-input mt-2">
                        <option value="">اختر المتجر المستلم</option>
                        @foreach($stores as $receiverStore)
                            <option value="{{ $receiverStore->id }}" @selected(old('receiver_store_id') == $receiverStore->id)>{{ $receiverStore->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <section class="ui-card p-4 sm:p-6 space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="ui-badge ui-badge-info">المنتجات</span>
                    <div><h2 class="ui-title text-lg font-bold">بنود طلب النقل</h2><p class="ui-text-soft ui-text-caption mt-1">لا يمكن تكرار المنتج، وتظهر الوحدات المناسبة له تلقائيًا.</p></div>
                </div>
                <button type="button" class="ui-btn ui-btn-info" @click="addItem()"><i class="fa-solid fa-plus" aria-hidden="true"></i> إضافة منتج</button>
            </div>

            <div class="space-y-3">
                <template x-for="(item, index) in items" :key="item.key">
                    <div class="ui-card-muted p-4 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <strong class="ui-title" x-text="`المنتج ${index + 1}`"></strong>
                            <button type="button" class="ui-btn ui-btn-danger px-3 py-2" @click="removeItem(index)" :disabled="items.length === 1" aria-label="حذف المنتج"><i class="fa-solid fa-trash" aria-hidden="true"></i> حذف</button>
                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                            <div class="relative md:col-span-6">
                                <label class="ui-label">المنتج</label>
                                <input type="hidden" :name="`items[${index}][sender_product_id]`" x-model="item.sender_product_id">
                                <input type="text" x-model="item.query" @focus="item.open = true" @input="item.sender_product_id = ''; item.open = true"
                                       autocomplete="off" placeholder="ابحث باسم المنتج..." class="ui-input mt-2" required>
                                <div x-show="item.open" @click.outside="item.open = false" x-cloak class="absolute z-50 mt-2 w-full max-h-64 overflow-y-auto rounded-xl border ui-border ui-surface-strong-bg shadow-2xl">
                                    <template x-for="product in filteredProducts(item)" :key="product.id">
                                        <button type="button" class="block w-full px-4 py-3 text-right ui-text-caption ui-title ui-hover-surface" @click="selectProduct(item, product)">
                                            <span class="font-bold" x-text="product.name"></span>
                                            <span class="ui-text-muted block mt-1" x-text="`المتوفر: ${product.quantity}`"></span>
                                        </button>
                                    </template>
                                    <p x-show="filteredProducts(item).length === 0" class="p-4 ui-text-caption ui-text-muted">لا توجد نتائج.</p>
                                </div>
                            </div>
                            <label class="md:col-span-3"><span class="ui-label">الكمية</span><input type="number" :name="`items[${index}][quantity]`" x-model="item.quantity" step="0.001" min="0.001" required class="ui-input mt-2"></label>
                            <label class="md:col-span-3"><span class="ui-label">الوحدة</span><select :name="`items[${index}][unit_type]`" x-model="item.unit_type" required class="ui-input mt-2"><template x-for="unit in unitsFor(item)" :key="unit.value"><option :value="unit.value" x-text="unit.label"></option></template></select></label>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <section class="ui-card p-4 sm:p-6 space-y-4">
            <div class="flex items-center gap-3"><span class="ui-badge ui-badge-info">المراجعة</span><div><h2 class="ui-title text-lg font-bold">الملاحظات والإرسال</h2><p class="ui-text-soft ui-text-caption mt-1">اكتب أي تعليمات تساعد المتجر المستلم قبل قبول المنتجات.</p></div></div>
            <label class="block"><span class="ui-label">ملاحظات الطلب (اختياري)</span><textarea name="notes" rows="3" class="ui-input mt-2" placeholder="مثال: المنتجات في المستودع الخلفي">{{ old('notes') }}</textarea></label>
            <div class="ui-alert ui-alert-warning"><strong>قبل الإرسال:</strong> ستُخصم الكميات فورًا من مخزون {{ $store->name }} وتبقى معلقة حتى يقبلها المتجر المستلم أو يعيدها بالرفض.</div>
            <button class="ui-btn ui-btn-primary w-full py-3"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> إرسال طلب النقل</button>
        </section>
    </form>
</div>
