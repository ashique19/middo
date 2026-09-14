import type { FC } from "react";
import { Composition } from "remotion";
import { MiddoAd } from "./MiddoAd";
import { defaultAdProps, middoAdSchema } from "./schema";
import { DURATION_IN_FRAMES, FPS } from "./theme";

export const RemotionRoot: FC = () => {
  return (
    <>
      <Composition
        id="MiddoAdVertical"
        component={MiddoAd}
        durationInFrames={DURATION_IN_FRAMES}
        fps={FPS}
        width={1080}
        height={1920}
        schema={middoAdSchema}
        defaultProps={{ ...defaultAdProps, layout: "vertical" }}
      />
      <Composition
        id="MiddoAdLandscape"
        component={MiddoAd}
        durationInFrames={DURATION_IN_FRAMES}
        fps={FPS}
        width={1920}
        height={1080}
        schema={middoAdSchema}
        defaultProps={{ ...defaultAdProps, layout: "landscape" }}
      />
    </>
  );
};
