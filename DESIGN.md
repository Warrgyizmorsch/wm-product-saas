---
name: Executive Slate & Ledger
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#45464d'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#006c4a'
  on-secondary: '#ffffff'
  secondary-container: '#82f5c1'
  on-secondary-container: '#00714e'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#40000c'
  on-tertiary-container: '#f83256'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#85f8c4'
  secondary-fixed-dim: '#68dba9'
  on-secondary-fixed: '#002114'
  on-secondary-fixed-variant: '#005137'
  tertiary-fixed: '#ffdada'
  tertiary-fixed-dim: '#ffb3b6'
  on-tertiary-fixed: '#40000c'
  on-tertiary-fixed-variant: '#920028'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Manrope
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.025em
  headline-lg:
    fontFamily: Manrope
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Manrope
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Manrope
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
  numeral-kpi:
    fontFamily: Manrope
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 34px
    letterSpacing: -0.02em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.5rem
  margin: 1rem
  margin-desktop: 1.75rem
  space-xxs: 0.125rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1.25rem
  space-xl: 1.75rem
  space-2xl: 2.5rem
---

## Brand & Style

This design system targets Chief Financial Officers, financial controllers, operational executives, and enterprise accountants who require precision, rapid situational awareness, and total trust in mission-critical data. The brand personality embodies institutional authority, analytical clarity, and modern understated luxury. It replaces the clunky, visual noise of legacy ERPs with an editorial financial cockpit that prioritizes information density without inducing cognitive fatigue.

The design movement is **Corporate / Modern** anchored in high-precision Swiss grid discipline, elevated through tactile depth and refined surface treatments. Key attributes:
- **Absolute Legibility:** Unforgiving structural grid alignment with crisp, high-contrast numerical hierarchies.
- **Architectural Surface Structure:** Clean slate-tinted canvas backdrops layered with pristine white card tiers, bordered by whisper-thin borders rather than harsh structural outlines.
- **Semantic Financial Signposting:** Intentional, high-visibility semantic cues—emerald/teal tones signaling revenue, positive variance, and liquidity; coral/rose tones signaling expenditures, burn, and risk—used strictly for data articulation rather than decorative distraction.

## Colors

The palette is engineered around high-contrast readability against a calibrated, cool backdrop.

### Foundation & Surfaces
- **Canvas Base (`#F8FAFC` - Slate 50):** The primary workstation canvas. Cool, matte, and eye-resting over long sessions.
- **Card Surfaces (`#FFFFFF`):** High-clarity elevated white surfaces for KPI cards, ledger sheets, and data grids.
- **Muted Structural Borders (`#E2E8F0` - Slate 200):** Subtle perimeter dividers ensuring clear segment separation without heavy lines.

### Brand Accents
- **Deep Slate/Navy (`#0F172A` - Slate 900):** Used for primary buttons, active sidebar highlights, and primary textual headings. Communicates executive poise and permanence.
- **Sub-Primary Interactive (`#1E293B` - Slate 800):** Hover and pressed states for core primary actions.

### Financial Semantics
- **Positive Inflow & Cash Flow (`#059669` - Emerald 600 / `#10B981` - Emerald 500):** Visual anchor for revenue, positive variances, liquidity surplus, and successful validations. Tinted container fills leverage `#ECFDF5`.
- **Negative Outflow & Expenditure (`#E11D48` - Rose 600 / `#F43F5E` - Rose 500):** Visual indicator for expenses, overheads, liabilities, and debit balances. Tinted container fills leverage `#FFF1F2`.
- **Neutral Information & Metadata (`#64748B` - Slate 500 / `#475569` - Slate 600):** Applied across timestamps, breadcrumbs, table column headers, and secondary captions.

## Typography

The typographic hierarchy combines **Manrope** for structured headings, executive scorecards, and high-impact numeric values with **Inter** for dense transactional UI, forms, and enterprise tables.

- **Tabular Numerals:** All financial tables, balance sheets, and metric cards must enforce `font-feature-settings: "tnum" 1` to guarantee vertical decimal and column alignment.
- **Section Headers & Metric Cards:** Manrope at semi-bold (`600`) and bold (`700`) weights delivers modern executive clarity without decorative serif embellishment.
- **Dense Data & Labels:** Inter at `11px` (`label-sm`) and `12px` (`label-md`) is configured with uppercase tracking (`letter-spacing: 0.04em`) for table headers, ledger codes, and currency markers.

## Layout & Spacing

The layout is built around a full-bleed dashboard cockpit leveraging a fixed-fluid hybrid structure:
- **Shell Layout:** Left-hand navigation rail fixed at `240px` (collapsible to `64px` icon mode), top utility navigation fixed at `56px` height, and a fluid main workspace spanning the remaining width.
- **Grid Architecture:** 12-column dynamic grid within the workspace. Key KPI metric decks span 3 columns each on desktop (`col-span-3`), dropping to 6 columns on tablet, and full width on mobile viewports.
- **Enterprise Density:** Spacing utilizes an 8pt base scale with 4pt half-steps (`space-xs`, `space-sm`) optimized for high screen utility. Vertical padding inside table cells is tightened to `0.5rem` (`space-sm`) to maximize visible ledger rows per viewport.
- **Breakpoints:**
  - Mobile: `< 768px` (drawer navigation, stacked single-column cards).
  - Tablet: `768px - 1199px` (collapsed sidebar rail, 2-column KPI grid).
  - Desktop: `≥ 1200px` (fully expanded workspace, 4-column metric decks, wide-aspect chart visualizations).

## Elevation & Depth

Visual depth is achieved through low-contrast outlines coupled with soft ambient micro-shadows, creating clean executive layers rather than exaggerated drop shadows.

- **Level 0 (Canvas):** Pure `#F8FAFC` background. Completely flat.
- **Level 1 (KPI & Dashboard Cards):** White background (`#FFFFFF`), `1px` border in `#E2E8F0`, and ambient micro-shadow: `box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.05), 0 1px 2px -1px rgba(15, 23, 42, 0.03)`.
- **Level 2 (Hovered Cards & Interactive Rows):** Subtle elevation shift: `box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05)` with border shifting to `#CBD5E1`.
- **Level 3 (Flyouts, Menus & Filter Dropdowns):** `box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.04)`, bordered with `#E2E8F0`.
- **Level 4 (Transaction Modals & Approval Drawers):** Backdrop scrim `rgba(15, 23, 42, 0.45)` with `backdrop-filter: blur(4px)`. Container elevated with `box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.08)`.

## Shapes

The design system implements a **Soft** shape archetype (`roundedness: 1`). Enterprise finance applications require tight grid discipline; excessive rounding wastes viewport density and compromises vertical scanlines.

- **Buttons, Form Inputs, and Selectors:** `border-radius: 0.375rem` (6px) for an intentional, crisp feel.
- **KPI Tiles, Chart Panels, and Data Surfaces:** `border-radius: 0.5rem` (8px).
- **Badges, Status Tags, and Micro Indicators:** `border-radius: 0.25rem` (4px) to retain compact geometry.
- **Circular Indicators:** Reserved strictly for user avatars, icon-only circle action buttons, and operational health pulse dots (`border-radius: 9999px`).

## Components

### Buttons
- **Primary:** Deep slate background (`#0F172A`), white text, `height: 36px`, padding `0 14px`, `font-size: 13px`, weight `600`. Hover: `#1E293B`.
- **Secondary / Action:** White background, `#0F172A` text, bordered in `#E2E8F0`. Hover: background `#F8FAFC`, border `#CBD5E1`.
- **Icon / Contextual Action:** Muted text `#64748B`, transparent background, `32px` square. Hover: `#F1F5F9`.

### KPI Metric Tiles
- Structure: Header row containing title (`label-md`, `#64748B`) and right-aligned semantic accent badge or tinted trend icon container.
- Main Value: `numeral-kpi` (`28px`, bold, `#0F172A`).
- Sub-footer: Trend variance indicator (`emerald-600` badge for positive cash flow / profit, `rose-600` badge for expenses / liabilities) paired with comparison context text (`body-sm`, `#94A3B8`).

### Data Tables & Ledger Grids
- **Header:** Height `36px`, background `#F8FAFC`, bottom border `1px solid #E2E8F0`. Text formatted with `label-sm` in `#475569`, uppercase, tracking `0.04em`.
- **Rows:** Height `44px` (dense mode: `36px`). Background `#FFFFFF`, bottom border `1px solid #F1F5F9`. Alternating row striping is avoided in favor of crisp hover transitions to `#F8FAFC`.
- **Financial Alignment:** Text descriptions left-aligned; ledger accounts and codes centered; currency amounts right-aligned with monospace digits.

### Input Fields & Selectors
- Standard input height `36px`, border `1px solid #CBD5E1`, background `#FFFFFF`, text `body-md` (`#0F172A`). Focus state: border `#0F172A` with outline ring `2px solid rgba(15, 23, 42, 0.1)`.
- Multi-filter bars contain integrated dropdown menus, fiscal period pickers, and instant reset actions aligned on a unified baseline.

### Badges & Status Chips
- Height `22px`, font-size `11px`, font-weight `600`, padding `0 8px`.
- **Positive / Inflow:** Emerald tint background (`#ECFDF5`), emerald text (`#047857`), border `1px solid #A7F3D0`.
- **Expense / Outflow:** Rose tint background (`#FFF1F2`), rose text (`#BE123C`), border `1px solid #FECDD3`.
- **Pending / In Audit:** Amber tint background (`#FFFBEB`), amber text (`#B45309`), border `1px solid #FDE68A`.
- **Neutral / Draft:** Slate tint background (`#F1F5F9`), slate text (`#475569`), border `1px solid #E2E8F0`.

### Financial Chart Containers
- Integrated segmented tabs to toggle metrics (e.g., Gross Profit, Direct Income, Cost of Sales).
- Two-tone bar series: Emerald (`#10B981`) for gross revenues vs. Rose (`#F43F5E`) for expenditures.
- Clean horizontal gridlines rendered in `#F1F5F9` with zero vertical grid lines to maintain clean scanning.