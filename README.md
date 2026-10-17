# TB-AI

TB-AI is a web application for tuberculosis (TB) programme staff. It combines a daily TB register with decision-support tools for smear microscopy workflows, and includes a Command Center for area-level views (facilities, maps, alerts, and related programme intelligence).

The product is **in development**. It is decision support, not a diagnostic device — the microscopist makes the final call. It does not diagnose patients.

## Stack

- **Backend:** Laravel (PHP), Inertia.js
- **Frontend:** React, TypeScript, Tailwind CSS, Vite
- **Auth:** Laravel Fortify

## Local setup

```bash
composer run setup
composer run dev
```

Copy `.env.example` to `.env` if needed (the setup script does this when `.env` is missing). See `.env.example` for feature flags such as screening and Command Center areas.

## Docs

- Suspect-finding screening API: [`docs/suspect-api.yaml`](docs/suspect-api.yaml)

## Credits

- **Fakhar Khan** — [https://github.com/fakharkhan](https://github.com/fakharkhan)
- **Hira** — [https://example.com/hira](https://example.com/hira) *(placeholder — replace with Hira’s website)*
