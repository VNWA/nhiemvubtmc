<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 64)->unique();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_room_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_room_id')->constrained('event_rooms')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('bg_color', 16)->default('#c62828');
            $table->string('text_color', 16)->default('#ffffff');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_room_id', 'sort_order']);
        });

        Schema::create('event_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_room_id')->constrained('event_rooms')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('name', 120)->default('');
            $table->string('status', 16);
            $table->foreignId('preset_option_id')->constrained('event_room_options')->restrictOnDelete();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('auto_end_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['event_room_id', 'status']);
            $table->unique(['event_room_id', 'round_number']);
        });

        Schema::create('event_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_round_id')->constrained('event_rounds')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('event_room_options')->restrictOnDelete();
            $table->unsignedBigInteger('amount_vnd');
            $table->timestamps();

            $table->unique(['event_round_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_bets');
        Schema::dropIfExists('event_rounds');
        Schema::dropIfExists('event_room_options');
        Schema::dropIfExists('event_rooms');
    }
};
