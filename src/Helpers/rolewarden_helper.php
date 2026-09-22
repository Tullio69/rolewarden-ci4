<?php

declare(strict_types=1);

// Load with helper('rolewarden'). A guest is denied everything.

if (! function_exists('can')) {
    function can(string $permission): bool
    {
        return auth()->loggedIn() && service('rolewarden')->can((int) auth()->id(), $permission);
    }
}

if (! function_exists('can_any')) {
    /** @param list<string> $permissions */
    function can_any(array $permissions): bool
    {
        return auth()->loggedIn() && service('rolewarden')->canAny((int) auth()->id(), $permissions);
    }
}

if (! function_exists('can_all')) {
    /** @param list<string> $permissions */
    function can_all(array $permissions): bool
    {
        return auth()->loggedIn() && service('rolewarden')->canAll((int) auth()->id(), $permissions);
    }
}

if (! function_exists('permissions')) {
    /** @return list<string> */
    function permissions(): array
    {
        return auth()->loggedIn() ? service('rolewarden')->permissions((int) auth()->id()) : [];
    }
}

if (! function_exists('rw_panel')) {
    /**
     * Renders a panel screen inside the shared layout.
     *
     * @param array<string, mixed> $data
     */
    function rw_panel(string $view, array $data, string $active, string $title): string
    {
        $body = view($view, $data);

        return view('RoleWarden\Views\layouts\panel', ['active' => $active, 'title' => $title, 'body' => $body]);
    }
}
