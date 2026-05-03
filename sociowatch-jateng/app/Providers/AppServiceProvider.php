<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // can-edit: superadmin & admin can create/update records
        Gate::define('can-edit', fn (User $user) => in_array($user->role, ['superadmin', 'admin']));

        // can-delete: only superadmin can permanently delete
        Gate::define('can-delete', fn (User $user) => $user->role === 'superadmin');

        // manage-users: only superadmin
        Gate::define('manage-users', fn (User $user) => $user->role === 'superadmin');

        // view-activity-log: only superadmin
        Gate::define('view-activity-log', fn (User $user) => $user->role === 'superadmin');

        // Inactive users cannot access anything
        Gate::before(fn (User $user) => $user->is_active ? null : false);
    }
}
