<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['number'] ?? 'Invoice' }}</title>
    @include('documents.invoice-styles', ['forPdf' => true])
</head>
<body>
    @include('documents.invoice', $document)
</body>
</html>
