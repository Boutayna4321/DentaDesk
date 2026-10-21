<?php

namespace App\Assistant;

use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Explains a patient's message to a dentist who does not read darija.
 *
 * Not a translation: a sentence built from what the model understood (intent, day, time,
 * care) plus a gloss of the darija words it knows. Nothing is invented; the original
 * message always stays on screen next to it.
 */
class Interpreter
{
    private const ACTION = [
        'prendre_rdv' => ['fr' => 'demande un rendez-vous', 'en' => 'is asking for an appointment'],
        'urgence' => ['fr' => 'signale une douleur et veut être reçu au plus vite', 'en' => 'reports pain and wants to be seen as soon as possible'],
        'annuler' => ['fr' => 'veut annuler son rendez-vous', 'en' => 'wants to cancel their appointment'],
        'deplacer' => ['fr' => 'veut déplacer son rendez-vous', 'en' => 'wants to move their appointment'],
        'horaires' => ['fr' => 'demande les horaires d’ouverture', 'en' => 'is asking about opening hours'],
        'adresse' => ['fr' => 'demande l’adresse ou comment venir', 'en' => 'is asking for the address or directions'],
        'question_faq' => ['fr' => 'pose une question', 'en' => 'has a question'],
    ];

    private const SERVICE_EN = [
        'consultation' => 'consultation', 'controle' => 'check-up', 'detartrage' => 'cleaning', 'carie' => 'filling',
        'extraction' => 'extraction', 'blanchiment' => 'whitening', 'implant' => 'implant', 'orthodontie' => 'orthodontics',
        'enfant' => 'child care', 'urgence' => 'emergency',
    ];

    private const PART = [
        'morning' => ['fr' => 'le matin', 'en' => 'in the morning'],
        'afternoon' => ['fr' => 'l’après-midi', 'en' => 'in the afternoon'],
        'evening' => ['fr' => 'en fin de journée', 'en' => 'late in the day'],
    ];

    public function __construct(private NluClient $nlu) {}

    /**
     * @param  CarbonImmutable  $sentAt  "ghedda" means the day after the message was sent, not after today.
     * @return array{sentence:string,glossary:list<array{word:string,fr:string,en:string}>}|null
     */
    public function describe(string $message, CarbonImmutable $sentAt, string $lang = 'fr'): ?array
    {
        $lang = $lang === 'en' ? 'en' : 'fr';
        $key = 'interpret:'.$sentAt->toDateString().':'.md5($message);
        $r = Cache::get($key);
        if ($r === null) {
            $r = $this->nlu->interpret($message, $sentAt->toDateString());
            if ($r === null) {
                return null; // NLU down: the original message is still shown
            }
            Cache::put($key, $r, now()->addDays(30));
        }

        return ['sentence' => $this->sentence($r, $lang), 'glossary' => $r['glossary'] ?? []];
    }

    private function sentence(array $r, string $lang): string
    {
        $e = $r['entities'] ?? [];
        $services = $e['services'] ?? [];
        $forChild = in_array('enfant', $services, true);

        $subject = $lang === 'fr'
            ? ($forChild ? 'Le patient, pour son enfant,' : 'Le patient')
            : ($forChild ? 'The patient, for their child,' : 'The patient');
        $action = self::ACTION[$r['intent']][$lang] ?? ($lang === 'fr' ? 'écrit au cabinet' : 'wrote to the clinic');

        $care = collect($services)->reject(fn ($s) => in_array($s, ['urgence', 'enfant'], true))->first();
        if ($care) {
            $name = $lang === 'fr' ? (Service::where('slug', $care)->value('name') ?? $care) : self::SERVICE_EN[$care];
            $action .= $lang === 'fr' ? ' ('.mb_strtolower($name).')' : " ($name)";
        }

        $when = [];
        if (! empty($e['date'])) {
            $d = CarbonImmutable::parse($e['date']);
            $when[] = $lang === 'fr' ? $d->locale('fr')->translatedFormat('l j F') : $d->locale('en')->translatedFormat('l j F');
        }
        if (! empty($e['part_of_day']) && empty($e['time'])) {
            $when[] = self::PART[$e['part_of_day']][$lang];
        }
        if (! empty($e['time'])) {
            $when[] = ($lang === 'fr' ? 'à ' : 'at ').$e['time'];
        }
        $tail = $when ? ($lang === 'fr' ? ', pour ' : ', for ').implode(' ', $when) : '';

        $sure = ($r['tier'] ?? 'act') === 'act' ? '' : ($lang === 'fr' ? ' (compréhension incertaine)' : ' (uncertain)');

        return "$subject $action$tail.$sure";
    }
}
