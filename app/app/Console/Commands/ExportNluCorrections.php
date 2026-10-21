<?php

namespace App\Console\Commands;

use App\Models\ConversationMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Closes the loop: what the clinic corrected in the dashboard becomes training data.
 *
 * Writes nlu/data/corrections.jsonl (human-labelled intents only). build_dataset.py adds it to the training set (never to
 * the handwritten test set), then train.py produces a new model version.
 */
#[Signature('nlu:export {--path= : Where to write corrections.jsonl}')]
#[Description('Export intent corrections and FAQ variants for the next training run')]
class ExportNluCorrections extends Command
{
    public function handle(): int
    {
        $path = $this->option('path') ?: env('NLU_DATA_DIR', base_path('../nlu/data')).'/corrections.jsonl';

        $rows = ConversationMessage::whereNotNull('corrected_intent')->get(['body', 'corrected_intent'])
            ->map(fn ($m) => ['text' => $m->body, 'intent' => $m->corrected_intent, 'source' => 'dashboard']);

        // FAQ variants are NOT exported as question_faq examples: "kayn parking ?" is a FAQ entry
        // but an "adresse" intent, and labelling it question_faq made the model worse (0.867 -> 0.857
        // on the handwritten test). FAQ variants already help where they belong: retrieval.
        $all = $rows->unique('text')->values();
        if (! is_dir(dirname($path))) {
            $this->error("Directory not found: ".dirname($path));

            return self::FAILURE;
        }
        file_put_contents($path, $all->map(fn ($r) => json_encode($r, JSON_UNESCAPED_UNICODE))->implode("\n")."\n");

        $this->info("{$all->count()} human corrections → $path");
        $this->line('Next: docker compose exec nlu sh -c "python data/build_dataset.py && python -W ignore train.py"');

        return self::SUCCESS;
    }
}
