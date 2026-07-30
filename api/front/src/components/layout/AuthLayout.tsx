import type { ReactNode } from "react";
import { motion } from "framer-motion";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIllustration from "@/components/BrandIllustration";

interface AuthLayoutProps {
  title: string;
  subtitle: string;
  /** Reassurance shown on the brand panel — why this account is worth making. */
  panelText: string;
  children: ReactNode;
  /** Link row under the form ("already have an account?" etc). */
  footer?: ReactNode;
}

/**
 * Shared shell for login / register / forgot-password.
 *
 * The three pages were previously three separate dark glass cards that had
 * drifted apart. Centralising the chrome means the auth flow reads as one
 * surface, and a change to the shell lands on all three at once.
 *
 * The teal panel repeats the hero's band + dot motif, so arriving at sign-in
 * from the landing page feels continuous rather than like a different product.
 */
const AuthLayout = ({ title, subtitle, panelText, children, footer }: AuthLayoutProps) => (
  <div className="min-h-screen bg-secondary flex flex-col">
    <Navbar />

    <main className="flex-1 flex items-center py-14 pt-32">
      <div className="container">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="card-tagdar mx-auto grid w-full max-w-5xl overflow-hidden md:grid-cols-2 hover:shadow-[var(--shadow-md)] hover:translate-y-0"
        >
          {/* Form — first in the DOM so it is first for keyboard and screen
              readers, and lands at the reading-start edge under RTL. */}
          <div className="p-8 sm:p-10">
            <h1 className="text-2xl font-black text-navy">{title}</h1>
            <p className="mt-2 text-muted-foreground leading-relaxed">{subtitle}</p>

            <div className="mt-8">{children}</div>

            {footer && <div className="mt-6 text-center text-muted-foreground">{footer}</div>}
          </div>

          {/* Brand panel — decorative, so it drops out entirely on small screens
              rather than pushing the form below the fold. The ground is a soft
              teal tint rather than solid teal: these are full-colour flat
              illustrations, and a saturated panel would fight them. */}
          <div className="relative hidden bg-primary/5 p-10 md:flex md:flex-col md:justify-center">
            <div className="absolute inset-0 dot-pattern-navy opacity-40" aria-hidden="true" />
            <div className="relative">
              <BrandIllustration name="welcome" className="mx-auto w-72 max-w-full" />
              <p className="mt-8 text-center text-lg font-medium leading-relaxed text-navy">
                {panelText}
              </p>
            </div>
          </div>
        </motion.div>
      </div>
    </main>

    <Footer />
  </div>
);

export default AuthLayout;
