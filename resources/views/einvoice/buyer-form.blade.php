<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>e-Invoice Buyer Details</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; background: #f3f0e8; color: #1c1c1c; margin: 0; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        h1 { font-size: 1.75rem; margin-bottom: .25rem; }
        .sub { color: #555; margin-bottom: 1.5rem; }
        label { display: block; margin-top: .85rem; font-size: .9rem; }
        input, textarea { width: 100%; padding: .65rem .75rem; border: 1px solid #c9c2b3; border-radius: 6px; background: #fff; box-sizing: border-box; }
        button { margin-top: 1.25rem; background: #1f4b3a; color: #fff; border: 0; padding: .75rem 1.25rem; border-radius: 6px; cursor: pointer; }
        .ok { background: #e7f6ec; border: 1px solid #9ccca8; padding: .75rem; border-radius: 6px; margin-bottom: 1rem; }
        .card { background: #fffdf8; border: 1px solid #ddd4c3; padding: 1.25rem; border-radius: 10px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Buyer details for e-Invoice</h1>
    <p class="sub">Invoice {{ $submission->invoice?->number }} · O&amp;G Transport</p>

    @if(session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif

    <div class="card">
        <form method="post" action="{{ route('einvoice.buyer.store', $submission->buyer_token) }}">
            @csrf
            <label>Buyer name</label>
            <input name="name" value="{{ old('name', $buyer['name'] ?? '') }}" required>
            <label>TIN</label>
            <input name="tin" value="{{ old('tin', $buyer['tin'] ?? '') }}">
            <label>BRN</label>
            <input name="brn" value="{{ old('brn', $buyer['brn'] ?? '') }}">
            <label>ID type</label>
            <input name="id_type" value="{{ old('id_type', $buyer['id_type'] ?? 'BRN') }}">
            <label>ID value</label>
            <input name="id_value" value="{{ old('id_value', $buyer['id_value'] ?? '') }}">
            <label>Address</label>
            <textarea name="address" rows="3">{{ old('address', $buyer['address'] ?? '') }}</textarea>
            <label>Email</label>
            <input name="email" type="email" value="{{ old('email', $buyer['email'] ?? '') }}">
            <label>Phone</label>
            <input name="phone" value="{{ old('phone', $buyer['phone'] ?? '') }}">
            <button type="submit">Save buyer info</button>
        </form>
    </div>
</div>
</body>
</html>
