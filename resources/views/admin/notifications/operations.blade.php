@extends('dashboard.app')

@section('title', 'ملخص وتحكم الإشعارات')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <header class="ui-card p-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="ui-title text-2xl font-black">ملخص وتحكم الإشعارات</h1>
            <p class="ui-text-soft mt-1">مراقبة السجلات وتنفيذ التنظيف المعتمد دون إيقاف Queue المسؤول عن التسليم.</p>
        </div>
        <a href="{{ route('admin.notifications.index') }}" class="ui-btn ui-btn-secondary">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            مركز الإشعارات
        </a>
    </header>

    @if(session('success'))
        <div class="ui-status-success-bg ui-status-success border ui-border rounded-xl p-4" role="status">
            {{ session('success') }}
        </div>
    @endif

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4" aria-label="ملخص الإشعارات">
        @foreach([
            ['label' => 'إجمالي السجلات', 'value' => $summary['total'], 'icon' => 'fa-database'],
            ['label' => 'أضيفت اليوم', 'value' => $summary['today'], 'icon' => 'fa-calendar-day'],
            ['label' => 'منتهية المدة', 'value' => $summary['expired'], 'icon' => 'fa-clock-rotate-left'],
            ['label' => 'داخل النظام', 'value' => $summary['site'], 'icon' => 'fa-display'],
            ['label' => 'Push أو القناتان', 'value' => $summary['push'], 'icon' => 'fa-mobile-screen-button'],
        ] as $metric)
            <article class="ui-card p-4">
                <i class="fa-solid {{ $metric['icon'] }} ui-status-info" aria-hidden="true"></i>
                <span class="ui-text-soft block mt-3">{{ $metric['label'] }}</span>
                <strong class="ui-title block text-2xl mt-1">{{ number_format($metric['value']) }}</strong>
            </article>
        @endforeach
    </section>

    <section class="ui-card p-5 space-y-4" aria-labelledby="notification-maintenance-title">
        <div>
            <h2 id="notification-maintenance-title" class="ui-title text-xl font-bold">الصيانة الآمنة</h2>
            <p class="ui-text-soft mt-1">Queue يوصل Push خارج طلب الويب، أما هذه الصفحة فتدير السجلات. الوظيفتان مكملتان وليستا بديلتين.</p>
        </div>
        <div class="ui-status-warning-bg ui-status-warning border ui-border rounded-xl p-4">
            الحذف الجماعي مقصور على الإشعارات الأقدم من 15 يومًا. لا يوجد زر لحذف كل السجلات حمايةً لسجل التشغيل.
        </div>
        <form method="POST" action="{{ route('admin.notification-operations.cleanup') }}"
              data-ui-confirm="سيحذف فقط الإشعارات التي تجاوزت 15 يومًا نهائيًا لجميع المستلمين."
              data-ui-confirm-title="تنفيذ تنظيف الإشعارات؟">
            @csrf
            @method('DELETE')
            <button type="submit" class="ui-btn ui-btn-danger" @disabled($summary['expired'] === 0)>
                <i class="fa-solid fa-broom" aria-hidden="true"></i>
                حذف المنتهية ({{ number_format($summary['expired']) }})
            </button>
        </form>
    </section>

    <section class="ui-card p-5 space-y-4" aria-labelledby="notification-targets-title">
        <h2 id="notification-targets-title" class="ui-title text-xl font-bold">التوزيع حسب نوع المستلم</h2>
        <div class="flex flex-wrap gap-2">
            @forelse($targetCounts as $target => $count)
                <span class="ui-badge ui-badge-info">{{ $target }}: {{ number_format($count) }}</span>
            @empty
                <span class="ui-text-muted">لا توجد بيانات.</span>
            @endforelse
        </div>
    </section>

    <section class="ui-card p-5 space-y-4" aria-labelledby="notification-records-title">
        <h2 id="notification-records-title" class="ui-title text-xl font-bold">أحدث الإشعارات</h2>
        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>العنوان</th>
                        <th>المستلم</th>
                        <th>القناة</th>
                        <th>التاريخ</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                        <tr>
                            <td>#{{ $notification->id }}</td>
                            <td class="ui-title font-bold">{{ $notification->title }}</td>
                            <td>{{ $notification->target_type }}</td>
                            <td>{{ $notification->channel }}</td>
                            <td>{{ $notification->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.notification-operations.destroy', $notification) }}"
                                      data-ui-confirm="سيحذف هذا الإشعار نهائيًا لدى جميع المستلمين."
                                      data-ui-confirm-title="حذف الإشعار #{{ $notification->id }}؟">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-btn ui-btn-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ui-text-muted text-center py-8">لا توجد إشعارات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $notifications->links() }}
    </section>
</div>
@endsection
