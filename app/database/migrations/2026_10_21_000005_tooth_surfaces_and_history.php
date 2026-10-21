<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-surface findings (M, O, D, V, L → caries | filling), next to the whole-tooth state.
        Schema::table('tooth_records', function (Blueprint $table) {
            $table->json('surfaces')->nullable()->after('state');
        });
        // Old single states become the new model: "to treat" = caries on the chewing surface, "treated" = a filling there.
        DB::table('tooth_records')->where('state', 'to_treat')->update(['state' => 'present', 'surfaces' => json_encode(['O' => 'caries'])]);
        DB::table('tooth_records')->where('state', 'treated')->update(['state' => 'present', 'surfaces' => json_encode(['O' => 'filling'])]);

        Schema::create('tooth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tooth');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['patient_id', 'tooth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tooth_events');
        Schema::table('tooth_records', fn (Blueprint $table) => $table->dropColumn('surfaces'));
    }
};
