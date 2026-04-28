# NeoGen Store — Brand Tokens v2.0 (R6 Sky + Cool White)

> **Mode change — v1.1 → v2.0:**
> v1.1 was a dark-mode-only system (near-black backgrounds, pure-cyan `#00d1ff` accent).
> v2.0 (Phase R6) migrates to a light-mode "Sky + Cool White" palette: off-white backgrounds,
> sky-blue accent `#38BDF8`, neutral dark borders. There is no longer a dark-mode variant.
> CSS token slugs are unchanged — the theme.json bridge continues to work without edits.
>
> Source of record: `neogen.css` v1.23.0 (`:root` block, confirmed deployed). The v1.1 HTML
> brand-kit files at the repo root are superseded by this document for colour decisions;
> typography and brand voice rules remain unchanged.

---

## Colour Tokens

### Backgrounds (light mode)

| Token | Hex / Value | Usage |
|-------|-------------|-------|
| `--bg` | `#F8FAFC` | Page background (slate-50) |
| `--bg-alt` | `#EEF2F6` | Alternate page background, zebra rows |
| `--surface` | `#FFFFFF` | Card / panel surface |
| `--surface-2` | `#F1F5F9` | Elevated surface (slate-100) |
| `--raised` | `#FFFFFF` | Hover / tooltip / popover |

### Accent (Sky Blue)

| Token | Hex / Value | Usage |
|-------|-------------|-------|
| `--accent` | `#38BDF8` | Primary accent, links, focus rings, glows (sky-400) |
| `--accent-soft` | `#BAE6FD` | Lighter tint for hover states (sky-200) |
| `--accent-deep` | `#0284C7` | Darker tint for pressed/active states (sky-600) |
| `--accent-wash` | `rgba(56, 189, 248, 0.06)` | Very light accent fill (hover backgrounds, nav items) |

> **Note for snippet authors:** The accent is no longer pure cyan (`#00d1ff`). Do not
> hardcode `rgba(56, 189, 248, …)` — always use `var(--accent)` so a future palette update
> propagates automatically. The focus box-shadow should be written as
> `0 0 0 3px var(--accent-wash)` or `0 0 0 3px rgba(56, 189, 248, 0.18)`.

### Semantic

| Token | Hex / Value | Usage |
|-------|-------------|-------|
| `--signal` | `#22C55E` | Success, in-stock, positive (green-500) |
| `--alert` | `#EF4444` | Error / danger state (red-500) — **changed from orange in v1.1** |
| `--warn` | `#F59E0B` | Warning / caution / out-of-stock (amber-400) — **new in R6** |
| `--indigo` | `#1A2B4B` | Deep indigo panel / badge background (unchanged) |
| `--indigo-deep` | `#0F1A2E` | Darker indigo variant (unchanged) |

> **Semantic shift:** `--alert` was orange/coral (`#e8734a`) in v1.1, signalling "warning/low-stock".
> In R6 it is hard red (`#EF4444`) for error/danger states. Out-of-stock and low-stock UI should
> now use `--warn` (amber). Update any template or snippet that uses `var(--alert)` for stock
> badges — these should migrate to `var(--warn)`.

### Text

| Token | Hex / Value | Usage |
|-------|-------------|-------|
| `--ice` | `#0F172A` | High-contrast headings (slate-950) |
| `--white` | `#0F172A` | **Repurposed in R6:** now the deepest ink/text colour, same as `--ice`. In light mode this token functions as the "maximum contrast" value. Do not use it expecting white — use `#ffffff` or a literal value where pure white is needed (e.g. icon fill on an accent background). |
| `--text` | `#0F172A` | Default body text (slate-950) |
| `--warm` | `#334155` | Secondary / supporting text (slate-700) |
| `--muted` | `#64748B` | Placeholder, metadata (slate-500) |
| `--dim` | `#94A3B8` | Disabled, decorative lines (slate-400) |

### Borders

| Token | Hex / Value | Usage |
|-------|-------------|-------|
| `--line` | `rgba(15, 23, 42, 0.10)` | Subtle borders, dividers (neutral dark-alpha) |
| `--line-strong` | `rgba(15, 23, 42, 0.24)` | Emphasis borders, focused inputs |
| `--line-warm` | `rgba(15, 23, 42, 0.06)` | Hairline / ghost borders — **new in R6** |

> **Note:** Borders in v1.1 were cyan-tinted (`rgba(0, 209, 255, …)`). R6 uses neutral dark-alpha
> borders that sit naturally on light surfaces. Do not introduce cyan-alpha border values; they
> belong to the archived v1.1 system.

### Shadows (R6 — new token system)

| Token | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(15,23,42,0.04), 0 1px 1px rgba(15,23,42,0.03)` | Subtle lift (buttons at rest, input fields) |
| `--shadow-md` | `0 4px 12px rgba(15,23,42,0.06), 0 2px 4px rgba(15,23,42,0.04)` | Card elevation |
| `--shadow-lg` | `0 16px 40px -16px rgba(15,23,42,0.18), 0 4px 8px rgba(15,23,42,0.04)` | Modals, hero side panel |
| `--shadow-xl` | `0 32px 72px -32px rgba(15,23,42,0.28), 0 8px 16px rgba(15,23,42,0.06)` | Large overlays on hover |
| `--shadow-accent` | `0 12px 32px -16px rgba(56,189,248,0.45), inset 0 1px 0 rgba(255,255,255,0.32)` | Primary CTA button at rest |
| `--shadow-accent-hover` | `0 18px 40px -18px rgba(56,189,248,0.65), inset 0 1px 0 rgba(255,255,255,0.40)` | Primary CTA button on hover |
| `--shadow-focus` | `0 0 0 3px rgba(56, 189, 248, 0.18)` | Keyboard focus ring on inputs |

> Use shadow tokens instead of hand-rolling `box-shadow` values. Hard-coded shadow hex must
> match the accent token; prefer `var(--shadow-*)` for consistency.

---

## Typography

Typography is **unchanged from v1.1**.

### Font Stacks

| Token | Fonts | Usage |
|-------|-------|-------|
| `--font-wordmark` | Chakra Petch, IBM Plex Sans, system-ui | Logo, site name, hero headings |
| `--font-sans` | Manrope, IBM Plex Sans, system-ui | Body, UI, product copy |
| `--font-mono` | IBM Plex Mono, JetBrains Mono, monospace | Prices, SKUs, code, spec labels |
| `--font-ultra` | Major Mono Display, monospace | Decorative large letterforms only |
| `--font-ar-display` | Rakkas, serif | Arabic hero / display headings |
| `--font-ar-ui` | Reem Kufi, Tajawal, system-ui | Arabic navigation, labels, buttons |
| `--font-ar-body` | Tajawal, Reem Kufi, system-ui | Arabic body text, descriptions |

### Weights in use
- 300 (Chakra Petch light), 400 (regular), 500 (medium), 600 (Reem Kufi semibold), 700 (bold)

---

## Layout

| Token | Value | Usage |
|-------|-------|-------|
| `--container` | `1240px` | Max content width (widened from 1160 px in v1.1) |
| `--radius` | `8px` | Standard border radius (increased from 6 px in v1.1) |
| `--radius-lg` | `14px` | Large radius — cards, hero side panel, modals (**new in R6**) |
| `--radius-pill` | `999px` | Pill shape — tags, stock badges only (**new in R6**) |

---

## Motion

| Token | Value | Usage |
|-------|-------|-------|
| `--ease` | `cubic-bezier(0.2, 0, 0.2, 1)` | All standard UI transitions (unchanged) |
| `--ease-out` | `cubic-bezier(0.16, 1, 0.3, 1)` | Spring/overshoot — hero entry animations only (**new in R6**) |

Recommended durations: 150 ms (micro), 200 ms (standard), 300 ms (expand/reveal), 500 ms+ (intentional page-level animation only). Use `--ease-out` only for elements that animate in on page load (`.ng-kicker`, `.ng-hero-h1`, reveal classes). Standard hover/focus interactions must use `--ease`.

---

## Background Patterns

### Body texture (global — R6 change)

R6 applies a subtle grid and radial glow to `body` globally. This replaces the v1.1
hero-only cyan grid:

```css
background:
  radial-gradient(600px circle at var(--mx) var(--my), rgba(56, 189, 248, 0.05), transparent 60%),
  linear-gradient(rgba(10, 10, 10, 0.04) 1px, transparent 1px),
  linear-gradient(90deg, rgba(10, 10, 10, 0.04) 1px, transparent 1px),
  var(--bg);
background-size: auto, 48px 48px, 48px 48px, auto;
```

The radial glow follows the cursor via `--mx` / `--my` CSS variables set by JS. Do not
reproduce this pattern on individual cards, sidebars, or section backgrounds — it is a
single body-level treatment only.

> **v1.1 hero grid (archived):** The 40×40 px cyan hairline grid (`rgba(0, 209, 255, 0.035)`)
> is no longer part of the live system. Do not reintroduce it.

---

## Logo Clearance

- Minimum size: see `NeoGen — Logo Size Kit.html` (repo root).
- Clearance zone: equal to the height of the "N" monogram on all sides.
- **R6 note:** The v1.1 rule "never place the logo on a background lighter than `--surface-2`"
  applied to the dark system. In R6 (light mode), the logo mark (`ng-mark.png`) should be
  verified against `--surface` (white) and `--bg` (slate-50) backgrounds using the Logo Size
  Kit. The monogram PNG was prepared for dark surfaces; confirm contrast is acceptable on white
  before using on `--surface` without a containing panel.

---

## Brand Voice (copy reference)

Unchanged from v1.1.

- Tone: precise, technical, forward-looking. Not casual, not corporate.
- Tagline: "جيل التقنية القادم" (AR) / "The Next Generation of Tech" (EN).
- Product naming: use model numbers and spec-led descriptions; avoid marketing superlatives.

---

## Version history

| Version | Date | Summary |
|---------|------|---------|
| v1.0 | — | Initial dark-mode brand system |
| v1.1 | — | Typography and motion additions; cyan border tokens |
| v2.0 (R6) | 2026-04-29 | Light-mode migration: Sky + Cool White palette. New shadow system, `--radius-lg`, `--radius-pill`, `--ease-out`, `--accent-wash`, `--line-warm`, `--warn` tokens. `--alert` changed from orange to red. `--ice` and `--white` repurposed to slate-950. Container widened to 1240 px, radius increased to 8 px. |
