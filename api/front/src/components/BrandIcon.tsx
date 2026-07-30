import type { LucideIcon } from "lucide-react";
import { cn } from "@/lib/utils";

interface BrandIconProps {
  icon: LucideIcon;
  /** Tile size. Icons scale with it. */
  size?: "sm" | "md";
  className?: string;
}

/**
 * The Tagdar icon treatment: a soft teal tile holding a light-stroke icon, with
 * a small orange dot pinned to the corner.
 *
 * The dot is taken straight from the client's icon sprite in
 * branding/student-portal.html, where every single glyph carries one. It is the
 * cheapest, most repeatable piece of the identity — using it consistently is
 * what makes an otherwise plain light page read as unmistakably theirs.
 */
const BrandIcon = ({ icon: Icon, size = "md", className }: BrandIconProps) => {
  const tile = size === "sm" ? "w-10 h-10 rounded-xl" : "w-14 h-14 rounded-2xl";
  const glyph = size === "sm" ? "w-5 h-5" : "w-7 h-7";
  const dot = size === "sm" ? "w-1.5 h-1.5 bottom-1.5 left-1.5" : "w-2 h-2 bottom-2 left-2";

  return (
    <span
      className={cn(
        "relative inline-flex items-center justify-center shrink-0 bg-primary/10 text-primary",
        tile,
        className
      )}
    >
      <Icon className={glyph} strokeWidth={1.75} aria-hidden="true" />
      <span className={cn("absolute rounded-full bg-spark", dot)} aria-hidden="true" />
    </span>
  );
};

export default BrandIcon;
