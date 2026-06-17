<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\User;
use App\Models\VerifiedReview;
use App\Services\VerifiedReviewService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Patient-facing verified-review submission. Two entry points, one
 * authorization model:
 *   - logged-in patient → owns the review (by account id or phone)
 *   - account-less patient → a SIGNED link sent by SMS (possession = auth)
 *
 * A review is only submittable while it is `pending` (one submission per
 * attended booking). Non-coercive: whatever the patient rates publishes
 * directly — no filtering of negatives.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly VerifiedReviewService $reviews) {}

    /** The logged-in patient's reviews — pending (to submit) + published. */
    public function mine(Request $request)
    {
        $user = auth('web')->user();
        $phone = PhoneNormalizer::normalizeOrSelf($user->phone);

        $reviews = VerifiedReview::query()
            ->where(function ($q) use ($user, $phone) {
                $q->where('user_id', $user->id);
                if ($phone) {
                    $q->orWhere('customer_phone', $phone);
                }
            })
            ->with(['clinic:id,name,slug', 'doctor:id,name'])
            ->latest()
            ->get();

        $pending   = $reviews->where('status', VerifiedReview::STATUS_PENDING)->values();
        $published = $reviews->where('status', VerifiedReview::STATUS_PUBLISHED)->values();

        return view('public.account.reviews', compact('user', 'pending', 'published'));
    }

    /** The submission form (signed link OR authed owner). */
    public function form(Request $request, VerifiedReview $review)
    {
        $this->ensureAccess($request, $review);

        $review->load(['clinic:id,name,slug', 'booking:id,service_id,attended_at']);

        // Already submitted → show a friendly "thank you" instead of a form.
        $alreadySubmitted = $review->status === VerifiedReview::STATUS_PUBLISHED;

        $doctors = Doctor::where('clinic_id', $review->clinic_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'specialty']);

        // The POST action carries its own authorization: a signed URL for
        // guests, the plain route for an authed owner.
        $action = ($request->user('web') && $this->owns($request->user('web'), $review))
            ? route('review.submit', $review)
            : URL::signedRoute('review.submit', ['review' => $review->id]);

        return view('public.review-form', compact('review', 'doctors', 'action', 'alreadySubmitted'));
    }

    /** Persist the verified submission. */
    public function submit(Request $request, VerifiedReview $review)
    {
        $this->ensureAccess($request, $review);

        $validated = $request->validate([
            'clinic_rating' => ['required', 'integer', 'between:1,5'],
            'doctor_rating' => ['nullable', 'integer', 'between:1,5'],
            'doctor_id'     => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('doctors', 'id')->where('clinic_id', $review->clinic_id)],
            'comment'       => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->reviews->submit($review, $validated);
        } catch (\RuntimeException $e) {
            return redirect()->route('review.form', $review)->with('error', __('site.review_' . $e->getMessage() . '_err'));
        }

        $slug = $review->clinic?->slug;
        $target = $slug ? route('clinic.show', $slug) : route('home');
        return redirect($target)->with('success', __('site.review_thanks'));
    }

    // ---------- internals ----------

    private function ensureAccess(Request $request, VerifiedReview $review): void
    {
        $ok = $request->hasValidSignature();
        if (! $ok && $request->user('web')) {
            $ok = $this->owns($request->user('web'), $review);
        }
        abort_unless($ok, 403);
    }

    private function owns(User $user, VerifiedReview $review): bool
    {
        if ($review->user_id && (int) $review->user_id === (int) $user->id) {
            return true;
        }
        $rp = PhoneNormalizer::normalizeOrSelf($review->customer_phone);
        $up = PhoneNormalizer::normalizeOrSelf($user->phone);
        return $rp && $up && $rp === $up;
    }
}
