<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_bets', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('amount_vnd');
            $table->unsignedBigInteger('refund_vnd')->default(0)->after('status');
            $table->unsignedBigInteger('commission_vnd')->default(0)->after('refund_vnd');
        });
    }

    public function down(): void
    {
        Schema::table('event_bets', function (Blueprint $table) {
            $table->dropColumn(['status', 'refund_vnd', 'commission_vnd']);
        });
    }
};
