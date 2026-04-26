<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kỳ đếm phiên: mỗi lần admin reset, round_session tăng — phiên mới bắt đầu lại #1 (unique theo cặp kỳ + số).
     */
    public function up(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->unsignedInteger('round_session')->default(1);
        });

        DB::table('event_rooms')->update(['round_session' => 1]);

        Schema::table('event_rounds', function (Blueprint $table) {
            $table->unsignedInteger('round_session')->default(1);
        });

        DB::table('event_rounds')->update(['round_session' => 1]);

        Schema::table('event_rounds', function (Blueprint $table) {
            $table->dropUnique('event_rounds_event_room_id_round_number_unique');
        });

        Schema::table('event_rounds', function (Blueprint $table) {
            $table->unique(
                ['event_room_id', 'round_session', 'round_number'],
                'event_rounds_event_room_id_round_session_round_number_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('event_rounds', function (Blueprint $table) {
            $table->dropUnique('event_rounds_event_room_id_round_session_round_number_unique');
        });

        Schema::table('event_rounds', function (Blueprint $table) {
            $table->dropColumn('round_session');
        });

        Schema::table('event_rooms', function (Blueprint $table) {
            $table->dropColumn('round_session');
        });

        Schema::table('event_rounds', function (Blueprint $table) {
            $table->unique(['event_room_id', 'round_number'], 'event_rounds_event_room_id_round_number_unique');
        });
    }
};
