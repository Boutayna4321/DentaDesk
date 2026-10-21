<div class="dropdown status-menu">
    <button class="pill st-{{ $appointment->status }} dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">{{ $appointment->status_label }}</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach (\App\Models\Appointment::STATUSES as $key => $label)
            <li><form method="post" action="{{ route('dash.appointments.status', $appointment) }}" @if (in_array($key, ['cancelled', 'no_show'])) data-confirm="{{ $label }} : {{ $appointment->patient->full_name }}, {{ $appointment->starts_at->format('d/m H:i') }} ?" @endif>@csrf @method('patch')<button class="dropdown-item" name="status" value="{{ $key }}" @disabled($key === $appointment->status)>{{ $label }}</button></form></li>
        @endforeach
    </ul>
</div>
