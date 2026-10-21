<?php

namespace Tests\Feature\Dashboard;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_message_about_a_patient_is_delivered_and_marked_read(): void
    {
        $dr = User::create(['name' => 'Dr Nadia Alaoui', 'email' => 'd@test', 'password' => 'x', 'role' => 'dentist']);
        $desk = User::create(['name' => 'Khadija Amrani', 'email' => 's@test', 'password' => 'x', 'role' => 'secretary']);
        $salma = Patient::create(['first_name' => 'Salma', 'last_name' => 'Bennani', 'phone' => '0612345678']);

        $this->actingAs($desk)->postJson('/dashboard/messages', ['to' => $dr->id, 'body' => 'Salma est arrivée.', 'patient_id' => $salma->id])->assertCreated();
        $this->assertSame(1, $this->actingAs($dr)->getJson('/dashboard/pulse')->json('badges')['dash.messages']);

        $this->actingAs($dr)->getJson("/dashboard/messages?with={$desk->id}")
            ->assertJsonPath('messages.0.body', 'Salma est arrivée.')
            ->assertJsonPath('messages.0.patient.name', 'Salma Bennani')
            ->assertJsonPath('messages.0.mine', false);

        $this->assertSame(0, $this->actingAs($dr)->getJson('/dashboard/pulse')->json('badges')['dash.messages']);
        $this->actingAs($desk)->getJson("/dashboard/messages?with={$dr->id}")->assertJsonPath('messages.0.read', true);
    }

    public function test_nobody_reads_a_conversation_they_are_not_in(): void
    {
        $a = User::create(['name' => 'A', 'email' => 'a@test', 'password' => 'x', 'role' => 'dentist']);
        $b = User::create(['name' => 'B', 'email' => 'b@test', 'password' => 'x', 'role' => 'secretary']);
        $c = User::create(['name' => 'C', 'email' => 'c@test', 'password' => 'x', 'role' => 'assistant']);
        $this->actingAs($a)->postJson('/dashboard/messages', ['to' => $b->id, 'body' => 'privé']);

        $this->actingAs($c)->getJson("/dashboard/messages?with={$a->id}")->assertJsonCount(0, 'messages');
    }
}
