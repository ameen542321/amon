<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id');
            $table->char('token_hash', 64)->unique();
            $table->string('device_uuid', 128);
            $table->string('device_name', 128);
            $table->string('platform', 32);
            $table->string('app_version', 32)->nullable();
            $table->json('abilities');
            $table->string('last_ip', 45)->nullable();
            $table->string('last_user_agent', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id', 'revoked_at'], 'api_tokens_actor_active_index');
            $table->index(['actor_type', 'actor_id', 'device_uuid'], 'api_tokens_actor_device_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_tokens');
    }
};
