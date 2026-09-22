# Login

The sign-in screen (Screen 3): a quiet, centred ledger sheet with email, password, remember me and a forgot-password link. No illustration, no sidebar.

**Consumer provides:** the client application's name (shown as the overline), the form action and CSRF field (CodeIgniter 4 `csrf_field()`), the forgot-password URL, the remember-me duration, and error text from the auth controller.

Layout: a 400px column centred on `surface`, with the same 48px row-number gutter as every table, so fields are numbered 01, 02, 03 in `note` style. The overline is the client's app name in `header`; the title "Sign in" is `title-page`; each field sits between `rule` hairlines, the header closes on a `rule-strong`. The product credit "Access managed by RoleWarden" sits in the bottom-left corner in `note`; buyers may remove it.

- Labels are always visible; placeholders only show the expected format.
- Password has a Show/Hide ghost button with `aria-pressed`; Forgot password sits on the label line, right-aligned.
- Field errors: the input border turns `denied` and an "Error: ..." line in `denied` appears below, linked by `aria-describedby`; focus moves to the first invalid field.
- A failed sign-in shows one `role="alert"` block under the button (`denied-tint` ground, `denied` border, "Error:" in `denied`) that names the remaining attempts. Never say which of email or password was wrong.
- Sign in is the only primary button and spans the column.
- Don't add illustrations, background images, social sign-in buttons or a card around the form: the sheet itself is the surface.
