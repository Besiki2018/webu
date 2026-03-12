# Repro: bug_20260304_f3594678c3e19916
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.error] Warning: You are calling ReactDOMClient.createRoot() on a container that has already been passed to createRoot() before. Instead, call root.render() on the existing root instead if you want to update it.
```