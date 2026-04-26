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
            // When non-null, indicates that the most recently configured
            // session uses an auto-rollover schedule: as soon as the current
            // round closes (manually-via-timer or via the auto-end job),
            // a brand-new round is opened automatically with this duration.
            // The admin clears the loop by pressing "Kết thúc phiên" — the
            // controller resets this column back to NULL.
            $table->unsignedSmallInteger('auto_rollover_seconds')->nullable();
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
            $table->json('selected_option_ids')->nullable();
            $table->unsignedBigInteger('amount_vnd');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('refund_vnd')->default(0);
            $table->unsignedBigInteger('commission_vnd')->default(0);
            $table->foreignId('refund_wallet_tx_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('commission_wallet_tx_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
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
