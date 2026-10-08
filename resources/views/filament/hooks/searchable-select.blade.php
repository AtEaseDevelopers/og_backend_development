{{-- Searchable selects: every plain single <select> opens a "type to filter, then pick" panel instead of the native popup. --}}
{{-- Shared by the admin panel (HEAD_END render hook) and the customer portal layout. Opt a select out with data-native-select. --}}
<link rel="stylesheet" href="{{ asset('css/og/searchable-select.css') }}?v={{ filemtime(public_path('css/og/searchable-select.css')) }}">
<script src="{{ asset('js/og/searchable-select.js') }}?v={{ filemtime(public_path('js/og/searchable-select.js')) }}" defer></script>
