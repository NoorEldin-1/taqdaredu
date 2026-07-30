import { motion } from "framer-motion";
import { ArrowLeft, GraduationCap } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";

/**
 * The teal band with the dot pattern is the client's own header motif, lifted
 * from branding/student-portal.html so the landing and the portal read as one
 * product. It replaces the previous owner's dark full-bleed video hero.
 *
 * Orange appears exactly twice here — the logo spark and the primary CTA. That
 * restraint is the brand book's 10% rule, not an accident.
 */

// Stats sit on the seam between the band and the page below, so the fold has
// something concrete in it rather than ending on decoration.
const stats = [
  { value: "+120", label: "دورة تدريبية" },
  { value: "+40", label: "مدرب معتمد" },
  { value: "+8,000", label: "متدرّب" },
  { value: "4.8", label: "متوسط التقييم" },
];

const HeroSection = () => {
  return (
    <section className="relative">
      {/* ── The band ── */}
      <div className="relative bg-teal overflow-hidden">
        <div
          className="absolute inset-0 dot-pattern opacity-20 pointer-events-none"
          aria-hidden="true"
        />

        <div className="container relative pt-28 pb-40 md:pt-36 md:pb-48">
          <div className="max-w-3xl mx-auto text-center">
            {/* Logo mark — the graduation cap drawn as an open book */}
            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.5 }}
              className="flex justify-center mb-6"
            >
              <span className="relative inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/15 backdrop-blur-sm">
                <GraduationCap className="w-8 h-8 text-white" strokeWidth={1.75} />
                {/* the identity spark */}
                <span className="absolute bottom-2.5 left-2.5 w-2 h-2 rounded-full bg-spark" />
              </span>
            </motion.div>

            <motion.h1
              initial={{ opacity: 0, y: 24 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.05 }}
              className="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-[1.25] text-balance mb-5"
            >
              تعلّم مهارة اليوم،
              <br />
              واصنع فرصة الغد
            </motion.h1>

            <motion.p
              initial={{ opacity: 0, y: 24 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.15 }}
              className="text-lg md:text-xl text-white/85 leading-relaxed max-w-2xl mx-auto mb-9"
            >
              منصة <span className="font-bold text-white">تقدر</span> تجمع لك نخبة
              المدربين في مسارات تعليمية واضحة — تبدأ من حيث أنت، وتنتهي بشهادة
              تفتح لك باباً جديداً.
            </motion.p>

            <motion.div
              initial={{ opacity: 0, y: 24 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.25 }}
              className="flex flex-col sm:flex-row gap-3 justify-center"
            >
              <Button asChild size="xl" className="bg-spark hover:bg-spark-dark text-navy font-bold border-0">
                <Link to="/courses">
                  تصفّح الدورات
                  <ArrowLeft className="w-5 h-5" />
                </Link>
              </Button>
              <Button
                asChild
                size="xl"
                variant="outline"
                className="bg-white/10 border-white/30 text-white hover:bg-white/20 hover:text-white font-bold"
              >
                <Link to="/about-us">تعرّف على تقدر</Link>
              </Button>
            </motion.div>
          </div>
        </div>

        {/* Curved seam — the band resolves into the page instead of cutting it */}
        <svg
          className="absolute bottom-0 inset-x-0 w-full h-10 md:h-14 text-secondary"
          viewBox="0 0 1440 60"
          preserveAspectRatio="none"
          aria-hidden="true"
        >
          <path d="M0,60 C360,0 1080,0 1440,60 Z" fill="currentColor" />
        </svg>
      </div>

      {/* ── Stats, straddling the seam ── */}
      <div className="container relative -mt-24 md:-mt-28 z-10">
        <motion.dl
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.35 }}
          className="card-tagdar grid grid-cols-2 md:grid-cols-4 divide-y divide-x divide-border md:divide-y-0 overflow-hidden"
        >
          {stats.map((s) => (
            <div key={s.label} className="px-6 py-7 text-center">
              <dt className="sr-only">{s.label}</dt>
              <dd>
                <span className="block text-3xl md:text-4xl font-black text-navy tabular-nums">
                  {s.value}
                </span>
                <span className="mt-1 block text-sm text-muted-foreground font-medium">
                  {s.label}
                </span>
              </dd>
            </div>
          ))}
        </motion.dl>
      </div>
    </section>
  );
};

export default HeroSection;
