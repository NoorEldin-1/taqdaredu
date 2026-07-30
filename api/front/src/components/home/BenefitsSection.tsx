import { motion } from "framer-motion";
import { BookOpen, Users, Award, Infinity as InfinityIcon } from "lucide-react";
import BrandIcon from "@/components/BrandIcon";

const benefits = [
  {
    icon: InfinityIcon,
    title: "وصول مدى الحياة",
    description: "بمجرد اشتراكك في الدورة تبقى معك للأبد — راجعها وقتما تشاء، بلا تاريخ انتهاء.",
  },
  {
    icon: BookOpen,
    title: "تعلّم بإيقاعك",
    description: "مسارات مرنة صُممت لمن يوازن بين العمل والدراسة. تتوقف وتُكمل من حيث انتهيت.",
  },
  {
    icon: Users,
    title: "مدرّبون من الميدان",
    description: "نخبة من الممارسين الذين يشرحون ما جرّبوه فعلاً، لا ما قرأوه في الكتب فقط.",
  },
  {
    icon: Award,
    title: "شهادات معتمدة",
    description: "أنهِ مسارك واحصل على شهادة موثّقة تضيف وزناً حقيقياً إلى سيرتك المهنية.",
  },
];

const BenefitsSection = () => {
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
            لماذا تقدر
          </span>
          <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
            تعليم يُحترم فيه وقتك
          </h2>
          <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
            بنينا المنصة حول سؤال واحد: ما الذي يجعل متدرّباً مشغولاً يُكمل ما بدأه؟
          </p>
        </motion.div>

        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
          {benefits.map((benefit, i) => (
            <motion.article
              key={benefit.title}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.45, delay: i * 0.08 }}
              className="card-tagdar p-7"
            >
              <BrandIcon icon={benefit.icon} />
              <h3 className="mt-5 text-lg font-bold text-navy">{benefit.title}</h3>
              <p className="mt-2 text-muted-foreground leading-relaxed">
                {benefit.description}
              </p>
            </motion.article>
          ))}
        </div>
      </div>
    </section>
  );
};

export default BenefitsSection;
