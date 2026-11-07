<?php
/**
 * @var string $name
 * @var string $app
 * @var string $ip
 * @var string $device
 * @var string $at
 * @var bool   $sessions the recipient can open rolewarden/sessions
 */
echo view('RoleWarden\Views\emails\layout', [
    'app' => $app,
    'title' => lang('RoleWarden.mail.newSignIn.title'),
    'lines' => [
        esc(lang('RoleWarden.mail.greeting', [$name])),
        esc(lang('RoleWarden.mail.newSignIn.body')),
        esc(lang('RoleWarden.mail.when')) . ' ' . esc($at) . '<br>' . esc(lang('RoleWarden.mail.device')) . ' ' . esc($device) . '<br>' . esc(lang('RoleWarden.mail.address')) . ' ' . esc($ip),
        esc(lang($sessions ? 'RoleWarden.mail.newSignIn.notYou' : 'RoleWarden.mail.newSignIn.notYouNoSessions')),
    ],
    'action' => $sessions ? lang('RoleWarden.mail.reviewSessions') : null,
    'actionUrl' => $sessions ? site_url('rolewarden/sessions') : null,
]);
