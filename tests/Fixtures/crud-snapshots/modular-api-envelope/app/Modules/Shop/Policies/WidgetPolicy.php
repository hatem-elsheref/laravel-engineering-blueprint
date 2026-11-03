<?php

declare(strict_types=1);

namespace App\Modules\Shop\Policies;

use App\Models\User;
use App\Modules\Shop\Models\Widget;
use Illuminate\Auth\Access\Response;

/**
 * Each ability needs a named permission (widgets.view, .create, .update, .delete). A widget belongs to the
 * user who created it. A widget the user may not view (without .view, or another user's unless they hold
 * widgets.any) is "not found"; one they may view but not change is "forbidden". widgets.any widens the
 * permissions they hold to every widget and grants nothing alone. Grant them to roles in
 * Permissions::roles() and run permissions:sync. Guests are always denied: every method requires a User.
 */
final class WidgetPolicy
{
    private const GROUP = 'widgets';

    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.view');
    }

    public function view(User $user, Widget $widget): Response
    {
        return $this->owned($user, $widget, 'view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.create');
    }

    public function update(User $user, Widget $widget): Response
    {
        return $this->owned($user, $widget, 'update');
    }

    public function delete(User $user, Widget $widget): Response
    {
        return $this->owned($user, $widget, 'delete');
    }

    /**
     * Whether the user may reach every widget, not only their own (ListWidgets asks this too).
     */
    public function any(User $user): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.any');
    }

    private function owned(User $user, Widget $widget, string $ability): Response
    {
        // A widget the user may not view is "not found" whatever they ask, so users can't probe which ones exist.
        if (! $user->checkPermissionTo(self::GROUP.'.view') || ! ($widget->user()->is($user) || $this->any($user))) {
            return Response::denyAsNotFound();
        }

        return $user->checkPermissionTo(self::GROUP.'.'.$ability) ? Response::allow() : Response::deny();
    }
}
