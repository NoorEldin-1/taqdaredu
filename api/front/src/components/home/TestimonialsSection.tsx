import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { ChevronLeft, ChevronRight, Quote, Star } from "lucide-react";
import { useTestimonials } from "@/hooks/useApi";

const avatarFor = (name: string) =>
  `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=00847B&color=fff`;

const TestimonialsSection = () => {
  const [currentIndex, setCurrentIndex] = useState(0);
  const { data } = useTestimonials();
  const testimonials = data?.data || [];

  // Don't render the section at all if there are no real testimonials.
  if (testimonials.length === 0) return null;

  const safeIndex = currentIndex % testimonials.length;
  const current = testimonials[safeIndex];
  // Relative paths are served from the same origin — no hardcoded host.
  const imgSrc = current.image || avatarFor(current.name);

  const next = () => setCurrentIndex((prev) => (prev + 1) % testimonials.length);
  const prev = () => setCurrentIndex((prev) => (prev - 1 + testimonials.length) % testimonials.length);

  return (
    <section className="py-20 md:py-24">
      <div className="container">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5 }}
          className="max-w-2xl mb-12"
        >
          <span className="eyebrow">
            <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
            آراء المتدرّبين
          </span>
          <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
            نتائج يرويها أصحابها
          </h2>
          <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
            قصص حقيقية لمتدرّبين أنهوا مساراتهم معنا وأحدثوا فرقاً ملموساً في مسيرتهم المهنية.
          </p>
        </motion.div>

        <div className="max-w-3xl">
          <AnimatePresence mode="wait">
            <motion.figure
              key={currentIndex}
              initial={{ opacity: 0, x: -40 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: 40 }}
              transition={{ duration: 0.4 }}
              className="card-tagdar relative p-8 md:p-10"
            >
              <Quote className="absolute top-8 left-8 w-10 h-10 text-primary/15" aria-hidden="true" />

              <blockquote className="text-lg md:text-xl text-navy leading-relaxed">
                «{current.review}»
              </blockquote>

              <figcaption className="mt-8 flex items-center gap-4">
                <img
                  src={imgSrc}
                  alt={`صورة ${current.name}`}
                  loading="lazy"
                  className="w-14 h-14 rounded-full object-cover border border-border"
                  onError={(e) => {
                    (e.target as HTMLImageElement).src = avatarFor(current.name);
                  }}
                />
                <div>
                  <div className="font-bold text-navy">{current.name}</div>
                  <div
                    className="flex items-center gap-0.5 mt-1"
                    aria-label={`التقييم ${current.rating || 5} من 5`}
                  >
                    {Array.from({ length: current.rating || 5 }).map((_, i) => (
                      <Star key={i} className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />
                    ))}
                  </div>
                </div>
              </figcaption>
            </motion.figure>
          </AnimatePresence>

          {/* RTL: "previous" sits on the right, so its chevron points right. */}
          <div className="flex items-center gap-4 mt-8">
            <button
              onClick={prev}
              aria-label="الرأي السابق"
              className="w-11 h-11 rounded-full border border-border bg-card text-navy flex items-center justify-center hover:border-primary hover:text-primary transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
              <ChevronRight className="w-5 h-5" aria-hidden="true" />
            </button>
            <div className="flex gap-2">
              {testimonials.map((_, index) => (
                <button
                  key={index}
                  onClick={() => setCurrentIndex(index)}
                  aria-label={`الانتقال إلى الرأي ${index + 1}`}
                  aria-current={index === safeIndex}
                  className={`h-2.5 rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${
                    index === safeIndex ? "bg-primary w-8" : "bg-border w-2.5 hover:bg-primary/40"
                  }`}
                />
              ))}
            </div>
            <button
              onClick={next}
              aria-label="الرأي التالي"
              className="w-11 h-11 rounded-full border border-border bg-card text-navy flex items-center justify-center hover:border-primary hover:text-primary transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
              <ChevronLeft className="w-5 h-5" aria-hidden="true" />
            </button>
          </div>
        </div>
      </div>
    </section>
  );
};

export default TestimonialsSection;
