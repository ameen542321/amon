<div data-pwa-center>
    <button type="button" class="ui-btn ui-btn-secondary ui-pwa-launcher" data-pwa-center-open aria-haspopup="dialog" aria-controls="pwa-center-dialog"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i><span>حالة التطبيق</span><span class="ui-text-caption" data-pwa-connectivity>فحص الاتصال…</span><span class="ui-badge ui-badge-info" data-pwa-pending-count>0</span></button>
    <div id="pwa-center-dialog" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="pwa-center-title" data-pwa-center-dialog>
        <div class="ui-modal-panel ui-modal-panel-wide">
            <div class="ui-modal-header"><h2 id="pwa-center-title" class="ui-title text-xl font-bold">حالة التطبيق والمزامنة</h2><button type="button" class="ui-modal-close-text-danger" data-pwa-center-close>إغلاق</button></div>
            <p class="ui-text-soft mt-4" data-pwa-server-status role="status">جارٍ التحقق من الاتصال…</p>
            <p class="ui-text-caption mt-2" data-pwa-last-sync>لم تتم مزامنة طلبات على هذا الجهاز.</p>
            <div class="flex flex-wrap gap-3 mt-4"><button type="button" class="ui-btn ui-btn-secondary" data-pwa-check>فحص الاتصال</button><button type="button" class="ui-btn ui-btn-primary" data-pwa-sync>مزامنة الآن</button><button type="button" class="ui-btn ui-btn-secondary" data-pwa-install-help>تثبيت التطبيق</button></div>
            <p class="ui-alert ui-alert-warning hidden mt-4" data-pwa-center-feedback role="status"></p>
            <section class="mt-6" data-pwa-account-section>
                <h3 class="ui-title font-bold">الطلبات المؤجلة</h3><p class="ui-text-soft mt-2">المزامنة تخص مسودات الجرد فقط؛ العمليات المالية واعتماد المخزون تتطلب الإنترنت.</p><div class="space-y-3 mt-3" data-pwa-outbox-list></div>
                <h3 class="ui-title font-bold mt-6">مسودات هذا الحساب على الجهاز</h3><p class="ui-text-soft mt-2" data-pwa-storage-size></p><div class="space-y-3 mt-3" data-pwa-drafts-list></div>
                <button type="button" class="ui-btn ui-btn-danger mt-4" data-pwa-clear-account>حذف المسودات والطلبات المؤجلة</button>
                <form method="POST" class="ui-card p-4 mt-6" data-pwa-preferences>
                    <h3 class="ui-title font-bold">إشعارات الأجهزة</h3><p class="ui-text-soft mt-2">يبقى سجل الإشعارات داخل النظام متاحًا عند كتم إشعارات الأجهزة.</p>
                    <div class="grid gap-3 mt-4"><label class="ui-cost-select ui-text-soft"><input type="checkbox" name="announcements" checked> إشعارات الإدارة العامة</label><label class="ui-cost-select ui-text-soft"><input type="checkbox" name="messages" checked> رسائل الملاك والمحاسبين</label><label class="ui-cost-select ui-text-soft"><input type="checkbox" name="operations" checked> تنبيهات الأنظمة</label></div>
                    <button type="submit" class="ui-btn ui-btn-primary mt-4" data-pwa-preferences-save disabled>حفظ تفضيلات الإشعارات</button>
                </form>
            </section>
        </div>
    </div>
</div>
