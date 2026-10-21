<?php

namespace Tests\Feature\Dashboard;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private User $dentist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dentist = User::create(['name' => 'Dr Nadia Alaoui', 'email' => 'd@test', 'password' => 'secret123', 'role' => 'dentist']);
    }

    public function test_only_the_dentist_manages_accounts(): void
    {
        $secretary = User::create(['name' => 'Khadija', 'email' => 's@test', 'password' => 'secret123', 'role' => 'secretary']);

        $this->actingAs($secretary)->get('/dashboard/equipe')->assertForbidden();
        $this->actingAs($this->dentist)->get('/dashboard/equipe')->assertOk()->assertSee('Khadija');
    }

    public function test_create_then_log_in(): void
    {
        $this->actingAs($this->dentist)->post('/dashboard/equipe', [
            'name' => 'Sara Idrissi', 'email' => 'sara@test', 'role' => 'assistant', 'password' => 'bonjour2026',
        ])->assertSessionHas('ok');

        auth()->logout();
        $this->post('/login', ['email' => 'sara@test', 'password' => 'bonjour2026'])->assertRedirect('/dashboard');
        $this->assertNotNull(User::where('email', 'sara@test')->value('last_login_at'));
        $this->assertSame('user.created', ActivityLog::value('action'));
    }

    public function test_a_deactivated_account_cannot_log_in_and_is_kicked_out(): void
    {
        $sara = User::create(['name' => 'Sara', 'email' => 'sara@test', 'password' => 'bonjour2026', 'role' => 'secretary']);
        $this->actingAs($this->dentist)->patch("/dashboard/equipe/{$sara->id}/actif");

        $this->actingAs($sara->fresh())->get("/dashboard")->assertRedirect("/login");
        auth()->logout();
        $this->post('/login', ['email' => 'sara@test', 'password' => 'bonjour2026'])
            ->assertSessionHasErrors(['email' => 'Ce compte est désactivé. Demandez à la dentiste de le réactiver.']);
    }

    public function test_an_account_with_history_can_be_deactivated_but_not_deleted(): void
    {
        $sara = User::create(['name' => 'Sara', 'email' => 'sara@test', 'password' => 'x', 'role' => 'secretary', 'last_login_at' => now()]);
        $fresh = User::create(['name' => 'Typo', 'email' => 'typo@test', 'password' => 'x', 'role' => 'secretary']);

        $this->actingAs($this->dentist)->delete("/dashboard/equipe/{$sara->id}")->assertSessionHas('error');
        $this->actingAs($this->dentist)->delete("/dashboard/equipe/{$fresh->id}")->assertSessionHas('ok');

        $this->assertModelExists($sara);
        $this->assertModelMissing($fresh);
    }

    public function test_the_dentist_cannot_lock_herself_out(): void
    {
        $this->actingAs($this->dentist)->patch("/dashboard/equipe/{$this->dentist->id}/actif")->assertSessionHas('error');
        $this->actingAs($this->dentist)->delete("/dashboard/equipe/{$this->dentist->id}")->assertSessionHas('error');
        $this->assertTrue($this->dentist->fresh()->is_active);
    }
}
