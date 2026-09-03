{{-- 0609 admin sidebar item. Staff only. Merchants do not see or upload this list. --}}
<li class="nav-item">
    <a class="nav-link {{ request()->is('admin/ftd-trusted*') ? 'active' : '' }}" href="{{ route('admin.ftd-trusted.index') }}">
        FTD vs trusted
    </a>
</li>
