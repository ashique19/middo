import {
  AbsoluteFill,
  interpolate,
  spring,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";
import { SceneBackground } from "../components/SceneBackground";
import { fontFamily } from "../font";
import { clamp, fadeInOut } from "../lib/motion";
import { colors } from "../theme";
import type { MiddoAdLayout } from "../schema";

const chips = ["Middo Kitchen", "Middo Rider", "Middo Corporate"] as const;

export const BenefitScene: React.FC<{
  readonly layout: MiddoAdLayout;
  readonly benefitLine: string;
  readonly benefitSub: string;
  readonly durationInFrames: number;
}> = ({ layout, benefitLine, benefitSub, durationInFrames }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const vertical = layout === "vertical";
  const opacity = fadeInOut(frame, durationInFrames, 10);
  const headline = spring({
    frame,
    fps,
    config: { damping: 14, stiffness: 90 },
  });
  const sub = spring({
    frame: frame - 10,
    fps,
    config: { damping: 14, stiffness: 100 },
  });
  const chip = spring({
    frame: frame - 22,
    fps,
    config: { damping: 12, stiffness: 110 },
  });

  return (
    <AbsoluteFill style={{ opacity }}>
      <SceneBackground tone="green" glowX={50} glowY={40} />
      <AbsoluteFill
        style={{
          alignItems: "center",
          justifyContent: "center",
          padding: vertical ? "80px 56px" : "64px 96px",
          textAlign: "center",
          gap: vertical ? 28 : 22,
        }}
      >
        <div
          style={{
            opacity: headline,
            transform: `scale(${interpolate(headline, [0, 1], [0.92, 1], clamp)})`,
            fontFamily,
            fontWeight: 800,
            fontSize: vertical ? 96 : 84,
            lineHeight: 0.98,
            letterSpacing: -2.4,
            color: colors.cream,
            maxWidth: vertical ? 900 : 1400,
          }}
        >
          {benefitLine}
        </div>
        <div
          style={{
            opacity: sub,
            transform: `translateY(${interpolate(sub, [0, 1], [24, 0], clamp)}px)`,
            fontFamily,
            fontWeight: 700,
            fontSize: vertical ? 48 : 40,
            color: "rgba(245, 242, 233, 0.88)",
          }}
        >
          {benefitSub}
        </div>
        <div
          style={{
            opacity: chip,
            display: "flex",
            flexWrap: "wrap",
            justifyContent: "center",
            gap: 12,
            marginTop: 18,
          }}
        >
          {chips.map((label) => (
            <div
              key={label}
              style={{
                fontFamily,
                fontWeight: 700,
                fontSize: vertical ? 22 : 20,
                color: colors.cream,
                background: "rgba(26, 28, 25, 0.28)",
                border: "1px solid rgba(245, 242, 233, 0.18)",
                borderRadius: 999,
                padding: "10px 20px",
              }}
            >
              {label}
            </div>
          ))}
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
