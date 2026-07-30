import { useState } from "react";
import { Link, useNavigate, useLocation } from "react-router-dom";
import { Eye, EyeOff, Mail, Lock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AuthLayout from "@/components/layout/AuthLayout";
import { useAuth } from "@/contexts/AuthContext";
import { useLogin } from "@/hooks/useApi";
import { useToast } from "@/hooks/use-toast";
import { getGuestCart, clearGuestCart } from "@/lib/guestCart";
import { cartService } from "@/lib/api/services";

const Login = () => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(true);
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();
  const loginMutation = useLogin();
  const { toast } = useToast();

  const from = (location.state as { from?: { pathname: string } })?.from?.pathname || "/";

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      const response = await loginMutation.mutateAsync({ email, password });
      if (response.status && response.data) {
        login(response.data, rememberMe);
        // Merge any guest cart into the user's server cart, then clear it
        const guestIds = getGuestCart();
        if (guestIds.length > 0) {
          await Promise.all(guestIds.map((cid) => cartService.add(cid).catch(() => null)));
          clearGuestCart();
        }
        toast({
          title: "أهلاً بعودتك",
          description: `تسعدنا رؤيتك مجدداً، ${response.data.first_name}`,
        });
        navigate(from, { replace: true });
      } else {
        toast({
          title: "تعذّر تسجيل الدخول",
          description: response.message || "البريد الإلكتروني أو كلمة المرور غير صحيحة.",
          variant: "destructive",
        });
      }
    } catch (error) {
      toast({
        title: "تعذّر تسجيل الدخول",
        description: "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.",
        variant: "destructive",
      });
    }
  };

  return (
    <AuthLayout
      title="أهلاً بعودتك"
      subtitle="سجّل دخولك لتكمل من حيث توقفت."
      panelText="مسارك محفوظ. ادخل وأكمل الدرس الذي تركته."
      footer={
        <>
          ليس لديك حساب؟{" "}
          <Link to="/register" className="font-bold text-primary hover:underline">
            أنشئ حساباً مجاناً
          </Link>
        </>
      }
    >
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

        <div className="space-y-2">
          <Label htmlFor="password" className="text-navy font-medium">كلمة المرور</Label>
          <div className="relative">
            <Lock
              className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
              aria-hidden="true"
            />
            <Input
              id="password"
              type={showPassword ? "text" : "password"}
              placeholder="أدخل كلمة المرور"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="h-12 rounded-xl ps-11 pe-11"
              required
              autoComplete="new-password"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              aria-label={showPassword ? "إخفاء كلمة المرور" : "إظهار كلمة المرور"}
              className="absolute end-3 top-1/2 -translate-y-1/2 rounded-md text-muted-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
            </button>
          </div>
        </div>

        <div className="flex items-center justify-between gap-3">
          <label htmlFor="remember-me" className="flex cursor-pointer select-none items-center gap-2">
            <input
              id="remember-me"
              type="checkbox"
              checked={rememberMe}
              onChange={(e) => setRememberMe(e.target.checked)}
              className="h-4 w-4 rounded border-border accent-primary"
            />
            <span className="text-sm text-muted-foreground">تذكّرني</span>
          </label>
          <Link
            to="/forgot-password"
            className="text-sm font-medium text-primary transition-colors hover:underline"
          >
            نسيت كلمة المرور؟
          </Link>
        </div>

        <Button type="submit" size="lg" className="w-full" disabled={loginMutation.isPending}>
          {loginMutation.isPending ? "جارٍ تسجيل الدخول…" : "تسجيل الدخول"}
        </Button>
      </form>
    </AuthLayout>
  );
};

export default Login;
