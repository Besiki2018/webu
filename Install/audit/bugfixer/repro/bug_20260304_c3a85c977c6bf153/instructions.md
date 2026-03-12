# Repro: bug_20260304_c3a85c977c6bf153
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /create
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.error] Warning: Function components cannot be given refs. Attempts to access this ref will fail. Did you mean to use React.forwardRef()?%s%s 

Check the render method of `Primitive.div.SlotClone`. 
    at SheetOverlay (http://127.0.0.1:5173/resources/js/components/ui/sheet.tsx:62:3)
    at http://127.0.0.1:5173/node_modules/.vite/deps/chunk-FJBTB7ZO.js?v=fac1e020:423:13
    at http://127.0.0.1:5173/node_modules/.vite/deps/chunk-FJBTB7ZO.js?v=fac1e020:400:13
    at http://127.0.0.1:5173/node_modules/.vite/deps/chunk-FJBTB7ZO.js?v=fac1e020:512:13
    at http://127.0.0.1:5173/node_modules/.vite/deps/chunk-FJBTB7ZO.js?v=fac1e020:527:22
    at Presence (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-4IGGD3YR.js?v=fac1e020:24:11)
    at Provider (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-B6D5BJVX.js?v=fac1e020:69:15)
    at DialogPortal (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-B6D5BJVX.js?v=fac1e020:320:11)
    at SheetPortal (http://127.0.0.1:5173/resources/js/components/ui/sheet.tsx:52:6)
    at SheetContent (http://127.0.0.1:5173/resources/js/components/ui/sheet.tsx:87:3)
    at Provider (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-B6D5BJVX.js?v=fac1e020:69:15)
    at Dialog (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-B6D5BJVX.js?v=fac1e020:260:5)
    at Sheet (http://127.0.0.1:5173/resources/js/components/ui/sheet.tsx:22:6)
    at http://127.0.0.1:5173/resources/js/components/ui/sidebar.tsx:159:7
    at AppSidebar (http://127.0.0.1:5173/reso
```