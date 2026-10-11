import { interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { fontFamily } from "../font";
import { colors } from "../theme";
import { clamp } from "../lib/motion";

export const CtaButton: React.FC<{
  readonly label: string;
  readonly width: number;
}> = ({ label, width }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = spring({
    frame,
    fps,
    config: { damping: 12, mass: 0.7, stiffness: 140 },
  });
  const pulse = interpolate(
    Math.sin((frame / fps) * Math.PI * 2),
    [-1, 1],
    [1, 1.035],
  );
  const scale = interpolate(enter, [0, 1], [0.82, 1], clamp) * pulse;

  return (
    <div
      style={{
        width,
        transform: `scale(${scale})`,
        background: colors.orange,
        color: colors.creamSoft,
        fontFamily,
        fontWeight: 800,
        fontSize: width > 420 ? 56 : 44,
        letterSpacing: -1,
        textAlign: "center",
        borderRadius: 999,
        padding: width > 420 ? "28px 16px" : "22px 16px",
        boxShadow: "0 18px 40px rgba(171, 63, 0, 0.35)",
      }}
    >
      {label}
    </div>
  );
};
