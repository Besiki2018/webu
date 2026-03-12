# Repro: bug_20260304_d18d5b726d554de1
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.error] `DialogContent` requires a `DialogTitle` for the component to be accessible for screen reader users.

If you want to hide the `DialogTitle`, you can wrap it with our VisuallyHidden component.

For more information, see https://radix-ui.com/primitives/docs/components/dialog
```