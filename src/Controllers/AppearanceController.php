<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Account\ActivityLog;
use RoleWarden\Settings\ColorScheme;
use RoleWarden\Settings\Theme;
use Throwable;

/**
 * Appearance: the theme customiser (permission appearance.update) and the
 * light/dark switch every signed-in user has in the top bar. The theme is a
 * file of the host application (RoleWarden\Settings\Theme), never a module
 * file, so updating the module keeps it.
 */
class AppearanceController extends BaseController
{
    public function index(): string
    {
        return rw_panel('RoleWarden\Views\appearance\index', [
            'theme' => Theme::current(),
            'pinned' => Theme::isPinned(),
            'errors' => session()->getFlashdata('rw_errors') ?? [],
        ], 'appearance', lang('RoleWarden.panel.appearance.title'));
    }

    public function update(): RedirectResponse
    {
        $back = site_url('rolewarden/appearance');

        if (Theme::isPinned()) {
            return redirect()->to($back)->with('rw_error', lang('RoleWarden.panel.appearance.pinned'));
        }

        $post = $this->request->getPost() ?? [];
        $errors = [];
        $base = is_string($post['base'] ?? null) ? $post['base'] : '';

        if (! isset(Theme::bases()[$base])) {
            $errors['base'] = lang('RoleWarden.panel.appearance.chooseTheme');
        }

        $radius = is_string($post['radius'] ?? null) && $post['radius'] !== '' ? (int) $post['radius'] : null;

        if ($radius !== null && ! in_array($radius, Theme::RADII, true)) {
            $errors['radius'] = lang('RoleWarden.panel.appearance.chooseListed');
        }

        $density = is_string($post['density'] ?? null) && $post['density'] !== '' ? $post['density'] : null;

        if ($density !== null && ! isset(Theme::DENSITIES[$density])) {
            $errors['density'] = lang('RoleWarden.panel.appearance.chooseListed');
        }

        $colors = ['light' => [], 'dark' => []];

        foreach (['light', 'dark'] as $mode) {
            foreach (Theme::COLORS as $key) {
                $value = $post['colors'][$mode][$key] ?? '';

                if (! is_string($value) || $value === '') {
                    continue;
                }

                if (! Theme::isHex($value)) {
                    $errors["{$mode}-{$key}"] = lang('RoleWarden.panel.appearance.hex');

                    continue;
                }

                $colors[$mode][$key] = $value;
            }
        }

        if ($errors !== []) {
            return redirect()->to($back)->withInput()->with('rw_errors', $errors);
        }

        $before = Theme::current();
        $after = Theme::normalise(['base' => $base, 'radius' => $radius, 'density' => $density, 'colors' => $colors]);

        try {
            Theme::save($after);
        } catch (Throwable $e) {
            log_message('error', 'RoleWarden: theme not saved: {message}', ['message' => $e->getMessage()]);

            return redirect()->to($back)->withInput()->with('rw_error', lang('RoleWarden.panel.appearance.notWritable'));
        }

        $changes = ActivityLog::diff(self::flat($before), self::flat($after));

        if ($changes !== []) {
            ActivityLog::record('appearance.updated', 'appearance', null, lang('RoleWarden.panel.appearance.title'), $changes);
        }

        return redirect()->to($back)->with('rw_success', lang('RoleWarden.panel.appearance.saved'));
    }

    public function reset(): RedirectResponse
    {
        $back = site_url('rolewarden/appearance');

        if (Theme::isPinned()) {
            return redirect()->to($back)->with('rw_error', lang('RoleWarden.panel.appearance.pinned'));
        }

        $before = Theme::current();
        Theme::reset();
        $changes = ActivityLog::diff(self::flat($before), self::flat(Theme::current()));

        if ($changes !== []) {
            ActivityLog::record('appearance.updated', 'appearance', null, lang('RoleWarden.panel.appearance.title'), $changes);
        }

        return redirect()->to($back)->with('rw_success', lang('RoleWarden.panel.appearance.resetDone'));
    }

    public function download(): ResponseInterface
    {
        $json = json_encode(Theme::current(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        return $this->response->download('theme.json', $json)->setContentType('application/json');
    }

    /**
     * The theme file's overrides as a stylesheet (linked, versioned, by the layout).
     */
    public function css(): ResponseInterface
    {
        return $this->response
            ->setContentType('text/css')
            ->setHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->setBody(Theme::css(Theme::current()));
    }

    /**
     * The signed-in user's light/dark choice, from the top bar.
     */
    public function mode(): RedirectResponse
    {
        $choice = $this->request->getPost('mode');

        if (is_string($choice) && in_array($choice, ColorScheme::CHOICES, true)) {
            ColorScheme::set((int) auth()->id(), $choice);
        }

        return redirect()->back();
    }

    /**
     * @param array{base: string, radius: int|null, density: string|null, colors: array{light: array<string, string>, dark: array<string, string>}} $theme
     *
     * @return array<string, mixed>
     */
    private static function flat(array $theme): array
    {
        $flat = ['base' => $theme['base'], 'radius' => $theme['radius'], 'density' => $theme['density']];

        foreach ($theme['colors'] as $mode => $colors) {
            foreach (Theme::COLORS as $key) {
                $flat["{$mode}.{$key}"] = $colors[$key] ?? null;
            }
        }

        return $flat;
    }
}
