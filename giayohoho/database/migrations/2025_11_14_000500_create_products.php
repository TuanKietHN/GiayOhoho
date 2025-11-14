<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand')->nullable();
            $table->enum('gender', ['male','female','unisex'])->default('unisex');
            $table->bigInteger('base_price');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id']);
            $table->index(['brand']);
            $table->index(['gender']);
            $table->index(['base_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

