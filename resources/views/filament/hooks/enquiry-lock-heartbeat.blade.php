{{-- Section A: keeps the enquiry edit lock alive while the quotation form is open (2-second availability check). --}}
<div wire:poll.2s="heartbeat" aria-hidden="true"></div>
