<?php

namespace Database\Seeders;

use App\Enums\ClinicRole;
use App\Models\Clinic;
use App\Models\ClinicActivityLog;
use App\Models\ClinicTeamMember;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds team members + a sprinkling of activity-log rows so the
 * "فريقي" + "نشاط الفريق" screens have realistic content after
 * `php artisan db:seed --fresh`.
 *
 * Picks the first ~10 publicly-visible clinics and gives each a
 * 2-5 member team across both roles, with a 6-character base
 * phone prefix + a sequential suffix so the numbers stay unique
 * across the platform.
 */
class ClinicTeamSeeder extends Seeder
{
    private const PASSWORD = 'password'; // matches the rest of the dev seeders
    private const TEAM_COUNT_RANGE = [2, 5];

    public function run(): void
    {
        $clinics = Clinic::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->take(10)
            ->get();

        $globalCounter = 0;
        foreach ($clinics as $clinic) {
            $count = random_int(self::TEAM_COUNT_RANGE[0], self::TEAM_COUNT_RANGE[1]);
            $members = [];
            for ($i = 1; $i <= $count; $i++) {
                $globalCounter++;
                // Phones land in the 0590000000+ range to avoid colliding
                // with the seeded clinic owner phones (0550000001+).
                $phone = '059' . str_pad((string) $globalCounter, 7, '0', STR_PAD_LEFT);
                $role  = $i === 1 ? ClinicRole::COORDINATOR : ($i % 2 === 0 ? ClinicRole::RECEPTION : ClinicRole::COORDINATOR);

                $members[] = ClinicTeamMember::create([
                    'clinic_id' => $clinic->id,
                    'name'      => $this->sampleName($i),
                    'phone'     => $phone,
                    'password'  => Hash::make(self::PASSWORD),
                    'role'      => $role->value,
                    'is_active' => true,
                    'last_login_at' => now()->subDays(random_int(0, 14))->subHours(random_int(0, 23)),
                ]);
            }

            // 5–10 activity rows per clinic. Some by the owner, some by
            // team members. Subjects pulled from existing services so
            // the model_type/id pairs resolve when the React UI
            // (eventually) links back to the original entity.
            $sample = Service::where('clinic_id', $clinic->id)->take(3)->get();
            $rounds = random_int(5, 10);
            for ($r = 0; $r < $rounds; $r++) {
                $actor = random_int(0, 3) === 0
                    ? ['type' => Clinic::class, 'id' => $clinic->id, 'name' => $clinic->name, 'role' => ClinicRole::OWNER->value]
                    : (function () use ($members) {
                        $m = $members[array_rand($members)];
                        return ['type' => ClinicTeamMember::class, 'id' => $m->id, 'name' => $m->name, 'role' => $m->role];
                    })();

                $action = $this->sampleAction();
                $subject = $sample->isNotEmpty() ? $sample->random() : null;

                ClinicActivityLog::create([
                    'clinic_id'  => $clinic->id,
                    'actor_type' => $actor['type'],
                    'actor_id'   => $actor['id'],
                    'actor_name' => $actor['name'],
                    'actor_role' => $actor['role'],
                    'action'     => $action,
                    'model_type' => $subject ? $subject::class : null,
                    'model_id'   => $subject?->id,
                    'summary'    => ['label' => $subject?->name ?? $this->fakeReference()],
                    'ip_address' => '127.0.0.1',
                    'created_at' => now()->subDays(random_int(0, 29))->subMinutes(random_int(0, 1440)),
                ]);
            }
        }
    }

    private function sampleName(int $index): string
    {
        $male = ['أحمد المحمد', 'محمد الزهراني', 'خالد الحربي', 'سعد الغامدي', 'فهد العتيبي'];
        $female = ['سارة القحطاني', 'مها السبيعي', 'نورة الشمري', 'ريم الدوسري'];
        $pool = $index % 2 === 0 ? $female : $male;
        return $pool[array_rand($pool)];
    }

    private function sampleAction(): string
    {
        return collect([
            'service.created', 'service.updated', 'service.deleted',
            'doctor.created', 'doctor.updated',
            'booking.contacted', 'booking.appointment_set', 'booking.completed',
            'price_quote.replied', 'complaint.replied',
            'article.created', 'article.updated',
            'auth.login',
        ])->random();
    }

    private function fakeReference(): string
    {
        return 'REF-' . strtoupper(Str::random(6));
    }
}
