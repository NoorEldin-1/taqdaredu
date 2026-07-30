import { useState } from "react";
import { Link } from "react-router-dom";
import { Mail, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AuthLayout from "@/components/layout/AuthLayout";
import { useForgotPassword } from "@/hooks/useApi";
import { useToast } from "@/hooks/use-toast";

const ForgotPassword = () => {
  const [email, setEmail] = useState("");
  const [isSubmitted, setIsSubmitted] = useState(false);
  const forgotPasswordMutation = useForgotPassword();
  const { toast } = useToast();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      const response = await forgotPasswordMutation.mutateAsync(email);
      if (response.status) {
        setIsSubmitted(true);
        toast({
          title: "تم إرسال الرسالة",
          description: "راجع بريدك الإلكتروني لإكمال خطوات استعادة كلمة المرور.",
        });
      } else {
        toast({
          title: "تعذّر إرسال الرسالة",
          description: response.message || "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.",
          variant: "destructive",
        });
      }
    } catch (error) {
      toast({
        title: "تعذّر إرسال الرسالة",
        description: "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.",
        variant: "destructive",
      });
    }
  };

  return (
    <AuthLayout
      // The heading carries the state change, so the confirmation reads as the
      // same page moving forward rather than a new screen.
      title={isSubmitted ? "راجع بريدك الإلكتروني" : "استعادة كلمة المرور"}
      subtitle={
        isSubmitted
          ? "أرسلنا رابط إعادة التعيين. صلاحيته محدودة، لذا استخدمه قريباً."
          : "أدخل بريدك الإلكتروني وسنرسل إليك رابط إعادة التعيين."
      }
      panelText="لا داعي للقلق، استعادة الحساب تستغرق دقيقة واحدة."
      footer={
        <Link
          to="/login"
          className="inline-flex items-center justify-center gap-2 font-medium text-primary transition-colors hover:underline"
        >
          <ArrowRight className="w-4 h-4" aria-hidden="true" />
          العودة إلى تسجيل الدخول
        </Link>
      }
    >
      {!isSubmitted ? (
        <form onSubmit={handleSubmit} className="space-y-5" autoComplete="off">
          <div className="space-y-2">
            <Label htmlFor="email" className="text-navy font-medium">البريد الإلكتروني</Label>
            <div className="relative">
              <Mail
                className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
              />
              <Input
                id="email"
                type="email"
                placeholder="name@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="h-12 rounded-xl ps-11"
                required
                autoComplete="off"
                dir="ltr"
              />
            </div>
          </div>

          <Button type="submit" size="lg" className="w-full" disabled={forgotPasswordMutation.isPending}>
            {forgotPasswordMutation.isPending ? "جارٍ الإرسال…" : "إرسال رابط الاستعادة"}
          </Button>
        </form>
      ) : (
        <div className="space-y-6">
          <div className="flex items-start gap-4 rounded-xl bg-secondary p-5">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10">
              <Mail className="h-6 w-6 text-primary" aria-hidden="true" />
            </span>
            <div className="space-y-1">
              <p className="text-muted-foreground">أرسلنا رابط إعادة التعيين إلى</p>
              {/* The address is echoed back so a typo is caught here, not after
                  waiting for an email that will never arrive. */}
              <p className="font-medium text-navy break-all" dir="ltr">{email}</p>
            </div>
          </div>

          {/* Returning to the form keeps the typed address, so a retry is one click. */}
          <Button
            type="button"
            variant="outline"
            size="lg"
            className="w-full"
            onClick={() => setIsSubmitted(false)}
          >
            لم تصلك الرسالة؟ حاول مرة أخرى
          </Button>
        </div>
      )}
    </AuthLayout>
  );
};

export default ForgotPassword;
