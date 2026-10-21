{{-- FDI numbering, as the dentist reads it facing the patient: 18→11 | 21→28 on top, 48→41 | 31→38 below. --}}
@php
    $editable ??= false;
@endphp
<div class="odo" @if ($editable) data-odontogram="{{ route('dash.patients.tooth', $patient) }}" @endif>
    @foreach ([[range(18, 11), range(21, 28)], [range(48, 41), range(31, 38)]] as $i => [$left, $right])
        <div class="arch {{ $i ? 'low' : '' }}">
            @foreach (array_merge($left, $right) as $n)
                @php $t = $teeth[$n] ?? null; @endphp
                 <button type="button" class="tooth" data-tooth="{{ $n }}" data-state="{{ $t?->state }}" data-note="{{ $t?->note }}" title="{{ $n }}{{ $t ? ' · '.\App\Models\ToothRecord::STATES[$t->state].($t->note ? ' — '.$t->note : '') : '' }}" @disabled(! $editable)>{{ $n }}</button>
            @endforeach
        </div>
    @endforeach
    <div class="legend">
        <span><i style="background:#fde8e8;border:1px solid #e57373"></i>À traiter</span>
        <span><i style="background:#eef3fd;border:1px solid #1f56d6"></i>Soignée</span>
        <span><i style="border:1px dashed #cbd5e6"></i>Absente</span>
        @if ($editable)<span class="ms-auto">Cliquez une dent pour son état et une note</span>@endif
    </div>
</div>
