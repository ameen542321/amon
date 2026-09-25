@props(['modalId', 'action', 'currentBusinessDate'])

<div id="{{ $modalId }}" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title">
    <div class="ui-modal-panel w-full max-w-lg p-5">
        <div class="ui-modal-header">
            <h2 id="{{ $modalId }}-title" class="ui-title text-lg font-bold">رفض طلب النقل</h2>
            <button type="button" data-ui-hide="{{ $modalId }}" data-ui-scroll-unlock class="ui-modal-close-text-danger">إغلاق</button>
        </div>
        <form method="POST" action="{{ $action }}" class="mt-4 space-y-4" data-ui-confirm="سيتم رفض النقل وإرجاع الكمية للمتجر المرسل." data-ui-confirm-title="تأكيد رفض النقل">
            @csrf
            <div class="ui-frame-row"><span class="ui-text-soft">يوم عمل الرفض</span><strong class="ui-title">{{ $currentBusinessDate }}</strong></div>
            <label class="block">
                <span class="ui-label">سبب الرفض</span>
                <textarea name="reason" rows="3" required maxlength="1000" class="ui-input" placeholder="اكتب سببًا واضحًا لرفض النقل"></textarea>
            </label>
            <button class="ui-btn ui-btn-danger w-full">تأكيد الرفض وإرجاع الكمية</button>
        </form>
    </div>
</div>
