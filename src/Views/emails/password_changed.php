<?php
/**
 * @var string $name
 * @var string $app
 * @var bool   $byAdmin
 * @var string $at
 */
echo view('RoleWarden\Views\emails\layout', [
    'app' => $app,
    'title' => lang('RoleWarden.mail.passwordChanged.title'),
    'lines' => [
        esc(lang('RoleWarden.mail.greeting', [$name])),
        esc(lang($byAdmin ? 'RoleWarden.mail.passwordChanged.byAdmin' : 'RoleWarden.mail.passwordChanged.body', [$at])),
        esc(lang('RoleWarden.mail.passwordChanged.notYou')),
    ],
    'action' => null,
    'actionUrl' => null,
]);
