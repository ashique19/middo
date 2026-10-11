import type { CSSProperties } from "react";
import { AbsoluteFill, interpolate, useCurrentFrame } from "remotion";
import { colors } from "../theme";
import { clamp } from "../lib/motion";

type BackgroundTone = "dark" | "green" | "cream";

export const SceneBackground: React.FC<{
  readonly tone: BackgroundTone;
  readonly glowX?: number;
  readonly glowY?: number;
}> = ({ tone, glowX = 50, glowY = 32 }) => {
  const frame = useCurrentFrame();
  const drift = interpolate(frame, [0, 90], [0, 18], clamp);
  const base =
    tone === "cream"
      ? colors.cream
      : tone === "green"
        ? colors.green
        : colors.dark;
  const glow =
    tone === "cream"
      ? "rgba(171, 63, 0, 0.18)"
      : tone === "green"
        ? "rgba(245, 242, 233, 0.16)"
        : colors.orangeGlow;

  const style: CSSProperties = {
    backgroundColor: base,
    backgroundImage: `radial-gradient(ellipse 80% 55% at ${glowX}% ${glowY + drift * 0.15}%, ${glow} 0%, transparent 62%)`,
  };

  return (
    <AbsoluteFill style={style}>
      <AbsoluteFill
        style={{
          opacity: tone === "cream" ? 0.18 : 0.08,
          backgroundImage:
            "repeating-linear-gradient(0deg, rgba(0,0,0,0.12) 0px, rgba(0,0,0,0.12) 1px, transparent 1px, transparent 3px)",
        }}
      />
    </AbsoluteFill>
  );
};
