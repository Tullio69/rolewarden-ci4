# UserStatus

A user's account status, active or disabled, shown as a word with a filled or hollow dot.

**Consumer provides:** the status as a boolean or enum; the word is always rendered, never the dot alone.

- `rw-status` (filled `granted` dot with a 3px `granted-tint` halo, `ink` text) for Active; add `rw-status--disabled` (hollow `ink-muted` ring, `ink-muted` text) for Disabled.
- Only the active dot takes `granted`: it is a dot, never a check, so it cannot be read as a permission mark. A disabled user is never `denied` ("red"): it stays a neutral hollow ring.
- The halo breathes three times when the page opens (1.6s each, under 5s, so no pause control is needed) and then rests; never under `prefers-reduced-motion`. The disabled ring has no halo and never moves.
- Why a user is disabled goes in the row's marginal note ("5 failed sign-ins", "contract ended").
- Tailwind: `inline-flex items-center gap-2 before:size-2 before:rounded-full before:bg-granted`.
