import { Img, staticFile } from "remotion";
import { assets } from "../theme";

export const Wordmark: React.FC<{
  readonly width: number;
  readonly invert?: boolean;
}> = ({ width, invert = false }) => {
  return (
    <Img
      src={staticFile(assets.wordmark)}
      style={{
        width,
        height: "auto",
        display: "block",
        filter: invert ? "brightness(0) invert(1)" : undefined,
      }}
    />
  );
};
