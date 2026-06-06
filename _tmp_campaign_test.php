<?php

use App\Http\Controllers\Api\V1\Clinic\CampaignController;
use App\Http\Requests\Api\V1\Clinic\StoreCampaignRequest;
use App\Models\Clinic;
use App\Models\Customer;
use App\Models\ClinicCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$clinic = Customer::query()->select('clinic_id')->groupBy('clinic_id')->first();
$clinic = $clinic ? Clinic::find($clinic->clinic_id) : Clinic::first();
if (! $clinic) { echo "NO CLINIC\n"; return; }

Auth::guard('clinic')->setUser($clinic);

$eligible = Customer::where('clinic_id', $clinic->id)
    ->where('marketing_opt_out', false)
    ->whereNotNull('phone')->where('phone', '!=', '')->count();
echo "Clinic #{$clinic->id} eligible customers (all): {$eligible}\n";

// Opt one customer out and confirm the audience shrinks by 1.
$victim = Customer::where('clinic_id', $clinic->id)->whereNotNull('phone')->where('marketing_opt_out', false)->first();
if ($victim) {
    $victim->update(['marketing_opt_out' => true]);
    $afterOptOut = Customer::where('clinic_id', $clinic->id)->where('marketing_opt_out', false)->whereNotNull('phone')->where('phone','!=','')->count();
    echo "[Opt-out] excluded customer #{$victim->id}; eligible now: {$afterOptOut} (was {$eligible})\n";
}

// --- preview() ---
$controller = app(CampaignController::class);
$previewReq = Request::create('/', 'POST', ['audience' => ['segment' => null]]);
$previewCount = json_decode($controller->preview($previewReq)->getContent(), true)['data']['count'];
echo "[preview] audience count = {$previewCount}\n";

// --- store() ---
$data = ['name' => 'حملة إعادة تفعيل', 'message_template' => 'مرحباً {{name}}، نفتقدك!', 'audience' => ['segment' => null]];
$storeReq = StoreCampaignRequest::create('/', 'POST', $data);
$storeReq->setContainer(app())->setRedirector(app('redirect'));
$storeReq->validateResolved();
$created = json_decode($controller->store($storeReq)->getContent(), true)['data'];
echo "[store] campaign #{$created['id']} total_recipients={$created['total_recipients']} status={$created['status']}\n";

$campaign = ClinicCampaign::find($created['id']);
$recipient = $campaign->recipients()->first();
echo "[recipients] first = {$recipient->name_snapshot} / {$recipient->phone_snapshot} ({$recipient->status})\n";

// --- markRecipient(): sent ---
$markReq = Request::create('/', 'POST', ['status' => 'sent']);
$after = json_decode($controller->markRecipient($campaign, $recipient, $markReq)->getContent(), true)['data'];
echo "[mark sent] campaign.sent_count={$after['campaign']['sent_count']} progress={$after['campaign']['progress']}%\n";

// cleanup
$campaign->recipients()->delete();
$campaign->delete();
if ($victim) $victim->update(['marketing_opt_out' => false]);
echo "Cleaned up.\n";
