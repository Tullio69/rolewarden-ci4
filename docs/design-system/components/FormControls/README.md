# FormControls

Native text inputs, selects and checkboxes styled for the ledger: 1px `control-border`, `surface-raised` fill, 4px radius.

**Consumer provides:** native `<input>`, `<select>` and `<input type="checkbox">` elements, each with a visible `<label>` (`rw-label`) or an `aria-label` when the context labels it (matrix select-all boxes).

- Input and select: `rw-input`, `rw-select`, height `size-control`, text in `body`, placeholder in `ink-faint`.
- Checkbox: `rw-check`, 16px, `radius-sm`; checked and indeterminate fill with `accent` and draw in `on-accent`. Set `indeterminate` from script (Alpine: `x-effect="$el.indeterminate = someGranted && !allGranted"`) for select-all per row and per column.
- Wrap a checkbox and its text in `rw-check-row` so the whole line is clickable.
- Errors: message under the field in `note` size with `denied` color and a leading "Error:" word; the field border turns `denied`. Never use red for anything else in a form.
- Don't build custom dropdowns or toggles; native controls keep keyboard and screen-reader behaviour for free.
