# UserStatus

A user's account status, active or disabled, shown as a word with a filled or hollow dot.

**Consumer provides:** the status as a boolean or enum; the word is always rendered, never the dot alone.

- `rw-status` (filled `ink` dot, `ink` text) for Active; add `rw-status--disabled` (hollow `ink-muted` ring, `ink-muted` text) for Disabled.
- Status is neutral on purpose: green and red are reserved for granted and denied permissions, so a disabled user is not "red".
- Why a user is disabled goes in the row's marginal note ("5 failed sign-ins", "contract ended").
- Tailwind: `inline-flex items-center gap-2 before:size-2 before:rounded-full before:bg-ink`.
