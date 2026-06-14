<?php

use App\Models\Clinic;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalise badge assignment from clinics-only to ANY badgeable entity
 * (clinic / offer / service / doctor) via one polymorphic pivot.
 *
 * Replaces the `badge_clinic` pivot: existing rows are migrated to
 * `badgeables` stamped as App\Models\Clinic, then the old table is dropped.
 * Morph type stores the full class name to match the rest of the codebase
 * (no global morphMap is enforced here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badgeables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->string('badgeable_type');                 // App\Models\Clinic|Offer|Service|Doctor
            $table->unsignedBigInteger('badgeable_id');
            $table->string('source')->default('manual');      // manual | auto
            $table->timestamp('expires_at')->nullable();      // time-windowed assignments
            $table->timestamps();

            $table->unique(['badge_id', 'badgeable_type', 'badgeable_id'], 'badgeables_unique');
            $table->index(['badgeable_type', 'badgeable_id']);
        });

        // Migrate existing clinic assignments into the polymorphic pivot.
        if (Schema::hasTable('badge_clinic')) {
            DB::table('badge_clinic')->orderBy('id')->chunk(500, function ($rows) {
                $now = now();
                $insert = $rows->map(fn ($r) => [
                    'badge_id'       => $r->badge_id,
                    'badgeable_type' => Clinic::class,
                    'badgeable_id'   => $r->clinic_id,
                    'source'         => $r->source ?? 'manual',
                    'expires_at'     => $r->expires_at,
                    'created_at'     => $r->created_at ?? $now,
                    'updated_at'     => $r->updated_at ?? $now,
                ])->all();

                if (! empty($insert)) {
                    DB::table('badgeables')->insert($insert);
                }
            });

            Schema::dropIfExists('badge_clinic');
        }
    }

    public function down(): void
    {
        // Recreate the old pivot and copy clinic rows back.
        if (! Schema::hasTable('badge_clinic')) {
            Schema::create('badge_clinic', function (Blueprint $table) {
                $table->id();
                $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->string('source')->default('manual');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['badge_id', 'clinic_id']);
                $table->index(['clinic_id', 'badge_id']);
            });
        }

        if (Schema::hasTable('badgeables')) {
            DB::table('badgeables')
                ->where('badgeable_type', Clinic::class)
                ->orderBy('id')
                ->chunk(500, function ($rows) {
                    $insert = $rows->map(fn ($r) => [
                        'badge_id'   => $r->badge_id,
                        'clinic_id'  => $r->badgeable_id,
                        'source'     => $r->source,
                        'expires_at' => $r->expires_at,
                        'created_at' => $r->created_at,
                        'updated_at' => $r->updated_at,
                    ])->all();

                    if (! empty($insert)) {
                        DB::table('badge_clinic')->insert($insert);
                    }
                });
        }

        Schema::dropIfExists('badgeables');
    }
};
