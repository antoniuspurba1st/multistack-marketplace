import Image from "next/image";
import Link from "next/link";

import AddToCartButton from "@/components/AddToCartButton";
import { formatCurrency, getProduct, getProductImageUrl, ProductImage } from "@/lib/api";

type ProductDetailPageProps = {
  params: Promise<{
    id: string;
  }>;
};

export default async function ProductDetailPage({
  params,
}: ProductDetailPageProps) {
  const { id } = await params;
  const response = await getProduct(Number(id));
  const product = response.data;

  const gallery: ProductImage[] =
    product.images.length > 0 ? product.images : [{ image_path: "" }];

  return (
    <main className="bg-product-detail min-h-screen">
      <section className="mx-auto max-w-7xl px-6 py-12 lg:px-10">
        <Link
          href="/products"
          className="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-amber-300 hover:text-amber-700"
        >
          Back to products
        </Link>

        <div className="mt-6 grid gap-10 rounded-4xl border border-white/70 bg-white/85 p-8 shadow-xl shadow-slate-200/60 lg:grid-cols-5">
          <div className="space-y-4 lg:col-span-3">
            <div className="relative aspect-4/3 overflow-hidden rounded-4xl bg-slate-100">
              <Image
                src={getProductImageUrl(gallery[0]?.image_path)}
                alt={product.name}
                fill
                unoptimized
                className="object-cover"
              />
            </div>

            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
              {gallery.map((image, index) => (
                <div
                  key={`${image.image_path || "fallback"}-${index}`}
                  className="relative aspect-square overflow-hidden rounded-2xl border border-slate-200 bg-slate-100"
                >
                  <Image
                    src={getProductImageUrl(image.image_path)}
                    alt={`${product.name} preview ${index + 1}`}
                    fill
                    unoptimized
                    className="object-cover"
                  />
                </div>
              ))}
            </div>
          </div>

          <div className="flex flex-col justify-between space-y-8 lg:col-span-2">
            <div className="space-y-5">
              <p className="text-sm font-semibold uppercase tracking-widest text-amber-600">
                Product Detail
              </p>
              <h1 className="text-4xl font-semibold text-slate-950">{product.name}</h1>
              <p className="text-3xl font-semibold text-slate-950">
                {formatCurrency(product.price)}
              </p>

              <div className="rounded-3xl bg-slate-50 p-5">
                <p className="text-sm font-medium text-slate-500">Description</p>
                <p className="mt-2 leading-7 text-slate-700">
                  {product.description ||
                    "This product is available through the marketplace gateway and demonstrates the product-detail flow across the polyglot stack."}
                </p>
              </div>
            </div>

            <div className="space-y-4 rounded-4xl border border-slate-200 bg-slate-50 p-6">
              <div className="flex items-center justify-between">
                <span className="text-slate-500">Stock status</span>
                <span
                  className={`font-semibold ${
                    product.stock > 0 ? "text-emerald-600" : "text-rose-600"
                  }`}
                >
                  {product.stock > 0 ? `${product.stock} available` : "Out of stock"}
                </span>
              </div>

              <AddToCartButton productId={product.id} disabled={product.stock <= 0} />
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
