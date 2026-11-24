<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/** The in-app notifications of a user. */
class Inbox
{
    private const LATEST = 50;

    /** @return Collection<int, DatabaseNotification> */
    public function latest(User $user): Collection
    {
        return $user->notifications()->limit(self::LATEST)->get();
    }

    public function unread(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
