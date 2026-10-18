# Sidebar

The main navigation in three styles, Paper (default), Ink and Charcoal, chosen in the theme customizer and stored in the buyer's theme file.

**Consumer provides:** the product or client name (and optional mark), the navigation groups with items, their routes and the permission each needs, counters per item, the Today figures (sign-ins today, failures, hourly counts), and the signed-in user's name, role and initials.

- Layout, top to bottom: name, command search button (opens CommandSearch, Ctrl K), groups under `header` labels, the Today panel, the user row with theme switch and sign out.
- Set `data-sidebar="paper|ink|charcoal"` on the `<nav>`; only the sidebar's variables change, never the markup. With no style in the theme file the sidebar is Paper, so an update from 1.0 looks the same; the demo and the screenshots use Ink.
- Items are `<a href>` links 38px tall (`size-nav-item`), icon 16px, label in `label`. The current page carries `aria-current="page"`: a `side-accent` fill with `on-side-accent` text in Ink and Charcoal, an `accent-tint` wash with `accent` text in Paper, weight 600 in all three. Never an `accent` fill on a dark rail: it reads at 2.1:1 against it.
- Counters are `note` figures on a `side-raised` chip; one that needs attention ("5 new") takes `side-accent`. Show a counter only when the number helps someone act.
- Render an item only if the user holds its permission. Never show a disabled item.
- The Today panel is optional (one switch). Its sparkline is 1.5px `side-accent`, no axes.
- Collapsed: a 64px rail of icons, each with a tooltip; the search becomes an icon button.
- Don't add a fourth style or a gradient ground; a buyer's brand color goes through `accent`.
