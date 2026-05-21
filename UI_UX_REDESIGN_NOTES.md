## Chosen palette

- Background:
  - very light cool gray-blue
  - soft white gradients
- Primary text:
  - deep navy
- Secondary text:
  - muted slate / gray-blue
- Accents:
  - teal
  - aqua
  - soft blue-violet
  - amber for review attention

## Typography decisions

- `Prompt` remains the display font for:
  - hero headline
  - KPI values
  - section titles
  - card titles
- `Sarabun` remains the body font for Thai readability.
- Important information was made larger and tighter.
- Secondary explanations were made smaller and lighter.

## Card hierarchy rules

- Primary:
  - hero title
  - summary numbers
  - KPI values
  - quick-action titles
- Secondary:
  - labels
  - subtext
  - helper lines
  - card eyebrow text
- Cards now favor one clear message each instead of mixing too much information in one block.

## Primary vs secondary information

- Primary information:
  - user greeting
  - current summary numbers
  - KPI values
  - quick actions
- Secondary information:
  - department / role / date chips
  - short subtext under KPIs
  - small panel hints
  - profile metadata

## Reusable component classes created

- `glass-card`
- `glass-card--hero`
- `glass-card--inner`
- `glass-card--kpi`
- `glass-card--action`
- `glass-card--profile`
- `glass-card--panel`
- `glass-card--alert`
- `prism-hero`
- `prism-kpi-card`
- `prism-action-card`
- `prism-chip`
- `prism-helper-text`
- `section-header--compact`

## How the glass/prism style was translated into hospital UI

- Used light translucent cards instead of heavy dark panels.
- Applied subtle gradients and glow tints only as depth cues.
- Kept cards structured and practical so data remains easy to read.
- Avoided flashy neon colors, oversized decoration, and consumer-app styling.
