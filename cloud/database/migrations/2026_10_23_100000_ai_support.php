<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('active'); // active, escalated, closed
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('channel', 16)->default('app'); // app, web
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // user, assistant
            $table->json('content');           // محتوای دقیق ارسال‌شده به API (برای بازپخش بدون تغییر تاریخچه)
            $table->text('display_text')->nullable(); // متن قابل نمایش؛ برای نوبت‌های tool_result خالی است
            $table->json('tools_used')->nullable();
            $table->string('source', 16)->default('model'); // model, fallback, user
            $table->timestamps();
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('source', 16)->default('app')->after('priority'); // app, web, ai, staff
            $table->timestamp('first_response_at')->nullable()->after('resolved_at');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('is_staff_reply');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', fn (Blueprint $t) => $t->dropColumn('is_internal'));
        Schema::table('support_tickets', fn (Blueprint $t) => $t->dropColumn(['source', 'first_response_at']));
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
