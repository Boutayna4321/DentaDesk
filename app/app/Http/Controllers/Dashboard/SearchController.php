<?php

namespace App\Http\Controllers\Dashboard;

use App\Billing\Money;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The search behind the magnifier: patients by name or phone, invoices by number. */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['patients' => [], 'documents' => []]);
        }
        $digits = Patient::normalizePhone($q);

        $patients = Patient::query()
            ->where(fn ($w) => $w->whereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?", ["%$q%"])
                ->orWhere('last_name', 'like', "%$q%")
                ->when(strlen($digits) >= 3, fn ($x) => $x->orWhere('phone', 'like', "%$digits%")))
            ->with(['appointments' => fn ($a) => $a->where('starts_at', '>', now())->whereIn('status', ['pending', 'confirmed'])->orderBy('starts_at')->limit(1)])
            ->orderBy('first_name')->limit(8)->get()
            ->map(fn (Patient $p) => [
                'name' => $p->full_name, 'phone' => $p->phone, 'url' => route('dash.patients.show', $p),
                'next' => $p->appointments->first()?->starts_at->translatedFormat('D j M H:i'),
            ]);

        $documents = Document::with('patient')->where('number', 'like', '%'.strtoupper($q).'%')->latest('id')->limit(5)->get()
            ->map(fn (Document $d) => [
                'number' => $d->number, 'patient' => $d->patient->full_name, 'total' => Money::format($d->total_cents),
                'url' => route('dash.documents.show', $d),
            ]);

        return response()->json(['patients' => $patients, 'documents' => $documents]);
    }
}
