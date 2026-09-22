# Button

Actions, in four variants: primary (ink blue fill), secondary (outlined), ghost (text in accent) and danger (outlined in `denied`).

**Consumer provides:** a native `<button>` with a verb-first, sentence-case label ("Save changes", "Invite user", "Delete role").

- Classes: `rw-btn` plus one of `rw-btn--primary`, `rw-btn--secondary`, `rw-btn--ghost`, `rw-btn--danger`; `rw-btn--sm` for toolbars (28px).
- Height `size-control` (36px), radius `radius-md`, label in `label`/`body` weight 500. Tailwind: `h-9 px-4 rounded-md bg-accent text-accent-on hover:bg-accent-hover`.
- One primary per view. On the matrix it is Save changes, disabled when nothing changed.
- Danger is for irreversible actions only and is never filled on the page; the filled `denied` + `on-denied` form appears only inside a confirmation dialog.
- Every button keeps the 2px `focus` ring on `:focus-visible`.
- Don't use icons alone without an `aria-label`; don't use pill shapes or shadows.
