# Middo commercial

A ~20 second image-driven motion-graphics ad for **Middo**, a multi-role food delivery platform (ops / kitchen / rider / corporate).

This folder is a **self-contained Remotion** app. It does not depend on the Laravel or Flutter apps in the rest of this repository.

Brand palette matches the product CSS tokens: cream `#f5f2e9`, dark `#1A1C19`, orange `#ab3f00`, green `#2f4a33`. All stills (food plate, wordmark, kitchen, rider, order) are original SVGs — no third-party or store trademarks.

## Compositions

| ID | Size | Use |
| --- | --- | --- |
| `MiddoAdVertical` | 1080×1920 | Reels / TikTok / Stories |
| `MiddoAdLandscape` | 1920×1080 | YouTube / web |

Duration: **20 seconds** at 30 fps.

### Beats

1. **Hook (0–3s)** — plated meal, Middo wordmark, “Hungry?”
2. **Journey (3–8s)** — Order → Kitchen → Rider en route
3. **Benefit (8–14s)** — “Fast local delivery” / “From kitchen to door”
4. **CTA (14–20s)** — “Get Middo” (no App Store / Play logos)

## Setup

Requires Node 18+ (Node 22 is fine). Chrome/Chromium is used for rendering.

```bash
cd ads/middo-commercial
npm install
```

## Remotion Studio

Preview and scrub the timeline:

```bash
npm run studio
```

(`npm run dev` is the same command.) Open the URL Remotion prints (usually `http://localhost:3000`). Select **MiddoAdVertical** in the sidebar.

## Render MP4

H.264 MP4 is the default codec.

Vertical (social):

```bash
npm run render
# or
npx remotion render MiddoAdVertical out/middo-ad-vertical.mp4
```

Landscape:

```bash
npm run render:landscape
# or
npx remotion render MiddoAdLandscape out/middo-ad-landscape.mp4
```

Outputs land in `out/` (gitignored).

Poster still from the hook:

```bash
npm run still:vertical
```

### Linux / headless notes

If Chrome is not discovered automatically:

```bash
export PUPPETEER_EXECUTABLE_PATH="$(command -v google-chrome || command -v google-chrome-stable || command -v chromium)"
npx remotion render MiddoAdVertical out/middo-ad-vertical.mp4 --gl=angle
```

## Project layout

```
ads/middo-commercial/
  public/                 original SVG stills
  src/index.ts            Remotion entry (registerRoot)
  src/Root.tsx            composition registry
  src/MiddoAd.tsx         20s sequence
  src/theme.ts            colors, timing, asset names
  src/scenes/             hook / journey / benefit / CTA
```

Copy and layout can be tweaked in Remotion Studio via the Zod schema on each composition (hook line, benefit lines, CTA label). Do not add fake metrics or reviews.

## License

Source in this folder is for Middo. Remotion itself may require a company license depending on the entity using it — see [Remotion license](https://www.remotion.dev/docs/license).
