<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\ToothEvent;
use App\Models\ToothRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientController extends Controller
{
    public const FILTERS = [
        'a_venir' => 'Rendez-vous à venir',
        'a_rappeler' => 'À rappeler (pas venu depuis 6 mois)',
        'impaye' => 'Facture à encaisser',
        'allergie' => 'Allergie connue',
        'nouveaux' => 'Nouveaux (30 jours)',
    ];

    public const COVERAGES = ['cnss' => 'CNSS', 'cnops' => 'CNOPS', 'mutuelle' => 'Mutuelle', 'aucune' => 'Sans couverture'];

    public const SORTS = ['nom' => 'Nom', 'derniere_visite' => 'Dernière visite', 'recent' => 'Derniers inscrits'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $only = array_values(array_intersect((array) $request->query('only', []), array_keys(self::FILTERS)));
        $coverage = array_values(array_intersect((array) $request->query('couverture', []), array_keys(self::COVERAGES)));
        $sort = array_key_exists($request->query('tri'), self::SORTS) ? $request->query('tri') : 'nom';

        $query = $this->query($q)
            ->withCount(['appointments as visits_count' => fn ($a) => $a->where('status', 'done')])
            ->withMax(['appointments as last_visit' => fn ($a) => $a->where('status', 'done')], 'starts_at')
            ->with(['appointments' => fn ($a) => $a->where('starts_at', '>', now())->whereIn('status', ['pending', 'confirmed'])->orderBy('starts_at')->limit(1)])
            ->when($coverage, fn ($w) => $w->where(fn ($c) => $c->whereIn('coverage', $coverage)
                ->when(in_array('aucune', $coverage, true), fn ($x) => $x->orWhereNull('coverage'))));

        foreach ($only as $f) {
            match ($f) {
                'a_venir' => $query->whereHas('appointments', fn ($a) => $a->where('starts_at', '>', now())->whereIn('status', ['pending', 'confirmed'])),
                'a_rappeler' => $query->whereHas('appointments', fn ($a) => $a->where('status', 'done'))
                    ->whereDoesntHave('appointments', fn ($a) => $a->where(fn ($x) => $x->where('status', 'done')->where('starts_at', '>', now()->subMonths(6))
                        ->orWhere(fn ($y) => $y->where('starts_at', '>', now())->whereIn('status', ['pending', 'confirmed'])))),
                'impaye' => $query->whereHas('documents', fn ($d) => $d->where('type', 'invoice')->where('status', 'validated')->whereColumn('paid_cents', '<', 'total_cents')),
                'allergie' => $query->whereNotNull('allergies')->where('allergies', '!=', ''),
                'nouveaux' => $query->where('created_at', '>=', now()->subDays(30)),
            };
        }
        match ($sort) {
            'derniere_visite' => $query->orderByDesc('last_visit'),
            'recent' => $query->orderByDesc('created_at'),
            default => $query->orderBy('first_name')->orderBy('last_name'),
        };

        return view('dashboard.patients', [
            'q' => $q, 'only' => $only, 'coverage' => $coverage, 'sort' => $sort,
            'patients' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'coverage' => ['nullable', Rule::in(['cnss', 'cnops', 'mutuelle', 'aucune'])],
        ]);
        $data['phone'] = Patient::normalizePhone($data['phone']);
        if ($existing = Patient::where('phone', $data['phone'])->first()) {
            return redirect()->route('dash.patients.show', $existing)->with('error', 'Ce numéro a déjà un dossier : le voici.');
        }

        return redirect()->route('dash.patients.show', Patient::create($data))->with('ok', 'Dossier créé.');
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json($this->query((string) $request->query('q'))->limit(8)->get()
            ->map(fn (Patient $p) => ['id' => $p->id, 'name' => $p->full_name, 'phone' => $p->phone]));
    }

    public function show(Patient $patient): View
    {
        $patient->load(['appointments' => fn ($q) => $q->with('service')->orderByDesc('starts_at'), 'teeth', 'documents']);

        return view('dashboard.patient', [
            'patient' => $patient,
            'teeth' => Gate::allows('medical') ? $patient->teeth->keyBy('tooth') : collect(),
        ]);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('patients')->ignore($patient)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'coverage' => ['nullable', Rule::in(['cnss', 'cnops', 'mutuelle', 'aucune'])],
            'allergies' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('ok', 'Dossier mis à jour.');
    }

    /** Saves one tooth: whole-tooth state, findings per surface, note. A healthy tooth is simply removed. */
    public function tooth(Request $request, Patient $patient): JsonResponse
    {
        $data = $request->validate([
            'tooth' => ['required', 'integer', Rule::in(array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48)))],
            'state' => ['required', Rule::in(array_keys(ToothRecord::STATES))],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => ['nullable', Rule::in(array_keys(ToothRecord::FINDINGS))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $surfaces = collect($data['surfaces'] ?? [])->only(ToothRecord::SURFACES)->filter()->all();
        // A missing tooth or an implant has no surfaces left to chart.
        if (in_array($data['state'], ['missing', 'implant'], true)) {
            $surfaces = [];
        }
        $before = $patient->teeth()->where('tooth', $data['tooth'])->first();
        $old = $before ? ToothRecord::describe($before->state, $before->surfaces ?? []) : 'Saine';
        $new = ToothRecord::describe($data['state'], $surfaces);

        if ($data['state'] === 'present' && ! $surfaces && blank($data['note'] ?? null)) {
            $before?->delete();
        } else {
            $patient->teeth()->updateOrCreate(['tooth' => $data['tooth']], ['state' => $data['state'], 'surfaces' => $surfaces ?: null, 'note' => $data['note'] ?? null]);
        }
        if ($old !== $new) {
            ToothEvent::create(['patient_id' => $patient->id, 'tooth' => $data['tooth'], 'user_id' => $request->user()->id, 'summary' => "$old → $new"]);
        }

        return response()->json(['tooth' => $data['tooth'], 'state' => $data['state'], 'surfaces' => (object) $surfaces, 'note' => $data['note'] ?? null,
            'label' => $new, 'history' => $this->history($patient, $data['tooth'])]);
    }

    private function history(Patient $patient, int $tooth): array
    {
        return $patient->toothEvents()->with('user')->where('tooth', $tooth)->take(10)->get()
            ->map(fn ($e) => ['at' => $e->created_at->translatedFormat('j M Y'), 'who' => $e->user?->name, 'what' => $e->summary])->all();
    }

    private function query(string $q)
    {
        return Patient::query()->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
            ->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")
            ->orWhere('phone', 'like', '%'.Patient::normalizePhone($q).'%')));
    }
}
