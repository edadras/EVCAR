<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cached_tariffs', function (Blueprint $table) {
            $table->json('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cached_tariffs', fn (Blueprint $t) => $t->dropColumn('meta'));
    }
};
