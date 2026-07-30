import { useState } from "react";
import { Loader2, XCircle, Award, RotateCcw, ListChecks } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useQuiz, useSubmitQuiz } from "@/hooks/useApi";
import { useToast } from "@/hooks/use-toast";
import { cn } from "@/lib/utils";
import type { QuizResult } from "@/lib/api/types";

interface QuizPlayerProps {
  lessonId: number;
}

/**
 * Interactive quiz/exam player for `lesson_type === 'quiz'` lessons.
 *
 * Talks to the existing backend endpoints (api_frontend: quiz_get / quiz_submit).
 * Answers are submitted as { [questionId]: optionIndexString[] } — option INDICES
 * as strings, matching how `correct_answers` is stored (see quiz_answer_sheet view).
 */
const QuizPlayer = ({ lessonId }: QuizPlayerProps) => {
  const { data, isLoading, isError } = useQuiz(lessonId);
  const submitQuiz = useSubmitQuiz();
  const { toast } = useToast();
  const [answers, setAnswers] = useState<Record<number, string[]>>({});
  const [result, setResult] = useState<QuizResult | null>(null);

  const quiz = data?.data;
  const questions = quiz?.questions ?? [];

  const toggleAnswer = (qId: number, val: string, multiple: boolean) => {
    setAnswers((prev) => {
      const cur = prev[qId] ?? [];
      if (multiple) {
        return { ...prev, [qId]: cur.includes(val) ? cur.filter((v) => v !== val) : [...cur, val] };
      }
      return { ...prev, [qId]: [val] };
    });
  };

  const handleSubmit = () => {
    submitQuiz.mutate(
      { lessonId, answers },
      {
        onSuccess: (res) => {
          if (res?.data) setResult(res.data);
          else toast({ title: "تعذّر إرسال الإجابات", description: res?.message || "يُرجى المحاولة مرة أخرى.", variant: "destructive" });
        },
        onError: () => toast({ title: "تعذّر إرسال الإجابات", description: "يُرجى المحاولة مرة أخرى.", variant: "destructive" }),
      }
    );
  };

  const handleRetake = () => {
    setAnswers({});
    setResult(null);
  };

  if (isLoading) {
    return (
      <div className="absolute inset-0 flex items-center justify-center bg-background">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

  if (isError || !quiz) {
    return (
      <div className="absolute inset-0 flex flex-col items-center justify-center bg-background text-center px-6">
        <XCircle className="w-12 h-12 text-destructive mb-3" />
        <h3 className="text-lg font-semibold text-foreground mb-1">تعذّر تحميل الاختبار</h3>
        <p className="text-muted-foreground text-sm">تأكّد من تسجيلك في هذه الدورة، ثم حاول مرة أخرى.</p>
      </div>
    );
  }

  if (questions.length === 0) {
    return (
      <div className="absolute inset-0 flex flex-col items-center justify-center bg-background text-center px-6">
        <ListChecks className="w-12 h-12 text-primary mb-3" />
        <h3 className="text-lg font-semibold text-foreground mb-1">{quiz.title}</h3>
        <p className="text-muted-foreground text-sm">لم تُضَف أي أسئلة إلى هذا الاختبار بعد.</p>
      </div>
    );
  }

  // ---- Result screen ----
  if (result) {
    const passMark = quiz.pass_mark ?? null;
    const passed = passMark != null ? result.score >= passMark : null;
    return (
      <div className="absolute inset-0 overflow-y-auto bg-background">
        <div className="max-w-2xl mx-auto p-6 md:p-10 text-center">
          <div
            className={cn(
              "mx-auto w-20 h-20 rounded-full flex items-center justify-center mb-4",
              passed === false ? "bg-destructive/15" : "bg-primary/15"
            )}
          >
            {passed === false ? <XCircle className="w-10 h-10 text-destructive" /> : <Award className="w-10 h-10 text-primary" />}
          </div>
          <h2 className="text-2xl font-bold text-foreground mb-1">
            {passed === true ? "اجتزت الاختبار!" : passed === false ? "لم تجتز الاختبار" : "اكتمل الاختبار"}
          </h2>
          <p className="text-muted-foreground mb-6">{quiz.title}</p>

          <div className="flex items-center justify-center gap-6 mb-8">
            <div>
              <div className="text-4xl font-bold text-primary">{result.score}%</div>
              <div className="text-xs text-muted-foreground mt-1">الدرجة</div>
            </div>
            <div className="h-12 w-px bg-border" />
            <div>
              <div className="text-4xl font-bold text-foreground">
                {result.correct}/{result.total}
              </div>
              <div className="text-xs text-muted-foreground mt-1">إجابات صحيحة</div>
            </div>
            {passMark != null && (
              <>
                <div className="h-12 w-px bg-border" />
                <div>
                  <div className="text-4xl font-bold text-foreground">{passMark}%</div>
                  <div className="text-xs text-muted-foreground mt-1">درجة النجاح</div>
                </div>
              </>
            )}
          </div>

          <Button onClick={handleRetake} variant="outline">
            <RotateCcw className="w-4 h-4 me-2" /> إعادة الاختبار
          </Button>
        </div>
      </div>
    );
  }

  // ---- Questions ----
  const answeredCount = questions.filter((q) => (answers[q.id]?.length ?? 0) > 0).length;
  const allAnswered = answeredCount === questions.length;
  const remaining = questions.length - answeredCount;
  // Arabic counts don't pluralise like English: 1 is singular, 2 is dual, 3–10
  // take the plural noun, and 11+ goes back to the singular in the accusative.
  const remainingLabel =
    remaining === 1
      ? "بقي سؤال واحد"
      : remaining === 2
        ? "بقي سؤالان"
        : remaining <= 10
          ? `بقي ${remaining} أسئلة`
          : `بقي ${remaining} سؤالاً`;

  return (
    <div className="absolute inset-0 overflow-y-auto bg-background">
      <div className="max-w-2xl mx-auto p-6 md:p-10">
        <div className="flex items-center gap-2 mb-1 text-primary">
          <ListChecks className="w-5 h-5" />
          <span className="text-sm font-medium">اختبار</span>
        </div>
        <h2 className="text-xl font-semibold text-foreground mb-1">{quiz.title}</h2>
        <p className="text-sm text-muted-foreground mb-6 tabular-nums">
          أجبت عن {answeredCount} من {questions.length}
        </p>

        <div className="space-y-6">
          {questions.map((q, qi) => {
            const isMultiple = q.type === "multiple_choice";
            const selected = answers[q.id] ?? [];
            const hasOptions = Array.isArray(q.options) && q.options.length > 0;
            return (
              <div key={q.id} className="rounded-xl border border-border bg-card p-5">
                <div className="font-medium text-foreground flex gap-2">
                  <span className="text-muted-foreground">{qi + 1}.</span>
                  <span dangerouslySetInnerHTML={{ __html: q.title }} />
                </div>
                {isMultiple && <p className="text-xs text-muted-foreground mt-1">اختر كل الإجابات الصحيحة</p>}
                {hasOptions ? (
                  <div className="space-y-2 mt-3">
                    {q.options.map((opt, oi) => {
                      const val = String(oi);
                      const checked = selected.includes(val);
                      return (
                        <label
                          key={oi}
                          className={cn(
                            "flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors",
                            checked ? "border-primary bg-primary/10" : "border-border hover:bg-secondary/50"
                          )}
                        >
                          <input
                            type={isMultiple ? "checkbox" : "radio"}
                            name={`q_${q.id}`}
                            checked={checked}
                            onChange={() => toggleAnswer(q.id, val, isMultiple)}
                            className="accent-primary w-4 h-4 flex-shrink-0"
                          />
                          <span className="text-sm text-foreground">{opt}</span>
                        </label>
                      );
                    })}
                  </div>
                ) : (
                  <input
                    type="text"
                    value={selected[0] ?? ""}
                    onChange={(e) => setAnswers((prev) => ({ ...prev, [q.id]: e.target.value ? [e.target.value] : [] }))}
                    placeholder="اكتب إجابتك"
                    className="mt-3 w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                  />
                )}
              </div>
            );
          })}
        </div>

        <div className="mt-8 flex items-center justify-between gap-4">
          <span className="text-sm text-muted-foreground tabular-nums">
            {allAnswered ? "أجبت عن جميع الأسئلة" : remainingLabel}
          </span>
          <Button onClick={handleSubmit} disabled={!allAnswered || submitQuiz.isPending}>
            {submitQuiz.isPending && <Loader2 className="w-4 h-4 me-2 animate-spin" />}
            إرسال الإجابات
          </Button>
        </div>
      </div>
    </div>
  );
};

export default QuizPlayer;
