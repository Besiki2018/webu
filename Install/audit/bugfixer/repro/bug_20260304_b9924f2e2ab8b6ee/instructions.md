# Repro: bug_20260304_b9924f2e2ab8b6ee
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /project/019cbabc-c8c2-70ee-96a6-4606aa0e768f
3. Perform the action that triggered the error (see event message below).
## Event
```
Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.
```
## Stack (excerpt)
```
NotFoundError: Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.
    at removeChildFromContainer (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:8509:23)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17558:21)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17627:15)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17627:15)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17627:15)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:17660:15)
```