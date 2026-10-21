<div class="dropdown status-menu">
    <button class="pill st-{{ $appointment->status }} dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">{{ $appointment->status_label }}</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach (\App\Models\Appointment::STATUSES as $key => $label)
            <li><form method="post" action="{{ route('dash.appointments.status', $appointment) }}">@csrf @method('patch')<button class="dropdown-item" name="status" value="{{ $key }}" @disabled($key === $appointment->status)>{{ $label }}</button></form></li>
        @endforeach
    </ul>
</div>
