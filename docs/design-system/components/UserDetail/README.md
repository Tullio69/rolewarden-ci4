# UserDetail

One user's page (Screen 5): their roles, their effective permissions with per-user overrides, and account actions, in three lettered ledger sections.

**Consumer provides:** the user (name, email, status, last login, member since), their roles with parent chain and permission counts, the effective state of every `area.action` with its source role, the user's saved overrides, and session and password facts.

- **A · Roles:** one row per role with its inheritance chain in `note`, Permissions and Remove ghost buttons, and an Add a role select + secondary button. Removing a role that feeds an override keeps the override and notes it.
- **B · Effective permissions:** the same matrix as the role screen, without row/column select-all. Solid checks and ditto marks come from roles (tooltip "Granted by Editor, Support agent" or "from Author, via Editor"). Clicking a cell toggles an override for this user only: on a granted cell it adds a denial, on an empty cell a grant. The unsaved bar reads "N unsaved changes to Priya's overrides" and saves with Save overrides. The header fact "Overrides" counts them live.
- **C · Account:** Send reset link and Sign out everywhere (secondary), Disable user (danger, outlined). Disabling is reversible and keeps roles and history; say so on the row.
- Don't let a user remove their own last Administrator role or disable themselves; disable the control and explain in the marginal note. "Administrator role" is defined by permission, not by name: a role the user cannot remove from themselves is one without which they would lose `roles.assign` (decided 2026-09-23).
