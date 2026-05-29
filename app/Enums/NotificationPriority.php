<?php

namespace App\Enums;

/**
 * Priority drives channel routing + sound + Web Push lock-screen
 * behaviour. Mapped here so any layer (Dispatcher, Service Worker,
 * UI) can ask one source.
 */
enum NotificationPriority: string
{
    case INFO   = 'info';     // in-app only, no sound
    case NORMAL = 'normal';   // in-app + web push, no sound
    case HIGH   = 'high';     // in-app + web push + normal sound
    case URGENT = 'urgent';   // all of the above + distinct sound + lock-screen

    public function shouldPlaySound(): bool
    {
        return in_array($this, [self::HIGH, self::URGENT], true);
    }

    public function shouldWebPush(): bool
    {
        return $this !== self::INFO;
    }
}
