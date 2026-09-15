import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { JourneyCard } from "../components/JourneyCard";
import { SceneBackground } from "../components/SceneBackground";
import { fontFamily } from "../font";
import { clamp, fadeInOut } from "../lib/motion";
import { assets, colors, copy } from "../theme";
import type { MiddoAdLayout } from "../schema";

export const JourneyScene: React.FC<{
  readonly layout: MiddoAdLayout;
  readonly durationInFrames: number;
}> = ({ layout, durationInFrames }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const vertical = layout === "vertical";
  const opacity = fadeInOut(frame, durationInFrames, 8);
  const titleEnter = spring({
    frame,
    fps,
    config: { damping: 16, stiffness: 120 },
  });
  const titleY = interpolate(titleEnter, [0, 1], [28, 0], clamp);

  const cards = (
    <div
      style={{
        display: "flex",
        flexDirection: vertical ? "column" : "row",
        gap: vertical ? 22 : 24,
        width: "100%",
      }}
    >
      {copy.journey.map((step, index) => (
        <JourneyCard
          key={step.label}
          icon={assets[step.iconKey]}
          label={step.label}
          caption={step.caption}
          delay={6 + index * 10}
          compact={!vertical}
        />
      ))}
    </div>
  );

  return (
    <AbsoluteFill style={{ opacity }}>
      <SceneBackground tone="dark" glowX={70} glowY={20} />
      <AbsoluteFill
        style={{
          padding: vertical ? "120px 64px" : "72px 80px",
          justifyContent: "center",
          gap: vertical ? 36 : 40,
        }}
      >
        <div
          style={{
            opacity: titleEnter,
            transform: `translateY(${titleY}px)`,
            fontFamily,
            fontWeight: 800,
            fontSize: vertical ? 64 : 52,
            color: colors.cream,
            letterSpacing: -1.4,
            textAlign: vertical ? "center" : "left",
          }}
        >
          {copy.journeyTitle}
        </div>
        {cards}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
