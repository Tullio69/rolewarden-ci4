RoleWarden is an admin panel for roles and permissions in CodeIgniter 4 apps. Its look is an **editorial ledger**: a strict grid, hairline rules, row numbers in the margin, tabular figures and short marginal notes. Identity comes from structure, never from texture or decoration. The panel has no dashboard; every screen is a working ledger.

## Content fundamentals

- Write in plain, short English. Sentence case everywhere ("Save changes", "Last active"), except column headers, which are set in the `header` style (uppercase).
- Address the admin as "you"; the product never says "we" or "I". No exclamation marks, no emoji.
- Name things by what they are: "Editor inherits from Author", "7 users", "2 unsaved changes", "Denied for 1 user".
- Permission identifiers are code: always `area.action` in lowercase, set in `code` or `code-sm` (`users.delete`, `roles.assign`).
- Marginal notes are one line, under six words, in `note` and `ink-faint`: "from Author", "changed", "3 overrides".
- Example data is realistic and clearly fictional (names like Dana Whitfield, domains like `northwind-studio.test`). Never lorem ipsum.

## Visual foundations

**Color.** The sheet is `surface`, near-neutral, never beige. Text is `ink`; secondary text is `ink-muted`; row numbers and notes are `ink-faint`. Rows are separated by `rule` hairlines; sections by `rule-strong`.
- `accent` (ink blue) is the only accent: primary buttons, links, focus, selection, unsaved-change markers. Selected and changed things sit on `accent-tint`.
- `granted` means granted. `denied` means denied or destructive. Never use either for decoration, status of a user, success toasts or charts.
- A user's status (active / disabled) is neutral: `ink` and `ink-muted`, told apart by a word and a filled or hollow dot.
- Dark theme is the same ledger inverted: charcoal `surface`, not black; rules stay subtle.

**Type.** Three IBM Plex families, loaded from Google Fonts.
- `serif` (IBM Plex Serif) only for `title-page`, `title-section` and `figure-lg`. Never inside tables, forms or buttons.
- `sans` (IBM Plex Sans) for all interface text: `body`, `body-strong`, `label`, `header`, `note`.
- `mono` (IBM Plex Mono) only for permission identifiers: `code`, `code-sm`.
- All figures use tabular numerals (`font-variant-numeric: tabular-nums`) so columns align.

**Grid and spacing.** Desktop is designed at 1440px: sidebar `size-sidebar`, top bar `size-topbar`, content with `space-12` side margins. Every table carries a left gutter `space-12` wide for row numbers (01, 02…) set in `note`. Rows are `size-row` tall; controls are `size-control` tall. Spacing steps are 4px based: `space-1` to `space-16`.

**Rules, not boxes.** Group content with hairlines and white space. No cards, no card grids, no KPI tiles. Tables have no outer border and no zebra stripes: a `rule` under each row, a `rule-strong` under the header row and above the footer.

**Radii and depth.** `radius-0` for all structure, `radius-sm` for checkboxes and labels, `radius-md` (4px) for buttons and inputs, and never more. Only menus and tooltips take `shadow-float`. No gradients, glass, textures or paper effects.

**States.**
- Focus: every control shows a 2px solid `focus` outline with a 2px offset (`:focus-visible`). Never remove it.
- Hover: rows take `surface-sunk`; links underline; buttons darken to `accent-hover`.
- Disabled: 50% opacity text, `cursor: not-allowed`, still readable.
- Unsaved: a changed cell carries a small `accent` corner dot and an `accent-tint` wash; the row's right margin notes "changed"; an `accent-tint` bar above the table counts the changes and holds Discard and Save changes.

**Motion.** 120ms ease-out on color and background only. Nothing slides, bounces or scales.

## Permission marks

Every cell of the permission matrix shows one of five states. They differ by **shape alone**, so the panel stays readable when a buyer rethemes it. Color only reinforces.

| Mark | Meaning | Drawn as | Color |
| --- | --- | --- | --- |
| ✓ | Granted by the role | solid check, 2px stroke | `granted` |
| 〃 | Inherited from the parent role | ditto mark, cell locked, tooltip "from Author" | `ink-muted` |
| (✓) | Granted by a per-user override | check inside a 1.5px circle | `granted` |
| ~~✓~~ | Denied by a per-user override | check struck through by a horizontal rule | `denied` |
| (empty) | Not granted | nothing | none |

In a **role** matrix, override marks summarise the overrides held by users of that role; the tooltip names them ("Override for 2 users: Priya Nair, Jonas Lindqvist"). Overrides are edited on the user's page (UserDetail), where clicking a cell adds or removes one: a denial where a role grants, a grant where none does. A denial is only meaningful on top of a role grant. Every matrix screen shows the compact five-mark legend.

## Screens

Six screens, all on the same shell (sidebar `size-sidebar`, top bar, 48px row-number gutter, 200px marginal notes column): PermissionMatrix (Permissions), UsersList and UserDetail (Users), RolesList (Roles), Settings, and Login. Every editable screen uses the same unsaved-changes bar: `accent-tint`, a count, the changed items, Discard and one primary Save. Sections within a page are lettered A, B, C in the gutter and titled in `title-section`.

## Iconography

- No icon font and no icon library. The five permission marks are inline SVG drawn with `currentColor` (see the PermissionMark component).
- Interface glyphs (chevron, search, lock) are 16px inline SVG, 1.5px stroke, square caps, `currentColor`.
- There is no logo: the product name is set in `title-section` (IBM Plex Serif) in the sidebar. Buyers replace it with their client's name.

## Building with Tailwind and Alpine

Map the tokens into `tailwind.config.js` as CSS variables so buyers can retheme without rebuilding:

```js
theme: { extend: {
  colors: { surface: 'var(--surface)', sunk: 'var(--surface-sunk)', raised: 'var(--surface-raised)',
    ink: { DEFAULT: 'var(--ink)', muted: 'var(--ink-muted)', faint: 'var(--ink-faint)' },
    rule: { DEFAULT: 'var(--rule)', strong: 'var(--rule-strong)' }, control: 'var(--control-border)',
    accent: { DEFAULT: 'var(--accent)', hover: 'var(--accent-hover)', tint: 'var(--accent-tint)', on: 'var(--on-accent)' },
    granted: 'var(--granted)', denied: 'var(--denied)' },
  fontFamily: { serif: ['IBM Plex Serif', 'serif'], sans: ['IBM Plex Sans', 'sans-serif'], mono: ['IBM Plex Mono', 'monospace'] },
  borderRadius: { sm: '2px', md: '4px' } } }
```

- Use `border-b border-rule` for row hairlines, `tabular-nums` on every figure, `focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent` on every control.
- Matrix state lives in Alpine (`x-data` holding a map of `area.action` → state); select-all checkboxes use the native `indeterminate` property. No custom widgets beyond native inputs, buttons and `<table>`.
- Dark theme: toggle `data-theme="dark"` on `<html>`; every color is a variable, so no `dark:` variants are needed.
