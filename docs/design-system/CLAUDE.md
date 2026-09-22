# RoleWarden design system

Questa cartella è il design system dell'admin panel RoleWarden (CodeIgniter 4 + Tailwind CSS + Alpine.js).
Quando crei o modifichi viste del pannello:

- Leggi prima `README.md` (regole visive, colori, tipografia, marchi dei permessi) e il `README.md` del componente o della schermata su cui lavori, in `components/<Nome>/`.
- Usa solo i token di `tokens.css` / `tokens.json` tramite variabili CSS (`var(--accent)`, `var(--rule)`...). Mai valori esadecimali scritti a mano.
- `components/bundle.css` contiene le classi base `rw-*`; `components/<Nome>/preview.html` è il riferimento visivo e di comportamento da ricreare in Tailwind + Alpine.
- Schermate: PermissionMatrix, UsersList, UserDetail, RolesList, Settings, Login.
- Verde (`granted`) e rosso (`denied`) solo per permessi concessi/negati e azioni distruttive. Un solo accento: `accent` (ink blue).
- IBM Plex Serif solo per titoli e numeri grandi; IBM Plex Sans per l'interfaccia; IBM Plex Mono solo per identificatori `area.action`.
- WCAG 2.1 AA e focus visibile (`outline 2px var(--focus)`, offset 2px) su ogni controllo.
