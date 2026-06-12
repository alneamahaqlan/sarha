<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\City;
use App\Models\PriceQuoteRequest;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    /** The "remember me" identity cookie a returning device carries. */
    private function identityCookie(string $name, string $phone): string
    {
        return json_encode(['name' => $name, 'phone' => $phone]);
    }

    private function quotePayload(City $city, string $phone): array
    {
        return [
            'customer_name'  => 'سارة',
            'customer_phone' => $phone,
            'service_name'   => 'تنظيف الأسنان',
            'description'    => 'أرغب في معرفة سعر تنظيف الأسنان مع التلميع.',
            'city_ids'       => [$city->id],
            'category_ids'   => [Category::factory()->create()->id],
        ];
    }

    /**
     * Regression: a returning device (cookie match) skips OTP. The submitter
     * must be logged in so they own the request and can open quotes.show
     * instead of hitting a 404.
     */
    public function test_returning_device_submits_quote_and_can_view_it(): void
    {
        $city = City::factory()->create();
        $phone = '0512345678';

        // EncryptCookies would otherwise try to decrypt our raw identity cookie
        // and drop it; disable it so the controller reads the value verbatim.
        $response = $this
            ->withoutMiddleware(EncryptCookies::class)
            ->withUnencryptedCookie('booking_identity', $this->identityCookie('سارة', $phone))
            ->post(route('quotes.store'), $this->quotePayload($city, $phone));

        $quote = PriceQuoteRequest::firstOrFail();
        $response->assertRedirect(route('quotes.show', $quote->id));

        $this->assertAuthenticated('web');
        $this->assertSame($quote->user_id, auth('web')->id());

        // Following the redirect as the now-logged-in owner returns the page.
        $this->get(route('quotes.show', $quote->id))->assertOk();
    }

    /**
     * A guest opening a private request (no public replies) is sent to login —
     * once authenticated they may turn out to be the owner — not a dead-end 404.
     */
    public function test_guest_viewing_private_quote_is_redirected_to_login(): void
    {
        $quote = PriceQuoteRequest::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->get(route('quotes.show', $quote->id))
            ->assertRedirect(route('login'));
    }

    /** A logged-in non-owner cannot peek at someone else's private request. */
    public function test_logged_in_non_owner_gets_404_on_private_quote(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $quote = PriceQuoteRequest::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other, 'web')
            ->get(route('quotes.show', $quote->id))
            ->assertNotFound();
    }
}
