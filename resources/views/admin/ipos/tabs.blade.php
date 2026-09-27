<nav class="tabs admin-tabs" aria-label="IPO sections">
    <a class="tab {{ request()->routeIs('admin.ipos.edit') ? 'active' : '' }}" href="{{ route('admin.ipos.edit', $ipo) }}"><x-icon name="layers" :size="15" /> Listing data</a>
    <a class="tab {{ request()->routeIs('admin.ipos.details.*') ? 'active' : '' }}" href="{{ route('admin.ipos.details.edit', $ipo) }}"><x-icon name="building" :size="15" /> Company &amp; financials</a>
</nav>
