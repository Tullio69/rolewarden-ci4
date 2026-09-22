# PermissionMatrix

The permission matrix for one role (Screen 1): areas as rows, actions as columns, one PermissionMark per cell, with select-all per row and per column, an unsaved-changes bar and a totals line.

**Consumer provides:** the role (name, parent role, user count, last saved), the list of areas and actions, the saved state of every `area.action` for this role, the inherited set from the parent role, and a summary of per-user overrides (count and names) for users holding the role.

Layout, left to right on the 1440px grid: a 48px gutter with row numbers (`note`, `ink-faint`), the area column (name in `body-strong`, `area.*` in `code-sm`), five 104px action columns, a "Row" select-all column set off by a `rule`, and a 200px marginal column for annotations ("1 changed", "3 from Author", "2 overrides").

Behaviour:
- Clicking a cell toggles only between granted by role and not granted. Inherited cells are locked (`aria-disabled`) and say "from Author" on hover and focus. Override cells are read-only here; they are edited on the user's page.
- Column and row checkboxes act only on editable cells; they show checked, mixed (`indeterminate`) or off.
- A changed cell takes `accent-tint` plus a 6px `accent` corner dot, so the change reads without color too. The bar above the table turns `accent-tint` with an `accent` rule and lists the count and the changed identifiers in `code-sm`, with Discard (ghost) and Save changes (primary). With no changes it reads "All changes saved." and Save is disabled.
- A denied override only appears where the role itself grants the permission (denying something the role never grants has no effect); its tooltip says both: "Granted by Editor, denied for 1 user: Priya Nair".
- The footer line "Effective" counts, per column, what the role grants (directly, by inheritance, or under a user's denial), in tabular figures. Grant overrides are not counted: they belong to users.
- Legend with all five marks sits in the toolbar, right-aligned, on every matrix screen.

Alpine sketch: `x-data="{ saved: {...}, cur: {...} }"`; each cell is a `<button @click="toggle(key)" :aria-pressed="cur[key]==='role'">`; `dirty` is a getter comparing `saved` and `cur`; Save posts `cur` to the CodeIgniter controller and copies it into `saved`.

- Don't use green or red for anything but the marks themselves.
- Don't hide the row numbers or the totals line: they are what makes it a ledger.
