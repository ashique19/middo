import { Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { fontFamily } from "../font";
import { colors } from "../theme";
import { clamp } from "../lib/motion";

export const JourneyCard: React.FC<{
  readonly icon: string;
  readonly label: string;
  readonly caption: string;
  readonly delay: number;
  readonly compact?: boolean;
}> = ({ icon, label, caption, delay, compact = false }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = spring({
    frame: frame - delay,
    fps,
    config: { damping: 14, mass: 0.6, stiffness: 120 },
  });
  const y = interpolate(enter, [0, 1], [48, 0], clamp);
  const size = compact ? 88 : 120;

  return (
    <div
      style={{
        flex: compact ? 1 : "none",
        opacity: enter,
        transform: `translateY(${y}px)`,
        background: colors.darkLift,
        border: `1px solid rgba(245, 242, 233, 0.12)`,
        borderRadius: compact ? 28 : 36,
        padding: compact ? 22 : 28,
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        gap: compact ? 12 : 16,
        minWidth: 0,
      }}
    >
      <Img src={staticFile(icon)} style={{ width: size, height: size }} />
      <div
        style={{
          fontFamily,
          fontWeight: 800,
          fontSize: compact ? 28 : 36,
          color: colors.cream,
          letterSpacing: -0.6,
          textAlign: "center",
        }}
      >
        {label}
      </div>
      <div
        style={{
          fontFamily,
          fontWeight: 600,
          fontSize: compact ? 18 : 22,
          color: colors.inkMuted,
          textAlign: "center",
          lineHeight: 1.25,
        }}
      >
        {caption}
      </div>
    </div>
  );
};
