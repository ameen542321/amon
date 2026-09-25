<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_access_tokens', function (Blueprint $table): void {
            $table->char('refresh_token_hash', 64)->nullable()->unique()->after('token_hash');
            $table->timestamp('refresh_expires_at')->nullable()->index()->after('expires_at');
            $table->timestamp('last_refreshed_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_access_tokens', function (Blueprint $table): void {
            $table->dropUnique(['refresh_token_hash']);
            $table->dropIndex(['refresh_expires_at']);
            $table->dropColumn(['refresh_token_hash', 'refresh_expires_at', 'last_refreshed_at']);
        });
    }
};
