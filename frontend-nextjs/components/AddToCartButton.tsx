"use client";

import { useState } from "react";

import { addToCart, DEFAULT_USER_ID } from "@/lib/api";

type AddToCartButtonProps = {
  productId: number;
  disabled?: boolean;
};

export default function AddToCartButton({
  productId,
  disabled = false,
}: AddToCartButtonProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [status, setStatus] = useState<string | null>(null);

  async function handleAddToCart() {
    try {
      setIsSubmitting(true);
      setStatus(null);
      await addToCart(productId, DEFAULT_USER_ID);
      window.dispatchEvent(new Event("cart-updated"));
      setStatus("Added to cart");
    } catch (error) {
      setStatus(error instanceof Error ? error.message : "Failed to add to cart");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="space-y-2">
      <button
        type="button"
        onClick={handleAddToCart}
        disabled={disabled || isSubmitting}
        className="inline-flex w-full items-center justify-center rounded-full bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
      >
        {isSubmitting ? "Adding..." : disabled ? "Out of stock" : "Add to cart"}
      </button>

      {status ? <p className="text-sm text-slate-600">{status}</p> : null}
    </div>
  );
}
