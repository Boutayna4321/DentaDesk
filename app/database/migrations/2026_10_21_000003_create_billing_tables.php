<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // The dentist's price list. Prices are never shown on the public site.
        Schema::create('acts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('code', 20)->nullable(); // cotation, e.g. "D 30"
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // invoice | quote
            $table->string('status')->default('draft'); // draft | validated | cancelled
            // Numbering is given at validation only, without gaps, per type and per year.
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('seq')->nullable();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->date('issued_on')->nullable();
            $table->text('body')->nullable();       // what the dentist writes: treatment plan, observations
            $table->unsignedInteger('total_cents')->default(0);
            $table->unsignedInteger('paid_cents')->default(0);
            $table->json('letterhead')->nullable(); // header and footer as they were on the day of validation
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['type', 'year', 'seq']);
            $table->index(['type', 'status']);
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('label');
            $table->unsignedTinyInteger('tooth')->nullable();
            $table->string('code', 20)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('amount_cents');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('method'); // especes | carte | cheque | virement
            $table->string('reference')->nullable(); // cheque number, card slip
            $table->date('paid_on');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        foreach (['payments', 'document_lines', 'documents', 'acts', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
