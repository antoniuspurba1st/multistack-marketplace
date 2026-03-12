"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { DEFAULT_USER_ID, getCart } from "@/lib/api";

const links = [
  { href: "/products", label: "Products" },
  { href: "/cart", label: "Cart" },
  { href: "/orders", label: "Orders" },
];

export default function Navbar() {
  const pathname = usePathname();
  const [cartCount, setCartCount] = useState(0);

  useEffect(() => {
    let ignore = false;

    async function loadCartCount() {
      try {
        const response = await getCart(DEFAULT_USER_ID);
        const count =
          response.data?.items.reduce((total, item) => total + item.quantity, 0) ?? 0;

        if (!ignore) {
          setCartCount(count);
        }
      } catch {
        if (!ignore) {
          setCartCount(0);
        }
      }
    }

    void loadCartCount();

    window.addEventListener("cart-updated", loadCartCount);

    return () => {
      ignore = true;
      window.removeEventListener("cart-updated", loadCartCount);
    };
  }, [pathname]);

  return (
    <nav className="sticky top-0 z-50 border-b border-white/50 bg-white/80 backdrop-blur-xl">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-10">
        <Link href="/" className="flex items-center gap-3 text-slate-950">
          <span className="grid h-10 w-10 place-items-center rounded-2xl bg-slate-950 text-sm font-bold text-white">
            MP
          </span>
          <div>
            <p className="text-xs font-semibold uppercase tracking-widest text-slate-400">
              Marketplace
            </p>
            <p className="text-lg font-semibold">Polyglot Demo</p>
          </div>
        </Link>

        <div className="flex items-center gap-2 sm:gap-3">
          {links.map((link) => {
            const isActive = pathname === link.href || pathname.startsWith(`${link.href}/`);

            return (
              <Link
                key={link.href}
                href={link.href}
                className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                  isActive
                    ? "bg-slate-950 text-white"
                    : "text-slate-600 hover:bg-slate-100 hover:text-slate-950"
                }`}
              >
                {link.label}
                {link.href === "/cart" ? (
                  <span
                    className={`ml-2 inline-flex min-w-6 justify-center rounded-full px-2 py-0.5 text-xs ${
                      isActive
                        ? "bg-white/20 text-white"
                        : "bg-amber-100 text-amber-700"
                    }`}
                  >
                    {cartCount}
                  </span>
                ) : null}
              </Link>
            );
          })}
        </div>
      </div>
    </nav>
  );
}
