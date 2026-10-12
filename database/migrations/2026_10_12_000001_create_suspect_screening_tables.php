<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suspect-Finding screening, Phase 1. Rule sets are versioned; questions,
     * score items, clinical rules and risk patterns belong to one set, so an
     * assessment keeps the exact rules it was scored with. No patient
     * identifiers: an assessment holds only an age band, sex and answers.
     * A question that was never asked has no answer row.
     */
    public function up(): void
    {
        Schema::create('suspect_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('status', 24)->default('draft');
            $table->string('engine_version', 16);
            $table->unsignedSmallInteger('high_threshold')->default(40);
            $table->unsignedSmallInteger('medium_threshold')->default(20);
            $table->unsignedSmallInteger('refer_threshold')->default(40);
            $table->foreignId('cloned_from_id')->nullable()->constrained('suspect_rule_sets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('clinical_signoff_reference')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('suspect_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_set_id')->constrained('suspect_rule_sets')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('section', 32);
            $table->string('prompt');
            $table->string('help')->nullable();
            $table->json('options');
            $table->json('ask_when')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['rule_set_id', 'key']);
        });

        Schema::create('suspect_score_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_set_id')->constrained('suspect_rule_sets')->cascadeOnDelete();
            $table->string('component', 1);
            $table->string('key', 64);
            $table->string('label');
            $table->json('condition');
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['rule_set_id', 'key']);
        });

        Schema::create('suspect_clinical_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_set_id')->constrained('suspect_rule_sets')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label');
            $table->json('condition')->nullable();
            $table->string('effect', 32);
            $table->string('priority_floor', 32);
            $table->string('reason_code', 64);
            $table->string('next_action');
            $table->string('criterion_reference')->nullable();
            $table->boolean('configured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['rule_set_id', 'code']);
        });

        Schema::create('suspect_risk_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_set_id')->constrained('suspect_rule_sets')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('reason');
            $table->json('condition');
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->unique(['rule_set_id', 'code']);
        });

        Schema::create('suspect_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('worker_role', 16)->nullable();
            $table->string('age_band', 16);
            $table->string('sex', 16);
            $table->foreignId('rule_set_id')->constrained('suspect_rule_sets');
            $table->string('engine_version', 16);
            $table->string('status', 16)->default('in_progress');
            $table->unsignedSmallInteger('symptom')->default(0);
            $table->unsignedSmallInteger('exposure')->default(0);
            $table->unsignedSmallInteger('history')->default(0);
            $table->unsignedSmallInteger('raw_score')->default(0);
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('priority', 32)->nullable();
            $table->boolean('refer_for_testing')->default(false);
            $table->string('recommended_next_action')->nullable();
            $table->unsignedSmallInteger('unknown_count')->default(0);
            $table->json('unknown_items')->nullable();
            $table->json('reason_codes')->nullable();
            $table->string('criterion_status', 16)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'created_at']);
            $table->index(['status', 'refer_for_testing']);
        });

        Schema::create('suspect_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('suspect_assessments')->cascadeOnDelete();
            $table->string('question_key', 64);
            $table->string('value', 32);
            $table->timestamp('answered_at');

            $table->unique(['assessment_id', 'question_key']);
        });

        Schema::create('suspect_assessment_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('suspect_assessments')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('code', 64);
            $table->string('reason');
            $table->timestamps();

            $table->index(['assessment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspect_assessment_flags');
        Schema::dropIfExists('suspect_answers');
        Schema::dropIfExists('suspect_assessments');
        Schema::dropIfExists('suspect_risk_patterns');
        Schema::dropIfExists('suspect_clinical_rules');
        Schema::dropIfExists('suspect_score_items');
        Schema::dropIfExists('suspect_questions');
        Schema::dropIfExists('suspect_rule_sets');
    }
};
