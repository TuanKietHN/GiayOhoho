<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size');
            $table->string('color');
            $table->string('sku')->nullable()->unique();
            $table->integer('stock')->default(0);
            $table->bigInteger('extra_price')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id']);
            $table->index(['product_id','size','color']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

