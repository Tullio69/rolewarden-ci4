# Dashboard

The first screen after sign-in (direction B, bold ledger): key figures in a ledger strip, sign-ins over time, when people sign in, who holds which role, and the latest permission changes.

**Consumer provides:** the period (7, 30 or 90 days) and its dates; the signed-in user's first name; an optional security notice with its action; the four key figures, each with a delta and a note; daily successful and failed sign-ins; sign-ins by weekday and hour; users per role; the latest permission changes with actor, target, identifier, mark and time; which blocks the user may see and in what order.

- Every block has its own permission (`dashboard.figures`, `dashboard.signins`, `dashboard.security`, `dashboard.roles`, `dashboard.changes`); a block the user can't see isn't rendered, and the layout closes up.
- Customize opens a panel where admins pick blocks, order and default period, with defaults per role; users may hide or reorder only the blocks they're allowed.
- Key figures: four at most, the first value in `accent`, the rest in `ink`, each with a `surface-sunk` delta chip and a `note`. Never tiles or cards.
- The security notice is one `accent-tint` strip with a shield glyph, one sentence and one primary action. Never `denied`: it informs, it doesn't alarm.
- Charts follow the README's chart rules: `accent` line over flat `accent-tint`, `ink` bars for failures, `heat-0` to `heat-4` for the heat map (each cell names its count in a tooltip), a split bar plus a ledger list for shares.
- The changes list uses the five permission marks and links to the activity log ("View all 23").
- Buyers add their own blocks through a registration hook; their blocks follow the same rules.
- Don't add a block without a figure someone acts on; empty blocks say what will appear and why it's empty.
