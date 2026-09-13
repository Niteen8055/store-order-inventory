<script setup lang="ts">
import { ref } from 'vue'
import CustomerHistory from './components/CustomerHistory.vue'
import LowStockProducts from './components/LowStockProducts.vue'
import OrderCreator from './components/OrderCreator.vue'

const activeTab = ref<'orders' | 'history' | 'stock'>('orders')
</script>

<template>
  <main class="app-shell">
    <header class="app-header">
      <div>
        <span class="app-kicker">Store Order & Inventory</span>
        <h1>Operations Console</h1>
      </div>
      <nav class="tabs">
        <button class="tab-button" :class="{ active: activeTab === 'orders' }" @click="activeTab = 'orders'">
          Create Order
        </button>
        <button class="tab-button" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
          Customer History
        </button>
        <button class="tab-button" :class="{ active: activeTab === 'stock' }" @click="activeTab = 'stock'">
          Low Stock
        </button>
      </nav>
    </header>

    <section class="content">
      <OrderCreator v-if="activeTab === 'orders'" />
      <CustomerHistory v-else-if="activeTab === 'history'" />
      <LowStockProducts v-else />
    </section>
  </main>
</template>
