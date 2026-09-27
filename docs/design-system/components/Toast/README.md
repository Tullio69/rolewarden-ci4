# Toast

Immediate feedback on the action just taken: saved, failed, or worth a second look. It lives only on the page it appears on and is never the record of what happened.

**Consumer provides:** a type (`success`, `warning` or `error`) and one short sentence that says what happened or what to do next ("Role saved.", "Could not save this change. Reload the page and try again.").

- Stack in a fixed column at the bottom right, 24px from the edges, 360px wide, 8px apart; the newest sits at the bottom. At most three are shown; an older one leaves when a fourth arrives.
- Each toast is a `surface-raised` card with a 1px `rule-strong` border, `radius-md` and `shadow-float`, 12px 16px padding. The first word says what kind it is, in `body-strong`: "Saved", "Warning:" or "Error:". The sentence follows in `body`. A ghost close button (×, `aria-label="Dismiss"`) sits top right.
- Kinds are told apart by that first word, not by colour. Only `error` uses colour: a `denied` border and a `denied` first word. Success is never `granted`, and warning has no colour of its own.
- Success and warning leave by themselves after 5 seconds. The timer pauses while the pointer is over the stack or focus is inside it. Errors stay until they are closed.
- Accessibility: the stack is `aria-live="polite"`, and an error toast carries `role="alert"`. The close button keeps the 2px `focus` ring. Opening a toast never moves focus.
- Motion: fade and rise 8px in 160ms `ease-out`; leave with a 120ms fade. With `prefers-reduced-motion`, appear and leave without movement.
- Without JavaScript, the same markup renders at the top of the content column, static and without the close button. The message stays readable.
- Classes: `rw-toasts` (stack; add `rw-toasts--static` for the no-JavaScript form), `rw-toast`, `rw-toast--success`, `rw-toast--warning`, `rw-toast--error`, `rw-toast__kind`, `rw-toast__close`.
- Don't use a toast for validation errors that belong to one field: show "Error: ..." under the control. Don't put links or actions inside a toast, and don't use it for anything the user must read later.
