import { useState, useEffect } from "react";
import { useParams, Link, useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import {
  Star, Clock, Users, BookOpen, Play, CheckCircle,
  Award, Globe, Download, Share2, Heart,
  Monitor, FileText, Trophy, ArrowRight, Loader2, Bell
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Progress } from "@/components/ui/progress";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIllustration from "@/components/BrandIllustration";
import { useCourse, useCourseReviews, useToggleWishlist, useAddToCart, useEnrollFree } from "@/hooks/useApi";
import { useAuth } from "@/contexts/AuthContext";
import { useToast } from "@/hooks/use-toast";
import { API_BASE_URL } from "@/lib/api/config";
import { addToGuestCart } from "@/lib/guestCart";
import Seo from "@/components/Seo";
import { Input } from "@/components/ui/input";
import PreviewVideoModal from "@/components/course/PreviewVideoModal";
import { slugify, courseUrl } from "@/lib/slugify";
import { useCurrency } from "@/lib/currency";

// The API stores levels as English slugs; translate for display only and fall
// back to the raw value so an unexpected level still renders.
const levelLabels: Record<string, string> = {
  beginner: "مبتدئ",
  intermediate: "متوسط",
  advanced: "متقدّم",
  expert: "احترافي",
};

const CourseDetails = () => {
  const { id } = useParams();
  // The route param may be a slug like "3-wireless-course-end-to-end" (canonical/SEO URL).
  // parseInt grabs the leading numeric id; Number() would yield NaN for slug URLs → "Course not found".
  const courseId = parseInt(String(id), 10);
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { toast } = useToast();
  const { format } = useCurrency();
  const [isWishlisted, setIsWishlisted] = useState(false);
  const [preview, setPreview] = useState<{ url: string; type?: string | null; title: string } | null>(null);
  const [notifyEmail, setNotifyEmail] = useState("");
  const [notifyDone, setNotifyDone] = useState(false);
  const [notifyLoading, setNotifyLoading] = useState(false);

  const { data: courseData, isLoading } = useCourse(courseId);
  const { data: reviewsData } = useCourseReviews(courseId);
  const toggleWishlist = useToggleWishlist();
  const addToCart = useAddToCart();
  const enrollFree = useEnrollFree();

  const course = courseData?.data;
  const reviews = reviewsData?.data?.reviews || [];
  const reviewStats = reviewsData?.data?.stats;

  // Keep the address bar on the canonical /courses/<id>-<slug> URL. If the user
  // arrived via an id-only link (e.g. /courses/36) or a stale slug, replace it
  // with the current slug URL so the course name shows and it matches the
  // indexable canonical (Seo.php course_detail + <Seo canonical>).
  useEffect(() => {
    if (!isNaN(courseId) && course?.title) {
      const canonical = courseUrl(courseId, course.title);
      if (window.location.pathname !== canonical) {
        navigate(canonical, { replace: true });
      }
    }
  }, [courseId, course?.title, navigate]);

  const handleWishlist = async () => {
    if (!isAuthenticated) {
      toast({ title: "سجّل دخولك لإضافة الدورة إلى المفضلة", variant: "destructive" });
      return;
    }
    await toggleWishlist.mutateAsync(courseId);
    setIsWishlisted(!isWishlisted);
  };

  const handleAddToCart = async () => {
    if (!isAuthenticated) {
      addToGuestCart(courseId);
      toast({ title: "أُضيفت إلى السلة", description: "سجّل دخولك عند الدفع لإتمام عملية الشراء." });
      return;
    }
    try {
      const res = await addToCart.mutateAsync(courseId);
      if (res?.status === false) {
        // Backend returned a handled conflict (already in cart / already enrolled)
        toast({ title: res.message || "الدورة موجودة في سلتك بالفعل", variant: "default" });
      } else {
        toast({ title: "أُضيفت إلى السلة" });
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
        || (err as { data?: { message?: string } })?.data?.message
        || "هذه الدورة موجودة في سلتك بالفعل.";
      toast({ title: msg });
    }
  };

  const handleEnroll = async () => {
    if (!isAuthenticated) {
      toast({ title: "سجّل دخولك للالتحاق بالدورة", variant: "destructive" });
      return;
    }
    if (course?.is_free) {
      await enrollFree.mutateAsync(courseId);
      toast({ title: "تم تسجيلك في الدورة بنجاح" });
    }
  };

  const handleBuyNow = async () => {
    if (!isAuthenticated) {
      addToGuestCart(courseId);
      navigate('/cart');
      return;
    }
    try {
      await addToCart.mutateAsync(courseId);
    } catch {
      // Already in cart (409) or similar — proceed to cart anyway so the user can check out.
    }
    navigate('/cart');
  };

  const handleNotify = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!notifyEmail) return;
    setNotifyLoading(true);
    try {
      const res = await fetch(`${API_BASE_URL}/api/api_frontend/notify_me`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ course_id: String(id), email: notifyEmail }),
      });
      const data = await res.json();
      if (data.status) {
        setNotifyDone(true);
        toast({ title: "أضفناك إلى القائمة", description: data.message });
      } else {
        toast({ title: "تعذّر إتمام الطلب", description: data.message || "يُرجى المحاولة مرة أخرى", variant: "destructive" });
      }
    } catch {
      toast({ title: "تعذّر إتمام الطلب", description: "لم يُرسل طلبك. يُرجى المحاولة مرة أخرى.", variant: "destructive" });
    } finally {
      setNotifyLoading(false);
    }
  };

  const handleShare = async () => {
    const shareData = {
      title: course?.title || 'دورة تدريبية',
      text: course?.short_description || 'ألقِ نظرة على هذه الدورة',
      url: window.location.href,
    };
    try {
      if (navigator.share) {
        await navigator.share(shareData);
      } else {
        await navigator.clipboard.writeText(window.location.href);
        toast({ title: "نُسخ الرابط" });
      }
    } catch {
      await navigator.clipboard.writeText(window.location.href);
      toast({ title: "نُسخ الرابط" });
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-secondary flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-primary animate-spin" />
        <span className="sr-only">جارٍ تحميل الدورة</span>
      </div>
    );
  }

  if (!course) {
    return (
      <div className="min-h-screen bg-secondary">
        <Navbar />
        <main className="container pt-40 pb-24">
          <div className="card-tagdar mx-auto flex max-w-xl flex-col items-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
            <BrandIllustration name="notFound" className="w-64 max-w-full" />
            <div>
              <h1 className="text-2xl font-bold text-navy">لم نعثر على هذه الدورة</h1>
              <p className="mt-2 text-muted-foreground leading-relaxed">
                ربما حُذفت الدورة أو تغيّر رابطها. تصفّح باقي المسارات المتاحة.
              </p>
            </div>
            <Button asChild variant="hero" size="lg">
              <Link to="/courses">تصفّح الدورات</Link>
            </Button>
          </div>
        </main>
        <Footer />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title={course.title}
        description={course.short_description}
        canonical={`/courses/${courseId}-${slugify(course.title)}`}
        image={course.thumbnail || course.image_url}
      />
      <Navbar />

      <main>
        {/* Header band — the brand's teal motif carries the course identity;
            the purchase card below overlaps its lower edge. */}
        <section className="relative bg-teal overflow-hidden">
          <div className="absolute inset-0 dot-pattern opacity-20 pointer-events-none" aria-hidden="true" />

          <div className="container relative pt-28 pb-12">
            {/* Breadcrumb */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              className="mb-6 flex flex-wrap items-center gap-3 text-sm text-white/75"
            >
              <Link to="/courses" className="flex items-center gap-1.5 transition-colors hover:text-white">
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
                العودة إلى الدورات
              </Link>
              {course.category?.name && (
                <span className="rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white">
                  {course.category.name}
                </span>
              )}
            </motion.div>

            <div className="grid lg:grid-cols-3 gap-10">
              {/* Left content */}
              <motion.div
                initial={{ opacity: 0, y: 24 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6 }}
                className="lg:col-span-2"
              >
                <h1 className="text-3xl md:text-4xl font-black text-white leading-[1.35] text-balance">
                  {course.title}
                </h1>
                {course.short_description && (
                  <p className="mt-4 text-lg text-white/85 leading-relaxed">
                    {course.short_description}
                  </p>
                )}

                {/* Stats — each hidden when it has no meaningful value */}
                <div className="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3">
                  {Number(course.rating) > 0 && (
                    <div className="flex items-center gap-1.5">
                      <Star className="w-5 h-5 fill-spark text-spark" aria-hidden="true" />
                      <span className="font-bold text-white tabular-nums">{course.rating}</span>
                      <span className="text-white/75">
                        ({(course.total_ratings || 0).toLocaleString()} تقييماً)
                      </span>
                    </div>
                  )}
                  {Number(course.total_students) > 0 && (
                    <div className="flex items-center gap-1.5 text-white/75">
                      <Users className="w-5 h-5" aria-hidden="true" />
                      <span>{Number(course.total_students).toLocaleString()} متدرّب</span>
                    </div>
                  )}
                  {course.duration && (
                    <div className="flex items-center gap-1.5 text-white/75">
                      <Clock className="w-5 h-5" aria-hidden="true" />
                      <span>{course.duration}</span>
                    </div>
                  )}
                  {Number(course.total_lessons) > 0 && (
                    <div className="flex items-center gap-1.5 text-white/75">
                      <BookOpen className="w-5 h-5" aria-hidden="true" />
                      <span>{course.total_lessons} درساً</span>
                    </div>
                  )}
                </div>

                {/* Instructor mini */}
                {course.instructor && (
                  <Link
                    to={`/instructors/${course.instructor.id}`}
                    className="mt-6 inline-flex items-center gap-3 transition-opacity hover:opacity-80"
                  >
                    <img
                      src={course.instructor.image || `https://ui-avatars.com/api/?name=${course.instructor.first_name}&background=00847B&color=fff`}
                      alt={`${course.instructor.first_name} ${course.instructor.last_name}`}
                      className="w-10 h-10 rounded-full border-2 border-white/40 object-cover"
                    />
                    <span className="block">
                      <span className="block text-sm text-white/75">إعداد</span>
                      <span className="block font-bold text-white">
                        {course.instructor.first_name} {course.instructor.last_name}
                      </span>
                    </span>
                  </Link>
                )}

                {/* Meta info */}
                <div className="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-white/75">
                  <div className="flex items-center gap-1.5">
                    <Globe className="w-4 h-4" aria-hidden="true" />
                    <span>{course.language || "العربية"}</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Award className="w-4 h-4" aria-hidden="true" />
                    <span>
                      {course.level ? (levelLabels[String(course.level).toLowerCase()] || course.level) : "كل المستويات"}
                    </span>
                  </div>
                  {course.updated_at && (
                    <div className="flex items-center gap-1.5">
                      <Clock className="w-4 h-4" aria-hidden="true" />
                      <span>آخر تحديث {new Date(course.updated_at).toLocaleDateString("ar-EG")}</span>
                    </div>
                  )}
                </div>
              </motion.div>

              {/* Right — purchase card */}
              <motion.div
                initial={{ opacity: 0, y: 24 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.15 }}
                className="lg:sticky lg:top-28 lg:self-start"
              >
                <div className="card-tagdar overflow-hidden hover:shadow-none hover:translate-y-0">
                  {/* Video preview */}
                  <div className="relative aspect-video bg-secondary">
                    <img
                      src={course.thumbnail || course.image_url || "https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&h=450&fit=crop"}
                      alt={course.title}
                      className="w-full h-full object-cover"
                    />
                    <div className="absolute inset-0 flex items-center justify-center bg-navy/40">
                      <motion.button
                        whileHover={course.video_url ? { scale: 1.08 } : undefined}
                        whileTap={course.video_url ? { scale: 0.95 } : undefined}
                        onClick={() => {
                          if (course.video_url) {
                            setPreview({
                              url: course.video_url,
                              type: course.video_type,
                              title: course.title,
                            });
                          }
                        }}
                        disabled={!course.video_url}
                        aria-label="تشغيل مقطع المعاينة"
                        className="flex h-16 w-16 items-center justify-center rounded-full bg-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        <Play className="w-6 h-6 fill-white text-white" aria-hidden="true" />
                      </motion.button>
                    </div>
                    <span className="absolute bottom-3 start-3 rounded-full bg-navy/80 px-3 py-1 text-sm text-white">
                      معاينة الدورة
                    </span>
                  </div>

                  <div className="p-6">
                    {course.status === "upcoming" ? (
                      <>
                        {/* Coming soon */}
                        <span className="inline-block rounded-full bg-spark/15 px-3 py-1.5 text-sm font-bold text-navy">
                          قريباً
                        </span>
                        <p className="mt-4 text-sm text-muted-foreground leading-relaxed">
                          هذه الدورة على وشك الانطلاق. اترك بريدك الإلكتروني ونُعلمك فور فتح باب التسجيل.
                        </p>
                        {notifyDone ? (
                          <div className="mt-4 flex items-center gap-2 rounded-xl bg-primary/10 p-4 text-sm font-medium text-primary">
                            <CheckCircle className="w-5 h-5" aria-hidden="true" />
                            سنُعلمك فور إطلاق الدورة.
                          </div>
                        ) : (
                          <form onSubmit={handleNotify} className="mt-4 space-y-3">
                            <Input
                              type="email"
                              required
                              placeholder="أدخل بريدك الإلكتروني"
                              aria-label="البريد الإلكتروني للإشعار بإطلاق الدورة"
                              value={notifyEmail}
                              onChange={(e) => setNotifyEmail(e.target.value)}
                              className="h-12 rounded-xl"
                              dir="ltr"
                            />
                            <Button type="submit" variant="hero" size="lg" className="w-full" disabled={notifyLoading}>
                              <Bell className="w-4 h-4" aria-hidden="true" />
                              {notifyLoading ? "جارٍ الإرسال…" : "أعلمني عند الإطلاق"}
                            </Button>
                          </form>
                        )}
                      </>
                    ) : (
                      <>
                        {/* Price */}
                        <div className="mb-5 flex flex-wrap items-center gap-3">
                          {course.is_free ? (
                            <span className="text-3xl font-black text-primary">مجاناً</span>
                          ) : (
                            <>
                              <span className="text-3xl font-black text-navy tabular-nums">
                                {format(course.discounted_price || course.price)}
                              </span>
                              {course.discounted_price && course.discounted_price < course.price && (
                                <>
                                  <span className="text-lg text-muted-foreground line-through tabular-nums">
                                    {format(course.price)}
                                  </span>
                                  <span className="rounded-lg bg-spark/15 px-2 py-1 text-sm font-bold text-navy tabular-nums">
                                    خصم {Math.round((1 - (course.discounted_price / course.price)) * 100)}%
                                  </span>
                                </>
                              )}
                            </>
                          )}
                        </div>

                        {/* CTA buttons */}
                        <div className="space-y-3">
                          {course.is_enrolled ? (
                            <Button asChild variant="hero" size="lg" className="w-full">
                              <Link to={`/courses/${course.id}/watch`}>متابعة التعلّم</Link>
                            </Button>
                          ) : course.is_free ? (
                            <Button variant="hero" size="lg" className="w-full" onClick={handleEnroll} disabled={enrollFree.isPending}>
                              {enrollFree.isPending ? "جارٍ التسجيل…" : "التحق الآن مجاناً"}
                            </Button>
                          ) : (
                            <>
                              <Button variant="hero" size="lg" className="w-full" onClick={handleAddToCart} disabled={addToCart.isPending}>
                                {addToCart.isPending ? "جارٍ الإضافة…" : "أضف إلى السلة"}
                              </Button>
                              <Button variant="outline" size="lg" className="w-full" onClick={handleBuyNow} disabled={addToCart.isPending}>
                                {addToCart.isPending ? "جارٍ المعالجة…" : "اشترِ الآن"}
                              </Button>
                            </>
                          )}
                        </div>

                        {/* Guarantee */}
                        <p className="mt-4 text-center text-sm text-muted-foreground">
                          ضمان استرداد المبلغ خلال 30 يوماً
                        </p>
                      </>
                    )}

                    {/* Quick features */}
                    <div className="mt-6 space-y-3">
                      <h2 className="text-base font-bold text-navy">تشمل هذه الدورة:</h2>
                      <div className="space-y-2 text-sm text-muted-foreground">
                        <div className="flex items-center gap-2">
                          <Monitor className="w-4 h-4 text-primary" aria-hidden="true" />
                          <span>مقاطع فيديو عند الطلب{course.duration ? ` (${course.duration})` : ""}</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <FileText className="w-4 h-4 text-primary" aria-hidden="true" />
                          <span>مرفقات قابلة للتنزيل</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Download className="w-4 h-4 text-primary" aria-hidden="true" />
                          <span>وصول مدى الحياة</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Trophy className="w-4 h-4 text-primary" aria-hidden="true" />
                          <span>شهادة إتمام</span>
                        </div>
                      </div>
                    </div>

                    {/* Share & wishlist */}
                    <div className="mt-6 flex items-center gap-3 border-t border-border pt-6">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="flex-1"
                        onClick={handleWishlist}
                        disabled={toggleWishlist.isPending}
                      >
                        <Heart
                          className={`w-4 h-4 ${isWishlisted || course.is_wishlisted ? "fill-spark text-spark" : ""}`}
                          aria-hidden="true"
                        />
                        المفضلة
                      </Button>
                      <Button variant="ghost" size="sm" className="flex-1" onClick={handleShare}>
                        <Share2 className="w-4 h-4" aria-hidden="true" />
                        مشاركة
                      </Button>
                    </div>
                  </div>
                </div>
              </motion.div>
            </div>
          </div>
        </section>

        {/* Main content */}
        <section className="py-12 md:py-16">
          <div className="container">
            <div className="grid lg:grid-cols-3 gap-10">
              <div className="lg:col-span-2">
                <Tabs defaultValue="overview" className="w-full">
                  <TabsList className="mb-8 w-full justify-start rounded-xl border border-border bg-card p-1">
                    <TabsTrigger value="overview">نظرة عامة</TabsTrigger>
                    <TabsTrigger value="curriculum">المحتوى</TabsTrigger>
                    <TabsTrigger value="instructor">المدرّب</TabsTrigger>
                    <TabsTrigger value="reviews">التقييمات</TabsTrigger>
                  </TabsList>

                  {/* Overview */}
                  <TabsContent value="overview">
                    <motion.div
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="space-y-6"
                    >
                      {course.outcomes && course.outcomes.length > 0 && (
                        <div className="card-tagdar p-7 hover:shadow-none hover:translate-y-0">
                          <h2 className="mb-4 text-xl font-bold text-navy">ما الذي ستتعلمه</h2>
                          <div className="grid md:grid-cols-2 gap-3">
                            {course.outcomes.map((item, index) => (
                              <div key={index} className="flex items-start gap-3">
                                <CheckCircle className="mt-0.5 w-5 h-5 shrink-0 text-primary" aria-hidden="true" />
                                <span className="text-muted-foreground leading-relaxed">{item}</span>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {course.requirements && course.requirements.length > 0 && (
                        <div className="card-tagdar p-7 hover:shadow-none hover:translate-y-0">
                          <h2 className="mb-4 text-xl font-bold text-navy">متطلبات الالتحاق</h2>
                          <ul className="space-y-2">
                            {course.requirements.map((item, index) => (
                              <li key={index} className="flex items-start gap-3 text-muted-foreground leading-relaxed">
                                <span className="mt-3 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true" />
                                {item}
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}

                      {course.description && (
                        <div className="card-tagdar p-7 hover:shadow-none hover:translate-y-0">
                          <h2 className="mb-4 text-xl font-bold text-navy">وصف الدورة</h2>
                          <div
                            className="prose prose-sm max-w-none text-muted-foreground"
                            dangerouslySetInnerHTML={{ __html: course.description }}
                          />
                        </div>
                      )}
                    </motion.div>
                  </TabsContent>

                  {/* Curriculum */}
                  <TabsContent value="curriculum">
                    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
                      <div className="card-tagdar overflow-hidden hover:shadow-none hover:translate-y-0">
                        <div className="border-b border-border p-4 text-sm text-muted-foreground">
                          {course.total_sections || 0} قسماً • {course.total_lessons || 0} درساً
                          {course.duration ? ` • المدة الإجمالية ${course.duration}` : ""}
                        </div>

                        {course.sections && course.sections.length > 0 ? (
                          <Accordion type="multiple" className="w-full">
                            {course.sections.map((section, index) => (
                              <AccordionItem key={section.id} value={`section-${index}`} className="border-border">
                                <AccordionTrigger className="px-4 py-4 hover:bg-secondary/50 hover:no-underline">
                                  <div className="flex w-full items-center justify-between gap-4 me-4">
                                    <span className="text-start font-bold text-navy">{section.title}</span>
                                    <span className="shrink-0 text-sm font-medium text-muted-foreground">
                                      {section.lessons?.length || 0} درساً
                                      {section.total_duration ? ` • ${section.total_duration}` : ""}
                                    </span>
                                  </div>
                                </AccordionTrigger>
                                <AccordionContent className="px-0 pb-0">
                                  {section.lessons?.map((lesson) => (
                                    <div
                                      key={lesson.id}
                                      className="flex items-center justify-between gap-4 border-t border-border px-6 py-3 hover:bg-secondary/50"
                                    >
                                      <div className="flex items-center gap-3">
                                        <Play className="w-4 h-4 text-muted-foreground" aria-hidden="true" />
                                        <span className="text-sm text-navy">{lesson.title}</span>
                                        {lesson.is_free && lesson.video_url && (
                                          <button
                                            type="button"
                                            onClick={(e) => {
                                              e.stopPropagation();
                                              setPreview({
                                                url: lesson.video_url!,
                                                type: lesson.video_type,
                                                title: lesson.title,
                                              });
                                            }}
                                            className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary transition-colors hover:bg-primary/20"
                                          >
                                            معاينة
                                          </button>
                                        )}
                                      </div>
                                      {lesson.duration && (
                                        <span className="shrink-0 text-sm text-muted-foreground">{lesson.duration}</span>
                                      )}
                                    </div>
                                  ))}
                                </AccordionContent>
                              </AccordionItem>
                            ))}
                          </Accordion>
                        ) : (
                          <div className="p-8 text-center text-muted-foreground">
                            تفاصيل محتوى الدورة ستُنشر قريباً.
                          </div>
                        )}
                      </div>
                    </motion.div>
                  </TabsContent>

                  {/* Instructor */}
                  <TabsContent value="instructor">
                    {course.instructor && (
                      <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="card-tagdar p-7 hover:shadow-none hover:translate-y-0"
                      >
                        <Link to={`/instructors/${course.instructor.id}`} className="flex flex-col sm:flex-row items-start gap-6">
                          <img
                            src={course.instructor.image || `https://ui-avatars.com/api/?name=${course.instructor.first_name}&background=00847B&color=fff&size=150`}
                            alt={`${course.instructor.first_name} ${course.instructor.last_name}`}
                            className="w-24 h-24 shrink-0 rounded-2xl object-cover"
                          />
                          <div>
                            <h2 className="text-xl font-bold text-navy transition-colors hover:text-primary">
                              {course.instructor.first_name} {course.instructor.last_name}
                            </h2>
                            <p className="mb-3 text-muted-foreground">{course.instructor.title || "مدرّب"}</p>
                            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
                              {Number(course.instructor.rating) > 0 && (
                                <div className="flex items-center gap-1.5">
                                  <Star className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />
                                  <span className="tabular-nums">{course.instructor.rating} تقييم</span>
                                </div>
                              )}
                              {Number(course.instructor.total_students) > 0 && (
                                <div className="flex items-center gap-1.5">
                                  <Users className="w-4 h-4" aria-hidden="true" />
                                  <span>{Number(course.instructor.total_students).toLocaleString()} متدرّب</span>
                                </div>
                              )}
                              {Number(course.instructor.total_courses) > 0 && (
                                <div className="flex items-center gap-1.5">
                                  <BookOpen className="w-4 h-4" aria-hidden="true" />
                                  <span>{course.instructor.total_courses} دورة</span>
                                </div>
                              )}
                            </div>
                          </div>
                        </Link>
                        {course.instructor.biography && (
                          <p className="mt-5 text-muted-foreground leading-relaxed">{course.instructor.biography}</p>
                        )}
                      </motion.div>
                    )}
                  </TabsContent>

                  {/* Reviews */}
                  <TabsContent value="reviews">
                    <motion.div
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="space-y-5"
                    >
                      {reviewStats && (
                        <div className="card-tagdar p-7 hover:shadow-none hover:translate-y-0">
                          <div className="flex flex-col sm:flex-row items-center gap-8">
                            <div className="text-center">
                              <div className="text-5xl font-black text-navy tabular-nums">
                                {reviewStats.average_rating?.toFixed(1) || "—"}
                              </div>
                              <div className="mt-2 flex items-center justify-center">
                                {[...Array(5)].map((_, i) => (
                                  <Star
                                    key={i}
                                    aria-hidden="true"
                                    className={`w-5 h-5 ${
                                      i < Math.round(reviewStats.average_rating || 0)
                                        ? "fill-spark text-spark"
                                        : "text-muted-foreground/40"
                                    }`}
                                  />
                                ))}
                              </div>
                              <p className="mt-1 text-sm text-muted-foreground">تقييم الدورة</p>
                            </div>
                            <div className="flex-1 w-full space-y-2">
                              {[5, 4, 3, 2, 1].map((rating) => (
                                <div key={rating} className="flex items-center gap-3">
                                  <div className="flex w-12 items-center gap-1">
                                    <Star className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />
                                    <span className="text-sm text-navy tabular-nums">{rating}</span>
                                  </div>
                                  <Progress value={reviewStats.rating_breakdown?.[rating] || 0} className="h-2 flex-1" />
                                  <span className="w-10 text-sm text-muted-foreground tabular-nums">
                                    {reviewStats.rating_breakdown?.[rating] || 0}%
                                  </span>
                                </div>
                              ))}
                            </div>
                          </div>
                        </div>
                      )}

                      <div className="space-y-4">
                        {reviews.length > 0 ? (
                          reviews.map((review) => (
                            <div key={review.id} className="card-tagdar p-7 hover:shadow-none hover:translate-y-0">
                              <div className="flex items-start gap-4">
                                <img
                                  src={review.user.image || `https://ui-avatars.com/api/?name=${review.user.name}&background=00847B&color=fff`}
                                  alt={review.user.name}
                                  className="w-12 h-12 shrink-0 rounded-full object-cover"
                                />
                                <div className="flex-1">
                                  <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-bold text-navy">{review.user.name}</h3>
                                    <span className="text-sm text-muted-foreground">
                                      {new Date(review.created_at).toLocaleDateString("ar-EG")}
                                    </span>
                                  </div>
                                  <div className="mt-1 flex items-center">
                                    {[...Array(5)].map((_, i) => (
                                      <Star
                                        key={i}
                                        aria-hidden="true"
                                        className={`w-4 h-4 ${
                                          i < review.rating ? "fill-spark text-spark" : "text-muted-foreground/40"
                                        }`}
                                      />
                                    ))}
                                  </div>
                                  {review.review && (
                                    <p className="mt-3 text-muted-foreground leading-relaxed">{review.review}</p>
                                  )}
                                </div>
                              </div>
                            </div>
                          ))
                        ) : (
                          <div className="card-tagdar flex flex-col items-center justify-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
                            <BrandIllustration name="noResults" className="w-64 max-w-full" />
                            <p className="max-w-sm text-muted-foreground leading-relaxed">
                              لا توجد تقييمات بعد. كن أول من يشارك رأيه بعد إنهاء الدورة.
                            </p>
                          </div>
                        )}
                      </div>
                    </motion.div>
                  </TabsContent>
                </Tabs>
              </div>
            </div>
          </div>
        </section>
      </main>

      <PreviewVideoModal
        open={!!preview}
        onClose={() => setPreview(null)}
        videoUrl={preview?.url ?? null}
        videoType={preview?.type ?? null}
        title={preview?.title}
      />

      <Footer />
    </div>
  );
};

export default CourseDetails;
