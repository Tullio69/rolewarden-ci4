# RoleWarden

Roles & Permissions Panel for CodeIgniter 4, built on top of [Shield](https://github.com/codeigniter4/shield).

Shield owns identity and authentication. RoleWarden moves roles and permissions out of the
config file and into the database, and adds an admin panel to manage them at runtime.

## Requirements

- PHP 8.3 or later (tested up to 8.5)
- CodeIgniter 4.5 or later (tested up to the latest 4.x)
- CodeIgniter Shield (supported range fixed during M0)
- MySQL or MariaDB with InnoDB

## Status

Pre-release. Currently building the MVP; see `_AI-LOG.md` for the current state of the work.

## Project layout

```
src/
  Authorization/   Framework-agnostic authorization layer (resolver, hierarchy, contracts)
  Config/          Module configuration and route/service registration
  Controllers/     Admin panel controllers
  Database/        Migrations and seeds
  Entities/        User entity extending Shield's
  Filters/         Route filter for permission checks
  Helpers/         View helpers
  Language/        Translatable strings
  Models/          Data access
  Views/           Admin panel views (overridable by the host application)
  Assets/          Compiled CSS and JS for the panel
tests/
  Unit/            Authorization logic
  Integration/     Framework and Shield integration
```

The CodeIgniter test application lives in a sibling directory,
`../rolewarden-app-test`, generated locally with `composer create-project
codeigniter4/appstarter` and wired to this package through a Composer path
repository. It is kept outside this package so the path symlink in its
`vendor/` does not point back at its own parent.

## Local development

The database runs in Docker; PHP and Composer run natively on the host.

```
docker compose up -d
```

This starts MariaDB 11 on port 3306 with two databases, `rolewarden` for the test
application and `rolewarden_test` for the suite, plus Adminer on
<http://localhost:8080> (server `db`, user `root`, password `rolewarden`).

If port 3306 or 8080 is already taken, override them without editing the file:

```
DB_PORT=3307 ADMINER_PORT=8081 docker compose up -d
```

`docker compose down -v` deletes the data volume and recreates both databases from
scratch on the next start. Use it when migrations leave the schema in a state that is
faster to rebuild than to repair.

Credentials here are deliberately trivial: this stack is local only and never exposed.

## Conventions

- Table prefix `acl_`, configurable, deliberately distinct from Shield's `auth_`
- Code checks permissions, never roles: `can('users.delete')`, never `hasRole('admin')`
- Permission slugs follow `area.action`
- No file inside this package is meant to be edited by the buyer; every customization
  belongs in the host application

## License

Proprietary. Sold on CodeCanyon.
