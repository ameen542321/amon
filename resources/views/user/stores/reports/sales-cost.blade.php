@extends('dashboard.app')
@section('title', 'تقرير تكلفة المبيعات - ' . $store->name)
@section('content')
@php
    $money = fn ($value) => number_format((float) $value, 2);
@endphp
<div class="ui-page ui-cost-report max-w-7xl mx-auto" dir="rtl" data-sales-cost-report>
    <header class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('user.stores.reports.index', $store) }}" class="ui-btn ui-btn-secondary" aria-label="العودة إلى مركز التقارير"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            <div><p class="ui-text-soft mb-1">{{ $store->name }}</p><h1 class="ui-title text-2xl sm:text-3xl font-black">تقرير تكلفة المبيعات</h1></div>
        </div>
        <x-ui.help title="تقرير التكلفة" body="يعرض إجمالي تكلفة المنتجات وشغل اليد. يمكنك إدخال تكلفة شغل اليد إذا كانت صفرًا أو غير مسجلة، ثم حفظ عملية واحدة أو تحديد عدة عمليات وحفظها معًا. الأيام المستخدمة لا تقبل التعديل." />
    </header>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5" aria-label="ملخص التقرير">
        <article class="ui-card p-4 sm:p-5"><p class="ui-text-soft">إجمالي المبيعات</p><p class="ui-title text-xl sm:text-2xl font-black mt-3">{{ $money($summary['sales_total']) }} <span class="ui-text-caption">ريال</span></p></article>
        <article class="ui-card p-4 sm:p-5"><p class="ui-text-soft">إجمالي التكلفة</p><p class="ui-status-success text-xl sm:text-2xl font-black mt-3">{{ $money($summary['total_cost']) }} <span class="ui-text-caption">ريال</span></p></article>
        <article class="ui-card p-4 sm:p-5"><p class="ui-text-soft">شغل اليد</p><p class="ui-title text-xl sm:text-2xl font-black mt-3">{{ $money($summary['labor_total']) }} <span class="ui-text-caption">ريال</span></p></article>
        <article class="ui-card p-4 sm:p-5"><p class="ui-text-soft">عدد العمليات</p><p class="ui-title text-xl sm:text-2xl font-black mt-3">{{ number_format($summary['operations_count']) }}</p></article>
    </section>

    <form method="GET" action="{{ route('user.stores.reports.sales-cost', $store) }}" class="ui-card p-4 sm:p-5 mb-5">
        <div class="grid sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            <label class="lg:col-span-5"><span class="ui-label">البحث</span><input class="ui-input w-full" type="search" name="q" maxlength="100" value="{{ $search }}" placeholder="اسم المنتج أو وصف العملية"></label>
            <label class="lg:col-span-3"><span class="ui-label">من تاريخ</span><input class="ui-input w-full" type="date" name="from" value="{{ $from }}" required></label>
            <label class="lg:col-span-3"><span class="ui-label">إلى تاريخ</span><input class="ui-input w-full" type="date" name="to" value="{{ $to }}" required></label>
            <button class="ui-btn ui-btn-primary lg:col-span-1" type="submit">بحث</button>
        </div>
        <div class="flex flex-wrap justify-between gap-3 mt-4">
            <label class="ui-cost-select ui-text-soft"><input type="checkbox" name="exclude_used" value="1" @checked($excludeUsed)> استبعاد الأيام المستخدمة</label>
            @if($overlappingUsedDates->isNotEmpty())
                <span class="inline-flex items-center gap-2"><span class="ui-badge ui-badge-warning">{{ $overlappingUsedDates->count() }} أيام مستخدمة</span><x-ui.help title="الأيام المستخدمة" :body="'الأيام: '.$overlappingUsedDates->implode('، ').'. يمكن عرضها أو استبعادها من البحث، ولا يمكن تعديل تكلفتها.'" /></span>
            @endif
        </div>
    </form>

    <section class="ui-card overflow-hidden" aria-labelledby="cost-results-title">
        <div class="flex flex-wrap justify-between items-center gap-3 p-4 sm:p-5 border-b ui-border">
            <div class="flex items-center gap-2"><h2 id="cost-results-title" class="ui-title font-black text-lg">العمليات</h2><span class="ui-badge ui-badge-info">{{ $rows->count() }}</span></div>
            @if($rows->isNotEmpty() && $matchedDates->diff($overlappingUsedDates)->isNotEmpty())
                <form method="POST" action="{{ route('user.stores.reports.sales-cost.mark-used', $store) }}">
                    @csrf
                    @include('user.stores.reports.partials.sales-cost-filters')
                    <button class="ui-btn ui-btn-warning" type="submit" data-cost-online>تحديد الأيام كمستخدمة</button>
                </form>
            @endif
        </div>
        @if($rows->contains(fn ($row) => $row['can_edit_labor_cost'] && !$usedDates->has($row['business_date'])))
            <div class="ui-cost-toolbar p-4 sm:px-5">
                <label class="ui-cost-select ui-text-soft"><input type="checkbox" data-cost-select-all> تحديد الكل</label>
                <span class="ui-text-soft" data-cost-selection-count aria-live="polite">لم تُحدد عمليات</span>
                <form id="sales-cost-bulk" method="POST" action="{{ route('user.stores.reports.sales-cost.labor-costs.update', $store) }}" data-cost-bulk-form>
                    @csrf @method('PATCH')
                    @include('user.stores.reports.partials.sales-cost-filters')
                    <div data-cost-bulk-values></div>
                    <button class="ui-btn ui-btn-primary" type="submit" data-cost-bulk-save disabled>حفظ المحدد</button>
                </form>
                <p class="ui-status-danger hidden" data-cost-feedback role="status"></p>
            </div>
        @endif
        <div class="ui-cost-columns ui-cost-head" aria-hidden="true"><span>العملية</span><span>المبيعات</span><span>شغل اليد</span><span>إجمالي التكلفة</span><span>الحالة</span></div>
        <div>
            @forelse($rows as $row)
                @php
                    $editable = $row['can_edit_labor_cost'] && !$usedDates->has($row['business_date']);
                    $costError = $errors->first('costs.'.$row['id']) ?: ((int) old('editing_sale') === $row['id'] ? $errors->first('labor_cost') : '');
                @endphp
                <article id="sale-{{ $row['id'] }}" class="ui-cost-columns ui-cost-row" data-cost-row="{{ $row['id'] }}" tabindex="-1" aria-label="عملية {{ $row['id'] }}">
                    <div class="min-w-0">
                        <div class="flex gap-3 items-center">
                            @if($editable)<label class="ui-cost-select"><input type="checkbox" data-cost-select="{{ $row['id'] }}" @checked(array_key_exists($row['id'], (array) old('costs', []))) aria-label="تحديد العملية {{ $row['id'] }}"></label>@endif
                            <h3 class="ui-title font-black">#{{ $row['id'] }}</h3>
                            <time class="ui-text-caption" datetime="{{ $row['business_date'] }}">{{ $row['business_date'] }}</time>
                        </div>
                        <p class="ui-text-soft font-bold mt-2">{{ $row['description'] }}</p>
                        @if($row['products'])<p class="ui-text-soft mt-1 break-words">{{ $row['products'] }}</p>@endif
                        <p class="ui-text-caption mt-2">{{ $row['accountant'] }}</p>
                    </div>
                    <div class="ui-cost-amount"><span class="ui-cost-mobile-label ui-text-soft">المبيعات</span><strong class="ui-title">{{ $money($row['sales_total']) }}</strong></div>
                    <div class="ui-cost-amount"><span class="ui-cost-mobile-label ui-text-soft">شغل اليد</span><strong class="ui-title">{{ $money($row['labor_total']) }}</strong></div>
                    <div class="ui-cost-total">
                        <div class="flex justify-between items-center gap-2"><span class="ui-cost-mobile-label ui-text-soft">إجمالي التكلفة</span><strong class="ui-status-success text-lg">{{ $money($row['total_cost']) }}</strong></div>
                        @if($editable)
                            <form method="POST" action="{{ route('user.stores.reports.sales-cost.labor-cost.update', [$store, $row['id']]) }}" class="mt-3" data-cost-single-form>
                                @csrf @method('PATCH')
                                @include('user.stores.reports.partials.sales-cost-filters')
                                <input type="hidden" name="editing_sale" value="{{ $row['id'] }}">
                                <label for="labor-cost-{{ $row['id'] }}" class="ui-label">تكلفة شغل اليد</label>
                                <div class="flex items-center gap-2"><input id="labor-cost-{{ $row['id'] }}" class="ui-input min-w-0 w-full" type="number" name="labor_cost" min="0" max="99999999.99" step="0.01" required value="{{ old('costs.'.$row['id'], (int) old('editing_sale') === $row['id'] ? old('labor_cost', $row['labor_cost']) : $row['labor_cost']) }}" placeholder="أدخل التكلفة" data-cost-input="{{ $row['id'] }}" aria-invalid="{{ $costError ? 'true' : 'false' }}" @if($costError) aria-describedby="cost-error-{{ $row['id'] }}" @endif><button class="ui-btn ui-btn-primary" type="submit" data-cost-online>حفظ</button></div>
                            </form>
                        @endif
                        @if($costError)<p id="cost-error-{{ $row['id'] }}" class="ui-status-danger mt-2" role="alert">{{ $costError }}</p>@endif
                    </div>
                    <div class="ui-cost-state">@if($usedDates->has($row['business_date']))<span class="ui-badge ui-badge-warning">مستخدم</span>@elseif($editable)<span class="ui-badge ui-badge-info">بانتظار التكلفة</span>@else<span class="ui-badge ui-badge-success">متاح</span>@endif</div>
                </article>
            @empty
                <div class="text-center p-8 sm:p-12"><i class="fa-solid fa-magnifying-glass ui-text-soft text-2xl" aria-hidden="true"></i><p class="ui-title font-bold mt-4">لا توجد عمليات مطابقة</p></div>
            @endforelse
        </div>
    </section>
    @if($errors->has('costs') || $errors->has('labor_cost'))<p class="ui-alert ui-alert-danger mt-4" role="alert">{{ $errors->first('costs') ?: $errors->first('labor_cost') }}</p>@endif
</div>
@endsection
