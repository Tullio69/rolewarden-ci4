# Dashboard

The first screen after sign-in (direction B, bold ledger): key figures in a ledger strip, sign-ins over time, when people sign in, who holds which role, and the latest permission changes.

**Consumer provides:** the period (7, 30 or 90 days) and its dates; the signed-in user's first name; an optional security notice with its action; the four key figures, each with a delta and a note; daily successful and failed sign-ins; sign-ins by weekday and hour; users per role; the latest permission changes with actor, target, identifier, mark and time; which blocks the user may see and in what order.

- Every block has its own permission (`dashboard.figures`, `dashboard.signins`, `dashboard.security`, `dashboard.roles`, `dashboard.changes`); a block the user can't see isn't rendered, and the layout closes up.
- Customize, a secondary button next to the period, opens a panel: one row per block the user can see, with a checkbox to show it and up and down buttons for its order, then the period the page opens with. "Save for me", "Back to the default", and "Save as default for everyone" for those with `settings.update`. No defaults per role, no dragging. The security notice can be hidden but never moves: when it shows, it comes first.
- Key figures: four at most, the first value in `accent`, the rest in `ink`, each with a `surface-sunk` delta chip and a `note`. Never tiles or cards.
- The security notice is one `accent-tint` strip with a shield glyph, one line per reason (every address at the lock threshold, then one line naming every locked email) and one primary action. Never `denied`: it informs, it doesn't alarm.
- Charts follow the README's chart rules: `accent` line over flat `accent-tint`, `ink` bars for failures, `heat-0` to `heat-4` for the heat map, drawn as SVG (each cell names its count in a tooltip), a split bar with a legend of names for shares. Every chart's numbers, the shares' ledger included, sit under it in a `<details>`.
- The changes list uses the five permission marks and links to the activity log ("View all 23").
- Buyers add their own blocks (a class extending `RoleWarden\Dashboard\Block`, listed in `Config\RoleWarden::$dashboardBlocks`); the module draws their section, letter and title, their view draws the body, and they follow the same rules.
- Don't add a block without a figure someone acts on; empty blocks say what will appear and why it's empty.
