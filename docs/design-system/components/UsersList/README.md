# UsersList

The users directory (Screen 2): a ledger table of everyone who can sign in, with search, role and status filters, sorting by last activity and pagination.

**Consumer provides:** the page of users (name, email, roles, active flag, last activity (Shield's last-active timestamp) or null, optional short note), the total and per-status counts, the current filters and sort, and the pager state.

Layout on the 1440px grid: the header repeats the matrix pattern (overline, `title-page`, one-line description, a hairline definition list of counts in serif figures). The toolbar holds Search (with a 16px search glyph), Role and Status selects, Clear filters (ghost, only when a filter is set), then Export CSV (secondary) and Invite user (primary) on the right.

Columns: row number gutter (`note`, numbering continues across pages), Name (`body-strong`, links to the user's page), Email (`ink-muted`), Roles (RoleLabel, up to three), Status (UserStatus), Last active (right-aligned, tabular, "Never" in `ink-faint` for invited users), and a 200px marginal Notes column ("you", "2 overrides", "invited 21 Sep", "5 failed sign-ins").

- Filters apply as you type and reset to page 1; Clear filters restores everything and returns focus to Search.
- No results: one row reading "No users match these filters." with a Clear filters button, never an illustration.
- Sorting: the Last active header is a button; it sorts by the user's last activity, not by the last sign-in; `aria-sort` on the `th` reflects the direction.
- Alpine sketch: `x-data="{ q:'', role:'', status:'', page:1 }"`, filters bound with `x-model` and debounced requests to the CodeIgniter controller; server-side pagination via CI4's Pager.
- Don't add avatars, checkboxes or bulk actions unless the product needs them; don't color status or roles.
