@inject('tracking', 'App\Services\Tracking\TrackingContext')

@if ($tracking->hasPixels() && $tracking->consentRequired)
{{--
    Cookie consent banner. Shows once (until the visitor chooses); the choice
    is stored in the `sarha_consent` cookie for a year. On "accept" we flip
    Consent Mode to granted and load the pixels; on "reject" nothing loads.
    Required for PDPL — no marketing pixel fires before an explicit accept.
--}}
<script>
function sarhaConsent() {
    return {
        show: false,
        init() {
            try { this.show = document.cookie.indexOf('sarha_consent=') === -1; }
            catch (e) { this.show = true; }
        },
        store(value) {
            try {
                var d = new Date();
                d.setFullYear(d.getFullYear() + 1);
                document.cookie = 'sarha_consent=' + value + '; path=/; expires='
                    + d.toUTCString() + '; SameSite=Lax';
            } catch (e) {}
        },
        accept() {
            this.store('granted');
            if (window.gtag) {
                gtag('consent', 'update', {
                    ad_storage: 'granted',
                    ad_user_data: 'granted',
                    ad_personalization: 'granted',
                    analytics_storage: 'granted'
                });
            }
            if (window.sarhaLoadPixels) window.sarhaLoadPixels();
            this.show = false;
        },
        reject() {
            this.store('denied');
            this.show = false;
        }
    };
}
</script>

<div x-data="sarhaConsent()" x-show="show" x-cloak
     class="fixed inset-x-0 bottom-0 z-[60] p-4 sm:p-5">
    <div class="max-w-4xl mx-auto bg-white border border-gray-200 shadow-lg rounded-2xl p-4 sm:p-5
                flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
        <p class="text-sm text-gray-600 leading-relaxed flex-1">
            {{ $tracking->consentText }}
            @if ($tracking->privacyUrl)
                <a href="{{ $tracking->privacyUrl }}" target="_blank" rel="noopener" class="text-emerald-600 hover:underline">{{ app()->getLocale() === 'en' ? 'Privacy policy' : 'سياسة الخصوصية' }}</a>
            @endif
        </p>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="reject()"
                    class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800
                           rounded-lg hover:bg-gray-100 transition">
                رفض
            </button>
            <button type="button" @click="accept()"
                    class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600
                           hover:bg-emerald-700 rounded-lg transition">
                قبول
            </button>
        </div>
    </div>
</div>
@endif
