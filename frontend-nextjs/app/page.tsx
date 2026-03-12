import Link from "next/link";

import ProductCard from "@/components/ProductCard";
import { getProducts } from "@/lib/api";

const technologyBadges = ["Next.js", "Laravel", "Django", "Go", "Node.js"];

export default async function HomePage() {
  const response = await getProducts({ page: 1 });
  const featuredProducts = response.data.data.slice(0, 3);

  return (
    <main className="bg-home-hero min-h-screen">
      <section className="mx-auto max-w-7xl px-6 py-14 lg:px-10">
        <div className="grid gap-10 rounded-5xl bg-slate-950 px-8 py-10 text-white shadow-2xl shadow-slate-300 lg:grid-cols-5 lg:px-12">
          <div className="space-y-6 lg:col-span-3">
            <p className="text-sm font-semibold uppercase tracking-widest text-amber-300">
              Polyglot Commerce Demo
            </p>
            <h1 className="max-w-3xl text-5xl font-semibold leading-tight">
              Polyglot Microservices Marketplace
            </h1>
            <p className="max-w-2xl text-lg leading-8 text-slate-300">
              A demo storefront powered by a Next.js frontend, Laravel API gateway,
              Django recommendation engine, Go auth service, and future Node.js chat
              service.
            </p>

            <div className="flex flex-wrap gap-3">
              {technologyBadges.map((badge) => (
                <span
                  key={badge}
                  className="rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white/90"
                >
                  {badge}
                </span>
              ))}
            </div>

            <div className="flex flex-wrap gap-4 pt-2">
              <Link
                href="/products"
                className="rounded-full bg-amber-400 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-300"
              >
                Explore products
              </Link>
              <Link
                href="/orders"
                className="rounded-full border border-white/20 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                View order history
              </Link>
            </div>
          </div>

          <div className="grid gap-4 rounded-4xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
            <div className="rounded-3xl bg-white/10 p-5">
              <p className="text-sm text-slate-300">Architecture</p>
              <p className="mt-2 text-2xl font-semibold">Frontend + Gateway + Services</p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="rounded-3xl bg-white/10 p-5">
                <p className="text-sm text-slate-300">API Flow</p>
                <p className="mt-2 text-lg font-semibold">Products, cart, checkout, orders</p>
              </div>
              <div className="rounded-3xl bg-white/10 p-5">
                <p className="text-sm text-slate-300">Marketplace UX</p>
                <p className="mt-2 text-lg font-semibold">Search, pagination, recommendations</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-6 pb-14 lg:px-10">
        <div className="mb-8 flex items-end justify-between gap-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-widest text-sky-600">
              Featured Products
            </p>
            <h2 className="mt-2 text-3xl font-semibold text-slate-950">
              Live data from the Laravel gateway
            </h2>
          </div>

          <Link
            href="/products"
            className="rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-sky-300 hover:text-sky-700"
          >
            See all products
          </Link>
        </div>

        <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
          {featuredProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>
    </main>
  );
}
