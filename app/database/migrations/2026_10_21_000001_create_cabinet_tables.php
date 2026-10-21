<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('title')->default('Dentiste');
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('summary');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_emergency')->default(false);
            $table->boolean('bookable_online')->default(true);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });

        // One row per opening span; the lunch break is the gap between two rows.
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO: 1 = lundi … 7 = dimanche
            $table->time('opens_at');
            $table->time('closes_at');
            $table->index(['practitioner_id', 'weekday']);
        });

        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['practitioner_id', 'starts_at']);
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone', 20)->unique();
            $table->date('birth_date')->nullable();
            $table->string('coverage')->nullable(); // cnss | cnops | mutuelle | aucune
            $table->string('allergies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('pending');
            $table->string('source')->default('phone'); // phone | web | assistant
            $table->string('reason')->nullable();
            $table->text('visit_note')->nullable();
            $table->timestamps();

            // A cancelled appointment frees its slot: the lock column is NULL for it,
            // and MySQL allows many NULLs under a unique index.
            $table->dateTime('slot_lock')->nullable()
                ->storedAs("CASE WHEN status IN ('cancelled','no_show') THEN NULL ELSE starts_at END");
            $table->unique(['practitioner_id', 'slot_lock']);
            $table->index(['practitioner_id', 'starts_at']);
        });

        // Odontogramme : last known state per tooth (FDI numbering).
        Schema::create('tooth_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tooth');
            $table->string('state'); // to_treat | treated | missing
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['patient_id', 'tooth']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->text('variants')->nullable(); // other ways patients ask it, one per line
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->json('state')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // patient | assistant
            $table->text('body');
            $table->string('intent')->nullable();
            $table->float('confidence')->nullable();
            $table->json('entities')->nullable();
            $table->string('corrected_intent')->nullable(); // set from the dashboard, feeds retraining
            $table->timestamps();
        });

        Schema::create('assistant_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // booking | reschedule | cancel | unanswered
            $table->text('message');
            $table->string('summary');
            $table->json('payload')->nullable();
            $table->string('status')->default('open'); // open | done | dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['assistant_requests', 'conversation_messages', 'conversations', 'faqs', 'tooth_records',
            'appointments', 'patients', 'time_offs', 'working_hours', 'services', 'practitioners'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
