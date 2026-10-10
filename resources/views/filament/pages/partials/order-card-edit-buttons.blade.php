{{-- Top right of an order card that edits in place: Edit, or Cancel + Save while editing (positive button on the right). --}}
@if ($editing)
    <span class="ow-actions">
        <button type="button" wire:click="{{ $cancel }}" class="ow-btn ow-btn-sm">Cancel</button>
        <button type="button" wire:click="{{ $save }}" wire:loading.attr="disabled" class="ow-btn ow-btn-sm ow-btn-primary">Save</button>
    </span>
@else
    <button type="button" wire:click="{{ $edit }}" class="ow-btn ow-btn-sm">Edit</button>
@endif
