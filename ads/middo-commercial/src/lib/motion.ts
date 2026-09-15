import { interpolate } from "remotion";

export const clamp = {
  extrapolateLeft: "clamp" as const,
  extrapolateRight: "clamp" as const,
};

export const fadeInOut = (
  frame: number,
  durationInFrames: number,
  fadeFrames = 10,
) => {
  return interpolate(
    frame,
    [0, durationInFrames - fadeFrames, durationInFrames],
    [1, 1, 0],
    clamp,
  );
};
