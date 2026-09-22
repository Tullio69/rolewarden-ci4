# PermissionMark

The five permission states of a matrix cell, drawn as 20px inline SVG that differ by shape alone.

**Consumer provides:** the state (`role`, `inherit`, `grant`, `deny`, `none`), the permission identifier and, for `inherit`, the source role name; for overrides, the count or names of affected users.

| State | Class | SVG |
| --- | --- | --- |
| Granted by role | `rw-mark rw-mark--role` | `<path d="M4.5 10.5l3.5 3.5 7.5-8" stroke-width="2.2"/>` |
| Inherited | `rw-mark rw-mark--inherit` | `<path d="M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6" stroke-width="2"/>` |
| User override | `rw-mark rw-mark--grant` | `<circle cx="10" cy="10" r="8" stroke-width="1.5"/>` + `<path d="M6.5 10.3l2.4 2.4 4.6-5" stroke-width="1.8"/>` |
| Denied for user | `rw-mark rw-mark--deny` | `<path d="M5 10.5l3.5 3.5 7-7.5"/>` + `<path d="M1.5 10.5h17"/>`, both 1.8 |
| Not granted | none | render nothing |

All strokes use `currentColor`, `fill="none"`, on a `0 0 20 20` viewBox.

- Do wrap each mark in a `<button>` inside the matrix cell and give it an `aria-label` that states the identifier and the state ("articles.update, inherited from Author. Locked.").
- Do show the tooltip on hover and on keyboard focus: identifier in `code-sm`, then "from Author" or "Override for 2 users".
- Do show the five-mark legend on every screen that shows marks. In the legend, render "Not granted" as a dashed `rule-strong` square so it has something to point at.
- Don't tell states apart by color. `granted` and `denied` only reinforce the shape.
- Don't reuse the check mark for anything that is not a permission (saved states, success messages).
