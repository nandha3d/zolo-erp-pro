{{-- Draft tabs for a classic document form (public/js/form-drafts.js). Include inside the page's scripts stack, after the page's own scripts.
     $draft: kind, label, shortcut, and optionally form (selector; absent on a register page), source ([id, reference] of a returned bill),
     createUrl (entry page of a source bill), newUrl (where a new document starts), party. --}}
<script type="application/json" id="form-draft-data">@json(['baseUrl' => url('documents/'.$draft['kind'])] + $draft)</script>
<script src="{{ asset('js/document-drafts.js') }}?v={{ filemtime(public_path('js/document-drafts.js')) }}"></script>
<script src="{{ asset('js/form-drafts.js') }}?v={{ filemtime(public_path('js/form-drafts.js')) }}"></script>
