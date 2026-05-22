<?php

namespace App\Livewire;

use App\Models\PlatformNotification;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    public string $guard = 'admin';
    public bool $open = false;

    public function mount(string $guard = 'admin'): void
    {
        $this->guard = $guard;
    }

    public function recipient(): ?Model
    {
        return auth($this->guard)->user();
    }

    #[Computed]
    public function unreadCount(): int
    {
        $user = $this->recipient();
        if (! $user) return 0;

        return PlatformNotification::forRecipient($user)->unread()->count();
    }

    #[Computed]
    public function notifications()
    {
        $user = $this->recipient();
        if (! $user) return collect();

        return PlatformNotification::forRecipient($user)
            ->latest()
            ->limit(15)
            ->get();
    }

    public function markAsRead(int $notificationId): void
    {
        $user = $this->recipient();
        if (! $user) return;

        PlatformNotification::forRecipient($user)
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);

        unset($this->unreadCount, $this->notifications);
    }

    public function markAllRead(): void
    {
        $user = $this->recipient();
        if (! $user) return;

        PlatformNotification::forRecipient($user)
            ->unread()
            ->update(['read_at' => now()]);

        unset($this->unreadCount, $this->notifications);
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
