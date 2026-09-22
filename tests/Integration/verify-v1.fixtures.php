<?php

/**
 * Fixtures for verify-v1: written straight into rolewarden_test after the README install
 * (Settings, Shield, RoleWarden migrations + seed). Shield-format users with hashes from
 * password_hash(PASSWORD_DEFAULT), Shield's own default. The password is random per run and
 * lives only in memory.
 */

declare(strict_types=1);

function make_fixtures(): array
{
    $pw = 'V1-' . bin2hex(random_bytes(6)) . '-Aa1!';
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');

    $role = fn (string $slug) => (int) q1('SELECT id FROM acl_roles WHERE slug = ?', [$slug]);
    $perm = fn (string $slug) => (int) q1('SELECT id FROM acl_permissions WHERE slug = ?', [$slug]);

    // Roles for hierarchy / limited access, written directly (role CRUD itself is exercised over HTTP).
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at) VALUES (?,?,?,NULL,0,0,?)', ['v1-parent', 'V1 Parent', 'Parent role for v1 tests', $now]);
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at) VALUES (?,?,?,?,0,0,?)', ['v1-child', 'V1 Child', 'Child role for v1 tests', $role('v1-parent'), $now]);
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at) VALUES (?,?,?,NULL,0,0,?)', ['v1-limited', 'V1 Limited', 'Only users.view', $now]);
    foreach (['roles.view', 'permissions.view'] as $p) {
        q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$role('v1-parent'), $perm($p)]);
    }
    q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$role('v1-child'), $perm('users.view')]);
    q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$role('v1-limited'), $perm('users.view')]);

    $users = [
        'owner'    => ['Dana Whitfield', 'dana@northwind-studio.test', 1, 'super-admin', '2026-09-20 10:00:00'],
        'admin2'   => ['Priya Nair', 'priya@northwind-studio.test', 1, 'admin', '2026-09-21 09:00:00'],
        'limited'  => ['Jonas Lindqvist', 'jonas@northwind-studio.test', 1, 'v1-limited', null],
        'subject'  => ['Mara Okafor', 'mara@northwind-studio.test', 1, 'user', '2026-09-19 08:00:00'],
        'child'    => ['Theo Brandt', 'theo@northwind-studio.test', 1, 'v1-child', '2026-09-18 08:00:00'],
        'disabled' => ['Lena Sato', 'lena@northwind-studio.test', 0, 'admin', '2026-09-17 08:00:00'],
    ];
    $ids = [];
    foreach ($users as $key => [$name, $email, $active, $roleSlug, $last]) {
        $username = substr(str_replace(' ', '', $name), 0, 30);
        q('INSERT INTO users (username, active, last_active, created_at, updated_at) VALUES (?,?,?,?,?)', [$username, $active, $last, $now, $now]);
        $id = (int) db()->insert_id;
        q('INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at, updated_at) VALUES (?,?,?,?,0,?,?)', [$id, 'email_password', $email, $hash, $now, $now]);
        q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$id, $role($roleSlug)]);
        $ids[$key] = ['id' => $id, 'email' => $email, 'name' => $name, 'username' => $username];
    }
    // Shield records last login in auth_logins; give two users a successful login row.
    foreach (['owner' => '2026-09-20 10:00:00', 'admin2' => '2026-09-21 09:00:00', 'subject' => '2026-09-19 08:00:00'] as $k => $d) {
        q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,1)', ['127.0.0.1', 'fixture', 'email_password', $ids[$k]['email'], $ids[$k]['id'], $d]);
    }

    return ['pw' => $pw, 'u' => $ids, 'role' => $role, 'perm' => $perm];
}
