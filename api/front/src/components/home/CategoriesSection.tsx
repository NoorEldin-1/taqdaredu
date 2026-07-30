import { motion } from "framer-motion";
import { ArrowLeft, Radio, Monitor, Network, Briefcase, Cpu, Folder } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { Link } from "react-router-dom";
import { useCategories } from "@/hooks/useApi";
import { Skeleton } from "@/components/ui/skeleton";
import BrandIcon from "@/components/BrandIcon";
import SectionEmpty from "./SectionEmpty";

// Keyed on the English category slugs the API returns — do not translate these keys.
const iconMap: Record<string, LucideIcon> = {
  radio: Radio,
  monitor: Monitor,
  network: Network,
  briefcase: Briefcase,
  cpu: Cpu,
  folder: Folder,
};

const CategoriesSection = () => {
  const { data: categoriesData, isLoading } = useCategories();
  const rawCategories = categoriesData?.data || [];
  // Surface the real sub-categories as separate cards rather than the single
  // generic parent, which carries no useful signal on its own.
  const categories = rawCategories.flatMap((c: { id: number; name: string; course_count?: number; sub_categories?: { id: number; name: string; course_count?: number }[] }) =>
    Array.isArray(c.sub_categories) && c.sub_categories.length > 0 ? c.sub_categories : [{ id: c.id, name: c.name, course_count: c.course_count }]
  );

  if (isLoading) {
    return (
      <section className="py-20 md:py-24">
        <div className="container">
          <div className="max-w-2xl mb-12">
            <Skeleton className="h-5 w-32 mb-4" />
            <Skeleton className="h-10 w-80 mb-4" />
            <Skeleton className="h-6 w-full max-w-md" />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 max-w-5xl">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <Skeleton key={i} className="h-44 rounded-2xl" />
            ))}
          </div>
        </div>
      </section>
    );
  }

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
            مجالات التعلّم
          </span>
          <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
            ابدأ من المجال الذي يخصّك
          </h2>
          <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
            اختر المجال الأقرب إلى عملك أو طموحك، وستجد داخله مسارات متدرّجة تبدأ من
            الأساسيات وتصل بك إلى مستوى الاحتراف.
          </p>
        </motion.div>

        {categories.length === 0 ? (
          <SectionEmpty
            illustration="path"
            message="لم تُضف مجالات بعد. سنعلن عن أولى المسارات قريباً."
          />
        ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {categories.slice(0, 6).map((category, index) => {
            const IconComponent = iconMap[(category.name || '').toLowerCase().split(' ')[0]] || Folder;

            return (
              <motion.div
                key={category.id}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.45, delay: index * 0.08 }}
                className="h-full"
              >
                <Link
                  to={`/courses?category=${category.id}`}
                  className="card-tagdar group flex h-full flex-col p-7 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                  <BrandIcon icon={IconComponent} />

                  <h3 className="mt-5 text-lg font-bold text-navy group-hover:text-primary transition-colors">
                    {category.name}
                  </h3>

                  {category.sub_categories && category.sub_categories.length > 0 && (
                    <p className="mt-2 text-sm text-muted-foreground">
                      {category.sub_categories.slice(0, 2).map(sub => sub.name).join('، ')}
                    </p>
                  )}

                  <div className="mt-auto pt-5 flex items-center justify-between">
                    <span className="text-sm font-bold text-primary">
                      {category.course_count || 0} دورة
                    </span>
                    <ArrowLeft
                      className="w-4 h-4 text-muted-foreground group-hover:text-primary group-hover:-translate-x-1 transition-all"
                      aria-hidden="true"
                    />
                  </div>
                </Link>
              </motion.div>
            );
          })}
        </div>
        )}
      </div>
    </section>
  );
};

export default CategoriesSection;
