{{-- the path at the top of every admin page (user, 4 Nov 2026): see App\Support\PageBreadcrumbs --}}
@php
    $page = \Livewire\Livewire::current();
    $crumbs = $page instanceof \Filament\Pages\Page ? \App\Support\PageBreadcrumbs::for($page) : [];
@endphp
@if ($crumbs !== [])
    {{-- the page title sits close under the breadcrumbs on every page (user, 13 Nov 2026): the page's 2rem gap between
         its parts is taken back here (the -mb-5 class was not in the compiled stylesheet, so it did nothing) --}}
    <x-filament::breadcrumbs :breadcrumbs="$crumbs" class="og-page-breadcrumbs" style="margin-bottom: -1.6rem" />
@endif
