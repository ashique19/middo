export const colors = {
  cream: "#f5f2e9",
  creamSoft: "#fffaf3",
  bgAlt: "#F2EDE4",
  dark: "#1A1C19",
  darkLift: "#242721",
  orange: "#ab3f00",
  orangeHot: "#d45a12",
  orangeGlow: "rgba(171, 63, 0, 0.55)",
  green: "#2f4a33",
  greenLift: "#3d5e42",
  white: "#ffffff",
  inkMuted: "rgba(245, 242, 233, 0.72)",
  inkOnCream: "rgba(26, 28, 25, 0.62)",
} as const;

export const FPS = 30;
export const DURATION_SECONDS = 20;
export const DURATION_IN_FRAMES = FPS * DURATION_SECONDS;

export const beats = {
  hook: { from: 0, durationInFrames: 3 * FPS },
  journey: { from: 3 * FPS, durationInFrames: 5 * FPS },
  benefit: { from: 8 * FPS, durationInFrames: 6 * FPS },
  cta: { from: 14 * FPS, durationInFrames: 6 * FPS },
} as const;

export const copy = {
  hookLine: "Lunchtime?",
  hookSub: "Middo brings it to your desk.",
  journeyTitle: "Menu to desk.",
  benefitLine: "Office lunch, sorted.",
  benefitSub: "From kitchen to your desk",
  ctaLabel: "Get Middo",
  ctaSub: "Office lunch, delivered.",
  chips: ["Daily menus", "Office delivery", "Track in the app"] as const,
  journey: [
    {
      iconKey: "order" as const,
      label: "Browse",
      caption: "Daily menus for the office",
    },
    {
      iconKey: "kitchen" as const,
      label: "Kitchen",
      caption: "Cooked fresh, packed for lunch",
    },
    {
      iconKey: "office" as const,
      label: "Desk",
      caption: "Delivered to your office",
    },
  ],
} as const;

export const assets = {
  wordmark: "middo-wordmark.svg",
  foodPlate: "food-plate.svg",
  order: "order-icon.svg",
  kitchen: "kitchen-icon.svg",
  rider: "rider-icon.svg",
  office: "office-icon.svg",
} as const;
