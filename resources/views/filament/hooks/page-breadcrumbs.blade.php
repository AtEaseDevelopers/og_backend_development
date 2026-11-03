{{-- the path at the top of every admin page (user, 4 Nov 2026): see App\Support\PageBreadcrumbs --}}
@php
    $page = \Livewire\Livewire::current();
    $crumbs = $page instanceof \Filament\Pages\Page ? \App\Support\PageBreadcrumbs::for($page) : [];
@endphp
@if ($crumbs !== [])
    <x-filament::breadcrumbs :breadcrumbs="$crumbs" class="og-page-breadcrumbs -mb-5" />
@endif
