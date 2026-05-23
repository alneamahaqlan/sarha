<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\PlatformNotification;
use App\Models\PriceQuoteRequest;
use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for creating in-app notifications.
 * Every business event that should ring a bell flows through here.
 */
class NotificationService
{
    // ============ ADMIN-side events ============

    public function newBooking(Booking $booking): void
    {
        $this->broadcastToAdmins(
            type: 'new_booking',
            icon: 'heroicon-o-calendar',
            url: '/app/admin/bookings',
            priority: 'normal',
            title: __('admin.notif.new_booking_title'),
            body:  __('admin.notif.new_booking_body', [
                'clinic'   => $booking->clinic?->name ?? '—',
                'customer' => $booking->customer_name,
            ]),
            data: ['booking_id' => $booking->id],
        );
    }

    public function newComplaint(Complaint $complaint): void
    {
        $this->broadcastToAdmins(
            type: 'new_complaint',
            icon: 'heroicon-o-exclamation-triangle',
            url: '/app/admin/complaints',
            priority: $complaint->priority === 'high' ? 'high' : 'normal',
            title: __('admin.notif.new_complaint_title'),
            body:  __('admin.notif.new_complaint_body', [
                'subject' => $complaint->subject,
            ]),
            data: ['complaint_id' => $complaint->id],
        );
    }

    public function newPriceQuoteRequest(PriceQuoteRequest $quote): void
    {
        // Goes to the targeted clinic, not admins
        if ($quote->clinic) {
            $this->push(
                $quote->clinic,
                type: 'new_price_quote',
                icon: 'heroicon-o-currency-dollar',
                url: '/app/clinic/price-quotes',
                priority: 'high',
                title: __('admin.notif.new_quote_title'),
                body:  __('admin.notif.new_quote_body', [
                    'service' => $quote->service_name,
                ]),
                data: ['quote_id' => $quote->id],
            );
        }
    }

    public function newClinicBooking(Booking $booking): void
    {
        if (! $booking->clinic) return;

        $this->push(
            $booking->clinic,
            type: 'new_booking',
            icon: 'heroicon-o-calendar',
            url: '/app/clinic/bookings',
            priority: 'high',
            title: __('admin.notif.new_booking_clinic_title'),
            body:  __('admin.notif.new_booking_clinic_body', [
                'customer' => $booking->customer_name,
                'ref'      => $booking->reference_code,
            ]),
            data: ['booking_id' => $booking->id],
        );
    }

    public function leadConverted(SalesLead $lead, Clinic $clinic): void
    {
        $this->broadcastToAdmins(
            type: 'lead_converted',
            icon: 'heroicon-o-check-badge',
            url: '/app/admin/clinics',
            priority: 'normal',
            title: __('admin.notif.lead_converted_title'),
            body:  __('admin.notif.lead_converted_body', ['clinic' => $clinic->name]),
            data: ['clinic_id' => $clinic->id, 'lead_id' => $lead->id],
        );
    }

    public function subscriptionExpiringSoon(Clinic $clinic, int $daysLeft): void
    {
        // Notify both the clinic owner AND the admins
        $title = __('admin.notif.expiring_title');
        $body  = __('admin.notif.expiring_body', ['days' => $daysLeft, 'clinic' => $clinic->name]);

        $this->push(
            $clinic,
            type: 'subscription_expiring',
            icon: 'heroicon-o-clock',
            url: null,
            priority: $daysLeft <= 3 ? 'urgent' : 'high',
            title: $title,
            body:  $body,
            data: ['days_left' => $daysLeft],
        );

        $this->broadcastToAdmins(
            type: 'subscription_expiring',
            icon: 'heroicon-o-clock',
            url: '/app/admin/clinics',
            priority: 'high',
            title: $title,
            body:  $body,
            data: ['clinic_id' => $clinic->id, 'days_left' => $daysLeft],
        );
    }

    public function subscriptionExpired(Clinic $clinic): void
    {
        $this->push(
            $clinic,
            type: 'subscription_expired',
            icon: 'heroicon-o-x-circle',
            url: null,
            priority: 'urgent',
            title: __('admin.notif.expired_title'),
            body:  __('admin.notif.expired_body'),
        );

        $this->broadcastToAdmins(
            type: 'subscription_expired',
            icon: 'heroicon-o-x-circle',
            url: '/app/admin/clinics',
            priority: 'high',
            title: __('admin.notif.expired_title_admin', ['clinic' => $clinic->name]),
            body:  __('admin.notif.expired_body_admin'),
            data: ['clinic_id' => $clinic->id],
        );
    }

    public function offerExpiringSoon(Clinic $clinic, int $servicesCount): void
    {
        $this->push(
            $clinic,
            type: 'offer_expiring',
            icon: 'heroicon-o-tag',
            url: null,
            priority: 'normal',
            title: __('admin.notif.offer_expiring_title'),
            body:  __('admin.notif.offer_expiring_body', ['count' => $servicesCount]),
            data: ['services' => $servicesCount],
        );
    }

    public function clinicApproved(Clinic $clinic): void
    {
        $this->push(
            $clinic,
            type: 'clinic_approved',
            icon: 'heroicon-o-check-circle',
            url: null,
            priority: 'high',
            title: __('admin.notif.approved_title'),
            body:  __('admin.notif.approved_body'),
        );
    }

    public function clinicRejected(Clinic $clinic, string $reason): void
    {
        $this->push(
            $clinic,
            type: 'clinic_rejected',
            icon: 'heroicon-o-x-circle',
            url: null,
            priority: 'urgent',
            title: __('admin.notif.rejected_title'),
            body:  __('admin.notif.rejected_body', ['reason' => $reason]),
        );
    }

    public function articlePublished(Article $article): void
    {
        $clinic = $article->clinic;

        $this->broadcastToAdmins(
            type: 'article_published',
            icon: 'heroicon-o-document-text',
            url: '/app/admin/articles',
            priority: 'normal',
            title: __('admin.notif.article_published_title'),
            body:  __('admin.notif.article_published_body', [
                'clinic'  => $clinic?->name ?? '—',
                'article' => $article->title ?? '—',
            ]),
            data: ['article_id' => $article->id, 'clinic_id' => $clinic?->id],
        );
    }

    public function adminImpersonated(Clinic $clinic, Admin $admin): void
    {
        $this->push(
            $clinic,
            type: 'admin_impersonated',
            icon: 'heroicon-o-eye',
            url: null,
            priority: 'high',
            title: __('admin.notif.impersonated_title'),
            body:  __('admin.notif.impersonated_body', ['admin' => $admin->name]),
            data: ['admin_id' => $admin->id],
        );
    }

    // ============ Public mass-notification page ============

    public function massNotify(iterable $recipients, array $payload): int
    {
        $count = 0;
        foreach ($recipients as $recipient) {
            $this->push(
                $recipient,
                type: $payload['type']     ?? 'broadcast',
                icon: $payload['icon']     ?? 'heroicon-o-megaphone',
                url: $payload['url']       ?? null,
                priority: $payload['priority'] ?? 'normal',
                title: $payload['title'],
                body:  $payload['body'],
                data: $payload['data'] ?? null,
            );
            $count++;
        }
        return $count;
    }

    // ============ Low-level primitives ============

    public function push(
        Model $recipient,
        string $type,
        string $title,
        string $body,
        ?string $icon = null,
        ?string $url = null,
        string $priority = 'normal',
        ?array $data = null,
    ): PlatformNotification {
        return PlatformNotification::create([
            'notifiable_type' => $recipient::class,
            'notifiable_id'   => $recipient->getKey(),
            'type'            => $type,
            'icon'            => $icon,
            'url'             => $url,
            'priority'        => $priority,
            'title'           => $title,
            'body'            => $body,
            'data'            => $data,
        ]);
    }

    public function broadcastToAdmins(
        string $type,
        string $title,
        string $body,
        ?string $icon = null,
        ?string $url = null,
        string $priority = 'normal',
        ?array $data = null,
    ): int {
        $count = 0;
        Admin::where('is_active', true)->each(function (Admin $admin) use (
            $type, $title, $body, $icon, $url, $priority, $data, &$count
        ) {
            $this->push($admin, $type, $title, $body, $icon, $url, $priority, $data);
            $count++;
        });
        return $count;
    }
}
