<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomaly_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_log_id')
                ->nullable()
                ->constrained('audit_logs')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('anomaly_type');          // e.g. order.deleted
            $table->enum('level', ['high', 'medium', 'low'])->default('medium');
            $table->string('title');                 // 顯示標題
            $table->text('detail');                  // 詳細說明
            $table->string('store_name')->nullable(); // 分店名稱
            $table->boolean('notified')->default(false); // 是否已推播
            $table->boolean('resolved')->default(false); // 老闆是否已處理
            $table->text('resolve_note')->nullable(); // 老闆備註
            $table->timestamps();

            $table->index(['level', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('notified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_logs');
    }
};
