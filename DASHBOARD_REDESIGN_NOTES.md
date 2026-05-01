# Dashboard Redesign Notes

## Sidebar Layout

- The dashboard keeps a fixed left sidebar on desktop and a drawer on smaller screens.
- Navigation is grouped into work, reports, support/admin, and logout so the page behaves like a focused hospital back-office workspace.
- Active items use a stronger filled state while non-active items keep a lighter hover treatment.

## Main Workspace

- The main content now reads as a composed admin canvas:
  - top utility bar
  - hero + KPI row
  - primary actions
  - secondary actions
  - compact bottom summary strip
- This keeps the page closer to a premium medical/admin dashboard instead of a homepage-style card stack.

## Hero And KPI

- The hero remains the visual anchor with a dark navy surface, greeting, concise status copy, and operational CTAs.
- The embedded profile widget is intentionally smaller so it supports the hero instead of competing with it.
- KPI cards are arranged as a 2x2 stat block to the right of the hero for faster scanning and better balance.

## Action Composition

- Shortcut cards are split into primary and secondary action rows rather than one auto-generated list.
- Primary actions carry the most-used workflow entries first.
- Secondary actions finish the row system cleanly while preserving permission-based visibility.
- Card spans are controlled on the desktop 12-column grid so the lower half does not leave a weak empty right side.

## Bottom Summary

- The bottom row uses compact summary cards only:
  - latest personal info
  - approval work
  - issue summary when needed
- These cards are shortened to summary + CTA so the page ends in a clean, intentional strip.

## Visual Tone

- White cards, calm mint accents, dark navy hero contrast, and soft shadows were preserved.
- Spacing was rebalanced to feel premium and calm without becoming hollow or crowded.
- CTA buttons stay visible and clearly interactive across hero, cards, sidebar, and bottom strip.
