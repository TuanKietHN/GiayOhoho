<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createFrameworkTables();
        $this->createAuthTables();
        $this->createCatalogTables();
        $this->createCommerceTables();
        $this->createProviderTables();
        $this->createOperationalTables();
        $this->createIndexesAndConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_size_guides');
        Schema::dropIfExists('mail_outbox');
        Schema::dropIfExists('shipping_events');
        Schema::dropIfExists('shipping_orders');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('account_coupons');
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('order_details');
        Schema::dropIfExists('cart_item');
        Schema::dropIfExists('cart');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('wishlist');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_specs_shoes');
        Schema::dropIfExists('product_surfaces');
        Schema::dropIfExists('surfaces');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('account_login_events');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('account_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }

    private function createFrameworkTables(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('account_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    private function createAuthTables(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->boolean('locked')->default(false);
            $table->string('status', 32)->default('ACTIVE');
            $table->boolean('email_verified')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 100)->nullable();
            $table->unsignedBigInteger('login_count')->default(0);
            $table->string('ban_reason')->nullable();
            $table->date('birth_of_date')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('deleted_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('account_roles', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['account_id', 'role_id']);
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->string('dtype', 50)->default('CustomerProfile');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->date('birth_of_date')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('description')->nullable();
            $table->string('module', 50);
            $table->timestamp('created_at')->nullable();

            $table->index('module');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('account_login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('email');
            $table->string('event_type', 50);
            $table->boolean('success');
            $table->string('failure_reason')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('ip_address', 100)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('device_fingerprint', 128)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_address', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('replaced_by_token', 64)->nullable();
            $table->unsignedBigInteger('expiration_in_seconds')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'revoked_at']);
            $table->index('device_fingerprint');
            $table->index('expires_at');
        });
    }

    private function createCatalogTables(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('address_line')->nullable();
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->integer('ghn_province_id')->nullable();
            $table->integer('ghn_district_id')->nullable();
            $table->string('ghn_ward_code', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('ghn_district_id');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand', 100)->nullable();
            $table->string('gender', 30)->nullable();
            $table->bigInteger('base_price')->default(0);
            $table->bigInteger('original_price')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('brand');
            $table->index('deleted_at');
        });

        Schema::create('surfaces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
        });

        Schema::create('product_surfaces', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('surface_id')->constrained('surfaces')->cascadeOnDelete();
            $table->primary(['product_id', 'surface_id']);
        });

        Schema::create('product_specs_shoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('cushioning_level')->nullable();
            $table->string('pronation_type')->nullable();
            $table->decimal('drop_mm', 5, 2)->nullable();
            $table->integer('weight_grams')->nullable();
            $table->boolean('is_waterproof')->default(false);
            $table->boolean('is_reflective')->default(false);
            $table->string('upper_material')->nullable();
            $table->string('midsole_technology')->nullable();
            $table->string('outsole_technology')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 50)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('sku')->unique();
            $table->integer('stock')->default(0);
            $table->bigInteger('extra_price')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('deleted_at');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('image_url', 1000);
            $table->string('alt_text')->nullable();
            $table->string('object_name', 512)->nullable();
            $table->string('content_type', 100)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_primary', 'sort_order']);
            $table->index(['product_variant_id', 'sort_order']);
            $table->index('object_name');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('wishlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'product_id']);
            $table->index('product_id');
        });
    }

    private function createCommerceTables(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('discount_type', 50);
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('min_purchase', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('version')->default(0);
            $table->string('applicable_scope', 20)->default('ALL');
            $table->string('applicable_brand', 100)->nullable();
            $table->foreignId('applicable_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('applicable_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index('applicable_scope');
            $table->index('applicable_brand');
        });

        Schema::create('cart', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->bigInteger('total')->default(0);
            $table->bigInteger('sub_total')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('cart')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity');
            $table->bigInteger('price');
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
            $table->index('product_variant_id');
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->bigInteger('total')->default(0);
            $table->bigInteger('sub_total')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('order_address', 500)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('shipping_provider', 50)->nullable();
            $table->bigInteger('shipping_fee')->default(0);
            $table->bigInteger('shipping_discount')->default(0);
            $table->integer('shipping_service_id')->nullable();
            $table->integer('shipping_service_type_id')->nullable();
            $table->string('shipping_quote_id', 100)->nullable();
            $table->integer('ghn_to_province_id')->nullable();
            $table->integer('ghn_to_district_id')->nullable();
            $table->string('ghn_to_ward_code', 50)->nullable();
            $table->timestamp('expected_delivery_time')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->string('status', 50)->default('PENDING');
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('order_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('order_details')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity');
            $table->bigInteger('price');
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_variant_id');
        });

        Schema::create('account_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('order_details')->cascadeOnDelete();
            $table->timestamp('used_at')->useCurrent();

            $table->index(['account_id', 'coupon_id']);
            $table->index('order_id');
        });
    }

    private function createProviderTables(): void
    {
        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('order_details')->cascadeOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->string('provider', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('provider_data')->nullable();
            $table->text('webhook_raw')->nullable();
            $table->string('webhook_idempotency_key')->nullable();
            $table->string('return_url', 500)->nullable();
            $table->string('cancel_url', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->index('transaction_id');
            $table->index(['provider', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payment_details')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 500)->nullable();
            $table->text('raw_data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('payment_id');
            $table->index('created_at');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_key');
            $table->foreignId('payment_id')->nullable()->constrained('payment_details')->nullOnDelete();
            $table->string('status', 30);
            $table->text('payload')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->unique(['provider', 'event_key']);
            $table->index('payment_id');
            $table->index(['status', 'received_at']);
        });

        Schema::create('shipping_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('order_details')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_order_code', 100)->nullable()->unique();
            $table->string('client_order_code', 100)->nullable()->unique();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_phone', 30)->nullable();
            $table->string('from_address', 500)->nullable();
            $table->integer('from_district_id')->nullable();
            $table->string('from_ward_code', 50)->nullable();
            $table->string('to_name')->nullable();
            $table->string('to_phone', 30)->nullable();
            $table->string('to_address', 500)->nullable();
            $table->integer('to_district_id')->nullable();
            $table->string('to_ward_code', 50)->nullable();
            $table->string('return_name')->nullable();
            $table->string('return_phone', 30)->nullable();
            $table->string('return_address', 500)->nullable();
            $table->integer('return_district_id')->nullable();
            $table->string('return_ward_code', 50)->nullable();
            $table->integer('service_id')->nullable();
            $table->integer('service_type_id')->nullable();
            $table->integer('payment_type_id')->nullable();
            $table->string('required_note', 100)->nullable();
            $table->bigInteger('cod_amount')->default(0);
            $table->bigInteger('cod_failed_amount')->default(0);
            $table->bigInteger('insurance_value')->default(0);
            $table->bigInteger('total_fee')->default(0);
            $table->bigInteger('main_service_fee')->default(0);
            $table->bigInteger('r2s_fee')->default(0);
            $table->bigInteger('coupon_value')->default(0);
            $table->bigInteger('document_return')->default(0);
            $table->bigInteger('double_check')->default(0);
            $table->bigInteger('pick_remote_areas_fee')->default(0);
            $table->bigInteger('deliver_remote_areas_fee')->default(0);
            $table->bigInteger('pick_remote_areas_fee_return')->default(0);
            $table->bigInteger('deliver_remote_areas_fee_return')->default(0);
            $table->integer('length_cm')->nullable();
            $table->integer('width_cm')->nullable();
            $table->integer('height_cm')->nullable();
            $table->integer('weight_grams')->nullable();
            $table->string('status_raw', 100)->nullable();
            $table->string('status_mapped', 50)->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->text('reason_message')->nullable();
            $table->timestamp('expected_delivery_time')->nullable();
            $table->text('raw_create_response')->nullable();
            $table->text('raw_latest_payload')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status_raw']);
            $table->index('client_order_code');
        });

        Schema::create('shipping_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('provider_order_code', 100)->nullable();
            $table->string('event_type', 100)->nullable();
            $table->string('status_raw', 100)->nullable();
            $table->string('idempotency_key')->unique();
            $table->text('payload');
            $table->timestamp('processed_at')->useCurrent();

            $table->index('provider_order_code');
        });
    }

    private function createOperationalTables(): void
    {
        Schema::create('mail_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 64);
            $table->string('aggregate_id', 128);
            $table->string('mail_type', 64);
            $table->string('dedupe_key')->unique();
            $table->string('recipient');
            $table->string('subject');
            $table->text('payload_json');
            $table->string('status', 32)->default('PENDING');
            $table->integer('attempts')->default(0);
            $table->timestamp('next_attempt_at')->useCurrent();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->timestamp('sent_at')->nullable();

            $table->index(['aggregate_type', 'aggregate_id', 'mail_type']);
            $table->index(['status', 'next_attempt_at', 'id']);
        });

        Schema::create('product_size_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('product_type', 120)->nullable();
            $table->string('title', 180);
            $table->string('measurement_unit', 30)->default('EU');
            $table->text('measurement_instructions')->nullable();
            $table->text('fit_notes')->nullable();
            $table->text('size_chart')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand', 'is_active', 'deleted_at']);
        });
    }

    private function createIndexesAndConstraints(): void
    {
        DB::statement("ALTER TABLE coupons ADD CONSTRAINT chk_coupons_applicable_scope CHECK (applicable_scope IN ('ALL', 'BRAND', 'PRODUCT', 'VARIANT'))");
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT chk_product_variants_stock_non_negative CHECK (stock >= 0)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT chk_reviews_rating_range CHECK (rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE product_size_guides ADD CONSTRAINT chk_product_size_guides_scope CHECK (product_id IS NOT NULL OR brand IS NOT NULL)');

        DB::statement('CREATE UNIQUE INDEX uq_order_details_idempotency_key_not_null ON order_details (idempotency_key) WHERE idempotency_key IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX uq_payment_details_webhook_key_not_null ON payment_details (webhook_idempotency_key) WHERE webhook_idempotency_key IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX uq_product_size_guides_product_active ON product_size_guides (product_id) WHERE product_id IS NOT NULL AND deleted_at IS NULL');
    }
};
