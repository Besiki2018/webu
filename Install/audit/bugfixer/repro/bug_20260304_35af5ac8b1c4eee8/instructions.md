# Repro: bug_20260304_35af5ac8b1c4eee8
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /admin/settings?tab=auth
3. Perform the action that triggered the error (see event message below).
## Event
```
useLanguage must be used within a LanguageProvider
```
## Stack (excerpt)
```
Error: useLanguage must be used within a LanguageProvider
    at useLanguage (http://127.0.0.1:5173/resources/js/contexts/LanguageContext.tsx?t=1772663921070:726:11)
    at useTranslation (http://127.0.0.1:5173/resources/js/contexts/LanguageContext.tsx?t=1772663921070:733:32)
    at Settings (http://127.0.0.1:5173/resources/js/Pages/Admin/Settings.tsx?t=1772663921070:128:17)
    at renderWithHooks (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:11596:26)
    at mountIndeterminateComponent (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:14974:21)
    at beginWork (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:15962:22)
    at beginWork$1 (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:19806:22)
    at performUnitOfWork (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:19251:20)
    at workLoopSync (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:19190:13)
    at renderRootSync (http://127.0.0.1:5173/node_modules/.vite/deps/chunk-LPF6KSF2.js?v=0ec34260:19169:15)
```