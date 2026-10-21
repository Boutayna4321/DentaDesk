<?php

namespace Tests\Feature\Dashboard;

use App\Billing\Billing;
use App\Billing\Letterhead;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $dentist;

    private User $secretary;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-21 10:00');
        $this->dentist = User::create(['name' => 'Dr Nadia Alaoui', 'email' => 'd@test', 'password' => 'x', 'role' => 'dentist']);
        $this->secretary = User::create(['name' => 'Khadija', 'email' => 's@test', 'password' => 'x', 'role' => 'secretary']);
        $this->patient = Patient::create(['first_name' => 'Salma', 'last_name' => 'Bennani', 'phone' => '0612345678']);
    }

    private function draft(string $type = 'invoice', int $cents = 70000): Document
    {
        $doc = Document::create(['type' => $type, 'patient_id' => $this->patient->id, 'created_by' => $this->dentist->id]);

        return app(Billing::class)->saveDraft($doc, [['label' => 'Composite 2 faces', 'tooth' => 36, 'code' => 'D 30', 'quantity' => 1, 'unit_price_cents' => $cents]], null);
    }

    public function test_the_dentist_writes_and_validates_through_the_editor(): void
    {
        $doc = $this->draft();

        $this->actingAs($this->dentist)->put("/dashboard/factures/{$doc->id}", [
            'action' => 'validate', 'body' => 'Contrôle dans 6 mois.',
            'lines' => [['label' => 'Consultation', 'quantity' => 1, 'unit_price' => '250'], ['label' => 'Composite', 'tooth' => 36, 'quantity' => 2, 'unit_price' => '350,50']],
        ])->assertRedirect("/dashboard/factures/{$doc->id}");

        $doc->refresh();
        $this->assertSame(['validated', 'F-2026-0001', 95100], [$doc->status, $doc->number, $doc->total_cents]);
        $this->actingAs($this->dentist)->get("/dashboard/factures/{$doc->id}/imprimer")->assertOk()
            ->assertSee('Neuf cent cinquante et un dirhams', false);
    }

    public function test_numbers_follow_each_other_per_type_and_year_and_are_given_only_at_validation(): void
    {
        $billing = app(Billing::class);
        $a = $this->draft();
        $b = $this->draft();
        $q = $this->draft('quote');
        $billing->validate($b, $this->dentist);
        $billing->validate($q, $this->dentist);
        $billing->validate($a, $this->dentist);

        $this->assertSame(['F-2026-0002', 'F-2026-0001', 'D-2026-0001'], [$a->number, $b->number, $q->number]);
    }

    public function test_a_validated_invoice_is_locked_and_cancelling_keeps_its_number(): void
    {
        $billing = app(Billing::class);
        $doc = $billing->validate($this->draft(), $this->dentist);

        try {
            $billing->saveDraft($doc, [], null);
            $this->fail('A validated invoice must not change.');
        } catch (RuntimeException) {
        }
        $billing->cancel($doc, 'Erreur de montant');
        $next = $billing->validate($this->draft(), $this->dentist);

        $this->assertSame('cancelled', $doc->fresh()->status);
        $this->assertSame('F-2026-0002', $next->number, 'no number is reused');
    }

    public function test_the_front_desk_takes_payment_but_cannot_write_invoices(): void
    {
        $doc = app(Billing::class)->validate($this->draft(), $this->dentist);

        $this->actingAs($this->secretary)->post('/dashboard/factures', ['patient_id' => $this->patient->id, 'type' => 'invoice'])->assertForbidden();
        $this->actingAs($this->secretary)->put("/dashboard/factures/{$doc->id}", ['lines' => []])->assertForbidden();

        $this->actingAs($this->secretary)->post("/dashboard/factures/{$doc->id}/paiements", ['amount' => '400', 'method' => 'especes'])->assertSessionHas('ok');
        $this->actingAs($this->secretary)->post("/dashboard/factures/{$doc->id}/paiements", ['amount' => '400', 'method' => 'carte'])->assertSessionHas('error');
        $this->actingAs($this->secretary)->post("/dashboard/factures/{$doc->id}/paiements", ['amount' => '300', 'method' => 'carte'])->assertSessionHas('ok');

        $this->assertSame('paid', $doc->fresh()->payment_state);
    }

    public function test_changing_the_letterhead_does_not_rewrite_issued_invoices(): void
    {
        Letterhead::save(['doctor' => 'Dr Nadia Alaoui']);
        $doc = app(Billing::class)->validate($this->draft(), $this->dentist);
        Letterhead::save(['doctor' => 'Dr N. Alaoui-Bennis']);

        $this->actingAs($this->dentist)->get("/dashboard/factures/{$doc->id}/imprimer")->assertSee('Dr Nadia Alaoui')->assertDontSee('Alaoui-Bennis');
        $this->actingAs($this->dentist)->get('/dashboard/facturation/apercu')->assertSee('Alaoui-Bennis');
    }

    public function test_a_quote_becomes_a_draft_invoice_with_the_same_lines(): void
    {
        $billing = app(Billing::class);
        $quote = $billing->validate($this->draft('quote', 900000), $this->dentist);

        $this->actingAs($this->dentist)->post("/dashboard/factures/{$quote->id}/facturer")->assertRedirect();

        $invoice = Document::where('type', 'invoice')->sole();
        $this->assertSame(['draft', 900000, $quote->id], [$invoice->status, $invoice->total_cents, $invoice->quote_id]);
    }
}
