<?php
/**
 * @var string $name
 * @var string $app
 * @var string $ip
 * @var string $device
 * @var string $at
 */
echo view('RoleWarden\Views\emails\layout', [
    'app' => $app,
    'title' => lang('RoleWarden.mail.newSignIn.title'),
    'lines' => [
        esc(lang('RoleWarden.mail.greeting', [$name])),
        esc(lang('RoleWarden.mail.newSignIn.body')),
        esc(lang('RoleWarden.mail.when')) . ' ' . esc($at) . '<br>' . esc(lang('RoleWarden.mail.device')) . ' ' . esc($device) . '<br>' . esc(lang('RoleWarden.mail.address')) . ' ' . esc($ip),
        esc(lang('RoleWarden.mail.newSignIn.notYou')),
    ],
    'action' => lang('RoleWarden.mail.reviewSessions'),
    'actionUrl' => site_url('rolewarden/sessions'),
]);
