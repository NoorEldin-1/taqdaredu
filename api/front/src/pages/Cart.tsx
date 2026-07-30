import { useState, useEffect } from "react";
import { motion } from "framer-motion";
import { Loader2, Trash2, LogIn, X, ArrowLeft } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIllustration from "@/components/BrandIllustration";
import { useCart, useRemoveFromCart, useCheckout, usePaymentMethods, useApplyCoupon } from "@/hooks/useApi";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToast } from "@/hooks/use-toast";
import { useGuestCart, removeFromGuestCart, pruneGuestCart } from "@/lib/guestCart";
import { API_BASE_URL } from "@/lib/api/config";
import { useCurrency } from "@/lib/currency";

/**
 * The page renders two independent trees — signed-in cart and guest cart. They
 * previously drifted apart visually. These two pieces are the shared surface:
 * whatever changes here lands on both paths at once.
 */
const CartEmpty = () => (
  <div className="card-tagdar flex flex-col items-center justify-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
    <BrandIllustration name="cart" className="w-64 max-w-full" />
    <div>
      <h2 className="text-xl font-bold text-navy">سلتك فارغة</h2>
      <p className="mx-auto mt-3 max-w-sm text-muted-foreground leading-relaxed">
        لم تُضِف أي دورة بعد. تصفّح ما هو متاح واختر ما يناسب مسارك.
      </p>
    </div>
    <Link to="/courses">
      <Button size="lg">
        تصفّح الدورات
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
      </Button>
    </Link>
  </div>
);

interface CartLineProps {
  title: string;
  price: number;
  thumb: string;
  onRemove: () => void;
}

// The hook lives here rather than in the parent: this is where the money is
// actually rendered, and the parent maps over items (hooks can't run in a map).
const CartLine = ({ title, price, thumb, onRemove }: CartLineProps) => {
  const { format } = useCurrency();
  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      className="card-tagdar flex items-center gap-4 p-4 hover:translate-y-0"
    >
      <img
        src={thumb || "/placeholder.svg"}
        alt={title}
        className="h-16 w-24 shrink-0 rounded-xl bg-secondary object-cover"
        onError={(e) => {
          (e.target as HTMLImageElement).src = "/placeholder.svg";
        }}
      />
      <div className="min-w-0 flex-1">
        <h3 className="font-bold text-navy leading-relaxed line-clamp-2">{title}</h3>
        <p className="mt-1 font-black text-primary tabular-nums">{format(price)}</p>
      </div>
      <Button
        variant="ghost"
        size="sm"
        onClick={onRemove}
        aria-label={`إزالة ${title} من السلة`}
        className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
      >
        <Trash2 className="w-4 h-4" aria-hidden="true" />
      </Button>
    </motion.div>
  );
};

const Cart = () => {
  const { isAuthenticated } = useAuth();
  const { data, isLoading } = useCart();
  const removeFromCart = useRemoveFromCart();
  const checkout = useCheckout();
  const applyCoupon = useApplyCoupon();
  const navigate = useNavigate();
  const { toast } = useToast();
  const { format } = useCurrency();
  const cart = data?.data;
  const items = cart?.items || [];
  // Backend total field is `subtotal`; older shape used `total`.
  const cartTotal = Number((cart as { subtotal?: number; total?: number })?.subtotal ?? (cart as { total?: number })?.total ?? 0);

  // Real, active payment gateways (PayPal / Tap / …) from the backend.
  const { data: methodsData } = usePaymentMethods();
  const methods = methodsData?.data || [];
  const [selectedMethod, setSelectedMethod] = useState<string>("");
  // Default to the first available gateway once methods load.
  const activeMethod = selectedMethod || methods[0]?.id || "";

  const [couponInput, setCouponInput] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState<{
    code: string;
    discount: number;
    percentage: number;
    finalTotal: number;
    originalTotal: number;
  } | null>(null);

  // The discount was computed against a specific cart total — if the cart
  // changes afterward (item added/removed), that number is stale, so drop it.
  useEffect(() => {
    if (appliedCoupon && appliedCoupon.originalTotal !== cartTotal) {
      setAppliedCoupon(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cartTotal]);

  const handleApplyCoupon = () => {
    const code = couponInput.trim();
    if (!code) return;
    applyCoupon.mutate(code, {
      onSuccess: (res) => {
        if (!res?.status || !res.data) {
          toast({ title: "كود غير صالح", description: res?.message || "هذا الكود غير صالح أو انتهت صلاحيته.", variant: "destructive" });
          return;
        }
        setAppliedCoupon({
          code,
          discount: res.data.discount,
          percentage: res.data.discount_percentage,
          finalTotal: res.data.final_total,
          originalTotal: res.data.original_total,
        });
        setCouponInput("");
        toast({ title: "تم تطبيق الكود", description: `طُبِّق خصم بنسبة ${res.data.discount_percentage}%.` });
      },
      onError: () => {
        toast({ title: "كود غير صالح", description: "هذا الكود غير صالح أو انتهت صلاحيته.", variant: "destructive" });
      },
    });
  };

  const handleCheckout = () => {
    if (cartTotal > 0 && !activeMethod) {
      toast({ title: "اختر طريقة الدفع", description: "يُرجى تحديد الوسيلة التي تفضّل الدفع بها.", variant: "destructive" });
      return;
    }
    checkout.mutate(
      { paymentMethod: activeMethod, couponCode: appliedCoupon?.code },
      {
        onSuccess: (res) => {
          // apiPost resolves even on logical failure — check the status flag.
          if (res?.status === false) {
            toast({ title: "تعذّر إتمام الشراء", description: res.message || "يُرجى المحاولة مرة أخرى أو التواصل مع الدعم.", variant: "destructive" });
            return;
          }
          // Paid courses: backend returns a gateway URL to complete payment.
          const redirectUrl = res?.data?.redirect_url;
          if (redirectUrl) {
            window.location.href = redirectUrl;
            return;
          }
          // Free courses: enrolled immediately.
          toast({ title: "تم تسجيلك بنجاح", description: "أصبح بإمكانك الوصول إلى دوراتك الآن." });
          navigate('/my-courses');
        },
        onError: () => {
          toast({ title: "تعذّر إتمام الشراء", description: "يُرجى المحاولة مرة أخرى أو التواصل مع الدعم.", variant: "destructive" });
        },
      }
    );
  };

  const guestIds = useGuestCart();
  const { data: guestItems = [], isLoading: guestLoading } = useQuery({
    queryKey: ["guest-cart", guestIds],
    enabled: !isAuthenticated && guestIds.length > 0,
    queryFn: async () => {
      const results = await Promise.all(
        guestIds.map(async (cid) => {
          try {
            const res = await fetch(`${API_BASE_URL}/api/api_frontend/course/${cid}`);
            // Definitive not-found (deleted/unpublished course) → prune from storage.
            if (res.status === 404) return { cid, course: null, dead: true };
            const json = await res.json().catch(() => null);
            if (res.ok && json?.status === false) return { cid, course: null, dead: true };
            return { cid, course: json?.status ? json.data : null, dead: false };
          } catch {
            // Network/CORS failure is transient — never wipe the cart on it.
            return { cid, course: null, dead: false };
          }
        })
      );
      // Badge counts raw stored IDs; dropping dead ones here keeps badge == cart.
      pruneGuestCart(results.filter((r) => r.dead).map((r) => r.cid));
      return results.map((r) => r.course).filter(Boolean);
    },
  });

  if (!isAuthenticated) {
    const guestTotal = guestItems.reduce((sum: number, c: { price?: number }) => sum + (Number(c?.price) || 0), 0);
    return (
      <div className="min-h-screen bg-secondary">
        <Navbar />
        <main className="pt-32 pb-20">
          <div className="container max-w-4xl">
            <h1 className="mb-8 text-3xl md:text-4xl font-black text-navy">سلة الشراء</h1>
            {guestLoading ? (
              <div className="flex justify-center py-20">
                <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
              </div>
            ) : guestItems.length === 0 ? (
              <CartEmpty />
            ) : (
              <div className="space-y-4">
                {guestItems.map((c: { id: number; title: string; price?: number; thumbnail?: string; image_url?: string }) => (
                  <CartLine
                    key={c.id}
                    title={c.title}
                    price={Number(c.price) || 0}
                    thumb={c.thumbnail || c.image_url || ""}
                    onRemove={() => removeFromGuestCart(c.id)}
                  />
                ))}
                <div className="card-tagdar mt-6 p-6 hover:translate-y-0">
                  <div className="mb-4 flex items-center justify-between">
                    <span className="font-medium text-navy">الإجمالي</span>
                    <span className="text-2xl font-black text-primary tabular-nums">{format(guestTotal)}</span>
                  </div>
                  <p className="mb-5 text-sm text-muted-foreground leading-relaxed">
                    سجّل دخولك لإتمام عملية الشراء — ستجد سلتك كما هي في انتظارك.
                  </p>
                  <Button
                    variant="gold"
                    size="lg"
                    className="w-full"
                    onClick={() => navigate("/login", { state: { from: { pathname: "/cart" } } })}
                  >
                    <LogIn className="w-4 h-4" aria-hidden="true" />
                    سجّل الدخول لإتمام الشراء
                  </Button>
                </div>
              </div>
            )}
          </div>
        </main>
        <Footer />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-secondary">
      <Navbar />
      <main className="pt-32 pb-20">
        <div className="container max-w-4xl">
          <h1 className="mb-8 text-3xl md:text-4xl font-black text-navy">سلة الشراء</h1>
          {isLoading ? (
            <div className="flex justify-center py-20">
              <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
            </div>
          ) : items.length === 0 ? (
            <CartEmpty />
          ) : (
            <div className="space-y-4">
              {items.map((item) => {
                // Backend returns flattened cart items (title/price/course_id at top level),
                // with an optional nested `course` for older shapes — support both.
                const c = (item as { course?: Record<string, unknown> }).course || (item as Record<string, unknown>);
                const courseId = Number((item as { course_id?: number }).course_id ?? (c as { id?: number }).id ?? (item as { id?: number }).id);
                const title = String((c as { title?: string }).title ?? "");
                const price = Number((item as { effective_price?: number }).effective_price ?? (c as { price?: number }).price ?? 0);
                const thumb = String((item as { thumbnail_url?: string }).thumbnail_url ?? (c as { thumbnail?: string; image_url?: string }).thumbnail ?? (c as { image_url?: string }).image_url ?? "");
                return (
                  <CartLine
                    key={String((item as { id?: number }).id ?? courseId)}
                    title={title}
                    price={price}
                    thumb={thumb}
                    onRemove={() => removeFromCart.mutate(courseId)}
                  />
                );
              })}
              <div className="card-tagdar mt-6 p-6 hover:translate-y-0">
                {cartTotal > 0 && (
                  <div className="mb-4">
                    {appliedCoupon ? (
                      <div className="flex items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/10 px-4 py-3">
                        <div>
                          <p className="text-sm font-medium text-navy">تم تطبيق الكود «{appliedCoupon.code}»</p>
                          <p className="text-xs text-primary tabular-nums">
                            خصم {appliedCoupon.percentage}% (-{format(appliedCoupon.discount)})
                          </p>
                        </div>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setAppliedCoupon(null)}
                          aria-label="إزالة كود الخصم"
                          className="shrink-0 text-muted-foreground"
                        >
                          <X className="w-4 h-4" aria-hidden="true" />
                        </Button>
                      </div>
                    ) : (
                      <div className="flex gap-2">
                        <Input
                          placeholder="كود الخصم"
                          aria-label="كود الخصم"
                          value={couponInput}
                          onChange={(e) => setCouponInput(e.target.value)}
                          onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); handleApplyCoupon(); } }}
                          className="h-12 rounded-xl"
                        />
                        <Button
                          variant="outline"
                          size="lg"
                          className="shrink-0"
                          onClick={handleApplyCoupon}
                          disabled={applyCoupon.isPending || !couponInput.trim()}
                        >
                          {applyCoupon.isPending ? <Loader2 className="w-4 h-4 animate-spin" aria-label="جارٍ التحقق" /> : "تطبيق"}
                        </Button>
                      </div>
                    )}
                  </div>
                )}
                <div className="mb-4 flex items-center justify-between">
                  <span className="font-medium text-navy">الإجمالي</span>
                  {appliedCoupon ? (
                    <span className="text-end">
                      <span className="block text-sm text-muted-foreground line-through tabular-nums">{format(cartTotal)}</span>
                      <span className="text-2xl font-black text-primary tabular-nums">{format(appliedCoupon.finalTotal)}</span>
                    </span>
                  ) : (
                    <span className="text-2xl font-black text-primary tabular-nums">{format(cartTotal)}</span>
                  )}
                </div>
                {cartTotal > 0 && methods.length > 0 && (
                  <div className="mb-4">
                    <p className="mb-2 text-sm font-medium text-navy">طريقة الدفع</p>
                    <div className="space-y-2">
                      {methods.map((m) => (
                        <label
                          key={m.id}
                          className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors ${
                            activeMethod === m.id ? "border-primary bg-primary/10" : "border-border hover:border-primary/50"
                          }`}
                        >
                          <input
                            type="radio"
                            name="paymentMethod"
                            value={m.id}
                            checked={activeMethod === m.id}
                            onChange={() => setSelectedMethod(m.id)}
                            className="accent-primary"
                          />
                          <span className="font-medium text-navy">{m.name}</span>
                          {m.description && <span className="text-xs text-muted-foreground">{m.description}</span>}
                        </label>
                      ))}
                    </div>
                  </div>
                )}
                {cartTotal > 0 && methods.length === 0 && (
                  <p className="mb-4 text-sm text-destructive leading-relaxed">
                    الدفع الإلكتروني غير متاح حالياً. يُرجى التواصل مع الدعم لإتمام تسجيلك.
                  </p>
                )}
                {/* The one orange CTA on the page: the action the cart exists for. */}
                <Button
                  variant="gold"
                  size="lg"
                  className="w-full"
                  onClick={handleCheckout}
                  disabled={checkout.isPending || (cartTotal > 0 && methods.length === 0)}
                >
                  {checkout.isPending ? "جارٍ المعالجة…" : "إتمام الشراء"}
                </Button>
              </div>
            </div>
          )}
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default Cart;
