# CommandSearch

The Ctrl K overlay: go to a screen, run an action or ask who holds a permission, from anywhere in the panel.

**Consumer provides:** the screens, actions and permission identifiers the signed-in user may reach (filtered server-side by permission), counts where they help ("248"), and for a matching identifier a one-line answer ("8 users through 2 roles, 1 denied by override": everyone who can do it, super admins and per-user grants included; the roles that grant it directly, not the children that inherit it; the users a negative override keeps from it).

- Opens from the sidebar search button or Ctrl K (Cmd K on macOS); Esc closes it and returns focus to where it was.
- A 600px `surface-raised` panel with `shadow-float`, 72px from the top, over a fixed dark scrim, `rgba(23, 25, 28, 0.45)` in light and dark mode alike (the `ink` token turns near-white in dark mode, so the scrim does not follow it). The field is the first focus.
- Results are grouped under `header` labels: Permission, Go to, Actions. The highlighted row takes `accent-tint` and shows the Enter hint.
- A typed identifier (`users.del`) matches permissions first: the top result answers "who can do this" and opens Check a permission.
- Results never include what the user may not do; the footer says so.
- Up and Down move, Enter opens. It is a `role="dialog"` with a labelled `combobox` and `listbox`.
- Don't put settings toggles in it; actions open their screen, they never act silently.
