"""Phrasings per intent. Darija in Latin script, darija in Arabic script, French, Arabic, English.

Fillers in {braces} come from FILLERS. Keep each intent between 25 and 45 templates so no
class drowns the others.
"""

FILLERS = {
    "day": ["ghedda", "ghda", "lyouma", "ba3d ghedda", "nhar jem3a", "nhar sebt", "nhar tnin", "larb3", "lkhmis",
            "demain", "aujourd'hui", "vendredi", "samedi", "lundi", "jeudi", "la semaine prochaine", "simana jaya"],
    "day_ar": ["غدا", "اليوم", "نهار الجمعة", "نهار السبت", "الاثنين", "الخميس", "بعد غدا"],
    "part": ["f sba7", "f l3chiya", "sba7", "l3chiya", "le matin", "l'après-midi", "en fin de journée", "f lil"],
    "time": ["10h", "11h30", "15h", "16h", "m3a l3achra", "m3a jouj", "m3a ttlata w nos", "9h30", "14h"],
    "service": ["détartrage", "detartrage", "tnqiya", "blanchiment", "tbyed snani", "implant", "appareil", "aligneurs",
                "consultation", "contrôle", "carie", "sossa", "plombage", "extraction", "n9le3 dersa"],
    "service_ar": ["تنظيف السنان", "تبييض السنان", "تقويم", "زرع", "حشوة", "نقلع ضرسة"],
    "kid": ["wlidi", "bnti", "weldi", "mon fils", "ma fille", "lwlad", "bniti"],
    "tooth": ["dersi", "derssa", "snani", "ma dent", "une molaire", "snn", "dersa lfou9", "dersa lte7t"],
    "day_en": ["tomorrow", "today", "on friday", "on saturday", "next monday", "on thursday", "the day after tomorrow", "next week"],
    "part_en": ["in the morning", "in the afternoon", "after work", "this evening", "around noon"],
    "time_en": ["10am", "3pm", "11:30", "9 am", "4 pm"],
    "service_en": ["a cleaning", "teeth whitening", "an implant", "braces", "a checkup", "a filling", "an extraction", "a consultation"],
    "kid_en": ["my son", "my daughter", "my kid", "my little boy"],
    "tooth_en": ["my tooth", "a molar", "my wisdom tooth", "my front tooth", "my gums"],
}

# English, added after the first model: tourists, expats and students in Casablanca.
ENGLISH = {
    "prendre_rdv": [
        "I'd like to book an appointment", "Can I get an appointment {day_en} {part_en}?", "Do you have anything {day_en}?",
        "I need {service_en}", "Book me in for {service_en} {day_en}", "Is there a slot {day_en} at {time_en}?",
        "I want to see the dentist {day_en}", "Appointment for {kid_en} please", "any availability {day_en}",
        "Can I come in {day_en} {part_en}", "I'd like {service_en}, when are you free?", "booking for {service_en}",
    ],
    "urgence": [
        "I have a terrible toothache", "{tooth_en} hurts a lot", "My tooth broke, can you see me today?", "I think I have an abscess",
        "My face is swollen", "It's an emergency", "{kid_en} has a toothache", "{tooth_en} is bleeding",
        "I couldn't sleep because of the pain", "severe pain in {tooth_en}, urgent", "I chipped {tooth_en}", "my filling fell out and it hurts",
    ],
    "annuler": [
        "I need to cancel my appointment", "Please cancel my booking {day_en}", "I can't make it {day_en}", "cancel it please",
        "I won't be able to come", "Cancel the appointment for {kid_en}", "I have to cancel, sorry",
    ],
    "deplacer": [
        "Can I reschedule my appointment?", "I'd like to move my appointment to {day_en}", "Can we change it to {day_en} {part_en}?",
        "Could I come {day_en} instead?", "Push my appointment back please", "I need a different time", "move my booking to {time_en}",
    ],
    "horaires": [
        "What are your opening hours?", "When are you open?", "Are you open {day_en}?", "What time do you close?",
        "Are you open on Sundays?", "What time do you open in the morning?", "opening times please",
    ],
    "adresse": [
        "Where are you located?", "What's the address?", "How do I get to the clinic?", "Is there parking nearby?",
        "Which floor is it on?", "Send me the location", "Are you near the tram?",
    ],
    "question_faq": [
        "Do you take insurance?", "Do you accept CNSS?", "How much is {service_en}?", "Does {service_en} hurt?",
        "Can I pay by card?", "Do you treat children?", "Does the dentist speak English?", "Do you do clear aligners?",
        "How long does {service_en} take?", "Can I get a quote?",
    ],
    "salutation": ["hello", "hi there", "good morning", "good evening", "hey", "hello doctor"],
    "merci": ["thank you", "thanks a lot", "thanks!", "much appreciated", "great, thank you", "cheers"],
    "affirmer": ["yes", "yes please", "sure", "that works", "sounds good", "perfect", "that one", "ok great"],
    "nier": ["no", "no thanks", "not that one", "that doesn't work", "another time please", "nope, something else"],
    "hors_sujet": ["what's the weather like", "are you a robot?", "tell me a joke", "who won the game", "do you know a good restaurant",
                   "I'm looking for a pharmacy", "are you hiring?", "what is your name"],
}


TEMPLATES = {
    "prendre_rdv": [
        "bghit rendez-vous", "bghit nakhod rendez-vous {day}", "bghit rdv {day} {part}", "wach kayn chi blassa {day}",
        "wach kayn chi rendez-vous {day} {part}", "kayn chi creneau {day}?", "bghit nji {day} {part}",
        "momkin nji {day} m3a {time}", "bghit ndir {service}", "bghit ndir {service} {day}", "3andkom blassa l {service}?",
        "bghit nakhod mow3id", "bghit nchouf tbiba", "bghit nchouf docteur {day}", "ila kan momkin {day} {part}",
        "bghit rdv l {kid}", "{kid} khasso controle, kayn chi blassa {day}?", "bghit nbooki {day}",
        "Je voudrais prendre rendez-vous", "Je souhaite un rendez-vous {day} {part}", "Avez-vous un créneau {day} ?",
        "Est-ce possible de venir {day} à {time} ?", "Je voudrais faire un {service}", "Rendez-vous pour {service} svp",
        "Bonjour, je cherche un rdv pour {kid}", "c'est possible {day} {part} ?", "Une place disponible {day} ?",
        "بغيت موعد", "بغيت ناخد موعد {day_ar}", "واش كاين شي بلاصة {day_ar}", "بغيت ندير {service_ar}",
        "أريد حجز موعد", "هل يوجد موعد {day_ar}؟", "أود حجز موعد عند الطبيبة", "bghit nji ndir {service} {day} {part}",
        "rdv {day} {time} possible?", "dispo {day}?", "chi blassa {part}?", "mow3id {day}", "nqder nji {day}?",
    ],
    "urgence": [
        "3andi wjee3 f {tooth}", "3andi wje3 bzaf f {tooth} kayn chi blassa daba", "{tooth} kaderni bzaf",
        "{tooth} kayderni mn lbare7", "ma n3ast lbare7 mn {tooth}", "wjee3 kbir f {tooth} 3afak lyouma",
        "{tooth} tkesrat", "therrsat liya dersa", "fommi mnfou5", "ntfa5 lia wjhi mn {tooth}",
        "{kid} kaderha {tooth} bzaf", "{kid} tay bki mn {tooth}", "urgent 3afak", "darouri lyouma", "c'est urgent",
        "J'ai très mal à {tooth}", "J'ai une douleur insupportable", "Ma dent s'est cassée", "J'ai un abcès",
        "J'ai la joue gonflée", "Mal de dents depuis hier soir, vous pouvez me prendre aujourd'hui ?",
        "Urgence dentaire, je saigne", "Mon fils a mal à une dent, c'est urgent", "Je n'ai pas dormi à cause d'une dent",
        "عندي وجع فضرسي", "ضرسي كيضرني بزاف", "تهرسات ليا سنة", "ولدي كيضرو سنو", "حالة مستعجلة", "عندي ألم شديد في الضرس",
        "fommi kaysil dem", "kaysil lia dem mn {tooth}", "sakhfa mn lwje3", "ma9adach nakol mn lwje3",
        "wjee3 f {tooth} ma kaytsaliche", "3andi infection f {tooth}", "{tooth} 3amra dem w kaderni",
    ],
    "annuler": [
        "bghit nlghi rendez-vous", "bghit nannuler rdv dyali", "ma ghadich nji {day}", "ma n9derch nji {day}",
        "lghi lia rendez-vous 3afak", "sme7 lia ma nqderch nji", "annuli rdv dyal {day}", "ghadi nsafer, lghiw rdv",
        "Je dois annuler mon rendez-vous", "Annuler mon rdv de {day} svp", "Je ne pourrai pas venir {day}",
        "Merci d'annuler le rendez-vous de {kid}", "Je ne viendrai pas", "annulation svp",
        "بغيت نلغي الموعد", "ما غاديش نجي {day_ar}", "أريد إلغاء الموعد", "الغي ليا الموعد عافاك",
        "rdv dyal {kid} lghih", "ma bqitch baghi rendez-vous", "trat lia mochkila ma nqderch nji", "lghi lmow3id",
        "khrej lia chi 7aja, ma ghadich nji", "rani mrida ma nqderch nji {day}", "cancel rdv",
        "Je préfère annuler finalement", "supprimez mon rendez-vous",
    ],
    "deplacer": [
        "bghit nbeddel rendez-vous", "bghit nbeddel rdv l {day}", "momkin nbeddel l {day} {part}",
        "bdel lia rdv dyali l {day}", "nqder nji {day} blast {day}?", "bghit nakhro rdv", "bghit n9eddem rdv",
        "rdv dyal {kid} bghit nbeddlo l {day}", "ma nqderch {day}, momkin {day}?", "bdlo lia lw9t l {time}",
        "Je voudrais déplacer mon rendez-vous", "Est-ce possible de décaler à {day} ?", "Changer mon rdv pour {day} {part}",
        "Je peux venir {day} au lieu de {day} ?", "Reporter mon rendez-vous à {day}", "Avancer mon rdv si possible",
        "بغيت نبدل الموعد", "بدل ليا الموعد ل{day_ar}", "أريد تغيير موعدي", "واش نقدر نبدل الموعد",
        "bghit nbeddel lw9t", "khlliha {day} blast lyouma", "decaler rdv {day}", "rdv dyali f {time} bghito {part}",
        "wach momkin n2ekhro rdv chwiya", "Modifier mon rendez-vous svp",
    ],
    "horaires": [
        "fo9ach kat7elo", "imta kat7elo", "imta katsdo", "ch7al d sa3a kat7elo", "wach m7lolin {day}",
        "wach khddamin nhar l7ed", "wach m7lolin f sebt", "les horaires dyalkom", "chno lw9t dyal lkhdma",
        "wach kat7elo f lil", "katkhdmo f ramadan?", "Quels sont vos horaires ?", "Vous êtes ouverts {day} ?",
        "Vous fermez à quelle heure ?", "Ouvert le samedi ?", "Horaires d'ouverture svp", "Vous ouvrez à quelle heure ?",
        "إمتى كتحلو", "شنو هي أوقات العمل", "واش محلولين نهار السبت", "ما هي ساعات العمل", "حتى لإمتى محلولين",
        "jusqu'à quelle heure ?", "wach m7lolin daba?", "fin wsl lw9t dyal sedan", "horaires", "wach khdamin lyouma",
    ],
    "adresse": [
        "fin jaya l3iyada", "fin kaynin", "3tini l'adresse", "l'adresse 3afak", "kifach nwsel lkom", "fin jayin f lma3arif",
        "chmen zen9a", "9rib l chi 7aja?", "kayn parking?", "fin nparki", "wach 9rib l tram", "localisation svp",
        "Où se trouve le cabinet ?", "Quelle est votre adresse ?", "Comment venir au cabinet ?", "Vous êtes où exactement ?",
        "Il y a un parking ?", "C'est près du tram ?", "Envoyez-moi la localisation", "C'est à quel étage ?",
        "فين جات العيادة", "عطيني العنوان", "كيفاش نوصل ليكم", "أين يقع العيادة", "واش كاين الباركينغ", "location",
        "position google maps", "f ina 3imara", "ch7al mn tabe9",
    ],
    "question_faq": [
        "wach kat9blo cnss", "wach kat9blo la cnops", "wach kat9blo l'assurance", "wach kat3mro feuille de soins",
        "ch7al taman {service}", "ch7al kayswa {service}", "bch7al {service}", "ch7al d sa3a kaykhod {service}",
        "wach {service} kayderr", "wach kayn takhfid", "wach nkhles b carte", "wach katkhdmo m3a drari sghar",
        "wach tbiba mra", "wach kaydirou {service}", "wach khass nsom 9bel {service}", "wach kaynin facilites f lkhlas",
        "Vous acceptez la CNSS ?", "Vous prenez la mutuelle ?", "Combien coûte un {service} ?", "Le {service} fait mal ?",
        "Je peux payer par carte ?", "Vous soignez les enfants ?", "Faites-vous le {service} ?", "Paiement en plusieurs fois ?",
        "Combien de séances pour un {service} ?", "Est-ce que le docteur parle anglais ?",
        "واش كتقبلو التعاضدية", "شحال كيسوا {service_ar}", "واش {service_ar} كيضر", "واش كتقبلو CNSS",
        "هل تقبلون التأمين", "كم ثمن {service_ar}", "wach kat3tiw devis", "wach kayn radio panoramique",
    ],
    "salutation": [
        "salam", "salam 3likom", "slm", "salam alikoum", "ahlan", "bonjour", "bonsoir", "salut", "hello", "hi",
        "سلام", "السلام عليكم", "مرحبا", "صباح الخير", "مساء الخير", "salam labas", "labas 3likom", "sba7 lkhir",
        "msa lkhir", "coucou", "bjr", "Bonjour madame", "salam docteur", "Bonjour docteur",
    ],
    "merci": [
        "chokran", "choukran bzaf", "merci", "merci beaucoup", "lah ykhlik", "lah yjazik bikhir", "baraka lah fik",
        "شكرا", "شكرا بزاف", "الله يخليك", "بارك الله فيك", "merci bien", "thanks", "top merci", "lah ybarek fik",
        "mzyan chokran", "safi chokran", "Parfait merci", "c'est noté merci", "tbarkallah 3likom",
    ],
    "affirmer": [
        "wakha", "iyeh", "ah", "ayeh", "ewa wakha", "oui", "ok", "d'accord", "c'est bon", "parfait", "safi", "mzyan",
        "نعم", "واخا", "إيه", "اه", "صافي", "oui svp", "oui merci", "wakha 3afak", "yes", "okay", "c bon",
        "hadak mzyan", "had lw9t mzyan", "iyeh hadak", "oui celui-là", "oui ça me va", "ça marche", "nickel",
    ],
    "nier": [
        "la", "lla", "non", "la la", "machi hadak", "la ma3jbnich", "non merci", "pas possible", "ma nqderch f had lw9t",
        "لا", "لا شكرا", "ماشي هادا", "non pas ce créneau", "la chi w9t akhor", "autre heure svp", "non ça ne va pas",
        "machi daba", "la, b3da", "pas celui-là", "la ma ymkench", "nope",
    ],
    "hors_sujet": [
        "chno smitek", "wach nta robot", "chkoun nta", "3tini numero dyal tbib dyal l3inin", "chno lw9t daba",
        "kifach lj3", "kayn chi pharmacie de garde", "wach kat3rf chi tbib dyal lwlada", "bghit nkhdem m3akom",
        "kanqelleb 3la stage", "wach kaybi3o dentifrice", "Vous êtes un robot ?", "Quel temps fait-il ?",
        "Je cherche un ophtalmologue", "Vous recrutez ?", "Qui a gagné le match ?", "Racontez-moi une blague",
        "chkoun rbe7 lmatch", "3tini blague", "شكون نتا", "واش نتا روبو", "فين كاينة الصيدلية",
        "bghit nchri dar", "chno ftor lyouma", "aji nhedro", "test", "ok google", "asdfgh", "123",
        "wach kat3rfo tbib dyal jild", "kifach ndir passport",
    ],
}

for _intent, _phrases in ENGLISH.items():
    TEMPLATES[_intent].extend(_phrases)
