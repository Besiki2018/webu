# Repro: bug_20260304_65ef8f2ac2aecb5d
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /admin/users
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.error] Warning: Encountered two children with the same key, `%s`. Keys should be unique so that components maintain their identity across updates. Non-unique keys may cause children to be duplicated and/or omitted — the behavior is unsupported and could change in a future version.%s plan 
    at tr
    at TableRow (http://127.0.0.1:5173/resources/js/components/ui/table.tsx:101:21)
    at thead
    at TableHeader (http://127.0.0.1:5173/resources/js/components/ui/table.tsx:41:24)
    at table
    at div
    at Table (http://127.0.0.1:5173/resources/js/components/ui/table.tsx:18:18)
    at div
    at div
    at DataTable (http://127.0.0.1:5173/resources/js/components/ui/data-table.tsx:48:3)
    at div
    at main
    at div
    at main
    at _c9 (http://127.0.0.1:5173/resources/js/components/ui/sidebar.tsx:422:48)
    at div
    at Provider2 (http://127.0.0.1:5173/node_modules/.vite/deps/@radix-ui_react-tooltip.js?v=0ec34260:61:15)
    at TooltipProvider (http://127.0.0.1:5173/node_modules/.vite/deps/@radix-ui_react-tooltip.js?v=0ec34260:257:5)
    at TooltipProvider (http://127.0.0.1:5173/resources/js/components/ui/tooltip.tsx:21:3)
    at http://127.0.0.1:5173/resources/js/components/ui/sidebar.tsx:60:7
    at Provider2 (http://127.0.0.1:5173/node_modules/.vite/deps/@radix-ui_react-tooltip.js?v=0ec34260:61:15)
    at TooltipProvider (http://127.0.0.1:5173/node_modules/.vite/deps/@radix-ui_react-tooltip.js?v=0ec34260:257:5)
    at TooltipProvider (http://127.0.0.1:5173/resources/js/c
```