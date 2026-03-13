const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'
const STORAGE_URL = import.meta.env.VITE_STORAGE_URL ?? 'http://localhost:8000/storage'

export type ApiEnvelope<T> = {
  success: boolean
  message: string
  data: T
}

export type ProductImage = {
  id: number
  product_id: number
  image_path: string
  created_at?: string
  updated_at?: string
}

export type Product = {
  id: number
  user_id: number
  name: string
  description: string | null
  price: number
  stock: number
  created_at: string
  updated_at: string
  images: ProductImage[]
}

export type PaginatedResponse<T> = {
  current_page: number
  data: T[]
  last_page: number
  per_page: number
  total: number
}

type ValidationError = {
  message?: string
  errors?: Record<string, string[]>
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...(init?.headers ?? {}),
    },
  })

  const payload = (await response.json()) as T | ValidationError

  if (!response.ok) {
    const fallbackMessage =
      typeof payload === 'object' && payload !== null && 'message' in payload
        ? payload.message
        : null

    const validationMessage =
      typeof payload === 'object' &&
      payload !== null &&
      'errors' in payload &&
      payload.errors
        ? Object.values(payload.errors)[0]?.[0]
        : null

    throw new Error(validationMessage || fallbackMessage || 'Request failed')
  }

  return payload as T
}

export function getProductImageUrl(imagePath?: string): string {
  return imagePath ? `${STORAGE_URL}/${imagePath}` : ''
}

export function formatCurrency(value: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}

export async function getProducts(search = ''): Promise<PaginatedResponse<Product>> {
  const params = new URLSearchParams()

  if (search.trim()) {
    params.set('search', search.trim())
  }

  const query = params.toString()
  const response = await request<ApiEnvelope<PaginatedResponse<Product>>>(
    `/products${query ? `?${query}` : ''}`,
  )

  return response.data
}

export async function createProduct(payload: {
  user_id: number
  name: string
  description: string
  price: number
  stock: number
}): Promise<Product> {
  const response = await request<ApiEnvelope<Product>>('/products', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })

  return response.data
}

export async function updateProduct(
  productId: number,
  payload: {
    user_id: number
    name: string
    description: string
    price: number
    stock: number
  },
): Promise<Product> {
  const response = await request<ApiEnvelope<Product>>(`/products/${productId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })

  return response.data
}

export async function deleteProduct(productId: number): Promise<void> {
  await request<ApiEnvelope<null>>(`/products/${productId}`, {
    method: 'DELETE',
  })
}

export async function uploadProductImage(productId: number, file: File): Promise<ProductImage> {
  const body = new FormData()
  body.append('product_id', String(productId))
  body.append('image', file)

  const response = await request<ApiEnvelope<ProductImage>>('/product/upload', {
    method: 'POST',
    body,
  })

  return response.data
}
