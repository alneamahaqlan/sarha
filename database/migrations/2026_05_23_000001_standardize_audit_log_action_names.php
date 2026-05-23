<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Normalises legacy audit_logs.action values to the unified "{model}.{verb}"
 * convention (e.g. created_Clinic → clinic.created, approved_Clinic →
 * clinic.approved). DB-agnostic: transforms in PHP per distinct value, and is
 * idempotent (already-dotted names contain no underscore-class pattern so they
 * pass through unchanged).
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('audit_logs')->distinct()->pluck('action') as $old) {
            $new = $this->convert((string) $old);
            if ($new !== $old) {
                DB::table('audit_logs')->where('action', $old)->update(['action' => $new]);
            }
        }
    }

    public function down(): void
    {
        // One-way data normalisation: several legacy names collapse into one
        // canonical form (e.g. extended_Clinic_30 and extended_Clinic_90 both
        // become clinic.extended), so a faithful reverse is not possible.
    }

    private function convert(string $action): string
    {
        // Non-conforming legacy names handled explicitly, before the generic rule.
        $explicit = [
            'impersonation_started' => 'clinic.impersonation_started',
            'impersonation_ended'   => 'clinic.impersonation_ended',
            'lead_converted'        => 'sales_lead.converted',
        ];
        if (isset($explicit[$action])) {
            return $explicit[$action];
        }

        // extended_Clinic_30 / extended_Clinic_90 → clinic.extended
        if (preg_match('/^extended_([A-Za-z]+)_\d+$/', $action, $m)) {
            return Str::snake($m[1]) . '.extended';
        }

        // Generic verb_ClassBasename → {snake_class}.{verb}
        // covers created_/updated_/deleted_/approved_/rejected_/activated_/suspended_
        if (preg_match('/^([a-z]+)_([A-Za-z]+)$/', $action, $m)) {
            return Str::snake($m[2]) . '.' . $m[1];
        }

        return $action;
    }
};
