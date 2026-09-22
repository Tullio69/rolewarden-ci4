# Settings

Global rules (Screen 6) as a ledger of numbered rows grouped in lettered sections: Sign-in, Passwords and 2FA, Roles, Audit log, and a separate Reset section.

**Consumer provides:** every setting's current value, allowed options or range, a one-line explanation, and optional live context for the marginal note ("3 accounts locked this month", "1 of 2 admins enrolled"); who changed settings last and when.

- Each row: number in the gutter, name in `body-strong` with an explanation in `note` (a 360px column), the control (select, number input with a unit word, or checkboxes), then the marginal note.
- Changing any control marks the row: its number turns `accent` and its note reads "changed"; the bar at the top lists the changed setting names and holds Discard and Save settings. Reverting a control by hand clears its mark.
- Number inputs are right-aligned, 96px wide, with the unit after them in `ink-muted`; validate against min and max on save and show "Error: ..." under the control in `denied`.
- The Reset section sits apart at the bottom: its title and outlined button are the only `denied` things on the page, and the button always opens a confirmation.
- Don't use toggle switches; a checkbox with a clear label does the same job with native keyboard support.
