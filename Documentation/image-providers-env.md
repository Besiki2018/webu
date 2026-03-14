# Image Providers Environment

Webu reads stock image provider credentials from backend environment variables only. The frontend never receives raw provider keys.

## `.env` Example

```dotenv
UNSPLASH_ACCESS_KEY=your_key_here
UNSPLASH_SECRET_KEY=your_secret_here
PEXELS_API_KEY=your_key_here
FREEPIK_API_KEY=your_key_here
```

These are consumed through [services.php](/Users/besikiekseulidze/web-development/webu/Install/config/services.php):

- `services.unsplash.access_key`
- `services.unsplash.secret_key`
- `services.pexels.key`
- `services.freepik.key`

## Validation

Backend validation lives in:

- [StockImageProviderConfig.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/StockImageProviderConfig.php)
- [StockImageProviderConfigurationException.php](/Users/besikiekseulidze/web-development/webu/Install/app/Services/Assets/StockImageProviderConfigurationException.php)

If a required key is missing, Webu returns a clear backend error such as:

- `Unsplash API key not configured`
- `Pexels API key not configured`
- `Freepik API key not configured`

## Frontend Safety

Frontend code calls only backend endpoints such as:

- `POST /api/assets/search`
- `POST /api/assets/import`

Provider credentials stay inside backend services and are never exposed to JavaScript bundles or Inertia props.
