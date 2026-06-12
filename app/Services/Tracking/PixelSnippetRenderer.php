<?php

namespace App\Services\Tracking;

use App\Enums\PixelProvider;

/**
 * Builds the JavaScript that initialises each configured pixel. The output
 * is the BODY of window.sarhaLoadPixels() — it runs ONLY after the visitor
 * grants consent (the blade partial never calls it before then).
 *
 * IDs are always re-validated by the resolver before reaching here and are
 * json_encode()'d on the way out — a value can never break out of the
 * string context. We never emit any PII; on sensitive (booking) pages the
 * gtag page_location/page_path is replaced with a neutral path so a URL
 * can't leak a medical service.
 */
class PixelSnippetRenderer
{
    public function loaderJs(TrackingContext $ctx): string
    {
        $parts = [];

        if ($gtagIds = $ctx->gtagIds()) {
            $parts[] = $this->gtagSnippet($gtagIds, $ctx->sanitizeUrl);
        }

        foreach ($ctx->standalonePixels() as $pixel) {
            $parts[] = match ($pixel['provider']) {
                PixelProvider::META     => $this->metaSnippet($pixel['id']),
                PixelProvider::SNAPCHAT => $this->snapSnippet($pixel['id']),
                PixelProvider::TIKTOK   => $this->tiktokSnippet($pixel['id']),
                PixelProvider::X        => $this->xSnippet($pixel['id']),
                default                 => '',
            };
        }

        return implode("\n", array_filter($parts));
    }

    /** GA4 + Google Ads — one gtag.js load, one config per ID. */
    private function gtagSnippet(array $ids, bool $sanitize): string
    {
        $first = $this->js($ids[0]);
        $js  = "var _g=document.createElement('script');_g.async=true;"
             . "_g.src='https://www.googletagmanager.com/gtag/js?id='+{$first};"
             . "document.head.appendChild(_g);\n";
        $js .= "gtag('js', new Date());\n";

        // Neutral location on sensitive pages so no service leaks via the URL.
        $cfg = $sanitize
            ? ", {page_path: '/booking', page_location: window.location.origin + '/booking'}"
            : '';

        foreach ($ids as $id) {
            $js .= "gtag('config', {$this->js($id)}{$cfg});\n";
        }

        return $js;
    }

    private function metaSnippet(string $id): string
    {
        $id = $this->js($id);
        return "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?"
            . "n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;"
            . "n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;"
            . "t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}"
            . "(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');\n"
            // Medical-privacy hardening: disable Meta Automatic Advanced
            // Matching so the pixel CANNOT auto-scrape PII (phone/email)
            // displayed on the page. Only our explicit, consented, hashed
            // values (Layer B) are ever sent.
            . "fbq('set', 'autoConfig', false, {$id});\n"
            . "fbq('init', {$id});\nfbq('track', 'PageView');";
    }

    private function snapSnippet(string $id): string
    {
        $id = $this->js($id);
        return "(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){"
            . "a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};"
            . "a.queue=[];var s='script';var r=t.createElement(s);r.async=!0;r.src=n;"
            . "var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u)})"
            . "(window,document,'https://sc-static.net/scevent.min.js');\n"
            . "snaptr('init', {$id});\nsnaptr('track', 'PAGE_VIEW');";
    }

    private function tiktokSnippet(string $id): string
    {
        $id = $this->js($id);
        return "!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];"
            . "ttq.methods=[\"page\",\"track\",\"identify\",\"instances\",\"debug\",\"on\",\"off\",\"once\",\"ready\",\"alias\",\"group\",\"enableCookie\",\"disableCookie\"],"
            . "ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};"
            . "for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);"
            . "ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},"
            . "ttq.load=function(e,n){var i=\"https://analytics.tiktok.com/i18n/pixel/events.js\";"
            . "ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,"
            . "ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=d.createElement(\"script\");o.type=\"text/javascript\","
            . "o.async=!0,o.src=i+\"?sdkid=\"+e+\"&lib=\"+t;var a=d.getElementsByTagName(\"script\")[0];"
            . "a.parentNode.insertBefore(o,a)};ttq.load({$id});ttq.page()}(window,document,'ttq');";
    }

    private function xSnippet(string $id): string
    {
        $id = $this->js($id);
        return "!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):"
            . "s.queue.push(arguments)},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,"
            . "u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],"
            . "a.parentNode.insertBefore(u,a))}(window,document,'script');\ntwq('config',{$id});";
    }

    /** JS-safe literal for a (already-validated) ID. */
    private function js(string $id): string
    {
        return json_encode($id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
}
