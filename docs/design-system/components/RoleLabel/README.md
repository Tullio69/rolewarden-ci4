# RoleLabel

A small square-cornered label naming a role, used in the users table and wherever roles are listed inline.

**Consumer provides:** the role name as text; optionally a link wrapper to the role's page.

- Class `rw-role`: 20px tall, 1px `rule-strong` border, `radius-sm`, text in `ink` at 12px weight 500. No fill, no per-role colors: roles are not categories to color-code.
- `rw-role--parent` (dashed border, `ink-muted`) marks a role shown as the source of inheritance.
- Separate several labels by `space-1`; after three labels show "+2" in `note` with the full list in a tooltip.
- Don't use green or red labels for roles, and don't use pill shapes.
