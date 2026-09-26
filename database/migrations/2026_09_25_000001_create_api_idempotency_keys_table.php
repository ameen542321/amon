<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id');
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('route_name');
            $table->string('status', 16)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->mediumText('response_body')->nullable();
            // DATETIME avoids the implicit TIMESTAMP defaults required by older MySQL/MariaDB.
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->unique(['actor_type', 'actor_id', 'key_hash'], 'api_idempotency_actor_key_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
