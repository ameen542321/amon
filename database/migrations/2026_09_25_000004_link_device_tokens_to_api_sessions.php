<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('api_access_token_id')->nullable()->after('accountant_id')->index();
            $table->string('provider', 32)->default('onesignal')->after('token');
            $table->timestamp('last_seen_at')->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropIndex(['api_access_token_id']);
            $table->dropColumn(['api_access_token_id', 'provider', 'last_seen_at']);
        });
    }
};
