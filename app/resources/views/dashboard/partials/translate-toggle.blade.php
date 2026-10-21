<form class="translate-toggle" method="post" action="{{ route('dash.translation') }}" title="Langue de l’explication sous chaque message">
    @csrf
    @foreach (['fr' => 'FR', 'en' => 'EN'] as $code => $label)
        <button name="to" value="{{ $code }}" class="{{ session('translate_to', 'fr') === $code ? 'on' : '' }}">{{ $label }}</button>
    @endforeach
</form>
