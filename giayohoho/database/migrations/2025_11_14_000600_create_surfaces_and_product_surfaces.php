<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('surfaces', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
        });

        Schema::create('product_surfaces', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('surface_id')->constrained('surfaces')->cascadeOnDelete();
            $table->primary(['product_id','surface_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_surfaces');
        Schema::dropIfExists('surfaces');
    }
};

