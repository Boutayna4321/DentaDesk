<?php

namespace App\Billing;

use App\Models\Setting;

/** What the dentist configures once and every invoice and quote prints. */
class Letterhead
{
    public const FIELDS = [
        'doctor' => 'Nom affiché',
        'specialty' => 'Spécialité / titres',
        'address' => 'Adresse',
        'city' => 'Ville (pour « Fait à … »)',
        'phone' => 'Téléphone',
        'email' => 'Email',
        'inpe' => 'INPE',
        'ice' => 'ICE',
        'if' => 'Identifiant fiscal (IF)',
        'patente' => 'Patente (TP)',
        'cnom' => 'N° d’inscription à l’Ordre',
        'rib' => 'RIB (pour les virements)',
        'footer' => 'Pied de page (texte libre)',
        'invoice_note' => 'Mention en bas des factures',
        'quote_note' => 'Mention en bas des devis',
    ];

    public static function defaults(): array
    {
        return [
            'doctor' => config('cabinet.doctor'), 'specialty' => 'Chirurgien-dentiste',
            'address' => config('cabinet.address'), 'city' => 'Casablanca', 'phone' => config('cabinet.phone'), 'email' => config('cabinet.email'),
            'inpe' => '', 'ice' => '', 'if' => '', 'patente' => '', 'cnom' => '', 'rib' => '',
            // Legal mentions (VAT status, etc.) are left to the dentist: they depend on her own tax situation.
            'footer' => '', 'invoice_note' => '',
            'quote_note' => 'Devis valable 3 mois. À joindre à votre demande de prise en charge.',
            'paper' => 'A4', 'accent' => '#1f56d6', 'logo' => null, 'show_signature' => true,
        ];
    }

    public static function current(): array
    {
        return array_merge(self::defaults(), Setting::get('letterhead', []));
    }

    public static function save(array $values): void
    {
        Setting::put('letterhead', array_merge(self::current(), $values));
    }
}
