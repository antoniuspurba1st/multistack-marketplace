import Image from "next/image";
import Link from "next/link";

import AddToCartButton from "@/components/AddToCartButton";
import { formatCurrency, getProductImageUrl, Product } from "@/lib/api";

type ProductCardProps = {
  product: Product;
};

export default function ProductCard({ product }: ProductCardProps) {
  const primaryImage = getProductImageUrl(product.images[0]?.image_path);

  return (
    <article className="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
      <Link href={`/products/${product.id}`} className="block">
        <div className="relative aspect-4/3 overflow-hidden bg-slate-100">
          <Image
            src={primaryImage}
            alt={product.name}
            fill
            unoptimized
            className="object-cover transition duration-300 group-hover:scale-105"
          />
        </div>
      </Link>

      <div className="space-y-4 p-5">
        <div className="space-y-2">
          <p className="text-xs font-semibold uppercase tracking-widest text-slate-400">
            Marketplace Item
          </p>
          <Link href={`/products/${product.id}`} className="block">
            <h2 className="text-xl font-semibold text-slate-950 transition group-hover:text-amber-600">
              {product.name}
            </h2>
          </Link>
          <p className="line-clamp-2 min-h-10 text-sm text-slate-600">
            {product.description || "A production-style demo item served by the Laravel gateway."}
          </p>
        </div>

        <div className="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm">
          <div>
            <p className="text-slate-500">Price</p>
            <p className="font-semibold text-slate-950">
              {formatCurrency(product.price)}
            </p>
          </div>
          <div className="text-right">
            <p className="text-slate-500">Stock</p>
            <p
              className={`font-semibold ${
                product.stock > 0 ? "text-emerald-600" : "text-rose-600"
              }`}
            >
              {product.stock > 0 ? `${product.stock} available` : "Unavailable"}
            </p>
          </div>
        </div>

        <AddToCartButton productId={product.id} disabled={product.stock <= 0} />
      </div>
    </article>
  );
}
