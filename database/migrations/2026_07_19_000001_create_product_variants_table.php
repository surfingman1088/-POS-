<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('name');                          // 規格名稱，例如：紅色、L號、原味
            $table->string('type')->default('general');      // 規格類型：color / size / flavor / general
            $table->decimal('price', 10, 2)->nullable();     // 若為 null 則繼承商品主價格
            $table->integer('stocks')->default(0);           // 各規格獨立庫存
            $table->integer('sold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // 在 order_items 加入 variant_id 欄位（nullable，舊訂單不受影響）
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn('variant_id');
        });

        Schema::dropIfExists('product_variants');
    }
};
