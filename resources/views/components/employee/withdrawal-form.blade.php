@props(['employee', 'modalId' => 'withdrawalModal'])

{{-- Overlay --}}
<div id="{{ $modalId }}"
     class="ui-modal-backdrop hidden">

    <div class="ui-modal-panel w-full max-w-lg max-h-[90vh] overflow-y-auto">

            {{-- العنوان + زر الإغلاق بنفس روح الفورم --}}
            <div class="ui-modal-header">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold ui-title">تسجيل سحب على {{ $employee->name }}</h2>
                    <x-ui.help title="سحب موظف" body="سجل المبلغ وتاريخ العملية. يظهر السحب في سجل الموظف وتقارير الشفت." />
                </div>

                {{-- إغلاق المودال يستخدم عقد الإجراءات المشترك. --}}
                <button type="button"
                        data-ui-hide="{{ $modalId }}"
                        class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">×</button>
            </div>

            {{-- الفورم بنفس نظام المسافات في إضافة محاسب --}}
            <form method="POST"
                  action="{{ route('user.employees.withdrawal.store', $employee->id) }}"
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
                    <button class="ui-btn ui-btn-warning w-full px-6 py-3 font-semibold justify-center">
                        <i class="fa-solid fa-check"></i>
                        حفظ السحب
                    </button>
                </div>

            </form>

    </div>
</div>
