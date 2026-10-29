<x-mail::message>
{!! preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', nl2br(e($body))) !!}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
