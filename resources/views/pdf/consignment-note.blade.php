<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['number'] ?? 'Consignment Note' }}</title>
    @include('documents.consignment-note-styles', ['forPdf' => true])
</head>
<body>
    @include('documents.consignment-note', array_merge($document, ['forPdf' => true]))
</body>
</html>
