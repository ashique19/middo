import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { CtaButton } from "../components/CtaButton";
import { SceneBackground } from "../components/SceneBackground";
import { Wordmark } from "../components/Wordmark";
import { fontFamily } from "../font";
import { clamp, fadeInOut } from "../lib/motion";
import { colors } from "../theme";
import type { MiddoAdLayout } from "../schema";

export const CtaScene: React.FC<{
  readonly layout: MiddoAdLayout;
  readonly ctaLabel: string;
  readonly durationInFrames: number;
}> = ({ layout, ctaLabel, durationInFrames }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const vertical = layout === "vertical";
  const opacity = fadeInOut(frame, durationInFrames, 8);
  const mark = spring({
    frame,
    fps,
    config: { damping: 16, stiffness: 120 },
  });
  const sub = spring({
    frame: frame - 12,
    fps,
    config: { damping: 14, stiffness: 110 },
  });

  return (
    <AbsoluteFill style={{ opacity }}>
      <SceneBackground tone="cream" glowX={50} glowY={70} />
      <AbsoluteFill
        style={{
          alignItems: "center",
          justifyContent: "center",
          gap: vertical ? 36 : 28,
          padding: vertical ? "80px 56px" : "48px 80px",
        }}
      >
        <div
          style={{
            opacity: mark,
            transform: `translateY(${interpolate(mark, [0, 1], [20, 0], clamp)}px)`,
          }}
        >
          <Wordmark width={vertical ? 420 : 380} />
        </div>
        <CtaButton label={ctaLabel} width={vertical ? 520 : 480} />
        <div
          style={{
            opacity: sub,
            fontFamily,
            fontWeight: 600,
            fontSize: vertical ? 28 : 24,
            color: colors.inkOnCream,
            letterSpacing: 0.2,
          }}
        >
          Kitchen · Rider · Corporate
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
