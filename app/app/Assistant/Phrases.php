<?php

namespace App\Assistant;

use Carbon\CarbonImmutable;

/**
 * What the assistant says, in the patient's own register:
 * da = darija in Latin letters, ar = Arabic script, fr = French.
 */
class Phrases
{
    private const LINES = [
        'greet' => [
            'da' => "Salam! Ana l'assistant dyal :cabinet. N9der nl9a lik rendez-vous, nbeddlo wla nlghih, w njaweb 3la les questions dyalek.",
            'fr' => 'Bonjour ! Je suis l’assistant du cabinet :cabinet. Je peux vous trouver un rendez-vous, le déplacer ou l’annuler, et répondre à vos questions.',
            'ar' => 'السلام! أنا المساعد ديال :cabinet. نقدر نلقى ليك موعد، نبدلو ولا نلغيه، ونجاوب على الأسئلة ديالك.',
        ],
        'offer' => [
            'da' => 'Hadi a9rab blayes l :service:when:',
            'fr' => 'Voici les prochains créneaux pour :service:when :',
            'ar' => 'هادو أقرب المواعيد ل:service:when:',
        ],
        'offer_urgent' => [
            'da' => 'Sme7 lia 3la lwje3. Hadi a9rab blayes:',
            'fr' => 'Désolé pour la douleur. Voici les créneaux les plus proches :',
            'ar' => 'سمح ليا على الوجع. هادو أقرب المواعيد:',
        ],
        'none_then' => [
            'da' => 'Ma b9atch blassa:when. Hadi a9rab blayes men b3d:',
            'fr' => 'Plus rien de libre:when. Voici les créneaux suivants :',
            'ar' => 'ما بقاتش بلاصة:when. هادو المواعيد اللي من بعد:',
        ],
        'none_at_all' => [
            'da' => 'Ma l9itch blassa khawya f jouj simanat jayin. 3ayet l cabinet: :phone',
            'fr' => 'Je ne trouve aucun créneau libre dans les deux semaines. Appelez le cabinet : :phone',
            'ar' => 'ما لقيتش بلاصة خاوية فجوج سيمانات جايين. عيط للعيادة: :phone',
        ],
        'urgent_call' => [
            'da' => 'Ila lwje3 ma kaytsaberch, 3ayet direct: :phone',
            'fr' => 'Si la douleur est trop forte, appelez directement : :phone',
            'ar' => 'إلا الوجع ما كيتصبرش، عيط نيشان: :phone',
        ],
        'pick_slot' => [
            'da' => "Khtar wa7ed men had lw9at, wla 9oul liya chi nhar akhor.",
            'fr' => 'Choisissez un de ces créneaux, ou dites-moi un autre jour.',
            'ar' => 'ختار واحد من هاد الأوقات، ولا قول ليا شي نهار آخر.',
        ],
        'ask_name' => [
            'da' => 'Mzyan, :slot. Chno smitek 3afak?',
            'fr' => 'Parfait, :slot. À quel nom je réserve ?',
            'ar' => 'مزيان، :slot. شنو سميتك عافاك؟',
        ],
        'ask_phone' => [
            'da' => '3tini ra9m dyalek :name, bach l cabinet yconfirmi m3ak f WhatsApp.',
            'fr' => 'Merci :name. Votre numéro de téléphone, pour la confirmation WhatsApp ?',
            'ar' => 'عطيني الرقم ديالك :name، باش العيادة تأكد معاك فالواتساب.',
        ],
        'bad_phone' => [
            'da' => 'Had ra9m ma bantch liya s7i7. Ktbo b7al 06 12 34 56 78.',
            'fr' => 'Je n’ai pas reconnu le numéro. Écrivez-le comme 06 12 34 56 78.',
            'ar' => 'هاد الرقم ما بانش ليا صحيح. كتبو بحال 06 12 34 56 78.',
        ],
        'booked' => [
            'da' => 'Safi :name, tsjel lik :service :slot. L cabinet ghadi yconfirmi m3ak f WhatsApp. Bslama!',
            'fr' => 'C’est noté :name : :service, :slot. Le cabinet vous confirme sur WhatsApp. À bientôt !',
            'ar' => 'صافي :name، تسجل ليك :service :slot. العيادة غادي تأكد معاك فالواتساب. بسلامة!',
        ],
        'slot_taken' => [
            'da' => 'Sme7 lia, had lblassa ba9i khdat chi wa7ed. Hadi lokhrin:',
            'fr' => 'Désolé, ce créneau vient d’être pris. Voici les autres :',
            'ar' => 'سمح ليا، هاد البلاصة باقي خداتها شي واحد. هادو لوخرين:',
        ],
        'clarify' => [
            'da' => 'Bghit nfhemk mzyan. Chno bghiti?',
            'fr' => 'Pour être sûr de bien comprendre, vous voulez :',
            'ar' => 'باش نفهمك مزيان، شنو بغيتي؟',
        ],
        'handover' => [
            'da' => 'Ma bghitch njaweb b chi 7aja machi s7i7a. Ghadi ndouz message dyalek l cabinet w yjawbouk f WhatsApp. 3tini ra9mek 3afak.',
            'fr' => 'Je préfère ne pas vous répondre au hasard : je transmets votre message au cabinet, qui vous répond sur WhatsApp. Votre numéro ?',
            'ar' => 'ما بغيتش نجاوب بشي حاجة ماشي صحيحة. غادي ندوز الميساج ديالك للعيادة ويجاوبوك فالواتساب. عطيني الرقم ديالك عافاك.',
        ],
        'handover_done' => [
            'da' => 'Safi, wsel l cabinet. Ghadi yjawbouk f a9rab w9t.',
            'fr' => 'C’est transmis. Le cabinet vous répond dès que possible.',
            'ar' => 'صافي، وصل للعيادة. غادي يجاوبوك فأقرب وقت.',
        ],
        'hours' => [
            'da' => 'L cabinet m7lol: :hours.',
            'fr' => 'Le cabinet est ouvert : :hours.',
            'ar' => 'العيادة محلولة: :hours.',
        ],
        'address' => [
            'da' => 'Kaynin f :address, :hint. Lmap: :maps',
            'fr' => 'Nous sommes au :address, :hint. Itinéraire : :maps',
            'ar' => 'كاينين ف :address، :hint. الخريطة: :maps',
        ],
        'thanks' => [
            'da' => 'Bla jmil! Ila b9a chi so2al ana hna.',
            'fr' => 'Avec plaisir ! Je reste là si besoin.',
            'ar' => 'بلا جميل! إلا بقا شي سؤال أنا هنا.',
        ],
        'off_topic' => [
            'da' => "Ana ghir l'assistant dyal l cabinet, ma n9derch njaweb 3la hadchi. N9der n3awnek f:",
            'fr' => 'Je suis l’assistant du cabinet, je ne peux pas répondre à ça. Je peux vous aider pour :',
            'ar' => 'أنا غير المساعد ديال العيادة، ما نقدرش نجاوب على هادشي. نقدر نعاونك ف:',
        ],
        'ask_existing_phone' => [
            'da' => '3tini ra9m li drti bih rendez-vous bach nl9ah.',
            'fr' => 'Donnez-moi le numéro utilisé pour la réservation, je retrouve votre rendez-vous.',
            'ar' => 'عطيني الرقم اللي درتي بيه الموعد باش نلقاه.',
        ],
        'no_appointment' => [
            'da' => 'Ma l9it 7ta rendez-vous jay b had ra9m.',
            'fr' => 'Je ne trouve aucun rendez-vous à venir avec ce numéro.',
            'ar' => 'ما لقيت حتى موعد جاي بهاد الرقم.',
        ],
        'confirm_cancel' => [
            'da' => 'L9it rendez-vous dyalek: :service, :slot. Nlghih?',
            'fr' => 'J’ai trouvé : :service, :slot. Je l’annule ?',
            'ar' => 'لقيت الموعد ديالك: :service، :slot. نلغيه؟',
        ],
        'cancelled' => [
            'da' => 'Safi, tlgha. Ila bghiti blassa jdida golha liya.',
            'fr' => 'C’est annulé. Si vous voulez un autre créneau, dites-le moi.',
            'ar' => 'صافي، تلغى. إلا بغيتي بلاصة جديدة قولها ليا.',
        ],
        'kept' => [
            'da' => 'Wakha, rendez-vous dyalek b9a kif ma kan.',
            'fr' => 'D’accord, votre rendez-vous reste inchangé.',
            'ar' => 'واخا، الموعد ديالك بقا كيف ما كان.',
        ],
        'move_offer' => [
            'da' => 'L9it rendez-vous dyalek: :service, :slot. Hadi blayes khawyin:',
            'fr' => 'J’ai trouvé : :service, :slot. Voici des créneaux libres :',
            'ar' => 'لقيت الموعد ديالك: :service، :slot. هادو بلايص خاويين:',
        ],
        'move_requested' => [
            'da' => 'Safi, tlbt men l cabinet ybeddlo l :slot. Ghadi yconfirmiw m3ak f WhatsApp.',
            'fr' => 'C’est demandé : :slot. Le cabinet vous confirme sur WhatsApp.',
            'ar' => 'صافي، طلبت من العيادة يبدلوه ل:slot. غادي يأكدو معاك فالواتساب.',
        ],
        'faq_prefix' => [
            'da' => '',
            'fr' => '',
            'ar' => '',
        ],
        'unavailable' => [
            'da' => "L'assistant ma khddamch daba. 3ayet l cabinet: :phone",
            'fr' => 'L’assistant est momentanément indisponible. Appelez le cabinet : :phone',
            'ar' => 'المساعد ما خدامش دابا. عيط للعيادة: :phone',
        ],
        'other_day' => [
            'da' => 'Wakha. Chmen nhar ywalmek?',
            'fr' => 'D’accord. Quel jour vous arrange ?',
            'ar' => 'واخا. شمن نهار يناسبك؟',
        ],
    ];

    private const CHIPS = [
        'prendre_rdv' => ['da' => 'Nakhod rendez-vous', 'fr' => 'Prendre rendez-vous', 'ar' => 'ناخد موعد'],
        'urgence' => ['da' => '3andi wjee3', 'fr' => 'J’ai mal, urgence', 'ar' => 'عندي وجع'],
        'deplacer' => ['da' => 'Nbeddel rendez-vous', 'fr' => 'Déplacer un rendez-vous', 'ar' => 'نبدل الموعد'],
        'annuler' => ['da' => 'Nlghi rendez-vous', 'fr' => 'Annuler un rendez-vous', 'ar' => 'نلغي الموعد'],
        'horaires' => ['da' => 'Lw9at dyal l cabinet', 'fr' => 'Horaires', 'ar' => 'الأوقات'],
        'adresse' => ['da' => "L'adresse", 'fr' => 'Adresse', 'ar' => 'العنوان'],
        'question_faq' => ['da' => '3andi so2al', 'fr' => 'Poser une question', 'ar' => 'عندي سؤال'],
        'yes' => ['da' => 'Iyeh', 'fr' => 'Oui', 'ar' => 'إيه'],
        'no' => ['da' => 'La', 'fr' => 'Non', 'ar' => 'لا'],
        'other_day' => ['da' => 'Nhar akhor', 'fr' => 'Un autre jour', 'ar' => 'نهار آخر'],
    ];

    private const DAYS = [
        'da' => [1 => 'tnin', 'tlat', 'larb3', 'lkhmis', 'jem3a', 'sebt', 'l7ed'],
        'ar' => [1 => 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد'],
    ];

    private const PARTS = [
        'morning' => ['da' => ' f sba7', 'fr' => ' le matin', 'ar' => ' فالصباح'],
        'afternoon' => ['da' => ' f l3chiya', 'fr' => ' l’après-midi', 'ar' => ' فالعشية'],
        'evening' => ['da' => ' f lkher dyal nhar', 'fr' => ' en fin de journée', 'ar' => ' فآخر النهار'],
    ];

    private const FR_MARKERS = ['je', 'vous', 'est', 'pour', 'le', 'les', 'un', 'une', 'bonjour', 'bonsoir', 'merci', 'avez',
        'voudrais', 'souhaite', 'demain', 'aujourd', 'svp', 'ce', 'mon', 'ma', 'mes', 'des', 'du', 'au', 'quel', 'quelle',
        'pas', 'possible', 'oui', 'non', 'faire', 'ai', 'mal', 'dent', 'la', 'matin', 'peux', 'acceptez', 'combien'];

    private const DA_MARKERS = ['wach', 'bghit', 'bghyt', 'kayn', 'chi', 'dyal', 'dyali', 'ghedda', 'ghda', 'lyouma', 'salam',
        'ana', 'nta', 'nti', '3afak', 'wakha', 'iyeh', 'momkin', 'f', 'mzyan', 'chno', 'fin', 'imta', 'ch7al', 'smiti',
        'blassa', 'nji', 'ndir', 'dersi', 'snani', 'sba7', 'l3chiya', 'safi', 'chokran', 'bzaf', 'mn', 'ra9mi', 'kayderni', 'kaderni', 'bnti', 'wlidi', 'nakhod', 'nlghi', 'nbeddel', 'kat9blo', 'kayn', 'ghir'];

    public static function detect(string $text, string $previous = 'fr'): string
    {
        if (preg_match('/\p{Arabic}/u', $text)) {
            return 'ar';
        }
        // "rendez-vous" and "rdv" are said in darija too, so they prove nothing.
        $text = preg_replace('/\b(rendez-vous|rendez vous|rdv)\b/iu', ' ', $text);
        $words = preg_split('/[^\p{L}\d]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) < 2) {
            return $previous; // "ok", "15:00", "Salma": keep the register we were in
        }
        $da = $fr = 0;
        foreach ($words as $w) {
            $da += in_array($w, self::DA_MARKERS, true) || preg_match('/[a-z][379][a-z]|^[379][a-z]|[a-z][379]$/', $w);
            $fr += in_array($w, self::FR_MARKERS, true);
        }

        return $da > $fr ? 'da' : ($fr > $da ? 'fr' : $previous);
    }

    public static function say(string $key, string $lang, array $vars = []): string
    {
        $line = self::LINES[$key][$lang] ?? self::LINES[$key]['fr'];
        foreach ($vars as $k => $v) {
            $line = str_replace(':'.$k, (string) $v, $line);
        }

        return $line;
    }

    public static function chip(string $key, string $lang, ?string $value = null): array
    {
        return ['label' => self::CHIPS[$key][$lang] ?? self::CHIPS[$key]['fr'], 'value' => $value ?? 'intent:'.$key];
    }

    /** The default menu shown after a greeting or when off topic. */
    public static function menu(string $lang): array
    {
        return array_map(fn ($k) => self::chip($k, $lang), ['prendre_rdv', 'urgence', 'deplacer', 'horaires', 'adresse']);
    }

    public static function slot(CarbonImmutable $at, string $lang, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();
        $time = $at->format('H:i');
        $relative = match ((int) $at->startOfDay()->diffInDays($today->startOfDay(), true)) {
            0 => ['da' => 'lyouma', 'fr' => 'aujourd’hui', 'ar' => 'اليوم'][$lang],
            1 => ['da' => 'ghedda', 'fr' => 'demain', 'ar' => 'غدا'][$lang],
            default => null,
        };

        return match ($lang) {
            'da' => ($relative ?? self::DAYS['da'][$at->isoWeekday()].' '.$at->format('d/m')).' m3a '.$time,
            'ar' => ($relative ?? self::DAYS['ar'][$at->isoWeekday()].' '.$at->format('d/m')).' على '.$time,
            default => ($relative ?? $at->locale('fr')->translatedFormat('l j F')).' à '.$time,
        };
    }

    /** Short label for a slot chip: "Mer 22 · 10:30". */
    public static function slotChip(CarbonImmutable $at, string $lang): string
    {
        $day = match ($lang) {
            'da' => ucfirst(self::DAYS['da'][$at->isoWeekday()]),
            'ar' => self::DAYS['ar'][$at->isoWeekday()],
            default => ucfirst($at->locale('fr')->translatedFormat('D')),
        };

        return $day.' '.$at->format('j').' · '.$at->format('H:i');
    }

    public static function when(?string $date, ?string $part, string $lang): string
    {
        $out = '';
        if ($date) {
            $out .= ' '.self::slotDay(CarbonImmutable::parse($date), $lang);
        }
        if ($part) {
            $out .= self::PARTS[$part][$lang];
        }

        return $out;
    }

    private static function slotDay(CarbonImmutable $day, string $lang): string
    {
        $diff = $day->diffInDays(CarbonImmutable::today(), true);
        if ($diff < 2) {
            return [0 => ['da' => 'lyouma', 'fr' => 'aujourd’hui', 'ar' => 'اليوم'], 1 => ['da' => 'ghedda', 'fr' => 'demain', 'ar' => 'غدا']][(int) $diff][$lang];
        }

        return match ($lang) {
            'da' => 'nhar '.self::DAYS['da'][$day->isoWeekday()],
            'ar' => 'نهار '.self::DAYS['ar'][$day->isoWeekday()],
            default => $day->locale('fr')->translatedFormat('l j'),
        };
    }
}
