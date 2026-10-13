<aside class="ui-workspace-sidebar" aria-label="التنقل الرئيسي">
    <div class="ui-workspace-sidebar-heading"><strong data-sidebar-label>مساحة العمل</strong><button type="button" class="ui-btn ui-btn-secondary" data-workspace-collapse aria-label="طي قائمة التنقل" aria-expanded="true"><i class="fa-solid fa-bars" aria-hidden="true"></i></button></div>
    @if($workspaceStore)<p class="ui-text-soft" data-sidebar-label>{{ $workspaceStore->name }}</p>@endif
    <nav class="ui-workspace-links">
        @foreach($workspaceLinks as $link)
            <a class="ui-workspace-link" href="{{ $link['url'] }}" title="{{ $link['title'] }}" @if(url()->current() === $link['url']) aria-current="page" @endif><i class="fa-solid fa-{{ $link['icon'] }}" aria-hidden="true"></i><span data-sidebar-label>{{ $link['title'] }}</span></a>
        @endforeach
    </nav>
</aside>
