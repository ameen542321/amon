<div class="mt-4 space-y-3" x-data="{ detailSection: null }">
    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4" aria-label="تفاصيل عمليات الموظف للشهر المحدد">
        <button type="button" class="ui-btn ui-btn-secondary justify-center px-3 py-3" :class="detailSection === 'withdrawals' ? 'is-active' : ''" @click="detailSection = detailSection === 'withdrawals' ? null : 'withdrawals'">السحوبات</button>
        <button type="button" class="ui-btn ui-btn-secondary justify-center px-3 py-3" :class="detailSection === 'absences' ? 'is-active' : ''" @click="detailSection = detailSection === 'absences' ? null : 'absences'">الغيابات</button>
        <button type="button" class="ui-btn ui-btn-secondary justify-center px-3 py-3" :class="detailSection === 'credit' ? 'is-active' : ''" @click="detailSection = detailSection === 'credit' ? null : 'credit'">الأجل وتحصيلاته</button>
        <button type="button" class="ui-btn ui-btn-secondary justify-center px-3 py-3" :class="detailSection === 'debts' ? 'is-active' : ''" @click="detailSection = detailSection === 'debts' ? null : 'debts'">المديونية وتحصيلاتها</button>
    </div>

    <section x-show="detailSection === 'withdrawals'" x-cloak class="ui-card-muted p-3">
        <h3 class="ui-title font-bold mb-3">سحوبات الشهر المحدد</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full ui-text-caption">
                <thead class="ui-text-muted"><tr><th class="px-3 py-2 text-right">التاريخ</th><th class="px-3 py-2 text-right">المبلغ</th><th class="px-3 py-2 text-right">من سجله</th><th class="px-3 py-2 text-right">الوصف</th></tr></thead>
                <tbody class="divide-y divide-ui-border ui-text-soft">
                    @forelse($row['withdrawal_rows'] as $withdrawal)
                        <tr><td class="px-3 py-2">{{ optional($withdrawal->business_date ?? $withdrawal->date ?? $withdrawal->created_at)->format('Y-m-d') }}</td><td class="px-3 py-2 font-bold">{{ number_format($withdrawal->amount, 2) }} ر.س</td><td class="px-3 py-2">{{ $withdrawal->addedBy?->name ?: 'غير محدد' }}</td><td class="px-3 py-2">{{ $withdrawal->description ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-center ui-text-muted">لا توجد سحوبات في الشهر المحدد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section x-show="detailSection === 'absences'" x-cloak class="ui-card-muted p-3">
        <h3 class="ui-title font-bold mb-3">غيابات الشهر المحدد</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full ui-text-caption">
                <thead class="ui-text-muted"><tr><th class="px-3 py-2 text-right">التاريخ</th><th class="px-3 py-2 text-right">خصم الغياب</th><th class="px-3 py-2 text-right">من سجله</th><th class="px-3 py-2 text-right">الملاحظة</th></tr></thead>
                <tbody class="divide-y divide-ui-border ui-text-soft">
                    @forelse($row['absence_rows'] as $absence)
                        <tr><td class="px-3 py-2">{{ optional($absence->date)->format('Y-m-d') }}</td><td class="px-3 py-2 font-bold">{{ number_format($absence->penalty_amount ?? 0, 2) }} ر.س</td><td class="px-3 py-2">{{ $absence->addedBy?->name ?: 'غير محدد' }}</td><td class="px-3 py-2">{{ $absence->description ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-center ui-text-muted">لا توجد غيابات في الشهر المحدد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section x-show="detailSection === 'credit'" x-cloak class="ui-card-muted p-3 space-y-4">
        <div>
            <h3 class="ui-title font-bold mb-3">عمليات الأجل</h3>
            <div class="overflow-x-auto"><table class="min-w-full ui-text-caption"><thead class="ui-text-muted"><tr><th class="px-3 py-2 text-right">رقم العملية</th><th class="px-3 py-2 text-right">التاريخ</th><th class="px-3 py-2 text-right">القيمة</th><th class="px-3 py-2 text-right">المتبقي</th><th class="px-3 py-2 text-right">البيان</th></tr></thead><tbody class="divide-y divide-ui-border ui-text-soft">
                @forelse($row['credit_sale_rows'] as $creditSale)
                    <tr><td class="px-3 py-2">#{{ $creditSale->id }}</td><td class="px-3 py-2">{{ optional($creditSale->date)->format('Y-m-d') }}</td><td class="px-3 py-2 font-bold">{{ number_format($creditSale->amount, 2) }} ر.س</td><td class="px-3 py-2">{{ number_format($creditSale->remaining_amount, 2) }} ر.س</td><td class="px-3 py-2">{{ $creditSale->operation_name ?: $creditSale->description ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-center ui-text-muted">لا توجد عمليات أجل في الشهر المحدد.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="ui-section-divider ui-section-divider-sm">
            <h3 class="ui-title font-bold mb-3">تحصيلات الأجل</h3>
            <div class="overflow-x-auto"><table class="min-w-full ui-text-caption"><thead class="ui-text-muted"><tr><th class="px-3 py-2 text-right">رقم التحصيل</th><th class="px-3 py-2 text-right">عملية الأجل</th><th class="px-3 py-2 text-right">التاريخ</th><th class="px-3 py-2 text-right">المبلغ</th><th class="px-3 py-2 text-right">من حصله</th><th class="px-3 py-2 text-right">الطريقة</th></tr></thead><tbody class="divide-y divide-ui-border ui-text-soft">
                @forelse($row['credit_collection_rows'] as $collection)
                    <tr><td class="px-3 py-2">#{{ $collection->id }}</td><td class="px-3 py-2">#{{ $collection->credit_sale_id }}</td><td class="px-3 py-2">{{ $collection->collection_date }}</td><td class="px-3 py-2 font-bold">{{ number_format($collection->amount, 2) }} ر.س</td><td class="px-3 py-2">{{ $collection->collector_name ?: 'غير محدد' }}</td><td class="px-3 py-2">{{ $collection->payment_method_label ?: 'كاش' }}@if($collection->note)<span class="block ui-text-muted">{{ $collection->note }}</span>@endif</td></tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-center ui-text-muted">لا توجد تحصيلات أجل في الشهر المحدد.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </section>

    <section x-show="detailSection === 'debts'" x-cloak class="ui-card-muted p-3 space-y-4">
        <div>
            <h3 class="ui-title font-bold mb-3">المديونيات</h3>
            <div class="overflow-x-auto"><table class="min-w-full ui-text-caption"><thead class="ui-text-muted"><tr><th class="px-3 py-2 text-right">رقم المديونية</th><th class="px-3 py-2 text-right">التاريخ</th><th class="px-3 py-2 text-right">القيمة</th><th class="px-3 py-2 text-right">من سجلها</th><th class="px-3 py-2 text-right">الوصف</th></tr></thead><tbody class="divide-y divide-ui-border ui-text-soft">
                @forelse($row['debt_rows'] as $debt)
                    <tr><td class="px-3 py-2">#{{ $debt->id }}</td><td class="px-3 py-2">{{ optional($debt->date)->format('Y-m-d') }}</td><td class="px-3 py-2 font-bold">{{ number_format($debt->amount, 2) }} ر.س</td><td class="px-3 py-2">{{ $debt->addedBy?->name ?: 'غير محدد' }}</td><td class="px-3 py-2">{{ $debt->description ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-center ui-text-muted">لا توجد مديونيات في الشهر المحدد.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="ui-section-divider ui-section-divider-sm">
            <h3 class="ui-title font-bold mb-3">تحصيلات المديونية</h3>
            <div class="overflow-x-auto"><table class="min-w-full ui-text-caption"><thead class="ui-text-muted"><tr><th class="px-3 py-2 text-right">رقم التحصيل</th><th class="px-3 py-2 text-right">أصل المديونية</th><th class="px-3 py-2 text-right">التاريخ</th><th class="px-3 py-2 text-right">المبلغ</th><th class="px-3 py-2 text-right">من حصله</th><th class="px-3 py-2 text-right">الطريقة</th></tr></thead><tbody class="divide-y divide-ui-border ui-text-soft">
                @forelse($row['debt_collection_rows'] as $collection)
                    <tr><td class="px-3 py-2">#{{ $collection['id'] }}</td><td class="px-3 py-2">{{ $collection['parent_id'] ? '#' . $collection['parent_id'] : '—' }}</td><td class="px-3 py-2">{{ $collection['date'] ?? '—' }}</td><td class="px-3 py-2 font-bold">{{ number_format($collection['amount'] ?? 0, 2) }} ر.س</td><td class="px-3 py-2">{{ $collection['collector'] ?? 'غير محدد' }}</td><td class="px-3 py-2">{{ $collection['payment_method_label'] ?? 'كاش' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-center ui-text-muted">لا توجد تحصيلات مديونية في الشهر المحدد.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </section>
</div>
