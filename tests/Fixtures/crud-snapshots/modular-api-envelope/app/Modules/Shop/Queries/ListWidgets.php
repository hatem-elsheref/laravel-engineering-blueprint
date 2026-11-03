<?php

declare(strict_types=1);

namespace App\Modules\Shop\Queries;

use App\Models\User;
use App\Modules\Shop\Models\Widget;
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListWidgets
{
    /**
     * The user's own widgets, or every one when they may see all (WidgetPolicy::any()).
     *
     * @return LengthAwarePaginator<int, Widget>
     */
    public function handle(User $user, ?int $perPage = null): LengthAwarePaginator
    {
        return Widget::query()
            ->when(! $user->can('any', Widget::class), fn ($query) => $query->whereBelongsTo($user))
            ->latest('id')
            ->paginate(ListPerPage::resolve($perPage));
    }
}
