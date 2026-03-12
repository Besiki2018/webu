# Repro: bug_20260304_bbd5c147ccc93355
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /create
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.error] Warning: You are calling ReactDOMClient.createRoot() on a container that has already been passed to createRoot() before. Instead, call root.render() on the existing root instead if you want to update it.
```