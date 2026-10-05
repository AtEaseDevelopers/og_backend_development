{{-- Salesperson assignment (salesperson is optional when an order is created; an order without one is "Pending salesperson"). --}}
@if ($can['assign_salesperson'] ?? false)
    <div id="og-assign-salesperson" style="margin-top:.85rem;padding-top:.75rem;border-top:1px solid var(--ow-line);scroll-margin-top:6rem">
        <label class="ow-label">{{ $current ? 'Reassign salesperson (audited)' : 'Assign salesperson' }}</label>
        <div style="display:flex;gap:.5rem">
            <select wire:model="assignSalespersonId" class="ow-select">
                <option value="">— Select salesperson —</option>
                @foreach ($this->salespersonOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <button type="button" wire:click="assignSalesperson" class="ow-btn ow-btn-primary">Assign</button>
        </div>
        @unless ($current)
            <div class="ow-note" style="margin-top:.35rem">Product prices from the UOM price list appear once a salesperson owns the order.</div>
        @endunless
    </div>
@endif
