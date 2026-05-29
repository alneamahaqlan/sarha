<?php

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Realtime channels for the unified-notification bell.
 *
 * Each role gets a private channel scoped to its own auth guard via
 * the `guards` option — that way the broadcasting/auth endpoint
 * resolves $user from the right guard (admin / clinic / web) before
 * running the callback. Without that, a logged-in clinic owner would
 * fail the admin channel check because the default guard wouldn't
 * have a user to compare.
 *
 *   admin.{id}   → Admin guard
 *   clinic.{id}  → Clinic guard
 *   user.{id}    → web (customer) guard
 */

Broadcast::channel('admin.{id}', function ($user, $id) {
    return $user instanceof Admin && (int) $user->id === (int) $id;
}, ['guards' => ['admin']]);

Broadcast::channel('clinic.{id}', function ($user, $id) {
    return $user instanceof Clinic && (int) $user->id === (int) $id;
}, ['guards' => ['clinic']]);

Broadcast::channel('user.{id}', function ($user, $id) {
    return $user instanceof User && (int) $user->id === (int) $id;
}, ['guards' => ['web']]);
