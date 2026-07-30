import { motion } from "framer-motion";
import { Link } from "react-router-dom";
import {
  Users, CheckCircle2, ArrowLeft, BookOpen, Award, Megaphone, LifeBuoy,
  Briefcase, Mail, Phone, User, FileText, Linkedin, Globe,
} from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIcon from "@/components/BrandIcon";
import BrandIllustration from "@/components/BrandIllustration";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { toast } from "@/hooks/use-toast";
import { useSubmitContact, useCategories } from "@/hooks/useApi";
import Seo from "@/components/Seo";

// Schema unchanged from the previous owner's page — only the messages are
// translated. The backend still receives the same contact payload.
const instructorSchema = z.object({
  fullName: z.string().trim().min(3, { message: "يجب ألا يقل الاسم عن ٣ أحرف" }).max(100),
  email: z.string().trim().email({ message: "يُرجى إدخال بريد إلكتروني صحيح" }),
  phone: z.string().trim().optional().or(z.literal("")),
  linkedin: z.string().trim().url({ message: "يُرجى إدخال رابط لينكدإن صحيح" }).optional().or(z.literal("")),
  expertise: z.string().min(1, { message: "يُرجى اختيار مجال تخصصك" }),
  experience: z.string().min(1, { message: "يُرجى اختيار سنوات خبرتك" }),
  bio: z.string().trim().min(50, { message: "يجب ألا تقل النبذة عن ٥٠ حرفاً" }).max(500),
  courseIdea: z.string().trim().min(20, { message: "يُرجى وصف فكرة دورتك في ٢٠ حرفاً على الأقل" }).max(1000),
  portfolio: z.string().trim().optional(),
  agreeTerms: z.boolean().refine(val => val === true, { message: "يلزم الموافقة على الشروط للمتابعة" }),
});

type InstructorFormValues = z.infer<typeof instructorSchema>;

const scrollToForm = () =>
  document.getElementById("application-form")?.scrollIntoView({ behavior: "smooth" });

const BecomeInstructor = () => {
  const submitContact = useSubmitContact();
  const { data: categoriesData } = useCategories();
  const categories = categoriesData?.data || [];

  const form = useForm<InstructorFormValues>({
    resolver: zodResolver(instructorSchema),
    defaultValues: {
      fullName: "",
      email: "",
      phone: "",
      linkedin: "",
      expertise: "",
      experience: "",
      bio: "",
      courseIdea: "",
      portfolio: "",
      agreeTerms: false,
    },
  });

  const onSubmit = async (data: InstructorFormValues) => {
    // The application rides on the generic contact endpoint, so everything the
    // form collects is flattened into the message body.
    const message = `
طلب انضمام مدرّب

الاسم: ${data.fullName}
البريد الإلكتروني: ${data.email}
رقم الهاتف: ${data.phone}
لينكدإن: ${data.linkedin || "غير مذكور"}
مجال التخصص: ${data.expertise}
سنوات الخبرة: ${data.experience}
الموقع أو معرض الأعمال: ${data.portfolio || "غير مذكور"}

نبذة تعريفية:
${data.bio}

فكرة الدورة:
${data.courseIdea}
    `.trim();

    const nameParts = data.fullName.split(' ');
    const firstName = nameParts[0];
    const lastName = nameParts.slice(1).join(' ');

    submitContact.mutate(
      {
        first_name: firstName,
        last_name: lastName,
        email: data.email,
        phone: data.phone,
        message: message,
        i_agree: data.agreeTerms ? 1 : 0,
      },
      {
        onSuccess: () => {
          /* TODO(client): replace with Tagdar's real review turnaround once the
             instructor onboarding process is defined. Placeholder copy makes no
             factual claim. */
          toast({
            title: "تم إرسال طلبك",
            description: "شكراً لاهتمامك بالتدريس مع تقدر. سيراجع فريقنا طلبك ويتواصل معك على بريدك الإلكتروني.",
          });
          form.reset();
        },
        onError: () => {
          toast({
            title: "تعذّر إرسال الطلب",
            description: "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.",
            variant: "destructive",
          });
        },
      }
    );
  };

  // Expertise options come from the platform's own catalogue rather than a
  // hardcoded list, so they always match what Tagdar actually teaches.
  const expertiseAreas = categories.map((category) => category.name);

  const experienceLevels = [
    "أقل من ٣ سنوات",
    "من ٣ إلى ٥ سنوات",
    "من ٥ إلى ١٠ سنوات",
    "أكثر من ١٠ سنوات",
  ];

  const benefits = [
    {
      icon: Users,
      title: "متدرّبون يبحثون عن خبرتك",
      description: "اعرض معرفتك أمام جمهور جاء إلى المنصة وهو يبحث عن التعلّم، لا عن الإعلان.",
    },
    {
      icon: BookOpen,
      title: "أدوات إنتاج ونشر",
      description: "ارفع دروسك ونظّم وحداتك وأضف اختباراتك من لوحة واحدة، دون خبرة تقنية.",
    },
    {
      icon: LifeBuoy,
      title: "فريق يساندك",
      description: "مرافقة في مراجعة المحتوى وضبط جودته منذ الفكرة الأولى وحتى نشر الدورة.",
    },
    {
      icon: Megaphone,
      title: "حضور مهني يبني اسمك",
      description: "صفحة مدرّب خاصة بك، ومحتوى يظل مرجعاً يعرّف بك في مجالك.",
    },
  ];

  const steps = [
    { step: "١", title: "قدّم طلبك", description: "املأ النموذج وعرّفنا بخبرتك وفكرة دورتك." },
    { step: "٢", title: "مراجعة الطلب", description: "يطّلع فريقنا على مؤهلاتك ويتواصل معك لاستكمال ما يلزم." },
    { step: "٣", title: "التجهيز", description: "نفتح لك أدوات المدرّب ونرافقك في بناء أول وحدة." },
    { step: "٤", title: "النشر", description: "تُنشر دورتك على المنصة وتبدأ باستقبال المتدرّبين." },
  ];

  /* TODO(client): replace with Tagdar's real eligibility criteria for
     instructors. Placeholder copy makes no factual claim. */
  const requirements = [
    "خبرة عملية حقيقية في المجال الذي ستدرّسه",
    "قدرة على شرح الأفكار المعقّدة بلغة واضحة",
    "التزام بجودة المحتوى ودقّته",
    "استعداد للتفاعل مع أسئلة المتدرّبين ومتابعتهم",
  ];

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title="كن مدرّباً"
        description="شارك خبرتك مع متدرّبين يبحثون عنها. قدّم طلب الانضمام إلى فريق المدرّبين في منصة تقدر التعليمية."
        canonical="/become-instructor"
      />
      <Navbar />

      <main>
        {/* ── Opening teal band ── */}
        <section className="relative bg-teal overflow-hidden">
          <div
            className="absolute inset-0 dot-pattern opacity-20 pointer-events-none"
            aria-hidden="true"
          />

          <div className="container relative pt-28 pb-24 md:pt-32 md:pb-32">
            <div className="grid lg:grid-cols-2 gap-12 items-center">
              <motion.div
                initial={{ opacity: 0, y: 24 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6 }}
              >
                <span className="inline-flex items-center gap-2 text-sm font-bold text-white/90">
                  <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                  انضم إلى مدرّبي تقدر
                </span>

                <h1 className="mt-4 text-4xl md:text-5xl font-black text-white leading-[1.25] text-balance">
                  خبرتك تستحق أن
                  <br />
                  <span className="text-spark">تصل إلى غيرك</span>
                </h1>

                <p className="mt-5 text-lg text-white/85 leading-relaxed max-w-xl">
                  حوّل ما أتقنته خلال سنوات عملك إلى دورة منظّمة يستفيد منها متدرّبون
                  يبحثون عن معرفة عملية. نتكفّل بالمنصة والأدوات والمتابعة، وتتكفّل أنت
                  بما تُجيده.
                </p>

                <div className="mt-9">
                  <Button
                    size="xl"
                    className="bg-spark hover:bg-spark-dark text-navy font-bold border-0"
                    onClick={scrollToForm}
                  >
                    ابدأ التدريس معنا
                    <ArrowLeft className="w-5 h-5" />
                  </Button>
                </div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.6, delay: 0.1 }}
                className="flex justify-center"
              >
                <BrandIllustration name="teaching" className="w-80 max-w-full" />
              </motion.div>
            </div>
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

        {/* ── Why teach with Tagdar ── */}
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
                لماذا تدرّس معنا
              </span>
              <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                منصة تتكفّل بالباقي
              </h2>
              <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
                {/* TODO(client): add Tagdar's real revenue-share terms here once
                    agreed. Placeholder copy makes no factual claim. */}
                همّك الوحيد أن تُحسن الشرح؛ أمّا المنصة والتقنية والمتابعة فمسؤوليتنا.
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

        {/* ── How it works ── */}
        <section className="pb-20 md:pb-24">
          <div className="container">
            <div className="grid lg:grid-cols-2 gap-12 items-center">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
                className="order-2 lg:order-1"
              >
                <span className="eyebrow">
                  <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                  الطريق إلى أول دورة
                </span>
                <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                  أربع خطوات واضحة
                </h2>

                <ol className="mt-8 space-y-5">
                  {steps.map((step, i) => (
                    <motion.li
                      key={step.step}
                      initial={{ opacity: 0, y: 16 }}
                      whileInView={{ opacity: 1, y: 0 }}
                      viewport={{ once: true }}
                      transition={{ duration: 0.4, delay: i * 0.08 }}
                      className="flex gap-4"
                    >
                      <span
                        className="flex shrink-0 items-center justify-center w-11 h-11 rounded-2xl bg-primary/10 text-primary font-black text-lg"
                        aria-hidden="true"
                      >
                        {step.step}
                      </span>
                      <div>
                        <h3 className="text-lg font-bold text-navy">{step.title}</h3>
                        <p className="mt-1 text-muted-foreground leading-relaxed">
                          {step.description}
                        </p>
                      </div>
                    </motion.li>
                  ))}
                </ol>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, scale: 0.95 }}
                whileInView={{ opacity: 1, scale: 1 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
                className="order-1 lg:order-2 flex justify-center"
              >
                <BrandIllustration name="path" className="w-72 max-w-full" />
              </motion.div>
            </div>
          </div>
        </section>

        {/* ── Application form ── */}
        <section id="application-form" className="pb-20 md:pb-24">
          <div className="container">
            <div className="grid lg:grid-cols-2 gap-10 items-start">
              {/* Requirements + context */}
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
                className="lg:sticky lg:top-28"
              >
                <span className="eyebrow">
                  <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                  قدّم طلبك
                </span>
                <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                  جاهز لتبدأ رحلتك في التدريس؟
                </h2>
                <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
                  املأ النموذج وسيراجع فريقنا خبرتك وفكرة دورتك. نبحث عن ممارسين شغوفين
                  قادرين على تقديم تعليم يترك أثراً حقيقياً.
                </p>

                <div className="card-tagdar p-7 mt-8">
                  <h3 className="text-lg font-bold text-navy">ما نبحث عنه</h3>
                  <ul className="mt-4 space-y-3">
                    {requirements.map((req) => (
                      <li key={req} className="flex items-start gap-3">
                        <CheckCircle2
                          className="w-5 h-5 shrink-0 mt-0.5 text-primary"
                          aria-hidden="true"
                        />
                        <span className="text-muted-foreground leading-relaxed">{req}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="mt-8 flex justify-center">
                  <BrandIllustration name="mentor" className="w-60 max-w-full" />
                </div>
              </motion.div>

              {/* Form */}
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: 0.1 }}
                className="card-tagdar p-6 md:p-8"
              >
                <div className="flex items-center gap-4 mb-7">
                  <BrandIcon icon={FileText} />
                  <div>
                    <h3 className="text-xl font-bold text-navy">نموذج طلب الانضمام</h3>
                    <p className="text-sm text-muted-foreground">
                      الحقول المعلَّمة بعلامة * إلزامية
                    </p>
                  </div>
                </div>

                <Form {...form}>
                  <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-5">
                    <FormField
                      control={form.control}
                      name="fullName"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-navy font-medium">الاسم الكامل *</FormLabel>
                          <FormControl>
                            <div className="relative">
                              <User
                                className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                              />
                              <Input
                                placeholder="مثال: سارة عبد الله"
                                {...field}
                                className="h-12 rounded-xl ps-11"
                              />
                            </div>
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <div className="grid sm:grid-cols-2 gap-4">
                      <FormField
                        control={form.control}
                        name="email"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-navy font-medium">البريد الإلكتروني *</FormLabel>
                            <FormControl>
                              <div className="relative">
                                <Mail
                                  className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                                  aria-hidden="true"
                                />
                                <Input
                                  type="email"
                                  placeholder="name@example.com"
                                  {...field}
                                  className="h-12 rounded-xl ps-11"
                                  dir="ltr"
                                />
                              </div>
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      <FormField
                        control={form.control}
                        name="phone"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-navy font-medium">رقم الهاتف (اختياري)</FormLabel>
                            <FormControl>
                              <div className="relative">
                                <Phone
                                  className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                                  aria-hidden="true"
                                />
                                <Input
                                  type="tel"
                                  placeholder="05XXXXXXXX"
                                  {...field}
                                  className="h-12 rounded-xl ps-11"
                                  dir="ltr"
                                />
                              </div>
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </div>

                    <FormField
                      control={form.control}
                      name="linkedin"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-navy font-medium">حساب لينكدإن (اختياري)</FormLabel>
                          <FormControl>
                            <div className="relative">
                              <Linkedin
                                className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                              />
                              <Input
                                placeholder="https://linkedin.com/in/username"
                                {...field}
                                className="h-12 rounded-xl ps-11"
                                dir="ltr"
                              />
                            </div>
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <div className="grid sm:grid-cols-2 gap-4">
                      <FormField
                        control={form.control}
                        name="expertise"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-navy font-medium">مجال التخصص *</FormLabel>
                            {/* Falls back to free text when the catalogue is
                                empty or the request failed. A select with no
                                options would make this form impossible to
                                submit, silently losing every applicant. */}
                            {expertiseAreas.length > 0 ? (
                              <Select onValueChange={field.onChange} value={field.value}>
                                <FormControl>
                                  <SelectTrigger className="h-12 rounded-xl">
                                    <Briefcase
                                      className="w-5 h-5 me-2 text-muted-foreground"
                                      aria-hidden="true"
                                    />
                                    <SelectValue placeholder="اختر مجالك" />
                                  </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                  {expertiseAreas.map((area) => (
                                    <SelectItem key={area} value={area}>
                                      {area}
                                    </SelectItem>
                                  ))}
                                </SelectContent>
                              </Select>
                            ) : (
                              <div className="relative">
                                <Briefcase
                                  className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                                  aria-hidden="true"
                                />
                                <FormControl>
                                  <Input
                                    {...field}
                                    className="h-12 rounded-xl ps-11"
                                    placeholder="اكتب مجال تخصصك"
                                  />
                                </FormControl>
                              </div>
                            )}
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      <FormField
                        control={form.control}
                        name="experience"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-navy font-medium">سنوات الخبرة *</FormLabel>
                            <Select onValueChange={field.onChange} value={field.value}>
                              <FormControl>
                                <SelectTrigger className="h-12 rounded-xl">
                                  <Award
                                    className="w-5 h-5 me-2 text-muted-foreground"
                                    aria-hidden="true"
                                  />
                                  <SelectValue placeholder="اختر المدة" />
                                </SelectTrigger>
                              </FormControl>
                              <SelectContent>
                                {experienceLevels.map((level) => (
                                  <SelectItem key={level} value={level}>
                                    {level}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </div>

                    <FormField
                      control={form.control}
                      name="bio"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-navy font-medium">نبذة تعريفية *</FormLabel>
                          <FormControl>
                            <Textarea
                              placeholder="عرّفنا بخلفيتك المهنية وأبرز أعمالك، وما الذي يجعلك مدرّباً مميزاً."
                              className="min-h-[110px] rounded-xl resize-none"
                              {...field}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name="courseIdea"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-navy font-medium">فكرة الدورة *</FormLabel>
                          <FormControl>
                            <Textarea
                              placeholder="اشرح الدورة التي تودّ تقديمها: ما الموضوعات التي ستغطيها؟ ومن هي الفئة المستهدفة؟"
                              className="min-h-[110px] rounded-xl resize-none"
                              {...field}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name="portfolio"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-navy font-medium">
                            الموقع أو معرض الأعمال (اختياري)
                          </FormLabel>
                          <FormControl>
                            <div className="relative">
                              <Globe
                                className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                              />
                              <Input
                                placeholder="https://example.com"
                                {...field}
                                className="h-12 rounded-xl ps-11"
                                dir="ltr"
                              />
                            </div>
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name="agreeTerms"
                      render={({ field }) => (
                        <FormItem className="flex flex-row items-start gap-3 space-y-0">
                          <FormControl>
                            <Checkbox
                              checked={field.value}
                              onCheckedChange={field.onChange}
                              className="mt-0.5"
                            />
                          </FormControl>
                          <div className="space-y-1 leading-none">
                            <FormLabel className="text-sm font-normal text-muted-foreground cursor-pointer leading-relaxed">
                              أوافق على{" "}
                              <Link to="/terms" target="_blank" className="font-medium text-primary hover:underline">
                                الشروط والأحكام
                              </Link>
                              {" "}و{" "}
                              <Link to="/privacy-policy" target="_blank" className="font-medium text-primary hover:underline">
                                سياسة الخصوصية
                              </Link>
                              ، وأقرّ بأن جميع البيانات المذكورة صحيحة.
                            </FormLabel>
                            <FormMessage />
                          </div>
                        </FormItem>
                      )}
                    />

                    <Button
                      type="submit"
                      size="lg"
                      className="w-full"
                      disabled={submitContact.isPending}
                    >
                      {submitContact.isPending ? "جارٍ الإرسال…" : "أرسل الطلب"}
                      {!submitContact.isPending && <ArrowLeft className="w-5 h-5" />}
                    </Button>
                  </form>
                </Form>
              </motion.div>
            </div>
          </div>
        </section>

        {/* ── Closing navy band ── */}
        <section className="relative bg-navy overflow-hidden">
          <div
            className="absolute inset-0 dot-pattern opacity-[0.12] pointer-events-none"
            aria-hidden="true"
          />

          <div className="container relative py-20 md:py-24">
            <motion.div
              initial={{ opacity: 0, y: 24 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6 }}
              className="max-w-2xl mx-auto text-center"
            >
              <h2 className="text-3xl md:text-4xl font-black text-white text-balance leading-[1.3]">
                معرفتك لن تنفع أحداً وهي
                <br />
                <span className="text-spark">حبيسة رأسك.</span>
              </h2>

              <p className="mt-5 text-lg text-white/75 leading-relaxed">
                خطوة واحدة تفصلك عن أول درس تشاركه. قدّم طلبك اليوم وابدأ بناء أثرك
                التعليمي.
              </p>

              <div className="mt-9 flex justify-center">
                <Button
                  size="xl"
                  className="bg-spark hover:bg-spark-dark text-navy font-bold border-0"
                  onClick={scrollToForm}
                >
                  قدّم طلبك الآن
                  <ArrowLeft className="w-5 h-5" />
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

export default BecomeInstructor;
