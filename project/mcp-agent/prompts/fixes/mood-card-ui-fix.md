Refine the existing history cards UI.

Do NOT redesign from scratch.
Keep current structure.

Goal:
Make the cards feel denser, more premium, and less empty.

----------------------------------------

IMPROVEMENTS:

1. CARD DENSITY
- Reduce vertical spacing inside cards
- Align content more horizontally where possible
- Avoid large empty gaps

2. HEADER ROW
- Keep:
  - left: type + date
  - right: level + buttons
- Align everything vertically centered
- Reduce spacing between level and buttons

3. LEVEL INDICATOR
- Make progress bar thicker (6px–8px)
- Use accent color (#88BDBC)
- Add rounded edges
- Add subtle background track

4. EMOTIONS & INFLUENCES
- Display as inline flex-wrap row (not stacked)
- Reduce spacing between label and tags
- Add gap between tags (6px–8px)
- Keep pills compact

5. CARD HOVER
- Add hover effect:
  - slight translateY(-2px)
  - stronger shadow
  - transition 0.2s ease

6. BUTTONS
- Keep style but:
  - slightly smaller padding
  - tighter spacing between Edit/Delete

7. GROUP CONTAINER (Today)
- Slightly reduce padding
- Keep background but make cards stand out more than container

----------------------------------------

IMPORTANT:
- Keep palette EXACTLY:
  #2F6F6D
  #E6F2F1
  #88BDBC
- Do NOT change logic
- Do NOT change structure drastically
- Just refine spacing, alignment, and styling

----------------------------------------

Output:
- Only updated CSS and minimal class adjustments if needed
