"use client";

import { useEffect, useState } from "react";

import { DEFAULT_USER_ID, formatCurrency, getOrders, Order } from "@/lib/api";

export default function OrdersPage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let ignore = false;

    async function fetchOrders() {
      try {
        setIsLoading(true);
        setError(null);

        const response = await getOrders(DEFAULT_USER_ID);

        if (!ignore) {
          setOrders(response.data);
        }
      } catch (fetchError) {
        if (!ignore) {
          setError(
            fetchError instanceof Error ? fetchError.message : "Failed to load orders.",
          );
        }
      } finally {
        if (!ignore) {
          setIsLoading(false);
        }
      }
    }

    void fetchOrders();

    return () => {
      ignore = true;
    };
  }, []);

  return (
    <main className="min-h-screen bg-slate-50">
      <section className="mx-auto max-w-6xl px-6 py-12 lg:px-10">
        <div className="mb-8">
          <p className="text-sm font-semibold uppercase tracking-widest text-sky-600">
            Orders
          </p>
          <h1 className="mt-2 text-4xl font-semibold text-slate-950">Order history</h1>
        </div>

        {isLoading ? <p className="text-slate-600">Loading orders...</p> : null}

        {error ? (
          <div className="rounded-3xl border border-rose-200 bg-rose-50 p-5 text-rose-700">
            {error}
          </div>
        ) : null}

        {!isLoading && !error && orders.length === 0 ? (
          <div className="rounded-4xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
            No orders yet.
          </div>
        ) : null}

        <div className="space-y-6">
          {orders.map((order) => (
            <article
              key={order.id}
              className="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm"
            >
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 className="text-2xl font-semibold text-slate-950">
                    Order #{order.id}
                  </h2>
                  <p className="mt-1 text-sm text-slate-500">{order.created_at}</p>
                </div>
                <p className="text-lg font-semibold text-slate-950">
                  {formatCurrency(Number(order.total_price))}
                </p>
              </div>

              <div className="mt-5 divide-y divide-slate-100 rounded-2xl bg-slate-50">
                {order.items.map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center justify-between gap-4 px-4 py-3"
                  >
                    <p className="font-medium text-slate-700">{item.product.name}</p>
                    <p className="text-sm text-slate-500">Qty {item.quantity}</p>
                  </div>
                ))}
              </div>
            </article>
          ))}
        </div>
      </section>
    </main>
  );
}
