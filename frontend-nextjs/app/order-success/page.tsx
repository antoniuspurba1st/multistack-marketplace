import { Recommendation } from "@/lib/api";

type OrderSuccessPageProps = {
  searchParams: Promise<{
    recs?: string;
  }>;
};

export default async function OrderSuccessPage({
  searchParams,
}: OrderSuccessPageProps) {
  const { recs } = await searchParams;

  const recommendations: Recommendation[] = recs
    ? (JSON.parse(decodeURIComponent(recs)) as Recommendation[])
    : [];

  return (
    <main className="min-h-screen bg-slate-50">
      <section className="mx-auto max-w-6xl px-6 py-12 lg:px-10">
        <div className="rounded-5xl bg-emerald-600 px-8 py-10 text-white shadow-xl">
          <p className="text-sm font-semibold uppercase tracking-widest text-emerald-100">
            Checkout complete
          </p>
          <h1 className="mt-3 text-4xl font-semibold">Order successful</h1>
          <p className="mt-4 max-w-2xl text-emerald-50">
            Thank you for your purchase. The Laravel gateway completed checkout and the
            recommendation service returned fresh suggestions below.
          </p>
        </div>

        {recommendations.length > 0 ? (
          <div className="mt-8 rounded-4xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 className="text-2xl font-semibold text-slate-950">
              Recommended products
            </h2>
            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {recommendations.map((recommendation) => (
                <div
                  key={recommendation.product_id}
                  className="rounded-3xl border border-slate-200 bg-slate-50 p-5"
                >
                  <p className="text-sm font-semibold uppercase tracking-widest text-slate-400">
                    Recommendation
                  </p>
                  <p className="mt-3 text-lg font-semibold text-slate-950">
                    {recommendation.name ??
                      `Recommended product #${recommendation.product_id}`}
                  </p>
                </div>
              ))}
            </div>
          </div>
        ) : null}
      </section>
    </main>
  );
}
