<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_specs_shoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->enum('cushioning_level', ['low','medium','high','maximum'])->default('medium');
            $table->enum('pronation_type', ['neutral','stability','motion_control'])->default('neutral');
            $table->decimal('drop_mm', 4, 1)->nullable();
            $table->integer('weight_grams')->nullable();
            $table->boolean('is_waterproof')->default(false);
            $table->boolean('is_reflective')->default(false);
            $table->string('upper_material')->nullable();
            $table->string('midsole_technology')->nullable();
            $table->string('outsole_technology')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specs_shoes');
    }
};

