@extends('dashboard.app')

@section('title', 'تقرير تكلفة المبيعات - ' . $store->name)

@section('content')
@php($money = fn ($value) => number_format((float) $value, 2))

<div class="max-w-7xl mx-auto px-3 py-5 sm:px-4 sm:py-6 text-right" dir="rtl">
    <header class="mb-5 rounded-3xl border ui-border ui-card p-4 shadow-xl sm:p-6">
        <div class="flex items-start gap-3">
            <a href="{{ route('user.stores.reports.index', $store) }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border ui-border ui-surface-strong-bg ui-title" aria-label="العودة إلى مركز التقارير">
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black ui-title">تقرير تكلفة المبيعات</h1>
                <p class="mt-1 ui-text-soft">{{ $store->name }} — تكلفة المنتجات المباعة وشغل اليد حسب يوم العمل.</p>
            </div>
        </div>
    </header>

    <form method="GET" action="{{ route('user.stores.reports.sales-cost', $store) }}" class="mb-5 rounded-3xl border ui-border ui-surface-strong-bg p-4 sm:p-5">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <label for="q" class="mb-2 block font-bold ui-text-soft">البحث الاختياري</label>
                <input id="q" name="q" value="{{ $search }}" type="text" maxlength="100" placeholder="مثال: تظليل، طباعة، اسم منتج أو باركود" class="ui-input w-full">
            </div>
            <div class="lg:col-span-3">
                <label for="from" class="mb-2 block font-bold ui-text-soft">من تاريخ</label>
                <input id="from" name="from" value="{{ $from }}" type="date" class="ui-input w-full" required>
            </div>
            <div class="lg:col-span-3">
                <label for="to" class="mb-2 block font-bold ui-text-soft">إلى تاريخ</label>
                <input id="to" name="to" value="{{ $to }}" type="date" class="ui-input w-full" required>
            </div>
            <div class="flex items-end lg:col-span-1">
                <button type="submit" class="ui-btn ui-btn-primary w-full"><i class="fa-solid fa-search"></i><span>بحث</span></button>
            </div>
        </div>
        <label class="mt-4 flex items-center gap-2 ui-text-soft">
            <input type="checkbox" name="exclude_used" value="1" @checked($excludeUsed)>
            <span>استبعاد الأيام المستخدمة</span>
        </label>
    </form>

    @if($overlappingUsedDates->isNotEmpty())
        <div class="ui-alert ui-alert-warning mb-5" role="alert">
            <div>
                <p class="ui-alert-title font-black">تنبيه: توجد أيام مستخدمة سابقًا</p>
                <p class="ui-alert-body mt-1">الأيام: {{ $overlappingUsedDates->implode('، ') }}. يمكنك عرضها للمراجعة أو استبعادها من خيار البحث.</p>
            </div>
        </div>
    @endif

    <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-3">
        <article class="rounded-2xl border ui-border ui-card p-4">
            <p class="ui-text-soft">إجمالي المبيعات</p>
            <p class="mt-2 text-xl font-black ui-title">{{ $money($summary['sales_total']) }} ريال</p>
        </article>
        <article class="rounded-2xl border ui-border ui-card p-4">
            <p class="ui-text-soft">تكلفة المنتجات</p>
            <p class="mt-2 text-xl font-black ui-status-success">{{ $money($summary['products_cost']) }} ريال</p>
        </article>
        <article class="rounded-2xl border ui-border ui-card p-4">
            <p class="ui-text-soft">شغل اليد</p>
            <p class="mt-2 text-xl font-black ui-title">{{ $money($summary['labor_total']) }} ريال</p>
        </article>
        <article class="rounded-2xl border ui-border ui-card p-4">
            <p class="ui-text-soft">تكلفة خيارات العمل</p>
            <p class="mt-2 text-xl font-black ui-status-success">{{ $money($summary['labor_cost']) }} ريال</p>
        </article>
        <article class="rounded-2xl border ui-border ui-card p-4">
            <p class="ui-text-soft">إجمالي التكلفة</p>
            <p class="mt-2 text-xl font-black ui-status-success">{{ $money($summary['total_cost']) }} ريال</p>
        </article>
        <article class="rounded-2xl border ui-border ui-card p-4">
            <p class="ui-text-soft">عدد العمليات</p>
            <p class="mt-2 text-xl font-black ui-title">{{ number_format($summary['operations_count']) }}</p>
        </article>
    </section>

    @if($summary['missing_labor_cost_count'] > 0)
        <div class="ui-alert ui-alert-warning mb-5" role="alert">
            <div>
                <p class="ui-alert-title font-black">تكلفة خيارات العمل غير متوفرة لبعض العمليات القديمة</p>
                <p class="ui-alert-body mt-1">عددها {{ number_format($summary['missing_labor_cost_count']) }}. لم تُقدّر هذه التكلفة بأسعار الخيارات الحالية حتى لا تتغير النتائج التاريخية، وإجمالي التكلفة أدناه يجمع القيم المحفوظة المتاحة فقط.</p>
            </div>
        </div>
    @endif

    <section class="overflow-hidden rounded-3xl border ui-border ui-card shadow-xl">
        <div class="flex flex-col gap-3 border-b ui-border p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-black ui-title">العمليات المطابقة</h2>
                <p class="mt-1 ui-text-muted">التكلفة المحفوظة وقت البيع هي الأدق، وتظهر العمليات القديمة الناقصة كتقديرية.</p>
            </div>
            @if($rows->isNotEmpty() && $matchedDates->diff($overlappingUsedDates)->isNotEmpty())
                <form method="POST" action="{{ route('user.stores.reports.sales-cost.mark-used', $store) }}">
                    @csrf
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <input type="hidden" name="q" value="{{ $search }}">
                    <button type="submit" class="ui-btn ui-btn-warning">
                        <i class="fa-solid fa-check"></i><span>تحديد الأيام كمستخدمة</span>
                    </button>
                </form>
            @endif
        </div>

        <div class="grid gap-3 p-3 md:hidden">
            @forelse($rows as $row)
                <article class="rounded-2xl border ui-border ui-surface-muted-bg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black ui-title">عملية #{{ $row['id'] }}</h3>
                            <p class="mt-1 ui-text-muted">{{ $row['business_date'] }} — {{ $row['accountant'] }}</p>
                        </div>
                        @if($usedDates->has($row['business_date']))
                            <span class="ui-badge ui-badge-warning">مستخدم</span>
                        @endif
                    </div>
                    <p class="mt-3 ui-text-soft">{{ $row['description'] }}</p>
                    @if($row['products'])<p class="mt-1 ui-text-muted">{{ $row['products'] }}</p>@endif
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-center">
                        <div><dt class="ui-text-muted">المبيعات</dt><dd class="font-black ui-title">{{ $money($row['sales_total']) }}</dd></div>
                        <div><dt class="ui-text-muted">التكلفة</dt><dd class="font-black ui-status-success">{{ $money($row['products_cost']) }}</dd></div>
                        <div><dt class="ui-text-muted">شغل اليد</dt><dd class="font-black ui-title">{{ $money($row['labor_total']) }}</dd></div>
                        <div><dt class="ui-text-muted">تكلفة العمل</dt><dd class="font-black {{ $row['has_labor_cost_snapshot'] ? 'ui-status-success' : 'ui-status-warning' }}">{{ $row['has_labor_cost_snapshot'] ? $money($row['labor_cost']) : 'غير متوفرة' }}</dd></div>
                    </dl>
                    @if($row['can_edit_labor_cost'] && !$usedDates->has($row['business_date']))
                        <form method="POST" action="{{ route('user.stores.reports.sales-cost.labor-cost.update', [$store, $row['id']]) }}" class="mt-3 flex items-end gap-2">
                            @csrf @method('PATCH')
                            <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="q" value="{{ $search }}">@if($excludeUsed)<input type="hidden" name="exclude_used" value="1">@endif
                            <label class="flex-1"><span class="ui-label">إدخال تكلفة العملية السابقة</span><input class="ui-input mt-1" type="number" name="labor_cost" min="0" max="99999999.99" step="0.01" required value="{{ $row['labor_cost'] }}" placeholder="0.00"></label>
                            <button class="ui-btn ui-btn-primary" type="submit">حفظ</button>
                        </form>
                    @endif
                    @if($row['labor_cost_breakdown'])<p class="mt-2 ui-text-muted">خيارات التكلفة: {{ $row['labor_cost_breakdown'] }}</p>@endif
                    <p class="mt-3 ui-text-muted">{{ $row['cost_source'] }}</p>
                </article>
            @empty
                <p class="p-8 text-center ui-text-muted">لا توجد عمليات مطابقة للفترة والبحث.</p>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="ui-table w-full min-w-[1050px]">
                <thead>
                    <tr>
                        <th>التاريخ</th><th>العملية</th><th>الوصف والمنتجات</th><th>المبيعات</th><th>تكلفة المنتجات</th><th>شغل اليد</th><th>تكلفة العمل</th><th>إجمالي التكلفة</th><th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['business_date'] }}</td>
                            <td>#{{ $row['id'] }}<p class="ui-text-muted">{{ $row['accountant'] }}</p></td>
                            <td><p class="font-bold ui-title">{{ $row['description'] }}</p><p class="ui-text-muted">{{ $row['products'] ?: 'لا توجد منتجات مسجلة' }}</p></td>
                            <td>{{ $money($row['sales_total']) }}</td>
                            <td><strong class="ui-status-success">{{ $money($row['products_cost']) }}</strong><p class="ui-text-muted">{{ $row['cost_source'] }}</p></td>
                            <td>{{ $money($row['labor_total']) }}</td>
                            <td>
                                @if($row['has_labor_cost_snapshot'])
                                    <strong class="ui-status-success">{{ $money($row['labor_cost']) }}</strong>@if($row['labor_cost_breakdown'])<p class="ui-text-muted">{{ $row['labor_cost_breakdown'] }}</p>@endif
                                @else
                                    <span class="ui-badge ui-badge-warning">غير متوفرة لعملية قديمة</span>
                                @endif
                                @if($row['can_edit_labor_cost'] && !$usedDates->has($row['business_date']))
                                    <form method="POST" action="{{ route('user.stores.reports.sales-cost.labor-cost.update', [$store, $row['id']]) }}" class="mt-2 flex items-center gap-2">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="q" value="{{ $search }}">@if($excludeUsed)<input type="hidden" name="exclude_used" value="1">@endif
                                        <input class="ui-input" type="number" name="labor_cost" min="0" max="99999999.99" step="0.01" required aria-label="تكلفة العمل للعملية السابقة" value="{{ $row['labor_cost'] }}" placeholder="0.00">
                                        <button class="ui-btn ui-btn-primary" type="submit">حفظ</button>
                                    </form>
                                @endif
                            </td>
                            <td><strong class="ui-status-success">{{ $money($row['total_cost']) }}</strong></td>
                            <td>@if($usedDates->has($row['business_date']))<span class="ui-badge ui-badge-warning">مستخدم</span>@else<span class="ui-badge ui-badge-success">متاح</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-10 text-center ui-text-muted">لا توجد عمليات مطابقة للفترة والبحث.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
