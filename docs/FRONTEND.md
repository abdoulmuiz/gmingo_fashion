# Frontend assets

## Stylesheets in production

| File | Role |
| --- | --- |
| `public/assets/app.css` | Primary design system (layout, forms, navigation, auth). Required. |
| `public/assets/tailwind.css` | Compiled, self-hosted Tailwind utilities/components. Optional at runtime only if the file is present. |

There is **no Tailwind CDN** at runtime. Production needs the compiled `tailwind.css` file committed or copied with the deploy; Node is not required on the server.

## Tailwind status

- **Implemented:** local Tailwind 3 build (`package.json` + `tailwind.config.js` + `assets/tailwind.input.css`).
- **Partially migrated:** selected screens use Tailwind utilities/components (`storefront/shop`, `sales/index`) alongside existing `.card` / `.btn` classes.
- **Legacy CSS still in use:** nearly all screens still depend on `app.css`.

## Build commands (development machine)

```bash
npm install
npm run build:css
```

Watch mode:

```bash
npm run watch:css
```

`preflight` is disabled so Tailwind does not reset the existing Gmingo CSS.

## Content scanning

`tailwind.config.js` scans:

- `app/fashion/views/**/*.php`
- `public/assets/**/*.js`

Dynamic class names used from PHP are listed in `safelist`.
