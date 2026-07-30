import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Eye, EyeOff, Mail, Lock, User } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AuthLayout from "@/components/layout/AuthLayout";
import { useRegister } from "@/hooks/useApi";
import { useToast } from "@/hooks/use-toast";

const Register = () => {
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const navigate = useNavigate();
  const registerMutation = useRegister();
  const { toast } = useToast();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Strong password policy: at least 8 chars with upper, lower and a number
    const strongEnough = password.length >= 8 && /[A-Z]/.test(password) && /[a-z]/.test(password) && /[0-9]/.test(password);
    if (!strongEnough) {
      toast({
        title: "كلمة المرور ضعيفة",
        description: "يجب ألا تقل كلمة المرور عن ٨ أحرف وأن تتضمن حرفاً كبيراً وحرفاً صغيراً ورقماً.",
        variant: "destructive",
      });
      return;
    }

    if (password !== confirmPassword) {
      toast({
        title: "كلمتا المرور غير متطابقتين",
        description: "يُرجى التأكد من تطابق كلمة المرور مع تأكيدها.",
        variant: "destructive",
      });
      return;
    }

    try {
      const response = await registerMutation.mutateAsync({ firstName, lastName, email, password });
      if (response.status) {
        toast({
          title: "تم إنشاء حسابك",
          description: "سجّل دخولك الآن للبدء في التعلّم.",
        });
        navigate("/login");
      } else {
        toast({
          title: "تعذّر إنشاء الحساب",
          description: response.message || "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.",
          variant: "destructive",
        });
      }
    } catch (error) {
      toast({
        title: "تعذّر إنشاء الحساب",
        description: "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.",
        variant: "destructive",
      });
    }
  };

  return (
    <AuthLayout
      title="أنشئ حسابك"
      subtitle="خطوة واحدة تفصلك عن أول درس."
      panelText="حساب واحد يجمع مساراتك وشهاداتك وتقدّمك في مكان واحد."
      footer={
        <>
          لديك حساب بالفعل؟{" "}
          <Link to="/login" className="font-bold text-primary hover:underline">
            سجّل دخولك
          </Link>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-5" autoComplete="off">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="firstName" className="text-navy font-medium">الاسم الأول</Label>
            <div className="relative">
              <User
                className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
              />
              <Input
                id="firstName"
                type="text"
                placeholder="محمد"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                className="h-12 rounded-xl ps-11"
                required
                autoComplete="off"
              />
            </div>
          </div>

          {/* Family name stays optional — the backend has always accepted it empty. */}
          <div className="space-y-2">
            <Label htmlFor="lastName" className="text-navy font-medium">اسم العائلة</Label>
            <div className="relative">
              <User
                className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
              />
              <Input
                id="lastName"
                type="text"
                placeholder="العمري"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                className="h-12 rounded-xl ps-11"
                autoComplete="off"
              />
            </div>
          </div>
        </div>

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
              placeholder="أنشئ كلمة مرور"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="h-12 rounded-xl ps-11 pe-11"
              required
              autoComplete="new-password"
              aria-describedby="password-hint"
            />
            {/* One toggle drives both password fields, so the user compares what
                they typed rather than toggling twice. */}
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              aria-label={showPassword ? "إخفاء كلمة المرور" : "إظهار كلمة المرور"}
              className="absolute end-3 top-1/2 -translate-y-1/2 rounded-md text-muted-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
            </button>
          </div>
          {/* Stating the rule up front beats only failing on submit. */}
          <p id="password-hint" className="text-sm text-muted-foreground">
            ٨ أحرف على الأقل، مع حرف كبير وحرف صغير ورقم.
          </p>
        </div>

        <div className="space-y-2">
          <Label htmlFor="confirmPassword" className="text-navy font-medium">تأكيد كلمة المرور</Label>
          <div className="relative">
            <Lock
              className="absolute start-3 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
              aria-hidden="true"
            />
            <Input
              id="confirmPassword"
              type={showPassword ? "text" : "password"}
              placeholder="أعد إدخال كلمة المرور"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              className="h-12 rounded-xl ps-11"
              required
              autoComplete="new-password"
            />
          </div>
        </div>

        <Button type="submit" size="lg" className="w-full" disabled={registerMutation.isPending}>
          {registerMutation.isPending ? "جارٍ إنشاء الحساب…" : "إنشاء الحساب"}
        </Button>
      </form>
    </AuthLayout>
  );
};

export default Register;
