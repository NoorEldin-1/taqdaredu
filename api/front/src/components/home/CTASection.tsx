import { motion } from "framer-motion";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";

/**
 * Navy closing band, bookending the teal band the page opened with. The two
 * bands are the only saturated surfaces on the landing — everything between
 * them is light. That is what gives the page its shape.
 */
const CTASection = () => {
  return (
    <section className="relative bg-navy overflow-hidden">
      <div
        className="absolute inset-0 dot-pattern opacity-[0.12] pointer-events-none"
        aria-hidden="true"
      />

      <div className="container relative py-20 md:py-28">
        <motion.div
          initial={{ opacity: 0, y: 24 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="max-w-2xl mx-auto text-center"
        >
          <h2 className="text-3xl md:text-4xl lg:text-5xl font-black text-white text-balance leading-[1.3]">
            الخطوة الأولى هي الأصعب.
            <br />
            <span className="text-spark">ابدأها اليوم.</span>
          </h2>

          <p className="mt-5 text-lg text-white/75 leading-relaxed">
            أنشئ حسابك مجاناً، وتصفّح المسارات، واختر ما يناسب هدفك. لا التزام،
            ولا بطاقة بنكية — فقط ابدأ.
          </p>

          <div className="mt-9 flex flex-col sm:flex-row gap-3 justify-center">
            <Button asChild size="xl" className="bg-spark hover:bg-spark-dark text-navy font-bold border-0">
              <Link to="/register">
                أنشئ حسابك مجاناً
                <ArrowLeft className="w-5 h-5" />
              </Link>
            </Button>
            <Button
              asChild
              size="xl"
              variant="outline"
              className="bg-white/10 border-white/30 text-white hover:bg-white/20 hover:text-white font-bold"
            >
              <Link to="/contact-us">تحدّث إلى مستشار</Link>
            </Button>
          </div>
        </motion.div>
      </div>
    </section>
  );
};

export default CTASection;
