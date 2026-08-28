<li class="nav-item {{ request()->is('bob-c*') ? 'active' : '' }}">
    <a class="nav-link" href="{{ url('/bob-c') }}">
        <span>BOB C</span>
    </a>
</li>
