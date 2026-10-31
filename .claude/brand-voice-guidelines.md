# RoleWarden Brand Voice Guidelines

## Generation Metadata
- Created: 2026-10-31
- Version: 1
- Sources: `docs/codecanyon/scheda-prodotto.md`, `docs/COMMERCIALE.md`, `docs/codecanyon/concorrenti.md`, `README.md`, `docs/design-system/README.md`, `src/Language/en/RoleWarden.php` (UI strings)
- Documents processed: 6
- Conversations analyzed: 0 (no sales calls or support transcripts exist yet: the product is not on sale)
- Discovery report used: No
- Overall confidence: Medium (0.79)

> Note: the content RoleWarden publishes is in English (listing, documentation, interface, emails). These
> guidelines are in Italian for Fabio; examples and terms are in English, exactly as they should be used.

---

## Executive Summary

RoleWarden speaks like an experienced CodeIgniter developer explaining their module to another developer: they say
exactly what it does, where it sits, which file you touch and which one you don't. Its distinctive trait is
**verifiable precision**: file paths, commands, method names, resolution order and limits written in black and white.
On CodeCanyon, where competitors sell "all in one" with adjectives and demos that don't work, RoleWarden earns trust by
stating its limits ("No. Roles and permissions are global to the application in 1.x.").

The core personality is **calm, plain and honest**. No hype, no exclamation marks, no promises with dates. Every
claim must be backed by the code (the rule in `inventario.md`: "Every claim is covered by inventario.md"). Shield is
never pushed into the background: RoleWarden *extends* Shield, and says so in the first sentence.

The reader should come away thinking "this person knows what they're doing and isn't selling me smoke". Voice stays
constant across listing, docs, interface and emails. What changes is how dense and technical the text gets.

---

## We Are / We Are Not

| We Are | We Are Not |
|--------|------------|
| **Precise**: paths, commands, method names, exact numbers | **Vague**: "powerful", "flexible", "advanced" without saying what |
| **Honest about limits**: we say what it doesn't do and who it isn't for | **Evasive**: hiding missing features or blurring them into "coming soon" |
| **Plain**: short sentences, active verbs, everyday words | **Bureaucratic or showy**: jargon for effect, marketing superlatives |
| **Respectful of Shield and the buyer's code**: "extends", "without touching" | **Invasive**: "replaces", "takes over", modules that ask you to edit their files |
| **Calm**: the outcome first, then what to do | **Alarmist or overexcited**: exclamation marks, capitals, urgency |
| **Developer to developer**: assumes competence, explains the why | **Condescending**: "simply", "just", obvious step-by-step guides |

### Voice Attributes Detail

#### 1. Precise
- **What it means**: every feature is described by what it does concretely, with the name of the file, method or command.
- **How it shows up**: `auth()->user()->can('invoices.approve')`, "one line in **app/Config/Auth.php**", "up to 1000 users per file", "Retention of 90 days, one year or forever".
- **What to avoid**: generic adjectives; features without a number or a name.
- **Evidence**: the whole description in `scheda-prodotto.md` §3; README ("4.7.4 fixes known security advisories…").
- **Confidence**: High

#### 2. Honest about limits
- **What it means**: we say clearly what it doesn't do, who it doesn't suit, and what is left to Shield.
- **How it shows up**: FAQ that open with "No."; "Support … does not cover custom development"; "No dates are promised"; "tested on MariaDB 11.8" instead of "works with every database".
- **What to avoid**: overpromising, roadmap dates, compatibility that was never tested.
- **Evidence**: FAQ in `scheda-prodotto.md` §5; decision in `COMMERCIALE.md` ("dire nella scheda che registrazione e recupero password li da' Shield"); the "tested on MariaDB 11.8" rule.
- **Confidence**: High

#### 3. Plain
- **What it means**: short, declarative sentences, one idea per sentence, active verbs.
- **How it shows up**: "An update is a folder replacement followed by…", "Nothing you may reach matches "{0}"."
- **What to avoid**: chained subordinate clauses, nominalisations ("the management of the configuration of…"), anglicised marketing.
- **Evidence**: UI strings (`RoleWarden.php`), README, FAQ.
- **Confidence**: High

#### 4. Respectful of Shield and the buyer's code
- **What it means**: RoleWarden is an extension, not a replacement; the buyer's code stays theirs.
- **How it shows up**: "without replacing Shield or touching its tables", "No file inside the module is meant to be edited", "Updates never overwrite your changes".
- **What to avoid**: presenting RoleWarden as an "auth system"; taking credit for login, registration or 2FA.
- **Evidence**: tagline, FAQ 1, non-negotiable constraint 1 in `CLAUDE.md`.
- **Confidence**: High

#### 5. Calm
- **What it means**: even errors and security emails state the fact and the next action, with no drama.
- **How it shows up**: "If this was you, there is nothing to do. If it was not, change your password now and sign out the sessions you do not recognise."
- **What to avoid**: "WARNING!", "Oops!", "Something went wrong :(", urgency in capitals.
- **Evidence**: email and error strings in `RoleWarden.php`; the design system (no decorative effects).
- **Confidence**: Medium (interface and emails only, no marketing examples)

#### 6. Developer to developer
- **What it means**: the reader is a CI4 developer or an agency; there's no need to explain what a migration is, but we do explain *why* a choice was made.
- **How it shows up**: "The panel deliberately does not create permissions, because a permission only means something to the code that checks it."
- **What to avoid**: "simply", "just", "easy!"; tutorials on basic concepts.
- **Evidence**: FAQ "How do I add permissions…", target audience in §3 ("Built for developers and agencies…").
- **Confidence**: Medium

---

## Brand Personality

- **Archetype**: the craftsman-guardian. It looks after access the way a good warden looks after keys: reliable, orderly, never showy.
- **If our brand were a person**: a senior developer who reads the documentation before answering, says "no, that's not supported" without embarrassment, and leaves your code tidier than they found it.
- **Core values expressed in voice**: reliability (tested before release), clarity, respect for the buyer's work, honesty about what's sold.

---

## Messaging Framework (Confidence: High)

### Primary Value Proposition
Move roles and permissions out of `AuthGroups.php` and into the database, with an admin panel built on Shield.

Variations observed:
- "Move roles and permissions out of AuthGroups.php and into the database, with an admin panel built on Shield." (tagline, scheda §2)
- "Shield owns identity and authentication. RoleWarden moves roles and permissions out of the config file and into the database, and adds an admin panel to manage them at runtime." (README)
- "Roles & Permissions Panel for CodeIgniter 4, built on top of Shield." (README, headline)

### Key Message Pillars

1. **Built on Shield, not instead of it**
   - Core idea: Shield keeps identity; RoleWarden only adds the authorisation layer and the panel.
   - When to use: opening of every listing, FAQ, comparison with competitors (none of them is built on Shield).
   - Example phrasing: "It extends Shield. Shield keeps users, credentials, sessions…"

2. **Drop-in: your code doesn't change**
   - Core idea: you keep checking permissions the Shield way; installing takes two lines, updating is a folder replacement.
   - When to use: installation, updates, objections like "do I need to rewrite?".
   - Example phrasing: "Your code keeps checking permissions the Shield way… The answer now comes from the database."

3. **Granular, explainable permissions**
   - Core idea: slug-based permissions, inheritance, per-user overrides, a fixed resolution order and "Check a permission" to see *why*.
   - When to use: features, demo, screenshots (the matrix comes first).
   - Example phrasing: "A denial beats every role, including super admin."

4. **The client manages it on their own**
   - Core idea: for agencies, permissions in place from day one that the client changes from the browser.
   - When to use: target audience, Regular licence, agency case.
   - Example phrasing: "…a permission layer that is in place on day one and that the client can manage without calling you."

5. **Updates that don't break**
   - Core idea: competitors' weak point is updates; RoleWarden updates through migrations, without overwriting customisations.
   - When to use: Support and updates, replies to reviews.
   - Example phrasing: "Updates are migrations: replace the module folder, run php spark migrate -n RoleWarden."

### Competitive Positioning
- vs. Ion Auth + Argon ($16): we don't compete on price; granular permissions instead of groups, docs, testing, a module rather than a starter app.
- vs. WebGuard ($39, SSO/2FA): we don't chase the feature list; we say login and 2FA belong to Shield, and stress updates that don't break.
- vs. Status Quo (`AuthGroups.php`): every permission change today is a code change plus a deploy.
- **Never name competitors in public copy**: the comparison stays implicit (what we do, said precisely).

---

## Tone-by-Context Matrix (Confidence: Medium)

Voice is constant. Tone flexes by context:

| Context | Formality | Energy | Technical Depth | Key Principle |
|---------|-----------|--------|-----------------|---------------|
| CodeCanyon listing (description) | Medium | Medium | High | Problem, then fit, then features: every claim verifiable |
| Feature list / sidebar (≤12) | Medium | Medium | Medium | One line, one fact, no adjectives |
| FAQ | Medium | Low | High | Answer first ("Yes." / "No."), then why |
| HTML documentation / README | Medium | Low | Very high | Exact commands and paths, reasons for choices |
| Interface (labels, toasts, empty states) | Medium | Low | Low | What happened + what to do, in one sentence |
| Errors and permission denials | Medium | Low | Low | No blame, no drama, a way forward |
| Security emails | Medium-high | Low | Low | Fact, impact, action; "if this was you, nothing to do" |
| Support replies | Medium | Medium | High | Reproduce, explain, point to the right page; state what support doesn't cover |
| Changelog | High | Low | Medium | What changes for an installed site |
| Social / launch / video | Low-medium | Medium-high | Low-medium | (inferred) One concrete benefit, demo link, no hype |

### Context-Specific Guidelines

#### CodeCanyon listing
- **Overall tone**: confident through facts, never through adjectives.
- **Opening approach**: the concrete problem (permissions in `AuthGroups.php` = deploy on every change), then the solution.
- **Do's**: name Shield in the first sentence; real paths and methods in `<strong>`; public demo credentials; a FAQ for the predictable objections.
- **Don'ts**: superlatives, "#1", "ultimate", roadmap dates, features covered by Shield presented as ours.
- **Example**: "RoleWarden moves roles, permissions, role inheritance and per-user overrides into the database and adds an admin panel to manage them at runtime, without replacing Shield or touching its tables."

#### Interface and errors
- **Overall tone**: neutral, brief, useful.
- **Do's**: second person ("You cannot…"); state the cause and the way out; plurals handled with ICU.
- **Don'ts**: "Oops", "Error!", SQL or technical details on screen (rule in `CLAUDE.md`).
- **Example**: "Every block is hidden. Open Customize to show them again."

#### Security emails
- **Overall tone**: calm and serious.
- **Do's**: what happened, when/from where, what to do if it wasn't you.
- **Don'ts**: urgency in capitals, generic links.
- **Example**: "If this was you, there is nothing to do. If it was not, change your password now and sign out the sessions you do not recognise."

#### Support replies (inferred)
- **Overall tone**: courteous, technical, bounded.
- **Do's**: thank briefly, answer the question, link the documentation section, say clearly what falls outside support.
- **Don'ts**: promising custom development; blaming the buyer's code without evidence.
- **Example**: "That screen is Shield's own, so it follows Shield's documentation: here is the page. Support covers installation, configuration and bugs in the module."

#### Social / launch (inferred, Low)
- **Do's**: one fact per post, a screenshot (the matrix), a demo link.
- **Don'ts**: hashtag spam, emoji in the text, "game changer".
- **Example**: "Shield's roles live in a config file. RoleWarden moves them into the database, with a panel to manage them. Live demo: [link]"

---

## Terminology Guide (Confidence: Medium)

### Must-Use Terms
| Term | Usage | Instead Of | Example |
|------|-------|------------|---------|
| sign in / sign-in / sign out | every action and noun to do with access | log in, login, log out | "Failed sign-ins" |
| permission | the unit that's checked | right, privilege, ACL entry | "Check a permission" |
| role | a group of permissions assigned to users | group (Shield's term) | "a role with children cannot be deleted" |
| override (grant / deny) | a per-user exception | exception, custom permission | "Per-user overrides: grant or deny a single permission" |
| super admin | a flag on a role | superuser, root, administrator | "super admin as a flag on a role" |
| slug `area.action` | how a permission is identified | permission ID, code | "`users.delete`" |
| extends Shield / built on Shield | relationship with Shield | replaces, alternative to Shield | "It extends Shield." |
| admin panel / panel | the interface | dashboard (that's one screen), backend | "the panel routes" |
| module | what you install | plugin, addon, script | "No file inside the module is meant to be edited" |

### Preferred Terms
| Term | Usage | Example |
|------|-------|---------|
| British spelling | colours, recognise, organisation, licence (noun) | "in the theme's colours" |
| "in the database" / "at runtime" | the core benefit | "manage them at runtime" |
| "without touching" / "folder replacement" | updates and installation | "an update is a folder replacement" |
| "tested on" + exact version | compatibility | "tested on MariaDB 11.8" |

### Avoid These Terms
| Term | Reason | Alternative |
|------|--------|-------------|
| powerful, robust, advanced, flexible | empty, used by every competitor | say the concrete function |
| simply, just, easy | condescending, often false | describe the step |
| seamless, effortless | unverifiable | "one line in app/Config/Auth.php" |
| login (as a verb) | inconsistent with the interface | sign in |
| groups (for RoleWarden roles) | that's Shield's model | roles (except when talking about importing Shield groups) |

### Never-Use Terms
| Term | Reason |
|------|--------|
| "replaces Shield", "authentication system" | contradicts constraint 1: RoleWarden does not do authentication |
| "works with any database", versions never tested (e.g. MariaDB 10.x) | unverified claim; tested: MariaDB 11.8, MySQL 8.0 and 8.4 |
| roadmap dates ("coming in Q1") | decision: "No dates are promised" |
| "ultimate", "best", "#1", "game changer" | hype, the opposite of the voice |

---

## Language to Avoid

### Anti-Patterns
1. **"The most powerful RBAC solution for CodeIgniter!"**: no fact, exclamation mark. Better: "Role inheritance, per-user overrides and a fixed resolution order, managed from an admin panel."
2. **"Supports 2FA and social login"** (through Shield): takes credit for something that isn't ours. Better: "Shield's own features in those areas keep working as documented by Shield."
3. **"Oops! Something went wrong."**: no cause, no way out. Better: "This block could not be loaded. The error is in the application log."
4. **"Simply drop it in and you're done!"**: condescending and vague. Better: "Point Shield at RoleWarden's user model: one line in app/Config/Auth.php."

---

## Content Examples

### Excellent Example
> "The panel deliberately does not create permissions, because a permission only means something to the code that checks it."
> (FAQ, scheda §5)

Why it's on-brand: it states a limit without apologising, explains the reason, and treats the reader as a developer.

### Example to Avoid
> "RoleWarden is the ultimate all-in-one authentication & user management solution with tons of features!"

Why it's off-brand: superlatives, "authentication" (it's Shield's job), "tons of features" says nothing. Fix: the tagline.

---

## Confidence Scores

| Section | Confidence | Basis | Sources |
|---------|------------|-------|---------|
| Voice Attributes | High | consistent across listing, README, UI and design decisions | 5 |
| Messaging Framework | High | explicit tagline and pillars, decisions in COMMERCIALE | 4 |
| Tone Matrix | Medium | listing/FAQ/UI/emails documented; support and social inferred | 3 |
| Terminology | Medium | consistent pattern but no explicit glossary; Customize/customizer inconsistency | 3 |
| Language Patterns | Low | no transcripts; anti-patterns inferred from competitors | 1 |

Aggregate: 0.30 + 0.25 + 0.12 + 0.09 + 0.03 = **0.79 → Medium**.

---

## Open Questions for Team Discussion

### High Priority
1. **American or British spelling?**
   - What was found: listing and UI use British ("colours", "recognise", "organisation", "licence"), but three UI strings say "Customize" (`src/Language/en/RoleWarden.php:147,148,161`, the dashboard) and the design system has "customizer"; CodeCanyon is a mostly American audience.
   - Agent recommendation: stay with British (it's already the majority, and Envato uses "licence") and align "Customize" → "Customise" in the next release.
   - Need from you: confirmation, plus a decision on whether to fix the UI string.

2. **"Shield" in the product title**
   - What was found: no competitor uses it; it's our main differentiator, but no one searches for it.
   - Agent recommendation: keep it in the title after "CodeIgniter 4" (proposal in scheda §1) and as a tag.
   - Need from you: approval of the title (it is still marked [PROPOSTA DA APPROVARE]).

### Medium Priority
3. **Tone of support replies**
   - What was found: no examples, only scope limits.
   - Agent recommendation: prepare 5 canned replies for the predictable questions (existing users table, coexisting with Shield, views) following the support row of the matrix.
   - Need from you: OK to draft them in `docs/`.

4. **Social channels and the launch video**
   - What was found: COMMERCIALE plans a 2-minute video; no social channel is defined.
   - Agent recommendation: no social channel at launch; the video uses the Plain/Precise tone, with narration describing the commands on screen.
   - Need from you: which channels, if any.

### Low Priority
5. **Mentioning the roadmap in the listing**
   - What was found: the Roadmap block exists, with a note saying it can be removed.
   - Agent recommendation: keep it (it's honest, with no dates) and limit it to one sentence.

---

## Data Gaps & Recommendations

- [ ] No real conversations: after launch, analyse the first 20 comments and support tickets to validate tone and objections.
- [ ] No glossary: turn the Terminology Guide into a section of the design guide, so the UI follows it too.
- [ ] Social/video: no examples; write the video script and check it against these guidelines.

---

## Appendix: Sources

| # | Source | Platform | Type | Date | Key Sections Used | Confidence |
|---|--------|----------|------|------|-------------------|------------|
| 1 | docs/codecanyon/scheda-prodotto.md | repo | AUTHORITATIVE | 2026-10-30 | tagline, description, FAQ, tags, price | High |
| 2 | docs/COMMERCIALE.md | repo | AUTHORITATIVE | 2026-10-18 | positioning, competitors, support | High |
| 3 | docs/codecanyon/concorrenti.md | repo | OPERATIONAL | 2026-10-28 | positioning, titles, complaints | Medium |
| 4 | README.md | repo | AUTHORITATIVE | 1.1 | value proposition, technical tone | High |
| 5 | docs/design-system/README.md | repo | AUTHORITATIVE | 1.1 | sobriety, accessibility, no effects | Medium |
| 6 | src/Language/en/RoleWarden.php | repo | OPERATIONAL | 1.1 | UI microcopy, errors, emails | High |
