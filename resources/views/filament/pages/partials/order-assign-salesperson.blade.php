{{--
    Salesperson of the ownership card (salesperson is optional when an order is created; an order without one is
    "Pending salesperson"): the name, or in edit mode (Edit at the top right of the card) a dropdown saved with Save
    (audited reassign).
--}}
@if ($this->editingOwnership && ($can['assign_salesperson'] ?? false))
    <select wire:model="assignSalespersonId" class="ow-select" aria-label="Salesperson">
        <option value="">— Select salesperson —</option>
        @foreach ($this->salespersonOptions() as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </select>
    @unless ($current)
        <div class="ow-note" style="margin-top:.35rem">Product prices from the UOM price list appear once a salesperson owns the order.</div>
    @endunless
@else
    {!! $current ? e($current) : '<span class="ow-pill ow-pill-action">Pending salesperson</span>' !!}
@endif
