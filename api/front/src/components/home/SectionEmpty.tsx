import BrandIllustration, { type IllustrationName } from "@/components/BrandIllustration";

interface SectionEmptyProps {
  illustration: IllustrationName;
  /** What is not here yet, phrased for a visitor rather than an admin. */
  message: string;
}

/**
 * Shown when a data-driven landing section has no rows yet.
 *
 * The section deliberately still renders its heading — the page keeps its
 * intended shape while the catalogue is being filled — so this fills the body
 * that would otherwise be blank. A visitor reads "nothing here yet", not
 * "something broke": no error styling, no destructive colour.
 */
const SectionEmpty = ({ illustration, message }: SectionEmptyProps) => (
  <div className="card-tagdar flex flex-col items-center justify-center gap-7 px-6 py-16 text-center border-dashed hover:shadow-none hover:translate-y-0">
    {/* Flat illustrations carry detail an icon does not, so they need real
        size — below ~200px the figures stop reading. */}
    <BrandIllustration name={illustration} className="w-64 max-w-full" />
    <p className="max-w-sm text-muted-foreground leading-relaxed">{message}</p>
  </div>
);

export default SectionEmpty;
