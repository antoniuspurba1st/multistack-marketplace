<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  createProduct,
  deleteProduct,
  formatCurrency,
  getProductImageUrl,
  getProducts,
  type Product,
  updateProduct,
  uploadProductImage,
} from './lib/adminApi'

type ProductFormState = {
  user_id: number
  name: string
  description: string
  price: number
  stock: number
}

const products = ref<Product[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const isUploading = ref(false)
const feedback = ref('')
const errorMessage = ref('')
const searchDraft = ref('')
const activeSearch = ref('')
const selectedProductId = ref<number | null>(null)
const selectedUploadProductId = ref<number | null>(null)
const imageInputKey = ref(0)

const form = reactive<ProductFormState>({
  user_id: 1,
  name: '',
  description: '',
  price: 0,
  stock: 0,
})

const selectedProduct = computed(() =>
  products.value.find((product) => product.id === selectedProductId.value) ?? null,
)

const formTitle = computed(() =>
  selectedProduct.value ? `Edit product #${selectedProduct.value.id}` : 'Create product',
)

const productSummary = computed(() => {
  const totalStock = products.value.reduce((sum, product) => sum + product.stock, 0)
  const lowStockCount = products.value.filter((product) => product.stock <= 5).length

  return {
    totalProducts: products.value.length,
    totalStock,
    lowStockCount,
  }
})

function resetForm() {
  selectedProductId.value = null
  form.user_id = 1
  form.name = ''
  form.description = ''
  form.price = 0
  form.stock = 0
}

function fillForm(product: Product) {
  selectedProductId.value = product.id
  form.user_id = product.user_id
  form.name = product.name
  form.description = product.description ?? ''
  form.price = Number(product.price)
  form.stock = product.stock
}

async function loadProducts(search = activeSearch.value) {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getProducts(search)
    products.value = response.data
    activeSearch.value = search
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Failed to load products'
  } finally {
    isLoading.value = false
  }
}

async function submitForm() {
  isSaving.value = true
  errorMessage.value = ''
  feedback.value = ''

  try {
    const payload = {
      user_id: Number(form.user_id),
      name: form.name.trim(),
      description: form.description.trim(),
      price: Number(form.price),
      stock: Number(form.stock),
    }

    if (selectedProductId.value) {
      await updateProduct(selectedProductId.value, payload)
      feedback.value = 'Product updated.'
    } else {
      const created = await createProduct(payload)
      selectedProductId.value = created.id
      feedback.value = 'Product created.'
    }

    await loadProducts(activeSearch.value)

    if (selectedProductId.value) {
      const freshProduct =
        products.value.find((product) => product.id === selectedProductId.value) ?? null

      if (freshProduct) {
        fillForm(freshProduct)
      }
    }

    if (!selectedProduct.value) {
      resetForm()
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Failed to save product'
  } finally {
    isSaving.value = false
  }
}

async function removeProduct(product: Product) {
  const confirmed = window.confirm(`Delete "${product.name}"? This action cannot be undone.`)

  if (!confirmed) {
    return
  }

  errorMessage.value = ''
  feedback.value = ''

  try {
    await deleteProduct(product.id)
    feedback.value = 'Product deleted.'

    if (selectedProductId.value === product.id) {
      resetForm()
    }

    await loadProducts(activeSearch.value)
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Failed to delete product'
  }
}

async function handleUpload(event: Event, productId: number) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (!file) {
    return
  }

  isUploading.value = true
  selectedUploadProductId.value = productId
  errorMessage.value = ''
  feedback.value = ''

  try {
    await uploadProductImage(productId, file)
    feedback.value = 'Image uploaded.'
    imageInputKey.value += 1
    await loadProducts(activeSearch.value)

    const freshProduct = products.value.find((product) => product.id === productId)

    if (freshProduct && selectedProductId.value === productId) {
      fillForm(freshProduct)
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Failed to upload image'
  } finally {
    isUploading.value = false
    selectedUploadProductId.value = null
    target.value = ''
  }
}

function submitSearch() {
  loadProducts(searchDraft.value)
}

onMounted(() => {
  loadProducts()
})
</script>

<template>
  <div class="shell">
    <section class="hero">
      <div>
        <p class="eyebrow">Marketplace Control Room</p>
        <h1>Admin dashboard for catalog operations.</h1>
        <p class="hero-copy">
          Manage products from one panel: create listings, revise stock, and upload cover
          images without leaving the workspace.
        </p>
      </div>

      <div class="stats">
        <article class="stat-card">
          <span>Total products</span>
          <strong>{{ productSummary.totalProducts }}</strong>
        </article>
        <article class="stat-card">
          <span>Total stock</span>
          <strong>{{ productSummary.totalStock }}</strong>
        </article>
        <article class="stat-card">
          <span>Low stock items</span>
          <strong>{{ productSummary.lowStockCount }}</strong>
        </article>
      </div>
    </section>

    <section class="workspace">
      <aside class="editor-panel">
        <div class="panel-header">
          <div>
            <p class="section-label">Editor</p>
            <h2>{{ formTitle }}</h2>
          </div>
          <button v-if="selectedProductId" class="ghost-button" type="button" @click="resetForm">
            New product
          </button>
        </div>

        <form class="product-form" @submit.prevent="submitForm">
          <label>
            Owner user ID
            <input v-model.number="form.user_id" min="1" type="number" required />
          </label>

          <label>
            Product name
            <input v-model="form.name" maxlength="255" placeholder="Wireless Headphones" required />
          </label>

          <label>
            Description
            <textarea
              v-model="form.description"
              placeholder="Short notes for merchandisers and operators"
              rows="5"
            />
          </label>

          <div class="grid-two">
            <label>
              Price
              <input v-model.number="form.price" min="0" step="0.01" type="number" required />
            </label>

            <label>
              Stock
              <input v-model.number="form.stock" min="0" step="1" type="number" required />
            </label>
          </div>

          <div class="form-actions">
            <button class="primary-button" type="submit" :disabled="isSaving">
              {{ isSaving ? 'Saving...' : selectedProductId ? 'Update product' : 'Create product' }}
            </button>
            <p class="hint">Use an existing `user_id` from Laravel. Default is set to `1`.</p>
          </div>
        </form>

        <p v-if="feedback" class="feedback success">{{ feedback }}</p>
        <p v-if="errorMessage" class="feedback error">{{ errorMessage }}</p>
      </aside>

      <section class="catalog-panel">
        <div class="panel-header">
          <div>
            <p class="section-label">Catalog</p>
            <h2>Product list</h2>
          </div>

          <form class="search-form" @submit.prevent="submitSearch">
            <input v-model="searchDraft" placeholder="Search by product name" type="search" />
            <button class="secondary-button" type="submit">Search</button>
          </form>
        </div>

        <p class="list-meta" v-if="activeSearch">
          Showing results for <strong>"{{ activeSearch }}"</strong>
        </p>

        <div v-if="isLoading" class="empty-state">Loading products...</div>
        <div v-else-if="products.length === 0" class="empty-state">
          No products found yet. Create the first one from the editor panel.
        </div>

        <div v-else class="product-list">
          <article
            v-for="product in products"
            :key="product.id"
            class="product-card"
            :class="{ selected: product.id === selectedProductId }"
          >
            <div class="product-card__main">
              <div class="product-card__heading">
                <div>
                  <h3>{{ product.name }}</h3>
                  <p>#{{ product.id }} • owner {{ product.user_id }}</p>
                </div>
                <span class="stock-badge" :class="{ low: product.stock <= 5 }">
                  {{ product.stock }} in stock
                </span>
              </div>

              <p class="product-card__description">
                {{ product.description || 'No description provided yet.' }}
              </p>

              <div class="product-card__meta">
                <strong>{{ formatCurrency(Number(product.price)) }}</strong>
                <span>{{ product.images.length }} image(s)</span>
              </div>

              <div v-if="product.images.length" class="image-strip">
                <img
                  v-for="image in product.images"
                  :key="image.id"
                  :src="getProductImageUrl(image.image_path)"
                  :alt="product.name"
                />
              </div>
            </div>

            <div class="product-card__actions">
              <button class="secondary-button" type="button" @click="fillForm(product)">
                Edit
              </button>

              <label class="upload-button" :class="{ busy: selectedUploadProductId === product.id && isUploading }">
                <input
                  :key="`${imageInputKey}-${product.id}`"
                  accept="image/*"
                  type="file"
                  @change="handleUpload($event, product.id)"
                />
                <span>
                  {{
                    selectedUploadProductId === product.id && isUploading
                      ? 'Uploading...'
                      : 'Upload image'
                  }}
                </span>
              </label>

              <button class="danger-button" type="button" @click="removeProduct(product)">
                Delete
              </button>
            </div>
          </article>
        </div>
      </section>
    </section>
  </div>
</template>
