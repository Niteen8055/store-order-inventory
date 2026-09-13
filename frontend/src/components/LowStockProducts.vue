<script setup lang="ts">
import { ref } from 'vue'
import { getLowStockProducts, isAxiosError } from '../services/storeApi'
import type { Product } from '../types/api'

const threshold = ref(5)
const products = ref<Product[]>([])
const loading = ref(false)
const apiError = ref('')

async function loadLowStock(): Promise<void> {
  loading.value = true
  apiError.value = ''

  try {
    products.value = await getLowStockProducts(Number(threshold.value) || 5)
  } catch (error) {
    products.value = []
    if (isAxiosError(error)) {
      apiError.value = error.response?.data?.message ?? 'Low-stock enquiry failed.'
      return
    }

    apiError.value = 'Network request failed.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="panel">
    <div class="panel-title">
      <div>
        <span class="overline">Low Stock</span>
        <h2>Inventory Watch</h2>
      </div>
    </div>

    <div class="search-row">
      <label class="field search-field">
        <span class="field-label">Stock threshold</span>
        <input v-model.number="threshold" type="number" min="1" placeholder="5" />
      </label>
      <button class="primary-button" type="button" :disabled="loading" @click="loadLowStock">
        {{ loading ? 'Checking...' : 'Check Low Stock' }}
      </button>
    </div>

    <div class="message-block" v-if="apiError">
      <span class="error-text">{{ apiError }}</span>
    </div>

    <div class="stock-list" v-if="products.length">
      <div class="stock-row" v-for="product in products" :key="product.id">
        <span>{{ product.name }}</span>
        <span>{{ product.code }}</span>
        <span>{{ product.stock_on_hand }} units</span>
      </div>
    </div>

    <div class="empty-state" v-if="!loading && !products.length && !apiError">
      No products below this threshold.
    </div>
  </section>
</template>
