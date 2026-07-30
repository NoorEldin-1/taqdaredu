import { cn } from "@/lib/utils";

import catalogueSvg from "@/assets/illustrations/catalogue.svg";
import pathSvg from "@/assets/illustrations/path.svg";
import mentorSvg from "@/assets/illustrations/mentor.svg";
import writingSvg from "@/assets/illustrations/writing.svg";
import welcomeSvg from "@/assets/illustrations/welcome.svg";
import notFoundSvg from "@/assets/illustrations/not-found.svg";
import noResultsSvg from "@/assets/illustrations/no-results.svg";
import cartSvg from "@/assets/illustrations/cart.svg";
import contactSvg from "@/assets/illustrations/contact.svg";
import teachingSvg from "@/assets/illustrations/teaching.svg";
import profileSvg from "@/assets/illustrations/profile.svg";

/**
 * Flat illustration set, sourced from unDraw and recoloured onto the Tagdar
 * palette (teal #00847B primary, navy #0F2C4C darks, navy-biased slates) by
 * scripts/recolor — see the colour map there. Skin tones are left untouched.
 *
 * unDraw's licence permits commercial use, modification and bundling into a
 * client site with no attribution; it does not permit redistributing them as an
 * illustration pack, which is why these live as project assets rather than a
 * dependency.
 *
 * They are rendered as <img> rather than inlined: the browser caches them
 * separately from the JS bundle, and none of them need to react to theme.
 */

export type IllustrationName =
  | "catalogue" // no courses published
  | "path" // no learning fields yet
  | "mentor" // no instructors yet
  | "writing" // no articles yet
  | "welcome" // auth pages
  | "notFound" // 404
  | "noResults" // a filter or search returned nothing
  | "cart" // empty cart / wishlist
  | "contact" // contact page
  | "teaching" // become-an-instructor
  | "profile"; // account pages

const sources: Record<IllustrationName, string> = {
  catalogue: catalogueSvg,
  path: pathSvg,
  mentor: mentorSvg,
  writing: writingSvg,
  welcome: welcomeSvg,
  notFound: notFoundSvg,
  noResults: noResultsSvg,
  cart: cartSvg,
  contact: contactSvg,
  teaching: teachingSvg,
  profile: profileSvg,
};

interface BrandIllustrationProps {
  name: IllustrationName;
  className?: string;
}

const BrandIllustration = ({ name, className }: BrandIllustrationProps) => (
  <img
    src={sources[name]}
    alt=""
    aria-hidden="true"
    loading="lazy"
    draggable={false}
    className={cn("h-auto w-full select-none", className)}
  />
);

export default BrandIllustration;
