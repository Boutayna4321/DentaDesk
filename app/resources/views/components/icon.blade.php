@props(['name', 'size' => 24, 'stroke' => 'currentColor'])
@php
$paths = [
    'tooth' => '<path d="M8.5 3C5.4 3 4 5.2 4 8c0 3.6 1.4 5.3 2.1 9 .4 2.2 1 3.5 2.1 3.5s1.4-2.2 1.9-4.3c.3-1.4.8-2.2 1.9-2.2s1.6.8 1.9 2.2c.5 2.1.8 4.3 1.9 4.3s1.7-1.3 2.1-3.5C18.6 13.3 20 11.6 20 8c0-2.8-1.4-5-4.5-5-2.2 0-2.9 1.2-3.5 1.2S10.7 3 8.5 3Z"/>',
    'sparkle' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><circle cx="12" cy="12" r="3"/>',
    'implant' => '<path d="M8 3h8l-1 5H9L8 3Z"/><path d="M9.5 8h5M10 11h4M10.5 14h3M11 17h2M11.5 20h1"/>',
    'ortho' => '<rect x="2.5" y="9" width="4" height="6" rx="1"/><rect x="10" y="9" width="4" height="6" rx="1"/><rect x="17.5" y="9" width="4" height="6" rx="1"/><path d="M1 12h22"/>',
    'child' => '<circle cx="12" cy="8" r="4"/><path d="M5 21c1-4 4-6 7-6s6 2 7 6"/><path d="M10 7.5h.01M14 7.5h.01M10.5 10c.8.6 2.2.6 3 0"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/>',
    'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
    'scan' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15c1.5-4 2.5-4 3.5 0s2 4 3.5 0 2-4 3 0"/>',
    'doc' => '<path d="M6 3h9l3 3v15H6z"/><path d="M9 10h6M9 14h6M9 18h4"/>',
    'pin' => '<path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
    'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'chat' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8M8 13h5"/>',
    'send' => '<path d="M4 12 20 4l-6 16-3-7-7-1Z"/>',
    'whatsapp' => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20Z"/><path d="M9 9.5c.3 2 2.5 4.2 4.5 4.5l1-1.2 1.8.9c-.3 1-1.2 1.6-2.3 1.4-2.6-.5-4.9-2.8-5.4-5.4-.2-1.1.4-2 1.4-2.3l.9 1.8-1 1.3"/>',
];
$map = ['consultation' => 'tooth', 'controle' => 'shield', 'detartrage' => 'tooth', 'carie' => 'tooth', 'extraction' => 'tooth',
    'blanchiment' => 'sparkle', 'implant' => 'implant', 'orthodontie' => 'ortho', 'enfant' => 'child', 'urgence' => 'clock'];
$d = $paths[$map[$name] ?? $name] ?? $paths['tooth'];
@endphp
<svg {{ $attributes->merge(['width' => $size, 'height' => $size]) }} viewBox="0 0 24 24" fill="none" stroke="{{ $stroke }}" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $d !!}</svg>
