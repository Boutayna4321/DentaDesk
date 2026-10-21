<?php

namespace Tests\Feature\Assistant;

use App\Assistant\Interpreter;
use Carbon\CarbonImmutable;
use Database\Seeders\CabinetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InterpreterTest extends TestCase
{
    use RefreshDatabase;

    private function fakeNlu(array $response): void
    {
        Http::fake(['*/interpret' => Http::response($response)]);
    }

    public function test_explains_a_darija_message_in_french_and_english(): void
    {
        $this->seed(CabinetSeeder::class);
        $this->fakeNlu([
            'intent' => 'urgence', 'tier' => 'act',
            'entities' => ['date' => '2026-10-22', 'part_of_day' => 'morning', 'time' => null, 'services' => ['urgence', 'enfant']],
            'glossary' => [['word' => 'wjee3', 'fr' => 'douleur', 'en' => 'pain'], ['word' => 'bnti', 'fr' => 'ma fille', 'en' => 'my daughter']],
        ]);
        $sent = CarbonImmutable::parse('2026-10-21 22:40');
        $interpreter = app(Interpreter::class);

        $fr = $interpreter->describe('bnti 3andha wjee3, kayn chi blassa ghedda f sba7?', $sent, 'fr');
        $en = $interpreter->describe('bnti 3andha wjee3, kayn chi blassa ghedda f sba7?', $sent, 'en');

        $this->assertSame('Le patient, pour son enfant, signale une douleur et veut être reçu au plus vite, pour jeudi 22 octobre le matin.', $fr['sentence']);
        $this->assertSame('The patient, for their child, reports pain and wants to be seen as soon as possible, for Thursday 22 October in the morning.', $en['sentence']);
        $this->assertSame('my daughter', $en['glossary'][1]['en']);
        Http::assertSentCount(1); // the second language comes from the cache
    }

    public function test_says_when_it_is_unsure_and_returns_nothing_when_the_service_is_down(): void
    {
        Http::fake(['*/interpret' => Http::sequence()
            ->push(['intent' => 'question_faq', 'tier' => 'clarify', 'entities' => ['services' => []], 'glossary' => []])
            ->push(null, 500)]);

        $this->assertSame('The patient has a question. (uncertain)', app(Interpreter::class)->describe('wach kayn ascenseur', CarbonImmutable::now(), 'en')['sentence']);
        $this->assertNull(app(Interpreter::class)->describe('autre message', CarbonImmutable::now(), 'fr'));
    }
}
