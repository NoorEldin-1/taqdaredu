import { motion } from "framer-motion";
import { Mail, Phone, MapPin, Clock, Send } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import BrandIcon from "@/components/BrandIcon";
import BrandIllustration from "@/components/BrandIllustration";
import Seo from "@/components/Seo";
import { toast } from "@/hooks/use-toast";
import { useSubmitContact, useSiteSettings } from "@/hooks/useApi";

const contactSchema = z.object({
  firstName: z
    .string()
    .trim()
    .min(2, { message: "الاسم الأول يجب ألا يقل عن حرفين" })
    .max(50, { message: "الاسم الأول يجب ألا يزيد عن 50 حرفاً" }),
  lastName: z
    .string()
    .trim()
    .max(50, { message: "اسم العائلة يجب ألا يزيد عن 50 حرفاً" })
    .optional(),
  email: z
    .string()
    .trim()
    .email({ message: "يُرجى إدخال بريد إلكتروني صحيح" })
    .max(255, { message: "البريد الإلكتروني يجب ألا يزيد عن 255 حرفاً" }),
  phone: z.string().trim().optional(),
  subject: z.string().min(1, { message: "يُرجى اختيار موضوع الرسالة" }),
  message: z
    .string()
    .trim()
    .min(10, { message: "الرسالة يجب ألا تقل عن 10 أحرف" })
    .max(1000, { message: "الرسالة يجب ألا تزيد عن 1000 حرف" }),
  consent: z.literal(true, {
    errorMap: () => ({ message: "يُرجى الموافقة على حفظ بياناتك حتى نتمكن من الرد عليك" }),
  }),
});

type ContactFormValues = z.infer<typeof contactSchema>;

const subjects = [
  "استفسار عن دورة",
  "دعم تقني",
  "شراكة",
  "الفواتير والمدفوعات",
  "استفسار عام",
];

const ContactUs = () => {
  const submitContact = useSubmitContact();
  const { data: settingsData } = useSiteSettings();
  const settings = settingsData?.data;

  const form = useForm<ContactFormValues>({
    resolver: zodResolver(contactSchema),
    defaultValues: {
      firstName: "",
      lastName: "",
      email: "",
      phone: "",
      subject: "",
      message: "",
      consent: false as unknown as true,
    },
  });

  const onSubmit = async (data: ContactFormValues) => {
    submitContact.mutate(
      {
        first_name: data.firstName,
        last_name: data.lastName,
        email: data.email,
        phone: data.phone,
        message: `[${data.subject}] ${data.message}`,
        i_agree: data.consent ? 1 : 0,
      },
      {
        onSuccess: () => {
          toast({
            title: "تم إرسال رسالتك",
            description: "شكراً لتواصلك معنا. سنعود إليك في أقرب وقت ممكن.",
          });
          form.reset();
        },
        onError: () => {
          toast({
            title: "تعذّر الإرسال",
            description: "لم نتمكن من إرسال رسالتك. يُرجى المحاولة مرة أخرى.",
            variant: "destructive",
          });
        },
      }
    );
  };

  // TODO(client): every row below falls back to a neutral placeholder until the
  // real values arrive from site settings. Do not hardcode contact details here
  // — the previous owner's email, phone and address used to live in these
  // fallbacks and shipped to visitors whenever a setting was blank.
  const PENDING = "بانتظار بيانات العميل";

  const contactInfo = [
    {
      icon: Mail,
      title: "راسلنا",
      details: [settings?.email || PENDING],
    },
    {
      icon: Phone,
      title: "اتصل بنا",
      details: [settings?.phone || PENDING],
    },
    {
      icon: MapPin,
      title: "مقرّنا",
      details: [settings?.address || PENDING],
    },
    {
      // Site settings has no office-hours field, so this stays a placeholder
      // rather than a made-up schedule.
      icon: Clock,
      title: "مواعيد العمل",
      details: [PENDING],
    },
  ];

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title="تواصل معنا"
        description="لديك سؤال عن دورة أو تحتاج مساعدة في التسجيل؟ راسل فريق منصة تقدر وسنعود إليك في أقرب وقت."
        canonical="/contact-us"
      />
      <Navbar />

      <main>
        {/* ── Teal band, matching the landing hero. Replaced the previous
            owner's photographic hero background. ── */}
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
                تواصل معنا
              </span>

              <h1 className="mt-5 text-4xl md:text-5xl font-black text-white leading-[1.25] text-balance">
                سؤال واحد قد يوفّر عليك أسابيع
              </h1>

              <p className="mt-5 text-lg md:text-xl text-white/85 leading-relaxed max-w-2xl mx-auto">
                سواء كنت تبحث عن الدورة المناسبة، أو تحتاج مساعدة تقنية، أو لديك اقتراح
                — اكتب لنا وسنعود إليك.
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

        <section className="py-20 md:py-24">
          <div className="container">
            <div className="grid lg:grid-cols-2 gap-12 lg:gap-16">
              {/* ── Contact details ── */}
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
              >
                <span className="eyebrow">
                  <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                  بيانات التواصل
                </span>
                <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                  نحن على بُعد رسالة واحدة
                </h2>
                <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
                  اختر الوسيلة الأنسب لك، أو استخدم النموذج المجاور وسنتولّى الباقي.
                </p>

                <div className="mt-8 space-y-4">
                  {contactInfo.map((item, index) => (
                    <motion.div
                      key={item.title}
                      initial={{ opacity: 0, y: 20 }}
                      whileInView={{ opacity: 1, y: 0 }}
                      viewport={{ once: true }}
                      transition={{ duration: 0.45, delay: index * 0.06 }}
                      className="card-tagdar flex gap-4 p-5"
                    >
                      <BrandIcon icon={item.icon} size="sm" />
                      <div>
                        <h3 className="font-bold text-navy">{item.title}</h3>
                        {item.details.map((detail, i) => (
                          <p key={i} className="mt-1 text-sm text-muted-foreground">
                            {detail}
                          </p>
                        ))}
                      </div>
                    </motion.div>
                  ))}
                </div>

                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.5, delay: 0.25 }}
                  className="card-tagdar mt-6 p-8 hover:translate-y-0"
                >
                  <BrandIllustration name="contact" className="mx-auto w-full max-w-xs" />
                  <p className="mt-6 text-center text-muted-foreground leading-relaxed">
                    نقرأ كل رسالة تصلنا، ونحرص على أن يصلك رد واضح من شخص حقيقي.
                  </p>
                </motion.div>
              </motion.div>

              {/* ── Form ── */}
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: 0.1 }}
              >
                <div className="card-tagdar p-7 sm:p-9 hover:translate-y-0">
                  <h2 className="text-2xl font-black text-navy">أرسل لنا رسالة</h2>
                  <p className="mt-2 text-muted-foreground">
                    املأ الحقول التالية وسنتواصل معك عبر البريد الإلكتروني.
                  </p>

                  <Form {...form}>
                    <form onSubmit={form.handleSubmit(onSubmit)} className="mt-7 space-y-5">
                      <div className="grid sm:grid-cols-2 gap-4">
                        <FormField
                          control={form.control}
                          name="firstName"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel className="text-navy font-medium">الاسم الأول</FormLabel>
                              <FormControl>
                                <Input
                                  placeholder="مثال: أحمد"
                                  className="h-12 rounded-xl"
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                        <FormField
                          control={form.control}
                          name="lastName"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel className="text-navy font-medium">
                                اسم العائلة (اختياري)
                              </FormLabel>
                              <FormControl>
                                <Input
                                  placeholder="مثال: الأنصاري"
                                  className="h-12 rounded-xl"
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </div>

                      <div className="grid sm:grid-cols-2 gap-4">
                        <FormField
                          control={form.control}
                          name="email"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel className="text-navy font-medium">
                                البريد الإلكتروني
                              </FormLabel>
                              <FormControl>
                                <Input
                                  type="email"
                                  placeholder="name@example.com"
                                  className="h-12 rounded-xl"
                                  dir="ltr"
                                  {...field}
                                />
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
                              <FormLabel className="text-navy font-medium">
                                رقم الهاتف (اختياري)
                              </FormLabel>
                              <FormControl>
                                <Input
                                  type="tel"
                                  className="h-12 rounded-xl"
                                  dir="ltr"
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </div>

                      <FormField
                        control={form.control}
                        name="subject"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-navy font-medium">موضوع الرسالة</FormLabel>
                            <Select onValueChange={field.onChange} defaultValue={field.value}>
                              <FormControl>
                                <SelectTrigger className="h-12 rounded-xl">
                                  <SelectValue placeholder="اختر موضوعاً" />
                                </SelectTrigger>
                              </FormControl>
                              <SelectContent>
                                {subjects.map((subject) => (
                                  <SelectItem key={subject} value={subject}>
                                    {subject}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                            <FormMessage />
                          </FormItem>
                        )}
                      />

                      <FormField
                        control={form.control}
                        name="message"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-navy font-medium">رسالتك</FormLabel>
                            <FormControl>
                              <Textarea
                                placeholder="اكتب تفاصيل استفسارك هنا…"
                                className="min-h-[150px] resize-none rounded-xl"
                                {...field}
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />

                      <FormField
                        control={form.control}
                        name="consent"
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
                              <FormLabel className="text-sm font-normal text-muted-foreground cursor-pointer">
                                أوافق على حفظ بياناتي للرد على استفساري.
                              </FormLabel>
                              <FormMessage />
                            </div>
                          </FormItem>
                        )}
                      />

                      <Button
                        type="submit"
                        variant="hero"
                        size="lg"
                        className="w-full"
                        disabled={submitContact.isPending}
                      >
                        <Send className="w-4 h-4" aria-hidden="true" />
                        {submitContact.isPending ? "جارٍ الإرسال…" : "إرسال الرسالة"}
                      </Button>
                    </form>
                  </Form>
                </div>
              </motion.div>
            </div>
          </div>
        </section>
      </main>

      <Footer />
    </div>
  );
};

export default ContactUs;
