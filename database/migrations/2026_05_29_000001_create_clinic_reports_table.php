<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits "clinic-source complaints" out of the complaints table into a
 * dedicated clinic_reports table. The previous design overloaded the
 * complaints table — a single clinic_id column meant "the clinic that
 * was complained about" for customer complaints and "the clinic that
 * filed the complaint" for clinic complaints, and the complaint type
 * options ("quality", "pricing"…) were copy-pasted from the customer
 * UI so they made no sense from the clinic's perspective.
 *
 * After this migration:
 *   - complaints → customer→clinic complaints ONLY (source='customer'|'admin')
 *   - clinic_reports → clinic→platform reports with proper report types
 *
 * The existing 134 source='clinic' rows are migrated into clinic_reports
 * with their type/priority/status preserved; their subject/description
 * stay untouched. The original rows are then removed from complaints so
 * the admin queue stops mixing two unrelated concerns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();

            // Report categorisation written FOR the clinic's perspective —
            // these are the things a complex would actually need to raise:
            //   technical       — bug, page error, dashboard glitch
            //   abusive_customer — harassment, no-show pattern, fraud
            //   fake_review     — google review they believe is fabricated
            //   billing         — subscription, refund, payment dispute
            //   suggestion      — feature request, UX improvement
            //   other
            $table->string('type');
            $table->string('priority')->default('medium');     // low | medium | high
            $table->string('status')->default('new');          // new | in_review | resolved | rejected

            $table->string('subject');
            $table->text('description');

            // Admin-side review fields (mirrors the complaints flow).
            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'status']);
            $table->index(['status', 'priority']);
        });

        // Migrate the existing 'clinic'-source complaint rows. Done in pure
        // SQL so the data move is one statement, no PHP loop overhead. We
        // map the old (customer-centric) type values to the closest report
        // type so no row loses its categorisation.
        if (Schema::hasTable('complaints')) {
            DB::statement(<<<'SQL'
                INSERT INTO clinic_reports (
                    reference_code, clinic_id, type, priority, status,
                    subject, description, admin_notes, resolution,
                    assigned_admin_id, resolved_at, created_at, updated_at, deleted_at
                )
                SELECT
                    COALESCE(reference_code, CONCAT('RPT-', LPAD(id, 8, '0'))) AS reference_code,
                    clinic_id,
                    CASE type
                        WHEN 'quality'         THEN 'other'
                        WHEN 'pricing'         THEN 'billing'
                        WHEN 'misleading_info' THEN 'fake_review'
                        ELSE 'other'
                    END AS type,
                    COALESCE(priority, 'medium') AS priority,
                    COALESCE(status, 'new')      AS status,
                    subject,
                    description,
                    admin_notes,
                    resolution,
                    assigned_admin_id,
                    resolved_at,
                    created_at,
                    updated_at,
                    deleted_at
                FROM complaints
                WHERE source = 'clinic'
            SQL);

            // Now purge the migrated rows from complaints so the admin
            // complaints queue stops mixing two unrelated concepts.
            DB::table('complaints')->where('source', 'clinic')->delete();
        }
    }

    public function down(): void
    {
        // Best-effort restore: copy the reports back into complaints so
        // the down-migration is non-destructive. Reference codes are
        // preserved; the type map is reversed approximately.
        if (Schema::hasTable('complaints') && Schema::hasTable('clinic_reports')) {
            DB::statement(<<<'SQL'
                INSERT INTO complaints (
                    reference_code, clinic_id, source, customer_name, customer_phone,
                    customer_email, type, priority, status, subject, description,
                    admin_notes, resolution, assigned_admin_id, resolved_at,
                    clinic_notified, created_at, updated_at, deleted_at
                )
                SELECT
                    cr.reference_code,
                    cr.clinic_id,
                    'clinic',
                    c.name,
                    COALESCE(c.phone, ''),
                    c.email,
                    CASE cr.type
                        WHEN 'billing'     THEN 'pricing'
                        WHEN 'fake_review' THEN 'misleading_info'
                        ELSE 'other'
                    END,
                    cr.priority, cr.status, cr.subject, cr.description,
                    cr.admin_notes, cr.resolution, cr.assigned_admin_id, cr.resolved_at,
                    0,
                    cr.created_at, cr.updated_at, cr.deleted_at
                FROM clinic_reports cr
                INNER JOIN clinics c ON c.id = cr.clinic_id
            SQL);
        }

        Schema::dropIfExists('clinic_reports');
    }
};
