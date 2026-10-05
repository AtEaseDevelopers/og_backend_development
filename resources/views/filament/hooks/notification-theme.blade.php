{{-- Toast notifications: tinted backgrounds so success / warning / error stand out (system-wide). --}}
<style>
    .fi-no-notification.fi-status-success:not(.fi-inline) { background-color: #f0fdf4; box-shadow: 0 0 0 1px #86efac, 0 10px 15px -3px rgb(0 0 0 / .1); }
    .fi-no-notification.fi-status-warning:not(.fi-inline) { background-color: #fffbeb; box-shadow: 0 0 0 1px #fcd34d, 0 10px 15px -3px rgb(0 0 0 / .1); }
    .fi-no-notification.fi-status-danger:not(.fi-inline) { background-color: #fef2f2; box-shadow: 0 0 0 1px #fca5a5, 0 10px 15px -3px rgb(0 0 0 / .1); }
    .fi-no-notification.fi-status-info:not(.fi-inline) { background-color: #eff6ff; box-shadow: 0 0 0 1px #93c5fd, 0 10px 15px -3px rgb(0 0 0 / .1); }

    .dark .fi-no-notification.fi-status-success:not(.fi-inline) { background-color: #052e1a; box-shadow: 0 0 0 1px rgb(34 197 94 / .45); }
    .dark .fi-no-notification.fi-status-warning:not(.fi-inline) { background-color: #3a2a06; box-shadow: 0 0 0 1px rgb(245 158 11 / .45); }
    .dark .fi-no-notification.fi-status-danger:not(.fi-inline) { background-color: #3b0d0d; box-shadow: 0 0 0 1px rgb(239 68 68 / .45); }
    .dark .fi-no-notification.fi-status-info:not(.fi-inline) { background-color: #0b1f3f; box-shadow: 0 0 0 1px rgb(59 130 246 / .45); }
</style>
