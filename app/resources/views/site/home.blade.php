@extends('layouts.site')

@section('content')
<div class="infobar d-none d-md-block">
    <div class="container d-flex justify-content-between py-2">
        <div><span><x-icon name="phone" size="13" class="me-1" />{{ $cabinet['phone'] }}</span><span><x-icon name="mail" size="13" class="me-1" />{{ $cabinet['email'] }}</span></div>
        <div><span><x-icon name="pin" size="13" class="me-1" />{{ $cabinet['address'] }}</span><span>{{ ucfirst($hours[0]['days'] ?? '') }} · {{ str_replace(', ', ' / ', $hours[0]['hours'] ?? '') }}</span></div>
    </div>
</div>

<nav class="navbar navbar-expand-lg site-nav sticky-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
            <x-icon name="tooth" size="30" stroke="#1f56d6" />
            <span>DentaDesk<small>CABINET DENTAIRE</small></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link active" href="#top">Accueil</a></li>
                <li class="nav-item"><a class="nav-link" href="#cabinet">Le cabinet</a></li>
                <li class="nav-item"><a class="nav-link" href="#soins">Soins</a></li>
                <li class="nav-item"><a class="nav-link" href="#avis">Avis</a></li>
                <li class="nav-item"><a class="nav-link" href="#questions">Questions</a></li>
                <li class="nav-item"><a class="nav-link" href="#acces">Accès</a></li>
            </ul>
            <a href="#reserver" class="btn btn-primary">Prendre rendez-vous</a>
        </div>
    </div>
</nav>

<header class="hero" id="top">
    <img class="photo" src="/img/hero.jpg" alt="Un patient sourit au fauteuil pendant un soin">
    <div class="fade-l"></div>
    <div class="container inner">
        <div class="col-lg-6" data-reveal>
            <div class="kick">Des soins doux · un sourire sain</div>
            <h1>Votre sourire,<span>entre de bonnes mains.</span></h1>
            <p class="lead-sm">Au cabinet du {{ $cabinet['doctor'] }}, on prend le temps d’expliquer chaque soin. Réservez en ligne en 40 secondes, on vous confirme sur WhatsApp.</p>
            <div class="d-flex gap-2 flex-wrap mt-4">
                <a href="#reserver" class="btn btn-primary"><x-icon name="calendar" size="16" class="me-1" /> Réserver un créneau</a>
                <a href="#soins" class="btn btn-outline-primary">Nos soins →</a>
            </div>
            <div class="feats">
                <div class="feat"><x-icon name="scan" stroke="#1f56d6" />Radio numérique<br>sur place</div>
                <div class="feat"><x-icon name="shield" stroke="#1f56d6" />Soins<br>sans douleur</div>
                <div class="feat"><x-icon name="doc" stroke="#1f56d6" />CNSS &amp;<br>mutuelles</div>
                <div class="feat"><x-icon name="clock" stroke="#1f56d6" />Urgence le<br>jour même</div>
            </div>
        </div>
    </div>
    <div class="urgent-card">
        <b>URGENCE DENTAIRE</b>Une douleur, une dent cassée&nbsp;? On vous reçoit aujourd’hui.
        <a href="tel:{{ str_replace(' ', '', $cabinet['emergency_phone']) }}">{{ $cabinet['emergency_phone'] }}</a>
    </div>
</header>

{{-- Booking bar: service → day → time → name + phone. Slots come from /api, never from here. --}}
<section class="container" id="reserver">
    <div class="booking" data-booking>
        <div class="row g-0">
            <div class="col-lg-2 step">
                <label class="cap" for="bk-service"><i class="n">1</i>Soin</label>
                <select class="form-select" id="bk-service" data-service>
                    @foreach ($services->where('bookable_online', true) as $s)
                        <option value="{{ $s->slug }}" @selected($s->slug === 'consultation')>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-5 step">
                <label class="cap"><i class="n">2</i>Jour</label>
                <div class="chips" data-days><span class="skeleton"></span><span class="skeleton"></span><span class="skeleton"></span></div>
            </div>
            <div class="col-lg step">
                <label class="cap"><i class="n">3</i>Heure <span class="text-primary" data-free></span></label>
                <div class="chips" data-slots><span class="text-mute small">Choisissez un jour</span></div>
            </div>
            <div class="col-lg-auto go">
                <button class="btn btn-primary w-100" data-continue disabled>Choisissez une heure</button>
            </div>
        </div>
        <form class="booking-form" data-form hidden novalidate>
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small fw-bold" for="bk-first">Prénom</label><input class="form-control" id="bk-first" name="first_name" required autocomplete="given-name"></div>
                <div class="col-md-3"><label class="form-label small fw-bold" for="bk-last">Nom</label><input class="form-control" id="bk-last" name="last_name" autocomplete="family-name"></div>
                <div class="col-md-3"><label class="form-label small fw-bold" for="bk-phone">Téléphone</label><input class="form-control" id="bk-phone" name="phone" type="tel" required placeholder="06 12 34 56 78" autocomplete="tel"><div class="invalid-feedback" data-phone-error></div></div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Confirmer <span data-summary></span></button></div>
            </div>
            <p class="small text-mute mt-2 mb-0" data-error></p>
        </form>
        <div class="booking-done" data-done hidden>
            <div class="tick">✓</div>
            <div class="flex-grow-1"><b data-done-title></b><div class="small text-mute">Le cabinet vous confirme sur WhatsApp. Un empêchement&nbsp;? Écrivez à l’assistant.</div></div>
            <a class="btn btn-outline-primary" data-ics download="rendez-vous-dentadesk.ics"><x-icon name="calendar" size="16" class="me-1" />Ajouter à mon agenda</a>
            <a class="btn btn-outline-primary" data-whatsapp target="_blank" rel="noopener"><x-icon name="whatsapp" size="16" class="me-1" />Envoyer sur WhatsApp</a>
            <button class="btn btn-link btn-sm" type="button" data-again>Réserver un autre rendez-vous</button>
        </div>
    </div>
</section>

<section class="sec text-center" id="soins">
    <div class="container">
        <span class="kick">Nos soins</span>
        <h2>Toute la famille, au même cabinet</h2>
        <div class="row g-3 mt-3 row-cols-2 row-cols-md-3 row-cols-lg-5">
            @foreach ($services as $s)
                <div class="col" data-reveal>
                    <div class="svc">
                        <div class="ic"><x-icon :name="$s->slug" size="26" stroke="#1f56d6" /></div>
                        <b>{{ $s->name }}</b>
                        <p>{{ $s->summary }}</p>
                        <button type="button" data-book-service="{{ $s->slug }}">Réserver →</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="sec" id="cabinet">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6" data-reveal>
                <div class="about-photo">
                    <img src="/img/about.jpg" alt="La dentiste explique le plan de soins à une patiente">
                    <div class="badge-years"><b>15 ans</b>au service de Maârif</div>
                </div>
            </div>
            <div class="col-lg-6" data-reveal>
                <span class="kick">Le cabinet</span>
                <h2>On explique avant de soigner.</h2>
                <p class="lead-sm mt-3">Chaque traitement commence par un examen et un plan écrit, que vous gardez. Vous savez ce qu’on fait, pourquoi, et en combien de séances.</p>
                <ul class="checks">
                    <li><i>✓</i>Radio panoramique numérique sur place</li>
                    <li><i>✓</i>Feuille CNSS et mutuelle remplie au cabinet</li>
                    <li><i>✓</i>Rappel WhatsApp la veille de votre rendez-vous</li>
                    <li><i>✓</i>On parle darija, français, arabe et anglais, l’assistant aussi</li>
                </ul>
                <div class="doctor">
                    <img src="/img/doctor.jpg" alt="{{ $cabinet['doctor'] }}">
                    <div><b>{{ $cabinet['doctor'] }}</b><div class="small text-mute">Chirurgien-dentiste, diplômée de la Faculté de médecine dentaire de Casablanca</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="band">
    <div class="container"><div class="row g-3">
        <div class="col-6 col-md-3"><b data-count="6000">6 000+</b><span>patients suivis</span></div>
        <div class="col-6 col-md-3"><b>15 ans</b><span>d’expérience</span></div>
        <div class="col-6 col-md-3"><b>4,9 / 5</b><span>sur 312 avis Google</span></div>
        <div class="col-6 col-md-3"><b>&lt; 24 h</b><span>pour une urgence</span></div>
    </div></div>
</div>

<section class="sec" id="avis">
    <div class="container">
        <div class="text-center"><span class="kick">Avis patients</span><h2>Ce que disent nos patients</h2></div>
        <div class="row g-3 mt-3">
            @foreach ([
                ['J’avais peur du dentiste depuis l’enfance. Le docteur m’a tout expliqué avant de commencer, je n’ai rien senti.', 'Salma B.', 'Maârif'],
                ['Réservé à 23h sur le site, confirmé sur WhatsApp le matin. Pas d’attente au cabinet.', 'Youssef E.', 'Bourgogne'],
                ['Ma fille de 6 ans est ressortie en riant. On revient pour son contrôle dans 6 mois.', 'Imane K.', 'Racine'],
            ] as [$text, $who, $where])
                <div class="col-md-4" data-reveal><div class="review"><div class="stars">★★★★★</div>{{ $text }}<div class="who">{{ $who }} <span>· {{ $where }}</span></div></div></div>
            @endforeach
        </div>
    </div>
</section>

<section class="sec faq" id="questions">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <span class="kick">Questions</span>
                <h2>Ce qu’on nous demande souvent</h2>
                <p class="lead-sm mt-3">Une autre question&nbsp;? Écrivez-la à l’assistant, en darija si vous voulez. S’il n’est pas sûr de la réponse, il la transmet au cabinet.</p>
                <button class="btn btn-outline-primary mt-2" data-open-assistant><x-icon name="chat" size="16" class="me-1" />Poser une question</button>
            </div>
            <div class="col-lg-8">
                <div class="accordion" id="faq">
                    @foreach ($faqs as $f)
                        <div class="accordion-item">
                            <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $f->id }}">{{ $f->question }}</button></h3>
                            <div id="faq{{ $f->id }}" class="accordion-collapse collapse" data-bs-parent="#faq"><div class="accordion-body">{{ $f->answer }}</div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="sec" id="acces">
    <div class="container">
        <div class="access row g-0">
            <div class="col-lg-5 p-4 p-lg-5">
                <span class="kick">Accès &amp; horaires</span>
                <h2>Au cœur de Maârif</h2>
                <p class="text-mute mt-2 mb-4">{{ $cabinet['address'] }}<br>{{ $cabinet['address_hint'] }}</p>
                @foreach ($hours as $h)
                    <div class="hours-row"><span>{{ $h['days'] }}</span><b>{{ $h['hours'] }}</b></div>
                @endforeach
                <div class="hours-row"><span>Dimanche</span><b class="text-mute">Fermé · urgences au {{ $cabinet['emergency_phone'] }}</b></div>
                <div class="d-flex gap-2 mt-4 flex-wrap">
                    <a class="btn btn-primary" href="{{ $cabinet['maps_url'] }}" target="_blank" rel="noopener"><x-icon name="pin" size="16" class="me-1" />Itinéraire</a>
                    <a class="btn btn-outline-primary" href="tel:{{ str_replace(' ', '', $cabinet['phone']) }}"><x-icon name="phone" size="16" class="me-1" />{{ $cabinet['phone'] }}</a>
                </div>
            </div>
            {{-- The map loads only on request: no Google cookies for visitors who never look at it. --}}
            <div class="col-lg-7 map-slot" data-map="https://maps.google.com/maps?q=Boulevard%20Al%20Massira%20Maarif%20Casablanca&z=15&output=embed">
                <img src="/img/room.jpg" alt="" class="map-cover">
                <button type="button" class="btn btn-light shadow-sm" data-load-map><x-icon name="pin" size="16" class="me-1" />Afficher la carte <small class="d-block text-mute fw-normal">Google Maps</small></button>
            </div>
        </div>
    </div>
</section>

<footer class="site">
    <div class="container d-flex flex-wrap justify-content-between gap-3">
        <div><b>DentaDesk</b> · cabinet dentaire du {{ $cabinet['doctor'] }}<br>{{ $cabinet['address'] }}</div>
        <div>Projet de démonstration conçu et développé par <a href="https://instagram.com/boutayna_metarfi" target="_blank" rel="noopener">@boutayna_metarfi</a></div>
    </div>
</footer>

{{-- Assistant --}}
<button class="assistant-fab" data-open-assistant aria-label="Ouvrir l’assistant">
    <span class="dot"><x-icon name="chat" size="16" /></span>
    <span>Une question&nbsp;?<small>Darija, français, عربي, English</small></span>
</button>
<div class="assistant" data-assistant hidden role="dialog" aria-label="Assistant du cabinet">
    <div class="hd">
        <span class="live"></span>
        <div><b>Assistant DentaDesk</b><small>Répond tout de suite · darija · FR · عربي · EN</small></div>
        <button class="btn-close btn-close-white" data-close-assistant aria-label="Fermer"></button>
    </div>
    <div class="log" data-log aria-live="polite"></div>
    <form data-chat-form>
        <input name="message" placeholder="Ex : kayn chi blassa ghedda f sba7 ?" autocomplete="off" maxlength="500">
        <button aria-label="Envoyer"><x-icon name="send" size="18" /></button>
    </form>
    <div class="note">L’assistant ne donne pas d’avis médical. En cas d’urgence grave, appelez le {{ $cabinet['emergency_phone'] }}.</div>
</div>
@endsection
