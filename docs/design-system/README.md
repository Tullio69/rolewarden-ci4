RoleWarden is an admin panel for roles and permissions in CodeIgniter 4 apps. Its look is an **editorial ledger**: a strict grid, hairline rules, row numbers in the margin, tabular figures and short marginal notes. Identity comes from structure, never from texture or decoration. Every screen is a working ledger; the Dashboard is the ledger's summary page, set in the bolder register described below (direction B, chosen 18 Oct 2026).

## Content fundamentals

- Write in plain, short English. Sentence case everywhere ("Save changes", "Last active"), except column headers, which are set in the `header` style (uppercase).
- Address the admin as "you"; the product never says "we" or "I". No exclamation marks, no emoji.
- Name things by what they are: "Editor inherits from Author", "7 users", "2 unsaved changes", "Denied for 1 user".
- Permission identifiers are code: always `area.action` in lowercase, set in `code` or `code-sm` (`users.delete`, `roles.assign`).
- Marginal notes are one line, under six words, in `note` and `ink-faint`: "from Author", "changed", "3 overrides".
- Figures carry their context in a note under them: "of 248 accounts", "peak 88 on 16 Oct". A number never stands alone.
- Example data is realistic and clearly fictional (names like Dana Whitfield, domains like `northwind-studio.test`). Never lorem ipsum.

## Visual foundations

**Color.** The sheet is `surface`, near-neutral, never beige. Text is `ink`; secondary text is `ink-muted`; row numbers and notes are `ink-faint`. Rows are separated by `rule` hairlines; sections by `rule-strong`.
- `accent` (ink blue) is the only accent: primary buttons, links, focus, selection, unsaved-change markers, the active navigation item and the main series of a chart. Selected and changed things sit on `accent-tint`.
- `granted` means granted. `denied` means denied or destructive. Never use either for decoration, status of a user, success toasts or charts.
- A user's status (active / disabled) is neutral: `ink` and `ink-muted`, told apart by a word and a filled or hollow dot.
- Dark theme is the same ledger inverted: charcoal `surface`, not black; rules stay subtle.

**Type.** Three IBM Plex families. The panel ships them as woff2 files and loads nothing from a CDN at runtime; the previews of this system load them from Google Fonts.
- `serif` (IBM Plex Serif) only for `title-display`, `title-page`, `title-section`, `figure-xl` and `figure-lg`. Never inside tables, forms or buttons.
- `sans` (IBM Plex Sans) for all interface text: `body`, `body-strong`, `label`, `header`, `note`.
- `mono` (IBM Plex Mono) only for permission identifiers: `code`, `code-sm`.
- All figures use tabular numerals (`font-variant-numeric: tabular-nums`) so columns align.

**Grid and spacing.** Desktop is designed at 1440px: sidebar `size-sidebar`, top bar `size-topbar`, content with `space-12` side margins. Every table carries a left gutter `space-12` wide for row numbers (01, 02…) set in `note`. Rows are `size-row` tall; controls are `size-control` tall. Spacing steps are 4px based: `space-1` to `space-16`. Below 1440px the page never scrolls sideways: the content column stops at the window and anything wider scrolls inside it. The permission matrix widths are what its columns get when they fit; with more actions than fit, the columns shrink to their content.

**Rules, not boxes.** Group content with hairlines and white space. No cards, no card grids, no KPI tiles. Tables have no outer border and no zebra stripes: a `rule` under each row, a `rule-strong` under the header row and above the footer. Key figures sit in a **ledger strip**, never in tiles: a 2px `ink` rule above, a `rule-strong` rule below, a `rule` between figures. The one raised panel in the product is the sidebar's Today summary.

**Radii and depth.** `radius-0` for all structure, `radius-sm` for checkboxes and labels, `radius-md` (4px) for buttons, inputs and the Today panel, and never more. Only menus, tooltips and the command search take `shadow-float`. No gradients, glass, textures or paper effects.

**States.**
- Focus: every control shows a 2px solid `focus` outline with a 2px offset (`:focus-visible`). Never remove it.
- Hover: rows take `surface-sunk`; links underline; buttons darken to `accent-hover`.
- Disabled: 50% opacity text, `cursor: not-allowed`, still readable.
- Unsaved: a changed cell carries a small `accent` corner dot and an `accent-tint` wash; the row's right margin notes "changed"; an `accent-tint` bar above the table counts the changes and holds Discard and Save changes.

**Motion.** 120ms ease-out on color and background only. Nothing slides, bounces or scales. The one exception is a toast, which fades and rises 8px (see Toast), and not at all under `prefers-reduced-motion`.

## Themes

The panel ships three themes, each in light and dark, and a customizer that overrides colors, radius and density in the buyer's theme file. The values in `tokens.json` are **Console**, the default, at the reference size of this book; the source of every theme's values is the panel's `resources/css/panel.css`, which wins where the two differ.

| Theme | Character | What changes |
| --- | --- | --- |
| Console (default) | The editorial ledger, dense | 13px body, 32px controls, 36px rows, 3px radius, sidebar 216px |
| Clarity | Airy, one indigo accent | 15px body, 40px controls, 52px rows, 8px radius, sidebar 248px |
| Contrast | Black on white or white on black, bold type | 15px body at weight 500, sans titles at 700, 2px section rules, 3px focus ring, 2px radius, sidebar 232px; yellow accent in dark |

- Every color token in this book has a value in each of the six theme and mode pairs. A token added here (the `side-*`, `charcoal-*` and `heat-*` families) takes its Clarity and Contrast values by the same rules: Clarity derives from its indigo and slate, Contrast from black, white and its accent, every text pair keeps 4.5:1 and every active item 3:1.

Values of the 1.1 tokens in Clarity and Contrast (approved 18 Oct 2026; Console is in `tokens.json`). Ink takes the theme's own hue, Charcoal the theme's dark surfaces in both modes, `side-accent` the theme's dark accent; heat maps run from the sunk (light) or raised (dark) surface to the accent in equal steps.

| Token | Clarity light | Clarity dark | Contrast light | Contrast dark |
| --- | --- | --- | --- | --- |
| `side-surface` | `#1a1b4b` | `#14153a` | `#072a66` | `#051d47` |
| `side-raised` | `#262a52` | `#1f2247` | `#0b3d91` | `#072a66` |
| `side-rule` | `#3a3f75` | `#33376a` | `#dde7f7` | `#dde7f7` |
| `side-ink` | `#eef0f5` | `#eef0f5` | `#ffffff` | `#ffffff` |
| `side-muted` | `#b4bad8` | `#b4bad8` | `#dde7f7` | `#dde7f7` |
| `side-accent` | `#a5b4fc` | `#a5b4fc` | `#facc15` | `#facc15` |
| `on-side-accent` | `#1a1b4b` | `#1a1b4b` | `#0a0a0b` | `#0a0a0b` |
| `charcoal-surface` | `#14161c` | `#14161c` | `#0a0a0b` | `#0a0a0b` |
| `charcoal-raised` | `#1b1e26` | `#1b1e26` | `#161618` | `#161618` |
| `charcoal-rule` | `#3a3f4c` | `#3a3f4c` | `#a1a1aa` | `#a1a1aa` |
| `charcoal-ink` | `#eef0f5` | `#eef0f5` | `#ffffff` | `#ffffff` |
| `charcoal-muted` | `#b4bac6` | `#b4bac6` | `#e4e4e7` | `#e4e4e7` |
| `heat-0` | `#f5f6f8` | `#1b1e26` | `#f0f0f0` | `#161618` |
| `heat-1` | `#b8c0ef` | `#3c4359` | `#a9bad6` | `#4d4327` |
| `heat-2` | `#8691e4` | `#5c648a` | `#6f8dbe` | `#7e6a2c` |
| `heat-3` | `#6066d7` | `#7e89be` | `#3e65a8` | `#b6972b` |
| `heat-4` | `#4338ca` | `#a5b4fc` | `#0b3d91` | `#facc15` |

## Sidebar

The sidebar has three styles, a choice in the theme customizer stored in the buyer's theme file. All three share one layout: product name, command search, navigation grouped under `header` labels (Overview, Access, Security, System), counters after items, the Today panel (optional) and the user row at the bottom.

| Style | Ground | Active item | Use |
| --- | --- | --- | --- |
| Paper (default) | `surface-sunk`, text `ink` | `accent-tint` wash, `accent` text, weight 600 | The classic ledger; what 1.0 shows, so an update looks the same |
| Ink | `side-surface`, text `side-ink`, labels `side-muted` | `side-accent` fill, `on-side-accent` text, weight 600 | Screenshots, the demo, buyers who want a bold rail |
| Charcoal | `charcoal-surface`, text `charcoal-ink`, labels `charcoal-muted` | `side-accent` fill, `on-side-accent` text, weight 600 | Buyers who want a dark rail without blue |

- The active item must differ from the rail by 3:1 or more, so on the dark rails it takes `side-accent` (7.8:1 on `side-surface`), never `accent`, which reads at 2.1:1 there.
- Counters are `note` figures on a `side-raised` chip; a count that needs attention ("5 new") takes `side-accent` with `on-side-accent` text.
- The Today panel shows sign-ins today as `figure-lg`, a one-line note and an hourly sparkline in `side-accent`. It hides with one switch.
- Every item renders only if the user holds its permission; never a disabled item.
- The sidebar collapses to a 64px rail of icons with tooltips; the command search stays reachable with Ctrl K.

## Dashboard

The first screen after sign-in. Each block has its own permission; a block the user cannot see is not rendered. Admins choose blocks, order and default period, with defaults per role; each user may hide or reorder the blocks they are allowed.

- Header: a greeting in `note`, a title in `title-display`, the period on the right. A security notice, when there is one, sits under it as an `accent-tint` strip with one primary action.
- Key figures: a ledger strip of four, each a `header` label, a `figure-xl` value (the first in `accent`, the rest `ink`), a `surface-sunk` delta chip and a note.
- Charts follow the chart rules below; tables follow the ledger table.

## Charts

- One series in `accent`: a 2.5px line over a flat `accent-tint` area. A second, smaller series in `ink` as bars. No third series; split the chart instead.
- Gridlines: one dashed `rule-strong` midline and a solid `ink` baseline. Axis labels in `note`, `ink-faint`.
- Heat maps use `heat-0` to `heat-4`, five steps, with a "Fewer … More" key under them. Neighbouring steps are close (`heat-0` to `heat-1` is about 1.2:1), so color never carries the value alone: each cell names its count in its tooltip, and the chart's numbers are also offered as a table.
- Shares of a whole: one horizontal bar split by `accent`, its lighter steps and `ink-muted`, with a ledger list under it giving each share as a number.
- The legend sits above the chart at the right, with drawn swatches. Every chart has `role="img"` and an `aria-label` that states its takeaway.
- `granted` and `denied` never appear in a chart.

## Permission marks

Every cell of the permission matrix shows one of five states. They differ by **shape alone**, so the panel stays readable when a buyer rethemes it. Color only reinforces.

| Mark | Meaning | Drawn as | Color |
| --- | --- | --- | --- |
| ✓ | Granted by the role | solid check, 2px stroke | `granted` |
| 〃 | Inherited from the parent role | ditto mark, cell locked, tooltip "from Author" | `ink-muted` |
| (✓) | Granted by a per-user override | check inside a 1.5px circle | `granted` |
| ~~✓~~ | Denied by a per-user override | check struck through by a horizontal rule | `denied` |
| ⬚ | Not granted | small dashed box, only where the permission exists; a cell with no permission stays empty | `ink-faint` |

In a **role** matrix, override marks summarise the overrides held by users of that role; the tooltip names them ("Override for 2 users: Priya Nair, Jonas Lindqvist"). Overrides are edited on the user's page (UserDetail), where clicking a cell adds or removes one: a denial where a role grants, a grant where none does. A denial is only meaningful on top of a role grant. Every matrix screen shows the compact five-mark legend. The same marks appear in the Dashboard's list of recent changes.

## Screens

Eight screens are drawn here, all on the same shell (sidebar `size-sidebar`, top bar, 48px row-number gutter, 200px marginal notes column): Dashboard, PermissionMatrix (Permissions), UsersList and UserDetail (Users), RolesList (Roles), Settings, Login, and the CommandSearch overlay. The panel's other screens (Sessions, Activity log, Appearance, Profile, Import users, the installer, and from 1.1 Check a permission) follow the same rules without a drawing of their own. Toasts give immediate feedback on any screen. Every editable screen uses the same unsaved-changes bar: `accent-tint`, a count, the changed items, Discard and one primary Save. Sections within a page are lettered A, B, C in the gutter and titled in `title-section`.

## Iconography

- No icon font and no icon library. The five permission marks are inline SVG drawn with `currentColor` (see the PermissionMark component).
- Interface glyphs (chevron, search, lock, and the navigation set: dashboard, users, roles, permissions, sessions, activity, check a permission, settings, appearance) are 16px inline SVG, 1.5px stroke, square caps, `currentColor`.
- There is no logo: the product name is set in `title-section` (IBM Plex Serif) in the sidebar, next to an optional 32px outlined shield mark. Buyers replace both with their client's name and mark.

## Building with Tailwind and Alpine

The panel's CSS is compiled with the standalone Tailwind v4 CLI (no Node) from `resources/css/panel.css`, and the compiled file is committed: a buyer never builds. Tailwind v4 has no `tailwind.config.js`; the tokens reach the utilities through an `@theme inline` block, under names of their own so a variable never refers to itself:

```css
@theme inline {
  --color-surface: var(--surface); --color-ink-muted: var(--ink-muted); --color-accent: var(--accent);
  --color-side-surface: var(--side-surface); --color-heat-1: var(--heat-1);
  --radius-control: var(--radius-md); --font-display: var(--font-title);
}
```

- Tokens come in three levels: primitives (`--p-*`, raw colors no component reads), semantic (`--surface`, `--accent`, `--size-control`…, the only ones components read) and component exceptions derived from them. A theme maps the semantic level onto primitives; the customizer's theme file overrides the semantic level only.
- The `rw-*` component classes stay, defined in the same source on the tokens.

- Use `border-b border-rule` for row hairlines, `tabular-nums` on every figure, `focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent` on every control.
- Matrix state lives in Alpine (`x-data` holding a map of `area.action` → state); select-all checkboxes use the native `indeterminate` property. No custom widgets beyond native inputs, buttons and `<table>`.
- Theme and mode: `data-rw-theme="console|clarity|contrast"` and `data-rw-mode="light|dark"` on `<html>` (no mode: follow the system); every color is a variable, so no `dark:` variants are needed.
- Sidebar style: `data-sidebar="paper|ink|charcoal"` on the sidebar; each style maps the sidebar's own variables, so the markup never changes.
- Charts are server-side SVG drawn from the same variables, so they follow theme, dark mode and the customizer with no chart library.
