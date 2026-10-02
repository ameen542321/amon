@props(['employee', 'withdrawals' => collect(), 'modalId' => 'withdrawalModal'])

{{-- Overlay --}}
<div id="{{ $modalId }}"
     class="ui-modal-backdrop hidden">

    <div class="ui-modal-panel w-full max-w-lg max-h-[90vh] overflow-y-auto">

            {{-- العنوان + زر الإغلاق بنفس روح الفورم --}}
            <div class="ui-modal-header">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold ui-title">تسجيل سحب على {{ $employee->name }}</h2>
                    <x-ui.help title="سحب موظف" body="سجل سحبًا جديدًا أو عدّل واحذف السحوبات الظاهرة. المالك غير مقيد بعدد مرات التعديل، وتُسجل كل التغييرات للمراجعة." />
                </div>

                {{-- إغلاق المودال يستخدم عقد الإجراءات المشترك. --}}
                <button type="button"
                        data-ui-hide="{{ $modalId }}"
                        class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">×</button>
            </div>

            {{-- الفورم بنفس نظام المسافات في إضافة محاسب --}}
            <form method="POST"
                  action="{{ route('user.employees.withdrawal.store', $employee->id) }}"
                  data-ui-single-submit
                  data-ui-busy-text="جاري حفظ السحب..."
                  class="p-5 space-y-4">

                @csrf

                {{-- المبلغ --}}
                <div>
                    <label class="block ui-text-soft font-medium mb-1">المبلغ</label>
                    <div class="relative">
                        <input type="number" name="amount" step="0.01" required
                               class="ui-input w-full px-10 py-3">
                        <i class="fa-solid fa-money-bill ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                    </div>
                </div>

                {{-- الوصف --}}
                <div>
                    <label class="block ui-text-soft font-medium mb-1">الوصف (اختياري)</label>
                    <div class="relative">
                        <input type="text" name="description"
                               class="ui-input w-full px-10 py-3">
                        <i class="fa-solid fa-align-right ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                    </div>
                </div>

                {{-- التاريخ --}}
                <div>
                    <label class="block ui-text-soft font-medium mb-1">تاريخ السحب</label>

                    {{-- حقل تاريخ أصلي ظاهر؛ يعمل مباشرة بالماوس واللمس ولا يعتمد على حقل شفاف للمزامنة. --}}
                    <input type="date"
                           name="date"
                           id="dateInput-{{ $modalId }}"
                           value="{{ old('date', now()->toDateString()) }}"
                           required
                           class="ui-input w-full px-4 py-3 cursor-pointer">
                </div>

                {{-- زر الحفظ --}}
                <div class="pt-2">
                    <button type="submit" class="ui-btn ui-btn-warning w-full px-6 py-3 font-semibold justify-center">
                        <i class="fa-solid fa-check"></i>
                        حفظ السحب
                    </button>
                </div>

            </form>

            <div class="ui-section-divider p-5 space-y-3">
                <div>
                    <h3 class="ui-title font-bold">تعديل أو حذف السحوبات</h3>
                    <p class="ui-text-soft ui-text-caption mt-1">يمكن للمالك تعديل السحب أو حذفه دون حد لعدد مرات التعديل، مع حفظ سجل تدقيق لكل تغيير.</p>
                </div>
                @forelse($withdrawals as $withdrawal)
                    <div class="ui-card-muted p-3 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="ui-title font-bold">سحب #{{ $withdrawal->id }}</span>
                            <span class="ui-text-soft ui-text-caption">اليوم المحاسبي: {{ $withdrawal->accounting_date_display ?? optional($withdrawal->business_date ?? $withdrawal->date)->format('Y-m-d') }}</span>
                        </div>
                        <form method="POST"
                              action="{{ route('user.employees.withdrawal.update', $withdrawal) }}"
                              data-ui-confirm="سيتم تعديل بيانات السحب وتسجيل القيم السابقة والجديدة في سجل الموظف. هل تريد المتابعة؟"
                              data-ui-confirm-title="تأكيد تعديل السحب"
                              data-ui-confirm-busy="جاري حفظ التعديل..."
                              class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <label><span class="ui-label">المبلغ</span><input type="number" name="amount" min="0.01" step="0.01" required value="{{ $withdrawal->amount }}" class="ui-input mt-1"></label>
                            <label><span class="ui-label">التاريخ</span><input type="date" name="date" required value="{{ optional($withdrawal->date ?? $withdrawal->business_date)->format('Y-m-d') }}" class="ui-input mt-1"></label>
                            <label class="sm:col-span-2"><span class="ui-label">الوصف</span><input type="text" name="description" maxlength="255" value="{{ $withdrawal->description }}" class="ui-input mt-1"></label>
                            <button type="submit" class="ui-btn ui-btn-secondary justify-center sm:col-span-2">حفظ التعديل</button>
                        </form>
                        <form method="POST"
                              action="{{ route('user.employees.withdrawal.destroy', $withdrawal) }}"
                              data-ui-confirm="سيتم حذف السحب من الحسابات مع الاحتفاظ ببياناته في سجل التدقيق. هل تريد المتابعة؟"
                              data-ui-confirm-title="تأكيد حذف السحب"
                              data-ui-confirm-busy="جاري الحذف...">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ui-btn ui-btn-danger w-full justify-center">حذف السحب</button>
                        </form>
                    </div>
                @empty
                    <div class="ui-card-muted p-4 text-center ui-text-muted">لا توجد سحوبات في الشهر المحدد.</div>
                @endforelse
            </div>

    </div>
</div>
