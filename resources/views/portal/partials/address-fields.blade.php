<input type="hidden" name="type" value="{{ $type }}">
<div class="grid-form">
    <div><label>Label *</label><input name="label" value="{{ $address?->label }}" required placeholder="{{ $type === 'pickup' ? 'e.g. Main warehouse' : 'e.g. Shah Alam outlet' }}"></div>
    <div><label>Google Maps link</label><input type="url" name="google_maps_url" value="{{ $address?->google_maps_url }}" placeholder="https://maps.app.goo.gl/…"></div>
</div>
<label>Address *</label>
<textarea name="address" rows="2" required>{{ $address?->address }}</textarea>
<div class="grid-form">
    <div><label>Postcode</label><input name="postcode" value="{{ $address?->postcode }}"></div>
    <div><label>City</label><input name="city" value="{{ $address?->city }}"></div>
    <div><label>State</label><input name="state" value="{{ $address?->state }}"></div>
</div>
<label style="display:flex;align-items:center;margin-bottom:.75rem"><input type="checkbox" name="is_default" value="1" @checked($address?->is_default)> Default {{ $type }} address</label>
