# Repro: bug_20260304_fea1b70553a8a045
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /project/019cbabc-c8c2-70ee-96a6-4606aa0e768f?tab=code
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.error] Failed to fetch files: Request failed with status code 500
AxiosError: Request failed with status code 500
    at settle (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-6FQ6SF2D.js?v=fac1e020:1257:12)
    at XMLHttpRequest.onloadend (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-6FQ6SF2D.js?v=fac1e020:1606:7)
    at Axios.request (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-6FQ6SF2D.js?v=fac1e020:2223:41)
    at async http://127.0.0.1:5173/resources/js/components/Code/FileTree.tsx?t=1772659479225:36:19
```