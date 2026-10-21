{{-- Dental chart. Drawn by resources/js/odontogram.js from the data below. --}}
@php
    $editable ??= false;
    $teethData = collect($teeth)->mapWithKeys(fn ($t) => [$t->tooth => ['state' => $t->state, 'surfaces' => (object) ($t->surfaces ?? []), 'note' => $t->note]]);
    $historyData = $patient->toothEvents()->with('user')->take(200)->get()->groupBy('tooth')
        ->map(fn ($events) => $events->take(10)->map(fn ($e) => ['at' => $e->created_at->translatedFormat('j M Y'), 'who' => $e->user?->name, 'what' => $e->summary])->values());
@endphp
<div class="odo2" data-odontogram data-teeth='@json($teethData)' data-history='@json($historyData)' @if ($editable) data-url="{{ route('dash.patients.tooth', $patient) }}" @endif>
    <noscript>Le schéma dentaire a besoin de JavaScript.</noscript>
</div>
