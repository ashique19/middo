import {
  AbsoluteFill,
  Img,
  interpolate,
  spring,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";
import { SceneBackground } from "../components/SceneBackground";
import { Wordmark } from "../components/Wordmark";
import { fontFamily } from "../font";
import { clamp, fadeInOut } from "../lib/motion";
import { assets, colors } from "../theme";
import type { MiddoAdLayout } from "../schema";

export const HookScene: React.FC<{
  readonly layout: MiddoAdLayout;
  readonly hookLine: string;
  readonly hookSub: string;
  readonly durationInFrames: number;
}> = ({ layout, hookLine, hookSub, durationInFrames }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const vertical = layout === "vertical";
  const opacity = fadeInOut(frame, durationInFrames, 8);
  const plateEnter = spring({
    frame,
    fps,
    config: { damping: 16, mass: 0.85, stiffness: 80 },
  });
  const plateScale = interpolate(plateEnter, [0, 1], [0.72, 1], clamp);
  const zoom = interpolate(frame, [0, durationInFrames], [1, 1.06], clamp);
  const textEnter = spring({
    frame: frame - 8,
    fps,
    config: { damping: 14, stiffness: 110 },
  });
  const textY = interpolate(textEnter, [0, 1], [36, 0], clamp);

  const plate = (
    <Img
      src={staticFile(assets.foodPlate)}
      style={{
        width: vertical ? 820 : 780,
        height: "auto",
        transform: `scale(${plateScale * zoom})`,
        filter: "drop-shadow(0 28px 40px rgba(0,0,0,0.4))",
      }}
    />
  );

  const copyBlock = (
    <div
      style={{
        opacity: textEnter,
        transform: `translateY(${textY}px)`,
        display: "flex",
        flexDirection: "column",
        alignItems: vertical ? "center" : "flex-start",
        gap: 18,
        textAlign: vertical ? "center" : "left",
      }}
    >
      <div
        style={{
          background: colors.cream,
          borderRadius: 20,
          padding: "10px 18px",
        }}
      >
        <Wordmark width={vertical ? 280 : 320} />
      </div>
      <div
        style={{
          fontFamily,
          fontWeight: 800,
          fontSize: vertical ? 120 : 92,
          lineHeight: 0.95,
          letterSpacing: -3,
          color: colors.cream,
        }}
      >
        {hookLine}
      </div>
      <div
        style={{
          fontFamily,
          fontWeight: 600,
          fontSize: vertical ? 40 : 34,
          color: colors.inkMuted,
          maxWidth: 640,
          lineHeight: 1.2,
        }}
      >
        {hookSub}
      </div>
    </div>
  );

  return (
    <AbsoluteFill style={{ opacity }}>
      <SceneBackground tone="dark" glowX={vertical ? 50 : 28} glowY={38} />
      {vertical ? (
        <AbsoluteFill
          style={{
            alignItems: "center",
            justifyContent: "center",
            gap: 8,
            padding: "80px 56px",
          }}
        >
          {plate}
          {copyBlock}
        </AbsoluteFill>
      ) : (
        <AbsoluteFill
          style={{
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "space-between",
            padding: "64px 80px",
          }}
        >
          <div style={{ flex: 1, display: "flex", justifyContent: "center" }}>
            {plate}
          </div>
          <div style={{ flex: 1, paddingLeft: 24 }}>{copyBlock}</div>
        </AbsoluteFill>
      )}
    </AbsoluteFill>
  );
};
