# Repro: bug_20260304_3107567ae40a6ee5
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /admin/transactions
3. Perform the action that triggered the error (see event message below).
## Event
```
useLanguage must be used within a LanguageProvider
```
## Stack (excerpt)
```
Error: useLanguage must be used within a LanguageProvider
    at useLanguage (http://127.0.0.1:5173/resources/js/contexts/LanguageContext.tsx?t=1772658395284:663:11)
    at useTranslation (http://127.0.0.1:5173/resources/js/contexts/LanguageContext.tsx?t=1772658395284:670:32)
    at Transactions (http://127.0.0.1:5173/resources/js/Pages/Admin/Transactions.tsx?t=1772658395284:86:25)
    at renderWithHooks (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:11596:26)
    at mountIndeterminateComponent (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:14974:21)
    at beginWork (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:15962:22)
    at beginWork$1 (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19806:22)
    at performUnitOfWork (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19251:20)
    at workLoopSync (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19190:13)
    at renderRootSync (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=fac1e020:19169:15)
```