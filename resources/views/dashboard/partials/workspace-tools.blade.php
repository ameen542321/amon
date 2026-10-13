<div data-workspace-tools data-workspace-tasks-store="{{ $workspaceStoreId ?: ($role === 'accountant' ? $workspaceStore?->id : 'all') }}" data-workspace-store="{{ $workspaceStore?->id ?? 'all' }}"
     data-workspace-search-url="{{ route($role === 'accountant' ? 'accountant.api.workspace.search' : ($role === 'admin' ? 'admin.api.workspace.search' : 'user.api.workspace.search')) }}" data-workspace-tasks-url="{{ route($role === 'accountant' ? 'accountant.api.workspace.tasks' : ($role === 'admin' ? 'admin.api.workspace.tasks' : 'user.api.workspace.tasks')) }}">
    <div class="ui-workspace-toolbar">
        <label class="ui-workspace-desktop-search"><span class="sr-only">البحث الموحد</span><input type="search" class="ui-input w-full" data-workspace-desktop-search placeholder="ابحث عن صفحة أو منتج أو طلبية… (Ctrl K)" autocomplete="off"></label>
        <button class="ui-btn ui-btn-secondary ui-workspace-search-trigger" type="button" data-workspace-open="search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>ابحث عن صفحة أو منتج أو طلبية</span><kbd class="hidden lg:inline">Ctrl K</kbd></button>
        <button type="button" class="ui-btn ui-btn-secondary" data-workspace-open="tasks"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>المهام</span><span data-workspace-task-count aria-live="polite"></span></button>
        <button type="button" class="ui-btn ui-btn-secondary" data-workspace-open="settings" aria-label="إعدادات العرض"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span class="hidden sm:inline">العرض</span></button>
    </div>
    @if(request()->routeIs('user.dashboard', 'accountant.dashboard', 'admin.dashboard.index'))
        <section class="ui-card ui-workspace-home" aria-label="اختصارات عملك">
            <div class="flex flex-wrap justify-between items-center gap-3"><h2 class="ui-title font-bold">{{ $role === 'accountant' ? 'ابدأ عملياتك' : ($role === 'admin' ? 'مساحة الدعم' : 'تابع متاجرك') }}</h2><button class="ui-btn ui-btn-secondary" type="button" data-workspace-open="tasks">ما الذي يحتاج مراجعتي؟</button></div>
            <div class="ui-workspace-shortcuts">@foreach(array_slice($workspaceLinks, 1, 4) as $link)<a class="ui-workspace-link" href="{{ $link['url'] }}"><i class="fa-solid fa-{{ $link['icon'] }}" aria-hidden="true"></i><span>{{ $link['title'] }}</span></a>@endforeach</div>
        </section>
    @endif
    <nav class="ui-workspace-bottom" aria-label="تنقل الجوال">
        @foreach(array_slice($workspaceLinks, 0, 3) as $link)<a href="{{ $link['url'] }}" @if(url()->current() === $link['url']) aria-current="page" @endif><i class="fa-solid fa-{{ $link['icon'] }}" aria-hidden="true"></i><span>{{ $link['title'] }}</span></a>@endforeach
        <button type="button" data-workspace-open="search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>بحث والمزيد</span></button>
    </nav>
    <dialog class="ui-workspace-dialog" data-workspace-dialog="search" aria-labelledby="workspace-search-title">
        <div class="ui-workspace-dialog-heading"><h2 id="workspace-search-title" class="ui-title font-bold">البحث الموحد</h2><button type="button" class="ui-btn ui-btn-secondary" data-workspace-close aria-label="إغلاق البحث">إغلاق</button></div>
        <label class="ui-text-soft" for="workspace-query">اسم صفحة، منتج، مورد أو رقم طلبية</label><input class="ui-input w-full" id="workspace-query" type="search" maxlength="100" autocomplete="off" data-workspace-query placeholder="اكتب للبحث…">
        @if($workspaceStores->count() > 1)<label class="ui-text-soft" for="workspace-search-store">المتجر</label><select id="workspace-search-store" class="ui-input" data-workspace-search-store><option value="">كل المتاجر المتاحة</option>@foreach($workspaceStores as $candidate)<option value="{{ $candidate->id }}" @selected($candidate->id === $workspaceStoreId)>{{ $candidate->name }}</option>@endforeach</select>@endif
        <p class="ui-text-soft" data-workspace-search-status role="status"></p>
        <div class="ui-workspace-results" data-workspace-results></div>
        <nav class="ui-workspace-links" data-workspace-command-links aria-label="الصفحات المتاحة">@foreach($workspaceLinks as $link)<a class="ui-workspace-link" href="{{ $link['url'] }}"><i class="fa-solid fa-{{ $link['icon'] }}" aria-hidden="true"></i><span>{{ $link['title'] }}</span></a>@endforeach</nav>
        @if($role === 'user')<a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.index') }}">اختيار متجر آخر</a>@endif
    </dialog>
    <dialog class="ui-workspace-dialog" data-workspace-dialog="tasks" aria-labelledby="workspace-tasks-title">
        <div class="ui-workspace-dialog-heading"><h2 id="workspace-tasks-title" class="ui-title font-bold">مركز المهام</h2><button type="button" class="ui-btn ui-btn-secondary" data-workspace-close>إغلاق</button></div>
        <p class="ui-text-soft" data-workspace-tasks-status role="status">الطلبيات والجرد والتحويلات التي تحتاج مراجعتك.</p>
        <div class="ui-workspace-results" data-workspace-tasks></div>
        <button class="ui-btn ui-btn-secondary" type="button" data-workspace-refresh>تحديث المهام</button>
        @if($role === 'admin')<a class="ui-btn ui-btn-primary" href="{{ route('admin.support-tickets.index') }}">فتح تذاكر الدعم</a>@endif
    </dialog>
    <dialog class="ui-workspace-dialog" data-workspace-dialog="settings" aria-labelledby="workspace-settings-title">
        <div class="ui-workspace-dialog-heading"><h2 id="workspace-settings-title" class="ui-title font-bold">إعدادات العرض</h2><button type="button" class="ui-btn ui-btn-secondary" data-workspace-close>إغلاق</button></div>
        <label class="ui-workspace-setting">المظهر<select class="ui-input" data-workspace-preference="theme"><option value="dark">مظلم</option><option value="light">فاتح</option></select></label>
        <label class="ui-workspace-setting">حجم النص<select class="ui-input" data-workspace-preference="font"><option value="normal">عادي</option><option value="large">كبير</option><option value="larger">أكبر</option></select></label>
        <label class="ui-workspace-setting">تباعد العرض<select class="ui-input" data-workspace-preference="density"><option value="comfortable">مريح للمس</option><option value="compact">مكثف للكمبيوتر</option></select></label>
        <label class="ui-workspace-setting">عرض البيانات<select class="ui-input" data-workspace-preference="view"><option value="auto">تلقائي حسب الشاشة</option><option value="cards">بطاقات</option><option value="table">جدول قابل للتمرير</option></select></label>
        <p class="ui-text-muted">تحفظ الإعدادات لهذا الحساب على هذا الجهاز. إعدادات الأعمدة متاحة بجانب الجداول المدعومة.</p>
        <p class="ui-text-soft" data-workspace-storage-status role="status"></p>
        <button class="ui-btn ui-btn-secondary" type="button" data-workspace-reset>استعادة العرض الافتراضي</button>
    </dialog>
</div>
