import type { FC } from "react";
import { AbsoluteFill, Sequence } from "remotion";
import { fontFamily } from "./font";
import type { MiddoAdProps } from "./schema";
import { BenefitScene } from "./scenes/BenefitScene";
import { CtaScene } from "./scenes/CtaScene";
import { HookScene } from "./scenes/HookScene";
import { JourneyScene } from "./scenes/JourneyScene";
import { beats, colors } from "./theme";

export const MiddoAd: FC<MiddoAdProps> = ({
  layout,
  hookLine,
  hookSub,
  benefitLine,
  benefitSub,
  ctaLabel,
  ctaSub,
}) => {
  return (
    <AbsoluteFill style={{ backgroundColor: colors.dark, fontFamily }}>
      <Sequence
        from={beats.hook.from}
        durationInFrames={beats.hook.durationInFrames}
        name="Hook"
        premountFor={15}
      >
        <HookScene
          layout={layout}
          hookLine={hookLine}
          hookSub={hookSub}
          durationInFrames={beats.hook.durationInFrames}
        />
      </Sequence>
      <Sequence
        from={beats.journey.from}
        durationInFrames={beats.journey.durationInFrames}
        name="Journey"
        premountFor={15}
      >
        <JourneyScene
          layout={layout}
          durationInFrames={beats.journey.durationInFrames}
        />
      </Sequence>
      <Sequence
        from={beats.benefit.from}
        durationInFrames={beats.benefit.durationInFrames}
        name="Benefit"
        premountFor={15}
      >
        <BenefitScene
          layout={layout}
          benefitLine={benefitLine}
          benefitSub={benefitSub}
          durationInFrames={beats.benefit.durationInFrames}
        />
      </Sequence>
      <Sequence
        from={beats.cta.from}
        durationInFrames={beats.cta.durationInFrames}
        name="CTA"
        premountFor={15}
      >
        <CtaScene
          layout={layout}
          ctaLabel={ctaLabel}
          ctaSub={ctaSub}
          durationInFrames={beats.cta.durationInFrames}
        />
      </Sequence>
    </AbsoluteFill>
  );
};
