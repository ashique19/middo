import { z } from "zod";
import { copy } from "./theme";

export const middoAdSchema = z.object({
  layout: z.enum(["vertical", "landscape"]),
  hookLine: z.string(),
  hookSub: z.string(),
  benefitLine: z.string(),
  benefitSub: z.string(),
  ctaLabel: z.string(),
  ctaSub: z.string(),
});

export type MiddoAdProps = z.infer<typeof middoAdSchema>;
export type MiddoAdLayout = MiddoAdProps["layout"];

export const defaultAdProps: MiddoAdProps = {
  layout: "vertical",
  hookLine: copy.hookLine,
  hookSub: copy.hookSub,
  benefitLine: copy.benefitLine,
  benefitSub: copy.benefitSub,
  ctaLabel: copy.ctaLabel,
  ctaSub: copy.ctaSub,
};
