<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\ToothRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('dashboard.patients', [
            'q' => $q,
            'patients' => $this->query($q)
                ->withCount(['appointments as visits_count' => fn ($a) => $a->where('status', 'done')])
                ->with(['appointments' => fn ($a) => $a->where('starts_at', '>', now())->whereIn('status', ['pending', 'confirmed'])->orderBy('starts_at')->limit(1)])
                ->orderBy('first_name')->paginate(20)->withQueryString(),
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
        $patient->load(['appointments' => fn ($q) => $q->with('service')->orderByDesc('starts_at'), 'teeth']);

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

    /** One click on a tooth cycles: healthy → to treat → treated → missing → healthy. */
    public function tooth(Request $request, Patient $patient): JsonResponse
    {
        $data = $request->validate([
            'tooth' => ['required', 'integer', Rule::in(array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48)))],
            'state' => ['nullable', Rule::in(array_keys(ToothRecord::STATES))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        if (empty($data['state'])) {
            $patient->teeth()->where('tooth', $data['tooth'])->delete();
        } else {
            $patient->teeth()->updateOrCreate(['tooth' => $data['tooth']], ['state' => $data['state'], 'note' => $data['note'] ?? null]);
        }

        return response()->json(['tooth' => $data['tooth'], 'state' => $data['state'] ?? null]);
    }

    private function query(string $q)
    {
        return Patient::query()->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
            ->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")
            ->orWhere('phone', 'like', '%'.Patient::normalizePhone($q).'%')));
    }
}
