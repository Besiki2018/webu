# Visual Builder Selection QA Checklist

## Purpose

Repeat the exact builder flows that previously produced selection jumps, sidebar desync, and typing flicker.

## Preconditions

- Start the local app in dev mode.
- Open a project page in the visual builder with `?tab=inspect`.
- Use a page that contains at least:
  - two identical section types
  - one nested/compound component with text, button, or image content
  - fixed header or footer enabled

## Checklist

### 1. Duplicate component selection

1. Click the lower instance of two identical components.
2. Confirm the lower component gets the highlight.
3. Confirm the left sidebar shows that component's full controls.
4. Click the upper instance.
5. Confirm the highlight moves to the upper component only.
6. Confirm the sidebar switches to the upper component without jumping back.

Expected:

- selection always follows the exact clicked instance
- no selection jump to the first matching component

### 2. Whole-component selection

1. Inside a selected component, click:
   - a text node
   - a button
   - an image
2. Confirm the same component remains selected after each click.
3. Confirm the sidebar still shows the full component parameter set.

Expected:

- child detail clicks do not change component identity
- sidebar does not collapse to partial field-only controls

### 3. Sidebar typing stability

1. Select a component with editable text.
2. Type into at least two text fields.
3. Keep typing quickly for several seconds.
4. Switch to another sidebar tab and type again if a text field exists there.

Expected:

- no flicker
- no focus loss
- no sidebar reset
- no selection jump
- no highlight jump in preview

### 4. Deselect and reselection

1. Click empty canvas space.
2. Confirm selection clears.
3. Press `Escape`.
4. Confirm nothing reselects automatically.
5. Click a component again.

Expected:

- deselect state remains clean
- builder does not auto-select the first section
- reselection works normally

### 5. Fixed section flow

1. Open fixed header or footer from the sidebar structure controls.
2. Change a variant.
3. Confirm preview updates.
4. Return back to normal section selection.

Expected:

- fixed-section selection stays isolated
- returning back does not corrupt normal component selection

### 6. Console verification

Keep DevTools open during all steps above.

Expected:

- no `Maximum update depth exceeded`
- no obvious selection echo loop
- no repeated selection spam during typing

## Sign-off

- `Automated verification completed`: yes
- `Manual browser QA completed`: pending
- `Blockers found`: none / list exact reproduction URL and console stack
