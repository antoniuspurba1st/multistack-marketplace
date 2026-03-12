"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";

import PaginationControls from "@/components/PaginationControls";
import ProductCard from "@/components/ProductCard";
import ProductGridSkeleton from "@/components/ProductGridSkeleton";
import { getProducts, PaginatedResponse, Product } from "@/lib/api";

type ProductsCatalogProps = {
  initialPage: number;
  initialSearch: string;
};

function buildProductsUrl(pathname: string, search: string, page: number): string {
  const params = new URLSearchParams();

  if (search) {
    params.set("search", search);
  }

  if (page > 1) {
    params.set("page", String(page));
  }

  const query = params.toString();

  return query ? `${pathname}?${query}` : pathname;
}

export default function ProductsCatalog({
  initialPage,
  initialSearch,
}: ProductsCatalogProps) {
  const router = useRouter();
  const pathname = usePathname();

  const [searchInput, setSearchInput] = useState(initialSearch);
  const [currentPage, setCurrentPage] = useState(initialPage);
  const [productsPage, setProductsPage] = useState<PaginatedResponse<Product> | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setSearchInput(initialSearch);
    setCurrentPage(initialPage);
  }, [initialPage, initialSearch]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (searchInput === initialSearch) {
        return;
      }

      const trimmedSearch = searchInput.trim();
      setCurrentPage(1);
      router.replace(buildProductsUrl(pathname, trimmedSearch, 1));
    }, 300);

    return () => window.clearTimeout(timer);
  }, [initialSearch, pathname, router, searchInput]);

  useEffect(() => {
    let ignore = false;

    async function loadProducts() {
      try {
        setIsLoading(true);
        setError(null);

        const response = await getProducts({
          page: currentPage,
          search: initialSearch || undefined,
        });

        if (!ignore) {
          setProductsPage(response.data);
        }
      } catch (loadError) {
        if (!ignore) {
          setError(
            loadError instanceof Error ? loadError.message : "Failed to load products.",
          );
        }
      } finally {
        if (!ignore) {
          setIsLoading(false);
        }
      }
    }

    void loadProducts();

    return () => {
      ignore = true;
    };
  }, [currentPage, initialSearch]);

  function handlePageChange(page: number) {
    if (!productsPage || page < 1 || page > productsPage.last_page) {
      return;
    }

    setCurrentPage(page);
    router.push(buildProductsUrl(pathname, initialSearch, page));
  }

  const products = productsPage?.data ?? [];

  return (
    <main className="bg-products-catalog min-h-screen">
      <section className="mx-auto max-w-7xl px-6 py-12 lg:px-10">
        <div className="mb-8 flex flex-col gap-5 rounded-4xl border border-white/70 bg-white/80 p-8 shadow-lg shadow-slate-200/60 backdrop-blur lg:flex-row lg:items-end lg:justify-between">
          <div className="max-w-2xl space-y-3">
            <p className="text-sm font-semibold uppercase tracking-widest text-amber-600">
              Product Catalog
            </p>
            <h1 className="text-4xl font-semibold text-slate-950">
              Browse marketplace products backed by the Laravel API gateway.
            </h1>
            <p className="text-base text-slate-600">
              Search products, inspect stock levels, and navigate paginated data without
              leaving the page.
            </p>
          </div>

          <label className="block w-full max-w-md">
            <span className="mb-2 block text-sm font-medium text-slate-700">
              Search products
            </span>
            <input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search by product name"
              className="w-full rounded-full border border-slate-200 bg-white px-5 py-3 text-slate-950 outline-none ring-0 transition placeholder:text-slate-400 focus:border-amber-400"
            />
          </label>
        </div>

        {isLoading && !productsPage ? <ProductGridSkeleton /> : null}

        {error ? (
          <div className="rounded-4xl border border-rose-200 bg-rose-50 p-6 text-rose-700">
            <p className="text-lg font-semibold">Unable to load products</p>
            <p className="mt-2 text-sm">{error}</p>
            <button
              type="button"
              onClick={() => router.refresh()}
              className="mt-4 rounded-full bg-rose-600 px-4 py-2 text-sm font-semibold text-white"
            >
              Try again
            </button>
          </div>
        ) : null}

        {!error && productsPage ? (
          <div className="space-y-8">
            <div className="flex items-center justify-between text-sm text-slate-600">
              <p>
                Showing <span className="font-semibold text-slate-950">{products.length}</span>{" "}
                of{" "}
                <span className="font-semibold text-slate-950">{productsPage.total}</span>{" "}
                products
              </p>
              <p>
                Page {productsPage.current_page} of {productsPage.last_page}
              </p>
            </div>

            {products.length === 0 ? (
              <div className="rounded-4xl border border-dashed border-slate-300 bg-white/70 p-10 text-center text-slate-600">
                No products matched your search.
              </div>
            ) : (
              <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                {products.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            )}

            <PaginationControls
              currentPage={productsPage.current_page}
              lastPage={productsPage.last_page}
              onPageChange={handlePageChange}
            />
          </div>
        ) : null}
      </section>
    </main>
  );
}
