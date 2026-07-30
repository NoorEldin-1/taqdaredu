import * as React from "react";
import { Slot } from "@radix-ui/react-slot";
import { cva, type VariantProps } from "class-variance-authority";

import { cn } from "@/lib/utils";

const buttonVariants = cva(
  // Text on teal is white, not navy: navy-on-teal measures 3.09:1, which fails
  // WCAG AA for normal text. White-on-teal is 4.58:1 and passes. Navy is kept
  // only on spark orange (6.46:1), where it is both legible and on-brand.
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-semibold ring-offset-background transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "bg-primary text-white hover:bg-primary-hover shadow-sm hover:shadow-primary",
        destructive: "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        outline: "border-2 border-primary text-primary bg-transparent hover:bg-primary hover:text-white",
        secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        // Hover resolves to teal, not `accent` — accent is the spark orange, and
        // tinting every ghost button orange on hover breaks the 10% rule.
        ghost: "hover:bg-primary/10 hover:text-primary",
        link: "text-primary underline-offset-4 hover:underline",
        hero: "bg-primary text-white hover:bg-primary-hover shadow-primary hover:shadow-lg transition-all duration-300 hover:-translate-y-0.5",
        heroOutline: "border-2 border-primary-foreground/80 text-primary-foreground bg-transparent hover:bg-primary-foreground/10",
        // The one intentionally orange variant: primary calls to action.
        gold: "bg-accent text-accent-foreground hover:bg-accent-hover shadow-sm",
        navCta: "bg-primary text-white hover:bg-primary-hover shadow-sm px-6",
      },
      size: {
        default: "h-10 px-5 py-2",
        sm: "h-9 rounded-lg px-4 text-xs",
        lg: "h-12 rounded-xl px-8 text-base",
        xl: "h-14 rounded-xl px-10 text-lg",
        icon: "h-10 w-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : "button";
    return <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />;
  }
);
Button.displayName = "Button";

export { Button, buttonVariants };
