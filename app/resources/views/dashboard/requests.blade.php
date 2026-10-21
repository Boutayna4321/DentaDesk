@extends('layouts.dashboard')
@section('title', 'Demandes')

@section('content')
<div class="top"><h1>Demandes<small>Ce que l’assistant n’a pas réglé seul, ou qui attend votre accord</small></h1></div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="box">
            <div class="hd">En attente <span>{{ $open->count() }}</span></div>
            @forelse ($open as $r)
                @include('dashboard.partials.request', ['r' => $r, 'faqs' => $faqs, 'full' => true])
            @empty
                <p class="text-mute p-4 mb-0">Tout est traité.</p>
            @endforelse
        </div>
    </div>
    <div class="col-xl-5">
        <div class="box">
            <div class="hd">Traitées récemment</div>
            @foreach ($handled as $r)
                <div class="req py-2"><div class="who"><div><span class="type {{ $r->type }}">{{ $r->status === 'dismissed' ? 'Refusée' : 'Faite' }}</span> {{ $r->patient?->full_name }}</div><span>{{ $r->updated_at->diffForHumans() }}</span></div>
                    <div class="sum mt-1">{{ $r->summary }}</div></div>
            @endforeach
        </div>
    </div>
</div>
@endsection
