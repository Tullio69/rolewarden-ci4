# Pagination

Page navigation under a ledger table: a range count on the left, Previous, numbered pages and Next on the right.

**Consumer provides:** current page, total pages and total rows (CodeIgniter's Pager supplies all three); the count text "Showing 13-24 of 41 users".

- `rw-pager` list of `rw-page` buttons (32px square, `radius-md`); the current page carries `aria-current="page"` and is drawn in `accent` on `accent-tint` with an `accent` border.
- Previous and Next are disabled, not hidden, on the first and last page.
- More than 7 pages: show first, last, the current page and its neighbours, with "…" in `ink-faint` between.
- The count sits in a `role="status"` region so filtering announces the new total.
- Sits on the table's closing `rule-strong`, aligned with the table's content edge (after the row-number gutter).
