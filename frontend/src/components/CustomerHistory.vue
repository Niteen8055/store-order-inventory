<script setup lang="ts">
import { ref } from 'vue'
import { getCustomerOrders, getErrorMessage, isAxiosError } from '../services/storeApi'
import type { CustomerOrderHistory } from '../types/api'

const email = ref('')
const history = ref<CustomerOrderHistory | null>(null)
const loading = ref(false)
const apiError = ref('')

async function loadHistory(): Promise<void> {
    const normalizedEmail = email.value.trim()
    if (!normalizedEmail) {
        apiError.value = 'Enter a customer email to search.'
        history.value = null
        return
    }

    loading.value = true
    apiError.value = ''

    try {
        history.value = await getCustomerOrders(normalizedEmail)
    } catch (error) {
        history.value = null
        if (isAxiosError(error)) {
            apiError.value = getErrorMessage(error, 'Customer orders could not be loaded.')
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
                <span class="overline">Customer History</span>
                <h2>Order History</h2>
            </div>
        </div>

        <div class="search-row">
            <label class="field search-field">
                <span class="field-label">Customer email</span>
                <input v-model="email" type="email" placeholder="customer@example.com" @keyup.enter="loadHistory" />
            </label>
            <button class="primary-button" type="button" :disabled="loading" @click="loadHistory">
                {{ loading ? 'Loading...' : 'Search' }}
            </button>
        </div>

        <div class="message-block" v-if="apiError">
            <span class="error-text">{{ apiError }}</span>
        </div>

        <div v-if="history" class="history-card">
            <div class="customer-summary">
                <div>
                    <span class="tiny-label">Customer</span>
                    <div class="customer-name">{{ history.name }}</div>
                    <div class="customer-email">{{ history.email }}</div>
                </div>
                <div class="order-count">{{ history.orders.length }} order(s)</div>
            </div>

            <div class="order-list" v-if="history.orders.length">
                <article class="order-card" v-for="order in history.orders" :key="order.id">
                    <div class="order-card-head">
                        <span class="order-id">Order #{{ order.id }}</span>
                        <span class="order-total">{{ order.grand_total }}</span>
                    </div>

                    <div class="order-totals">
                        <span>Subtotal: {{ order.subtotal }}</span>
                        <span>Tax: {{ order.tax }}</span>
                        <span>Grand total: {{ order.grand_total }}</span>
                    </div>

                    <div class="items-list">
                        <div class="item-row" v-for="item in order.items" :key="item.id">
                            <span>{{ item.product?.name ?? `Product #${item.product_id}` }}</span>
                            <span>{{ item.quantity }} × {{ item.unit_price }}</span>
                            <span>{{ item.line_total }}</span>
                        </div>
                    </div>
                </article>
            </div>

            <div class="empty-state" v-if="!history.orders.length">
                No orders found for this customer.
            </div>
        </div>

        <div class="empty-state" v-if="!history && !loading && !apiError">
            Search for an order history record.
        </div>
    </section>
</template>
