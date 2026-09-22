# RolesList

The roles screen (Screen 4): every role as one ledger row, ordered as an inheritance tree, with user counts and permission counts in tabular columns.

**Consumer provides:** the roles with name, one-line description, parent role, number of users, own and inherited permission counts, whether the role is a protected system role, and an optional short note.

- Order roles depth-first: a child sits directly under its parent, indented 24px per level and joined by a small `rule-strong` elbow. The "Inherits from" column repeats the parent as a dashed RoleLabel, so the tree also reads without the indent.
- Permissions read "7 own + 5 inherited"; a role with no parent reads "2 of 30". Figures in `body`, the words in `note`.
- Row actions are ghost buttons: Permissions (opens the matrix for that role) and Delete in `denied`. A system role (Administrator) has no Delete, and its note says why.
- Delete always opens a confirmation that names the users who will lose the role; the filled `denied` button appears only there.
- The footer totals role assignments and permissions defined; the note explains why assignments can exceed users.
- Don't show roles as cards or colored chips; don't draw a graph.
