<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('connector_auth_grants')) {
            Schema::table('connector_auth_grants', function (Blueprint $table) {
                if (! $this->indexExists('connector_auth_grants', 'conn_auth_grants_user_uuid')) {
                    $table->index('cached_user_uuid', 'conn_auth_grants_user_uuid');
                }
                if (! $this->indexExists('connector_auth_grants', 'conn_auth_grants_user_conn_exp')) {
                    $table->index(['cached_user_uuid', 'connector_id', 'expires_at'], 'conn_auth_grants_user_conn_exp');
                }
            });

            return;
        }

        Schema::create('connector_auth_grants', function (Blueprint $table) {
            $table->id();
            $table->uuid('cached_user_uuid')->index();
            $table->foreignId('connector_id')->constrained('connectors')->cascadeOnDelete();
            $table->string('grant_type', 16); // anpr, qr, rfid
            $table->uuid('source_uuid')->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['cached_user_uuid', 'connector_id', 'expires_at'], 'conn_auth_grants_user_conn_exp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_auth_grants');
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);

        return collect($indexes)->contains(fn (array $row) => ($row['name'] ?? '') === $index);
    }
};
