# Repro: bug_20260304_cf4f6deee02e557c
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /create
3. Perform the action that triggered the error (see event message below).
## Event
```
Label is not defined
```
## Stack (excerpt)
```
ReferenceError: Label is not defined
    at Create (http://127.0.0.1:5173/resources/js/Pages/Create.tsx?t=1772657330056:613:40)
    at renderWithHooks (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:11596:26)
    at mountIndeterminateComponent (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:14974:21)
    at beginWork (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:15962:22)
    at beginWork$1 (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19806:22)
    at performUnitOfWork (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19251:20)
    at workLoopSync (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19190:13)
    at renderRootSync (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19169:15)
    at recoverFromConcurrentError (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:18786:28)
    at performSyncWorkOnRoot (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:18932:28)
```