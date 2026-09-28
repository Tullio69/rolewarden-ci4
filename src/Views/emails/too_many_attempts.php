<?php
/**
 * @var string $name
 * @var string $app
 * @var string $email   the email that was locked
 * @var int    $minutes
 * @var string $ip
 * @var bool   $isOwner the recipient owns that email; otherwise an admin with security.alerts
 */
echo view('RoleWarden\Views\emails\layout', [
    'app' => $app,
    'title' => lang('RoleWarden.mail.tooManyAttempts.title'),
    'lines' => [
        esc(lang('RoleWarden.mail.greeting', [$name])),
        esc(lang($isOwner ? 'RoleWarden.mail.tooManyAttempts.owner' : 'RoleWarden.mail.tooManyAttempts.admin', [$email, $minutes])),
        esc(lang('RoleWarden.mail.address')) . ' ' . esc($ip),
        esc(lang($isOwner ? 'RoleWarden.mail.tooManyAttempts.ownerAdvice' : 'RoleWarden.mail.tooManyAttempts.adminAdvice')),
    ],
    'action' => $isOwner ? null : lang('RoleWarden.mail.reviewActivity'),
    'actionUrl' => $isOwner ? null : site_url('rolewarden/activity') . '?' . http_build_query(['user' => $email]),
]);
