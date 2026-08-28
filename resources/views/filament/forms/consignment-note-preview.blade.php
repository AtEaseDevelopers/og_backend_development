@include('documents.consignment-note-styles', ['forPdf' => false])

<div class="csn-document csn-document--screen">
    @include('documents.consignment-note', array_merge($document, ['forPdf' => false]))
</div>
