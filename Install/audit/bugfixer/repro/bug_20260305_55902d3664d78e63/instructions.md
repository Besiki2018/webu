# Repro: bug_20260305_55902d3664d78e63
Severity: high | Source: frontend
## Steps to reproduce
1. Ensure app is running (e.g. npm run start).
2. Navigate to route: /
3. Perform the action that triggered the error (see event message below).
## Event
```
[console.error] The above error occurred in the <Landing> component:

    at Landing (http://127.0.0.1:5173/resources/js/Pages/Landing.tsx:68:3)
    at DisabledReCaptchaProvider (http://127.0.0.1:5173/resources/js/components/Auth/ReCaptchaProvider.tsx:60:38)
    at ReCaptchaProvider (http://127.0.0.1:5173/resources/js/components/Auth/ReCaptchaProvider.tsx:77:37)
    at LanguageProvider (http://127.0.0.1:5173/resources/js/contexts/LanguageContext.tsx?t=1772669788559:681:36)
    at AppWrapper (http://127.0.0.1:5173/resources/js/app.tsx:30:23)
    at App (http://127.0.0.1:5173/node_modules/.vite/deps/@inertiajs_react.js?v=0ec34260:14984:3)
    at BugfixerErrorBoundary (http://127.0.0.1:5173/resources/js/components/BugfixerErrorBoundary.tsx:7:5)
    at ThemeProvider (http://127.0.0.1:5173/resources/js/contexts/ThemeContext.tsx:41:33)

React will try to recreate this component tree from scratch using the error boundary you provided, BugfixerErrorBoundary.
```