<?php
// Phase B (S1, point 3): a clean installation seeds exactly the same English texts as b1cca14 (SPEC 2026-10-16: "Il
// testo salvato resta quello inglese del seed"). Empties rolewarden_test twice: run it last, before G.
$seedState = function (): array {
    $roles = q('SELECT r.slug, r.name, r.description, p.slug AS parent, r.is_system, r.is_super_admin, r.deleted_at FROM acl_roles r LEFT JOIN acl_roles p ON p.id=r.parent_id ORDER BY r.slug');
    $perms = q('SELECT slug, area, description, is_system FROM acl_permissions ORDER BY slug');
    $grants = q('SELECT r.slug AS role, p.slug AS perm FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id ORDER BY r.slug, p.slug');
    $settings = q('SELECT class, `key`, value, type, context FROM settings ORDER BY class, `key`, context');
    $docs = tables() && in_array('demo_documents', tables(), true) ? q("SELECT d.title, d.body, d.status, i.secret AS owner FROM demo_documents d JOIN auth_identities i ON i.user_id=d.owner_id AND i.type='email_password' ORDER BY d.title") : [];
    $users = q("SELECT u.username, u.active, i.secret FROM users u LEFT JOIN auth_identities i ON i.user_id=u.id AND i.type='email_password' ORDER BY u.username");
    return compact('roles', 'perms', 'grants', 'settings', 'docs', 'users');
};
$res = [];
foreach (['b1cca14' => "$WORK/basefull", '30de388' => $MODFULL] as $rev => $mod) {
    $AB = app_copy("appB$rev", $mod, true);
    $ok = base_install($AB);
    $o = sp8($AB, ['demo:install'], ['demo.password' => DPW]);
    $res[$rev] = $seedState();
    info("seed $rev: install " . json_encode($ok) . ', demo ' . rc($o) . ', ' . count($res[$rev]['roles']) . ' roles, ' . count($res[$rev]['perms']) . ' permissions, ' . count($res[$rev]['grants']) . ' grants, ' . count($res[$rev]['settings']) . ' settings, ' . count($res[$rev]['docs']) . ' documents');
}
foreach (['roles', 'perms', 'grants', 'settings', 'docs', 'users'] as $k) {
    $a = array_map('json_encode', $res['b1cca14'][$k]); $b = array_map('json_encode', $res['30de388'][$k]);
    check("B01-$k", "Clean installation (module seed and demo:install): $k identical to b1cca14 (" . count($b) . ' rows)', $a === $b, json_encode(['only b1cca14' => array_values(array_diff($a, $b)), 'only 30de388' => array_values(array_diff($b, $a))], JSON_UNESCAPED_UNICODE));
}
