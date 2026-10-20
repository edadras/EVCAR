<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // امتیاز و نظر مشتری پس از هر جلسهٔ شارژ
        Schema::create('station_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charging_session_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['station_id', 'is_hidden']);
        });

        // «وقتی آزاد شد خبرم کن» برای ایستگاه‌های پر
        Schema::create('station_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_watches');
        Schema::dropIfExists('station_reviews');
    }
};
