const API_URL = "http://localhost:8000/api";
const STORAGE_URL = "http://localhost:8000/storage";

export const DEFAULT_USER_ID = 1;

export type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
};

export type ProductImage = {
  id?: number;
  image_path: string;
};

export type Product = {
  id: number;
  user_id: number;
  name: string;
  description: string | null;
  price: number;
  stock: number;
  created_at?: string;
  updated_at?: string;
  images: ProductImage[];
};

export type PaginatedResponse<T> = {
  current_page: number;
  data: T[];
  last_page: number;
  per_page: number;
  total: number;
};

export type CartItem = {
  id: number;
  cart_id: number;
  product_id: number;
  quantity: number;
  product: Product;
};

export type Cart = {
  id: number;
  user_id: number;
  items: CartItem[];
};

export type OrderItem = {
  id: number;
  quantity: number;
  price: number;
  product: Product;
};

export type Order = {
  id: number;
  user_id: number;
  total_price: number;
  created_at: string;
  updated_at: string;
  items: OrderItem[];
};

export type Recommendation = {
  product_id: number;
  name?: string;
};

type RequestOptions = RequestInit & {
  expectEnvelope?: boolean;
};

type ValidationErrorResponse = {
  message?: string;
};

async function request<T>(
  path: string,
  { expectEnvelope = true, headers, ...init }: RequestOptions = {},
): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...headers,
    },
    cache: init.cache ?? "no-store",
  });

  const payload = (await response.json()) as T | ValidationErrorResponse;

  if (!response.ok) {
    const message =
      typeof payload === "object" && payload !== null && "message" in payload
        ? payload.message ?? "Request failed"
        : "Request failed";

    throw new Error(message);
  }

  if (expectEnvelope) {
    const envelope = payload as ApiEnvelope<unknown>;

    if (!envelope.success) {
      throw new Error(envelope.message || "Request failed");
    }
  }

  return payload as T;
}

export function getProductImageUrl(imagePath?: string): string {
  return imagePath ? `${STORAGE_URL}/${imagePath}` : "/product-placeholder.svg";
}

export function formatCurrency(price: number): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(price);
}

export async function getProducts(params?: {
  page?: number;
  search?: string;
}): Promise<ApiEnvelope<PaginatedResponse<Product>>> {
  const searchParams = new URLSearchParams();

  if (params?.page) {
    searchParams.set("page", String(params.page));
  }

  if (params?.search) {
    searchParams.set("search", params.search);
  }

  const query = searchParams.toString();

  return request<ApiEnvelope<PaginatedResponse<Product>>>(
    `/products${query ? `?${query}` : ""}`,
  );
}

export async function getProduct(id: number): Promise<ApiEnvelope<Product>> {
  return request<ApiEnvelope<Product>>(`/products/${id}`);
}

export async function addToCart(
  productId: number,
  userId: number,
): Promise<ApiEnvelope<CartItem>> {
  return request<ApiEnvelope<CartItem>>("/cart/add", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      product_id: productId,
      user_id: userId,
      quantity: 1,
    }),
  });
}

export async function getCart(userId: number): Promise<ApiEnvelope<Cart | null>> {
  return request<ApiEnvelope<Cart | null>>(`/cart/${userId}`);
}

export async function checkout(userId: number): Promise<
  ApiEnvelope<{
    order: Order;
    recommendations: Recommendation[];
  }>
> {
  return request<
    ApiEnvelope<{
      order: Order;
      recommendations: Recommendation[];
    }>
  >("/checkout", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Idempotency-Key": crypto.randomUUID(),
    },
    body: JSON.stringify({
      user_id: userId,
    }),
  });
}

export async function getOrders(userId: number): Promise<ApiEnvelope<Order[]>> {
  return request<ApiEnvelope<Order[]>>(`/orders/${userId}`);
}
