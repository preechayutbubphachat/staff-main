# Background Frame Bugfix

## Root Cause

- The top overview wrapper `.dashboard-canvas` still had its own background, border, rounded corners, shadow, backdrop blur, and pseudo-element background.
- The workflow wrapper `.section-board` also had its own background, border, rounded corners, shadow, backdrop blur, and pseudo-element glow layer.
- Those wrappers were intended as board containers, but visually they looked like extra cards behind the real content cards.

## Layers Removed

- Removed visible surface styling from `.dashboard-canvas`:
  - background
  - border
  - shadow
  - rounded outer frame
  - backdrop blur
  - pseudo-element grid/glow layer
- Removed visible surface styling from `.section-board`:
  - background
  - border
  - shadow
  - rounded outer frame
  - backdrop blur
  - pseudo-element glow layer

## Wrappers Kept For Layout Only

- `.dashboard-canvas` remains as a transparent 12-column grid wrapper for the top overview layout.
- `.section-board` remains as a transparent 12-column grid wrapper for the workflow layout.
- Both wrappers now provide layout and spacing only, not visible card surfaces.

## Confirmation

- Top overview section now shows only the real left intro card, center live overview card, and right summary cards.
- Workflow section now shows only the real left workflow intro card and the four workflow action cards.
- No duplicate background frame is intentionally rendered behind those two sections.
