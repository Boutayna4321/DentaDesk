<?php

namespace App\Http\Controllers\Dashboard;

use App\Billing\Letterhead;
use App\Billing\Money;
use App\Http\Controllers\Controller;
use App\Models\Act;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Letterhead (header + footer) and the price list: configured once by the dentist. */
class BillingSettingsController extends Controller
{
    public function edit(): View
    {
        return view('dashboard.billing-settings', [
            'head' => Letterhead::current(),
            'fields' => Letterhead::FIELDS,
            'acts' => Act::orderBy('position')->get(),
        ]);
    }

    public function letterhead(Request $request): RedirectResponse
    {
        $rules = collect(Letterhead::FIELDS)->mapWithKeys(fn ($label, $key) => [$key => ['nullable', 'string', 'max:500']])->all();
        $data = $request->validate($rules + [
            'paper' => ['required', Rule::in(['A4', 'A5'])],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'show_signature' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);
        unset($data['logo']);
        $data['show_signature'] = $request->boolean('show_signature');
        if ($request->hasFile('logo')) {
            $data['logo'] = 'data:'.$request->file('logo')->getMimeType().';base64,'.base64_encode($request->file('logo')->get());
        } elseif ($request->boolean('remove_logo')) {
            $data['logo'] = null;
        }
        unset($data['remove_logo']);
        Letterhead::save($data);

        return back()->with('ok', 'En-tête et pied de page enregistrés. Les documents déjà validés gardent l’ancienne version.');
    }

    /** A sample invoice with the current letterhead, never saved. */
    public function preview(): View
    {
        $doc = new \App\Models\Document(['type' => 'invoice', 'status' => 'validated', 'number' => 'F-'.date('Y').'-0001', 'issued_on' => now(),
            'body' => 'Soins réalisés ce jour. Contrôle conseillé dans 6 mois.', 'total_cents' => 95000, 'paid_cents' => 0]);
        $doc->setRelation('patient', new \App\Models\Patient(['first_name' => 'Salma', 'last_name' => 'Bennani', 'coverage' => 'cnss']));
        $doc->setRelation('lines', collect([
            new \App\Models\DocumentLine(['label' => 'Consultation', 'quantity' => 1, 'unit_price_cents' => 25000, 'amount_cents' => 25000]),
            new \App\Models\DocumentLine(['label' => 'Composite 2 faces', 'tooth' => 36, 'code' => 'D 30', 'quantity' => 1, 'unit_price_cents' => 70000, 'amount_cents' => 70000]),
        ]));
        $doc->setRelation('payments', collect());

        return view('documents.print', ['doc' => $doc, 'head' => Letterhead::current(), 'words' => \App\Billing\AmountInWords::dirhams(95000)]);
    }

    public function acts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'acts' => ['array'],
            'acts.*.label' => ['nullable', 'string', 'max:160'],
            'acts.*.code' => ['nullable', 'string', 'max:20'],
            'acts.*.price' => ['nullable', 'string', 'max:20'],
            'acts.*.is_active' => ['nullable', 'boolean'],
        ]);
        foreach ($data['acts'] ?? [] as $key => $a) {
            if (blank($a['label'] ?? null)) {
                continue;
            }
            $values = ['label' => $a['label'], 'code' => $a['code'] ?? null, 'price_cents' => Money::parse($a['price'] ?? 0), 'is_active' => (bool) ($a['is_active'] ?? false)];
            $key === 'new' ? Act::create($values + ['position' => Act::max('position') + 1, 'is_active' => true]) : Act::whereKey($key)->update($values);
        }

        return back()->with('ok', 'Tarifs enregistrés.');
    }
}
