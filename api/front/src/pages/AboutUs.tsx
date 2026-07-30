import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import {
  ArrowLeft,
  BookOpen,
  Compass,
  GraduationCap,
  Headphones,
  Loader2,
  ShieldCheck,
  Sparkles,
  Users,
} from "lucide-react";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import { Button } from "@/components/ui/button";
import BrandIcon from "@/components/BrandIcon";
import BrandIllustration from "@/components/BrandIllustration";
import SectionEmpty from "@/components/home/SectionEmpty";
import Seo from "@/components/Seo";
import { useSiteSettings, usePage, useInstructors } from "@/hooks/useApi";

/**
 * The previous owner's corporate history (founding dates, partnerships,
 * conferences, country and graduate counts) lived here and has been removed
 * wholesale rather than restyled. Nothing on this page asserts a verifiable
 * fact about Tagdar; the client's own story arrives through the `about` CMS
 * page below, which is the only block on this page that can carry claims.
 */

// Principles, not achievements — each is a promise about how the platform
// works, so none of them can go stale or turn out to be untrue.
const principles = [
  {
    icon: BookOpen,
    title: "محتوى مُعدّ بعناية",
    description:
      "كل دورة تمرّ بمراجعة قبل نشرها، حتى يصل إليك محتوى مرتّب وواضح يستحق وقتك.",
  },
  {
    icon: Compass,
    title: "مسار واضح المعالم",
    description:
      "تعرف من البداية إلى أين يقودك المسار، وما الذي ستُتقنه في نهايته — بلا غموض.",
  },
  {
    icon: Users,
    title: "خبرة عملية",
    description:
      "نُفسح المجال لمن مارس المهنة فعلاً ليشرح ما تعلّمه منها، لا ما قرأه عنها فقط.",
  },
  {
    icon: ShieldCheck,
    title: "شفافية في التعامل",
    description:
      "أسعار معلنة، ومحتوى موصوف كما هو، وشروط واضحة قبل أن تدفع أي مبلغ.",
  },
  {
    icon: Headphones,
    title: "دعم يرافقك",
    description: "فريق يجيب عن أسئلتك التقنية والتعليمية طوال رحلتك في المنصة.",
  },
  {
    icon: Sparkles,
    title: "تحسين مستمر",
    description:
      "نستمع إلى ملاحظات المتدرّبين ونطوّر المنصة والمحتوى بناءً عليها باستمرار.",
  },
];

// The learner's journey through the product — descriptive, not a claim.
const journey = [
  {
    title: "اختر ما يناسبك",
    description: "تصفّح المجالات والدورات، واقرأ وصف كل مسار ومخرجاته قبل أن تقرر.",
  },
  {
    title: "تعلّم بإيقاعك",
    description: "ادرس وقتما تشاء ومن أي جهاز، وتوقّف وأكمل من حيث انتهيت.",
  },
  {
    title: "طبّق ما تعلّمت",
    description: "تمارين ومهام عملية تحوّل ما شاهدته إلى مهارة تستطيع استخدامها.",
  },
  {
    title: "أنهِ واحصل على شهادتك",
    description: "أكمل متطلبات المسار واحصل على شهادة إتمام تُوثّق ما أنجزته.",
  },
];

const AboutUs = () => {
  const { data: settingsData } = useSiteSettings();
  const { data: pageData, isLoading: pageLoading } = usePage("about");
  const { data: instructorsData } = useInstructors(1, 4);

  const settings = settingsData?.data;
  const aboutPage = pageData?.data;
  const instructors = instructorsData?.data || [];
  const siteName = settings?.site_name || "تقدر";

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title="من نحن"
        description="تعرّف على منصة تقدر التعليمية: كيف نختار المحتوى، وكيف نبني المسارات، وما الذي نلتزم به تجاه كل متدرّب."
        canonical="/about-us"
      />
      <Navbar />

      <main>
        {/* ── Opening teal band, mirroring the landing hero ── */}
        <section className="relative bg-teal overflow-hidden">
          <div
            className="absolute inset-0 dot-pattern opacity-20 pointer-events-none"
            aria-hidden="true"
          />

          <div className="container relative pt-28 pb-24 md:pt-36 md:pb-32">
            <motion.div
              initial={{ opacity: 0, y: 24 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6 }}
              className="max-w-3xl mx-auto text-center"
            >
              <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-sm font-bold text-white">
                <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                من نحن
              </span>

              <h1 className="mt-5 text-4xl md:text-5xl font-black text-white leading-[1.25] text-balance">
                نؤمن أن التعلّم حق،
                <br />
                وأن الوقت أمانة
              </h1>

              <p className="mt-5 text-lg md:text-xl text-white/85 leading-relaxed max-w-2xl mx-auto">
                <span className="font-bold text-white">{siteName}</span> منصة تعليمية
                عربية تجمع المحتوى الجيّد والمدرّب المتمكّن في مكان واحد، وتضعهما بين
                يديك في مسارات مرتّبة تبدأ من حيث أنت.
              </p>
            </motion.div>
          </div>

          <svg
            className="absolute bottom-0 inset-x-0 w-full h-10 md:h-14 text-secondary"
            viewBox="0 0 1440 60"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <path d="M0,60 C360,0 1080,0 1440,60 Z" fill="currentColor" />
          </svg>
        </section>

        {/* ── What the platform is for ── */}
        <section className="py-20 md:py-24">
          <div className="container">
            <div className="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
              >
                <span className="eyebrow">
                  <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                  فكرتنا
                </span>
                <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                  تعليم يبدأ من احتياجك، لا من المنهج
                </h2>
                <p className="mt-5 text-lg text-muted-foreground leading-relaxed">
                  أغلب من يبدأ التعلّم عبر الإنترنت لا يتوقف لأن المحتوى صعب، بل لأنه
                  مبعثر: مقاطع متفرقة، ومسار غير واضح، ولا أحد يجيب عن سؤال عالق.
                </p>
                <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
                  بنينا المنصة على العكس من ذلك تماماً — مسارات مرتّبة من البداية إلى
                  النهاية، ووصف صريح لما ستخرج به من كل دورة، ودعم يرافقك حتى تُنهي ما
                  بدأته.
                </p>

                <div className="mt-8 flex flex-col sm:flex-row gap-3">
                  <Button asChild variant="hero" size="lg" className="group">
                    <Link to="/courses">
                      تصفّح الدورات
                      <ArrowLeft
                        className="w-5 h-5 group-hover:-translate-x-1 transition-transform"
                        aria-hidden="true"
                      />
                    </Link>
                  </Button>
                  <Button asChild variant="outline" size="lg">
                    <Link to="/contact-us">تواصل معنا</Link>
                  </Button>
                </div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: 0.1 }}
                className="card-tagdar p-8 md:p-10 hover:translate-y-0"
              >
                <BrandIllustration name="teaching" className="w-full max-w-md mx-auto" />
              </motion.div>
            </div>
          </div>
        </section>

        {/* ── The client's own story, authored in the admin panel ──
            TODO(client): write Tagdar's real story, background and credentials in
            the "about" page inside the admin panel. This block renders it as-is;
            nothing is hardcoded here so no claim ships without the client. */}
        {pageLoading ? (
          <section className="pb-4">
            <div className="flex justify-center py-10">
              <Loader2
                className="w-8 h-8 text-primary animate-spin"
                aria-label="جارٍ التحميل"
              />
            </div>
          </section>
        ) : aboutPage?.content ? (
          <section className="pb-20 md:pb-24">
            <div className="container max-w-4xl">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
                className="card-tagdar p-7 sm:p-10 hover:shadow-[var(--shadow-sm)] hover:translate-y-0"
              >
                {aboutPage.title && (
                  <h2 className="mb-6 text-2xl md:text-3xl font-black text-navy text-balance">
                    {aboutPage.title}
                  </h2>
                )}
                {/* Body is admin-authored HTML, so its markup cannot be
                    controlled here — the prose overrides keep it legible. */}
                <div
                  className="prose prose-lg max-w-none
                    prose-headings:text-navy prose-headings:font-black prose-headings:text-balance
                    prose-p:text-foreground prose-p:leading-relaxed
                    prose-li:text-foreground prose-li:marker:text-primary
                    prose-strong:text-navy prose-strong:font-bold
                    prose-a:text-primary prose-a:font-medium prose-a:no-underline hover:prose-a:underline
                    prose-blockquote:border-s-4 prose-blockquote:border-e-0 prose-blockquote:border-primary prose-blockquote:text-muted-foreground prose-blockquote:not-italic
                    prose-hr:border-border prose-img:rounded-xl
                    prose-table:text-foreground prose-th:text-navy prose-th:font-bold prose-td:border-border prose-th:border-border"
                  dangerouslySetInnerHTML={{ __html: aboutPage.content }}
                />
              </motion.div>
            </div>
          </section>
        ) : null}

        {/* ── Principles ── */}
        <section className="pb-20 md:pb-24">
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
                ما نلتزم به
              </span>
              <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                مبادئ تحكم كل تفصيلة في المنصة
              </h2>
              <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
                ليست شعارات معلّقة، بل قواعد نراجع عليها كل دورة قبل أن تصل إليك.
              </p>
            </motion.div>

            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
              {principles.map((item, i) => (
                <motion.article
                  key={item.title}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.45, delay: i * 0.06 }}
                  className="card-tagdar p-7"
                >
                  <BrandIcon icon={item.icon} />
                  <h3 className="mt-5 text-lg font-bold text-navy">{item.title}</h3>
                  <p className="mt-2 text-muted-foreground leading-relaxed">
                    {item.description}
                  </p>
                </motion.article>
              ))}
            </div>
          </div>
        </section>

        {/* ── How learning works here ── */}
        <section className="pb-20 md:pb-24">
          <div className="container">
            <div className="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
                className="card-tagdar p-8 md:p-10 order-last lg:order-first hover:translate-y-0"
              >
                <BrandIllustration name="path" className="w-full max-w-md mx-auto" />
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: 0.1 }}
              >
                <span className="eyebrow">
                  <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                  كيف تسير الرحلة
                </span>
                <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                  من أول درس إلى الشهادة
                </h2>

                <ol className="mt-8 space-y-6">
                  {journey.map((step, i) => (
                    <li key={step.title} className="flex gap-4">
                      <span
                        className="flex items-center justify-center shrink-0 w-9 h-9 rounded-xl bg-primary/10 text-primary font-black tabular-nums"
                        aria-hidden="true"
                      >
                        {i + 1}
                      </span>
                      <div>
                        <h3 className="font-bold text-navy">{step.title}</h3>
                        <p className="mt-1 text-muted-foreground leading-relaxed">
                          {step.description}
                        </p>
                      </div>
                    </li>
                  ))}
                </ol>
              </motion.div>
            </div>
          </div>
        </section>

        {/* ── Instructors, straight from the API ── */}
        <section className="pb-20 md:pb-24">
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
                من يقف خلف الدورات
              </span>
              <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                مدرّبون تختارهم المنصة بعناية
              </h2>
              <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
                نراجع خبرة كل مدرّب ومحتواه قبل أن يُنشر على المنصة.
              </p>
            </motion.div>

            {instructors.length === 0 ? (
              <SectionEmpty
                illustration="mentor"
                message="لم يُعلن عن المدرّبين بعد. سنعرضهم هنا فور انضمامهم إلى المنصة."
              />
            ) : (
              <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                {instructors.map((instructor, i) => {
                  const name =
                    instructor.name ||
                    `${instructor.first_name} ${instructor.last_name}`.trim();
                  return (
                    <motion.article
                      key={instructor.id}
                      initial={{ opacity: 0, y: 20 }}
                      whileInView={{ opacity: 1, y: 0 }}
                      viewport={{ once: true }}
                      transition={{ duration: 0.45, delay: i * 0.06 }}
                      className="card-tagdar p-6 text-center"
                    >
                      <div className="mx-auto w-24 h-24 rounded-2xl overflow-hidden bg-secondary">
                        <img
                          src={
                            instructor.image ||
                            `https://ui-avatars.com/api/?name=${encodeURIComponent(
                              name
                            )}&background=00847B&color=fff&size=256`
                          }
                          alt={`صورة المدرّب ${name}`}
                          loading="lazy"
                          className="w-full h-full object-cover"
                        />
                      </div>
                      <h3 className="mt-4 font-bold text-navy">{name}</h3>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {instructor.title || "مدرّب"}
                      </p>
                      <Link
                        to={`/instructors/${instructor.id}`}
                        className="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-primary rounded-md hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      >
                        عرض الملف الشخصي
                        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                      </Link>
                    </motion.article>
                  );
                })}
              </div>
            )}

            <div className="mt-10 flex justify-center">
              <Button asChild variant="outline" size="lg">
                <Link to="/instructors">تصفّح جميع المدرّبين</Link>
              </Button>
            </div>
          </div>
        </section>

        {/* TODO(client): Tagdar leadership section removed with the previous owner's staff data. Re-add once the client supplies real names, roles and photos. */}

        {/* ── Closing navy band, bookending the teal opening ── */}
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
              <GraduationCap
                className="mx-auto w-10 h-10 text-spark"
                strokeWidth={1.75}
                aria-hidden="true"
              />
              <h2 className="mt-5 text-3xl md:text-4xl font-black text-white text-balance leading-[1.3]">
                ابدأ من حيث أنت
              </h2>
              <p className="mt-5 text-lg text-white/75 leading-relaxed">
                أنشئ حسابك، وتصفّح المسارات، واختر ما يقرّبك من هدفك. لا التزام ولا
                بطاقة بنكية للبدء.
              </p>

              <div className="mt-9 flex flex-col sm:flex-row gap-3 justify-center">
                <Button
                  asChild
                  size="xl"
                  className="bg-spark hover:bg-spark-dark text-navy font-bold border-0"
                >
                  <Link to="/register">
                    أنشئ حسابك مجاناً
                    <ArrowLeft className="w-5 h-5" aria-hidden="true" />
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
      </main>

      <Footer />
    </div>
  );
};

export default AboutUs;
