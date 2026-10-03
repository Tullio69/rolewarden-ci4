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

if (! function_exists('rw_query')) {
    /**
     * A query-string value as text: '' when missing or when it is not a
     * string (e.g. ?q[]=x), so filters never see an array.
     */
    function rw_query(string $key): string
    {
        $value = service('request')->getGet($key);

        return is_string($value) ? trim($value) : '';
    }
}

if (! function_exists('rw_html_attributes')) {
    /**
     * Attributes for <html>: the global theme and the signed-in user's light or
     * dark choice (absent = follow the system). Read by panel.css.
     */
    function rw_html_attributes(): string
    {
        $theme = RoleWarden\Settings\Theme::current();
        $mode = RoleWarden\Settings\ColorScheme::forUser(auth()->loggedIn() ? (int) auth()->id() : null);

        return 'data-rw-theme="' . esc($theme['base'], 'attr') . '"' . ($mode !== 'system' ? ' data-rw-mode="' . esc($mode, 'attr') . '"' : '');
    }
}

if (! function_exists('rw_stylesheets')) {
    /**
     * The panel's stylesheet, versioned by file time so an update is picked up
     * at once, and the theme file's overrides when there are any.
     */
    function rw_stylesheets(): string
    {
        $css = __DIR__ . '/../Assets/css/panel.css';
        $html = '<link rel="stylesheet" href="' . esc(site_url('rolewarden/assets/css/panel.css') . '?v=' . (string) @filemtime($css)) . '">';
        $overrides = RoleWarden\Settings\Theme::css(RoleWarden\Settings\Theme::current());

        $hostSheet = config('RoleWarden')->themeStylesheet ?? null;

        if (is_string($hostSheet) && $hostSheet !== '') {
            $html .= '
  ' . '<link rel="stylesheet" href="' . esc($hostSheet, 'attr') . '">';
        }

        if ($overrides !== '') {
            $html .= '
  ' . '<link rel="stylesheet" href="' . esc(site_url('rolewarden/theme.css') . '?v=' . substr(md5($overrides), 0, 12)) . '">';
        }

        return $html;
    }
}

if (! function_exists('rw_panel')) {
    /**
     * Renders a panel screen inside the shared layout. $crumbs is the inner
     * HTML of the breadcrumb nav; left empty, the layout shows just the
     * active section's own name.
     *
     * @param array<string, mixed> $data
     */
    function rw_panel(string $view, array $data, string $active, string $title, string $crumbs = ''): string
    {
        $body = view($view, $data);

        return view('RoleWarden\Views\layouts\panel', ['active' => $active, 'title' => $title, 'body' => $body, 'crumbs' => $crumbs]);
    }
}
