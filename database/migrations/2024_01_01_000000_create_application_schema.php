<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated application schema. Every table is created once here in
 * dependency order with its final column set, indexes and foreign keys —
 * replacing the previous incremental add/modify/drop migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Reference / lookup tables (no domain dependencies)
        // ---------------------------------------------------------------
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('emoji')->nullable();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'admin', 'sales'])->default('admin');
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('code', 6);
            $table->string('purpose')->default('login');
            $table->boolean('is_used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('tagline_ar')->nullable();
            $table->text('tagline_en')->nullable();
            $table->string('color_token', 16)->default('gray');
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('services_limit')->nullable();
            $table->unsignedInteger('articles_monthly_limit')->nullable();
            $table->boolean('ai_article_generation')->default(false);
            $table->boolean('featured_in_search')->default(false);
            $table->unsignedTinyInteger('ai_assistant_priority')->default(0);
            $table->boolean('google_reviews_sync')->default(false);
            $table->boolean('verified_badge')->default(false);
            $table->string('analytics_level', 16)->default('basic');
            $table->unsignedInteger('quote_replies_monthly_limit')->nullable();
            $table->unsignedTinyInteger('banner_slots')->default(0);
            $table->boolean('allow_offers_packages')->default(false);
            $table->boolean('allow_doctors_before_after')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type');
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('item_limit')->nullable();
            $table->unsignedSmallInteger('banner_interval_seconds')->nullable();
            $table->boolean('show_on_mobile')->default(true);
            $table->boolean('show_on_desktop')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('homepage_banner_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained('homepage_sections')->cascadeOnDelete();
            $table->string('image');
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['homepage_section_id', 'is_active', 'sort_order'], 'banner_slides_render_idx');
        });

        Schema::create('platform_notifications', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');
            $table->string('type');
            $table->string('event_type', 64)->nullable()->index();
            $table->string('icon')->nullable();
            $table->string('url')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->json('action_buttons')->nullable();
            $table->string('group_key', 80)->nullable()->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'pn_recipient_read');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscribable');
            $table->text('endpoint');
            $table->string('p256dh_key', 88);
            $table->string('auth_key', 24);
            $table->string('content_encoding', 16)->default('aesgcm');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->string('endpoint_hash', 64)->unique();
        });

        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->string('clinic_name');
            $table->string('contact_name')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('license_number')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('district')->nullable();
            $table->string('address')->nullable();
            $table->enum('status', ['new', 'contacted', 'interested', 'negotiating', 'converted', 'lost'])->default('new');
            $table->text('notes')->nullable();
            $table->text('sales_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('admin_name');
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['model_type', 'model_id']);
            $table->index('admin_id');
            $table->index('created_at');
        });

        // ---------------------------------------------------------------
        // AI logs (depend on users / admins)
        // ---------------------------------------------------------------
        Schema::create('ai_response_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('ai_restrictions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['banned_topic', 'emergency_keyword', 'clinic_blocklist', 'category_blocklist']);
            $table->string('value');
            $table->text('response_override')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'value']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('ai_assistant_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_id', 64)->nullable();
            $table->string('guard', 16)->nullable();
            $table->char('conversation_id', 36)->nullable();
            $table->text('query');
            $table->text('reply')->nullable();
            $table->string('kind', 32);
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('locale', 5)->nullable();
            $table->unsignedBigInteger('blocked_by_restriction_id')->nullable();
            $table->boolean('was_emergency')->default(false);
            $table->boolean('was_blocked')->default(false);
            $table->boolean('stats_bumped')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['visitor_id', 'created_at']);
            $table->index('created_at');
            $table->index('kind');
            $table->index('was_emergency');
            $table->index('was_blocked');
            $table->index('conversation_id');
        });

        Schema::create('ai_user_interaction_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('log_id')->nullable()->constrained('ai_assistant_logs')->nullOnDelete();
            $table->text('topic');
            $table->json('categories')->nullable();
            $table->json('clinics')->nullable();
            $table->enum('seriousness', ['exploration', 'comparing', 'near_decision'])->default('exploration');
            $table->string('model_used', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'generated_at']);
        });

        // ---------------------------------------------------------------
        // Clinics and everything that hangs off them
        // ---------------------------------------------------------------
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('license_number')->nullable();
            $table->string('password');
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('district')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('maps_url')->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->json('gallery')->nullable();
            $table->string('website')->nullable();
            $table->string('instagram')->nullable();
            $table->string('twitter')->nullable();
            $table->string('snapchat')->nullable();
            $table->string('tiktok')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])->default('pending');
            $table->string('subscription_type', 32)->nullable();
            $table->foreignId('subscription_package_id')->nullable()->constrained('subscription_packages')->nullOnDelete();
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('relatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship_type', 32);
            $table->string('relationship_label')->nullable();
            $table->string('phone', 20);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('name', 120);
            $table->string('email', 191)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->enum('last_interaction_type', ['booking', 'complaint', 'quote_request'])->nullable();
            $table->unsignedInteger('total_bookings')->default(0);
            $table->unsignedInteger('completed_bookings')->default(0);
            $table->unsignedInteger('total_complaints')->default(0);
            $table->unsignedInteger('total_quote_requests')->default(0);
            $table->timestamps();
            $table->unique(['clinic_id', 'phone'], 'customers_clinic_phone_uq');
            $table->index(['clinic_id', 'last_interaction_at'], 'customers_clinic_last_idx');
            $table->index(['clinic_id', 'completed_bookings'], 'customers_clinic_completed_idx');
            $table->index(['clinic_id', 'total_complaints'], 'customers_clinic_complaints_idx');
            $table->index(['clinic_id', 'total_bookings'], 'customers_clinic_total_idx');
        });

        Schema::create('clinic_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['clinic_id', 'category_id']);
        });

        Schema::create('sub_clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('catalog_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->text('default_description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->foreignId('requested_by_clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('catalog_service_id')->nullable()->constrained('catalog_services')->nullOnDelete();
            $table->foreignId('sub_clinic_id')->nullable()->constrained('sub_clinics')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('approval_status')->default('approved');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['approval_status', 'is_active']);
        });

        Schema::create('category_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'category_id']);
            $table->index(['category_id', 'service_id']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('package_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['package_id', 'service_id']);
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('sub_clinic_id')->nullable()->constrained('sub_clinics')->nullOnDelete();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->string('gender')->nullable();
            $table->string('photo')->nullable();
            $table->text('bio')->nullable();
            $table->text('qualifications')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->string('university')->nullable();
            $table->string('languages')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->string('cover_image')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->enum('type', ['service', 'general']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['clinic_id', 'is_active', 'starts_at', 'ends_at'], 'offers_render_idx');
            $table->index(['is_featured', 'is_active'], 'offers_featured_idx');
        });

        Schema::create('before_after_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('sub_clinic_id')->nullable()->constrained('sub_clinics')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('before_image');
            $table->string('after_image');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('category_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('google_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('reviewer_name');
            $table->string('reviewer_photo')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('review_text')->nullable();
            $table->string('google_review_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'clinic_id']);
            $table->index('user_id');
        });

        Schema::create('clinic_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->tinyInteger('day_of_week');
            $table->boolean('is_open')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
            $table->unique(['clinic_id', 'day_of_week']);
        });

        Schema::create('clinic_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('search_appearances')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('quote_requests_count')->default(0);
            $table->unsignedInteger('booking_clicks')->default(0);
            $table->unsignedInteger('directions_clicks')->default(0);
            $table->unsignedInteger('call_clicks')->default(0);
            $table->unsignedInteger('whatsapp_clicks')->default(0);
            $table->timestamps();
            $table->unique(['clinic_id', 'date']);
        });

        Schema::create('clinic_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('password');
            $table->string('role', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['clinic_id', 'is_active']);
            $table->index(['phone', 'deleted_at']);
        });

        Schema::create('clinic_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->nullableMorphs('actor');
            $table->string('actor_name');
            $table->string('actor_role', 16)->nullable();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('summary')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['clinic_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index(['clinic_id', 'model_type', 'model_id', 'created_at'], 'cal_subject_time_idx');
        });

        Schema::create('clinic_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('key');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->unsignedSmallInteger('item_limit')->nullable();
            $table->timestamps();
            $table->unique(['clinic_id', 'key']);
            $table->index(['clinic_id', 'is_active', 'sort_order'], 'cps_render_idx');
        });

        Schema::create('clinic_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('type');
            $table->string('priority')->default('medium');
            $table->string('status')->default('new');
            $table->string('subject');
            $table->text('description');
            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['clinic_id', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('clinic_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->date('date');
            $table->string('source', 32);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['clinic_id', 'date', 'source'], 'clinic_impressions_unique');
            $table->index(['date', 'source'], 'clinic_impressions_aggregate_idx');
        });

        Schema::create('service_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->date('date');
            $table->string('source', 32);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['service_id', 'date', 'source'], 'service_impressions_unique');
            $table->index(['clinic_id', 'date', 'source'], 'service_impressions_clinic_idx');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('subscription_package_id')->nullable()->constrained('subscription_packages')->nullOnDelete();
            $table->string('billing_cycle', 16)->default('quarterly');
            $table->unsignedTinyInteger('bonus_months')->default(0);
            $table->string('type', 32);
            $table->decimal('amount', 10, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending_payment'])->default('pending_payment');
            $table->string('moyasar_payment_id')->nullable();
            $table->string('moyasar_invoice_id')->nullable();
            $table->json('payment_metadata')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('type');
            $table->string('priority')->default('medium');
            $table->string('status')->default('new');
            $table->string('subject');
            $table->text('description');
            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('customer_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('color', 16);
            $table->nullableMorphs('created_by');
            $table->timestamps();
            $table->unique(['customer_id', 'label'], 'customer_tags_customer_label_uq');
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->nullableMorphs('created_by');
            $table->string('created_by_name')->default('—');
            $table->timestamps();
            $table->index(['customer_id', 'is_pinned', 'created_at'], 'cn_customer_pinned_created_idx');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 16)->nullable()->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('relative_id')->nullable()->constrained('relatives')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('notes')->nullable();
            $table->enum('status', ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'])->default('new');
            $table->text('clinic_notes')->nullable();
            $table->timestamp('appointment_at')->nullable();
            $table->string('source')->default('website');
            $table->timestamps();
            $table->softDeletes();
            $table->nullableMorphs('assignee');
            $table->index(['clinic_id', 'status', 'appointment_at'], 'bookings_clinic_status_appt_idx');
            $table->index(['clinic_id', 'customer_phone'], 'bookings_clinic_phone_idx');
        });

        Schema::create('booking_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('color', 16);
            $table->nullableMorphs('created_by');
            $table->timestamps();
            $table->unique(['booking_id', 'label'], 'booking_tags_booking_label_uq');
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 16)->unique();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('source')->default('admin')->index();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->enum('type', ['quality', 'pricing', 'misleading_info', 'other']);
            $table->enum('status', ['new', 'in_review', 'resolved', 'rejected'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->text('subject');
            $table->text('description');
            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();
            $table->text('clinic_reply_text')->nullable();
            $table->foreignId('clinic_replied_by_member_id')->nullable()->constrained('clinic_team_members')->nullOnDelete();
            $table->string('clinic_replied_by_name_snapshot')->nullable();
            $table->string('clinic_replied_by_role_snapshot', 16)->nullable();
            $table->timestamp('clinic_replied_at')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('clinic_notified')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'priority']);
            $table->index('clinic_id');
            $table->index('customer_id');
        });

        Schema::create('price_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('service_name');
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'replied', 'closed'])->default('new');
            $table->text('clinic_reply')->nullable();
            $table->timestamps();
        });

        Schema::create('price_quote_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_quote_request_id')->constrained('price_quote_requests')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('replied_by_member_id')->nullable()->constrained('clinic_team_members')->nullOnDelete();
            $table->string('replied_by_name_snapshot')->nullable();
            $table->string('replied_by_role_snapshot', 16)->nullable();
            $table->text('body');
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['price_quote_request_id', 'clinic_id'], 'pqr_reply_unique');
            $table->index('clinic_id');
        });

        Schema::create('price_quote_request_city', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_quote_request_id')->constrained('price_quote_requests')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->unique(['price_quote_request_id', 'city_id'], 'pqr_city_unique');
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->enum('type', ['chat', 'article_generation', 'excel_analysis'])->default('chat');
            $table->json('messages');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_assistant_log_clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('ai_assistant_logs')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['log_id', 'clinic_id']);
            $table->index(['clinic_id', 'created_at']);
        });

        Schema::create('ai_assistant_log_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('ai_assistant_logs')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['log_id', 'category_id']);
            $table->index(['category_id', 'created_at']);
        });

        Schema::create('user_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title')->nullable();
            $table->json('details')->nullable();
            $table->foreignId('related_clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('related_url')->nullable();
            $table->unsignedBigInteger('visit_session_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'type']);
            $table->index(['related_clinic_id', 'occurred_at']);
        });

        Schema::create('user_visit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'user_visit_sessions', 'user_activity_events', 'ai_assistant_log_categories',
            'ai_assistant_log_clinics', 'ai_conversations', 'price_quote_request_city',
            'price_quote_replies', 'price_quote_requests', 'complaints', 'booking_tags',
            'bookings', 'customer_notes', 'customer_tags', 'customer_reports', 'subscriptions',
            'service_impressions', 'clinic_impressions', 'clinic_reports', 'clinic_page_sections',
            'clinic_activity_logs', 'clinic_team_members', 'clinic_stats', 'clinic_working_hours',
            'favorites', 'google_reviews', 'category_requests', 'before_after_photos', 'offers',
            'articles', 'doctors', 'package_service', 'packages', 'category_service', 'services',
            'catalog_services', 'sub_clinics', 'clinic_categories', 'customers', 'relatives',
            'clinics', 'ai_user_interaction_summaries', 'ai_assistant_logs', 'ai_restrictions',
            'ai_response_templates', 'audit_logs', 'sales_leads', 'push_subscriptions',
            'platform_notifications', 'homepage_banner_slides', 'homepage_sections',
            'subscription_packages', 'otp_codes', 'system_settings', 'admins', 'categories', 'cities',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::enableForeignKeyConstraints();
    }
};
