"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";

import {
  Cart,
  checkout,
  DEFAULT_USER_ID,
  formatCurrency,
  getCart,
} from "@/lib/api";

export default function CartPage() {
  const router = useRouter();
  const [cart, setCart] = useState<Cart | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isCheckingOut, setIsCheckingOut] = useState(false);

  useEffect(() => {
    let ignore = false;

    async function fetchCart() {
      try {
        setIsLoading(true);
        setError(null);

        const response = await getCart(DEFAULT_USER_ID);

        if (!ignore) {
          setCart(response.data);
        }
      } catch (fetchError) {
        if (!ignore) {
          setError(fetchError instanceof Error ? fetchError.message : "Failed to load cart.");
        }
      } finally {
        if (!ignore) {
          setIsLoading(false);
        }
      }
    }

    void fetchCart();

    return () => {
      ignore = true;
    };
  }, []);

  const total = useMemo(
    () =>
      cart?.items.reduce(
        (sum, item) => sum + Number(item.product.price) * item.quantity,
        0,
      ) ?? 0,
    [cart],
  );

  async function handleCheckout() {
    try {
      setIsCheckingOut(true);
      const response = await checkout(DEFAULT_USER_ID);
      const encoded = encodeURIComponent(JSON.stringify(response.data.recommendations));
      window.dispatchEvent(new Event("cart-updated"));
      router.push(`/order-success?recs=${encoded}`);
    } catch (checkoutError) {
      setError(
        checkoutError instanceof Error ? checkoutError.message : "Checkout failed.",
      );
    } finally {
      setIsCheckingOut(false);
    }
  }

  if (isLoading) {
    return <p className="p-10 text-slate-600">Loading cart...</p>;
  }

  return (
    <main className="min-h-screen bg-slate-50">
      <section className="mx-auto max-w-6xl px-6 py-12 lg:px-10">
        <div className="mb-8">
          <p className="text-sm font-semibold uppercase tracking-widest text-amber-600">
            Cart
          </p>
          <h1 className="mt-2 text-4xl font-semibold text-slate-950">
            Review items before checkout
          </h1>
        </div>

        {error ? (
          <div className="mb-6 rounded-3xl border border-rose-200 bg-rose-50 p-5 text-rose-700">
            {error}
          </div>
        ) : null}

        {!cart || cart.items.length === 0 ? (
          <div className="rounded-4xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
            Your cart is empty.
          </div>
        ) : (
          <div className="grid gap-8 lg:grid-cols-5">
            <div className="space-y-4 lg:col-span-3">
              {cart.items.map((item) => (
                <article
                  key={item.id}
                  className="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div>
                      <h2 className="text-xl font-semibold text-slate-950">
                        {item.product.name}
                      </h2>
                      <p className="mt-2 text-sm text-slate-500">
                        Quantity: {item.quantity}
                      </p>
                    </div>
                    <p className="text-lg font-semibold text-slate-950">
                      {formatCurrency(Number(item.product.price) * item.quantity)}
                    </p>
                  </div>
                </article>
              ))}
            </div>

            <aside className="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
              <p className="text-sm font-semibold uppercase tracking-widest text-slate-400">
                Summary
              </p>
              <div className="mt-6 flex items-center justify-between text-slate-600">
                <span>Items</span>
                <span>{cart.items.length}</span>
              </div>
              <div className="mt-3 flex items-center justify-between text-lg font-semibold text-slate-950">
                <span>Total</span>
                <span>{formatCurrency(total)}</span>
              </div>

              <button
                type="button"
                onClick={handleCheckout}
                disabled={isCheckingOut}
                className="mt-8 w-full rounded-full bg-emerald-600 px-4 py-3 font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-300"
              >
                {isCheckingOut ? "Processing..." : "Checkout"}
              </button>
            </aside>
          </div>
        )}
      </section>
    </main>
  );
}
