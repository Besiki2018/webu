# Repro: bug_20260304_b17a3f70cfb06d5a
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /projects
3. Perform the action that triggered the error (see event message below).
## Event
```
Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.
```
## Stack (excerpt)
```
NotFoundError: Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.
    at removeChildFromContainer (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:8509:23)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17558:21)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17660:15)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17627:15)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17627:15)
    at recursivelyTraverseDeletionEffects (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17535:13)
    at commitDeletionEffectsOnFiber (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:17627:15)
```