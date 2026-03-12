# Repro: bug_20260304_3ab17cfbc8fec92a
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /project/019cbabc-c8c2-70ee-96a6-4606aa0e768f/cms?tab=cms-entries
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.warn] [tiptap warn]: Duplicate extension names found: ['link']. This can lead to issues.
```