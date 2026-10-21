@php
    $faqs ??= collect();
    $full ??= false;
@endphp
<div class="req">
    <div class="who">
        <div><span class="type {{ $r->type }}">{{ ['booking' => 'Réservation', 'reschedule' => 'Déplacement', 'unanswered' => 'Question', 'cancel' => 'Annulation'][$r->type] }}</span>
            <a class="ms-1 text-reset" href="{{ $r->patient ? route('dash.patients.show', $r->patient) : '#' }}">{{ $r->patient?->full_name ?? 'Inconnu' }}</a></div>
        <span>{{ $r->created_at->diffForHumans() }}</span>
    </div>
    <div class="said">« {{ $r->message }} »</div>
    <div class="sum">{{ $r->type === 'unanswered' ? 'L’assistant n’était pas sûr : il n’a pas répondu.' : 'Compris par l’assistant :' }} <b>{{ $r->type === 'unanswered' ? '' : $r->summary }}</b></div>
    @if ($full && $r->conversation)
        <details class="mt-2 small"><summary class="text-mute">Toute la conversation ({{ $r->conversation->messages->count() }} messages)</summary>
            @foreach ($r->conversation->messages as $m)
                <div class="mt-1"><b class="text-mute">{{ $m->role === 'patient' ? 'Patient' : 'Assistant' }} :</b> {{ $m->body }}</div>
            @endforeach
        </details>
    @endif
    @if ($r->status === 'open')
        @php
            $wa = $r->patient ? 'https://wa.me/212'.substr($r->patient->phone, 1).'?text='.rawurlencode('Bonjour '.$r->patient->first_name.', ici le cabinet snan. ') : null;
        @endphp
        <div class="act">
            @switch($r->type)
                @case('booking')
                    <form method="post" action="{{ route('dash.requests.act', [$r, 'confirm']) }}">@csrf<button class="btn btn-primary btn-sm">Confirmer</button></form>
                    <form method="post" action="{{ route('dash.requests.act', [$r, 'decline']) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Refuser</button></form>
                    @break
                @case('reschedule')
                    <form method="post" action="{{ route('dash.requests.act', [$r, 'accept']) }}">@csrf<button class="btn btn-primary btn-sm">Accepter</button></form>
                    <form method="post" action="{{ route('dash.requests.act', [$r, 'decline']) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Refuser</button></form>
                    @break
                @default
                    @if ($full)
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#faq-{{ $r->id }}">Ajouter à la FAQ</button>
                    @endif
                    <form method="post" action="{{ route('dash.requests.act', [$r, 'done']) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Traité</button></form>
            @endswitch
            @if ($wa)<a class="btn btn-outline-secondary btn-sm" href="{{ $wa }}" target="_blank" rel="noopener">WhatsApp</a>@endif
        </div>
        @if ($full && $r->type === 'unanswered')
            <form class="collapse mt-2 border rounded p-2" id="faq-{{ $r->id }}" method="post" action="{{ route('dash.requests.act', [$r, 'faq']) }}">
                @csrf
                <label class="form-label small fw-bold mb-1">C’est une autre façon de demander…</label>
                <select class="form-select form-select-sm mb-2" name="faq_id">
                    <option value="">— une nouvelle question —</option>
                    @foreach ($faqs as $f)<option value="{{ $f->id }}">{{ $f->question }}</option>@endforeach
                </select>
                <input class="form-control form-control-sm mb-2" name="question" placeholder="Nouvelle question (ex : Le cabinet a-t-il un ascenseur ?)">
                <textarea class="form-control form-control-sm mb-2" name="answer" rows="2" placeholder="Réponse que l’assistant donnera"></textarea>
                <button class="btn btn-primary btn-sm">Enregistrer</button>
            </form>
        @endif
    @endif
</div>
