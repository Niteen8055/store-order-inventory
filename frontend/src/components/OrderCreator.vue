<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { createOrder, getAllProductsForSelection, getErrorMessage, isAxiosError } from '../services/storeApi'
import type { CreateOrderPayload, ErrorPayload, Product } from '../types/api'

interface Line {
    product_id: number
    quantity: number
}

const products = ref<Product[]>([])
const lineItems = ref<Line[]>([{ product_id: 0, quantity: 1 }])
const customerName = ref('')
const customerEmail = ref('')
const loadingProducts = ref(false)
const submitting = ref(false)
const successMessage = ref('')
const apiError = ref('')
const validationErrors = ref<Record<string, string[]>>({})

const selectedProducts = computed(() => {
    return lineItems.value
        .map((line) => {
            const product = products.value.find((item) => item.id === line.product_id)
            if (!product || line.quantity < 1) {
                return null
            }

            return {
                product,
                quantity: line.quantity,
            }
        })
        .filter((entry): entry is { product: Product; quantity: number } => Boolean(entry))
})

onMounted(async () => {
    await loadProducts()
})

async function loadProducts(): Promise<void> {
    loadingProducts.value = true
    try {
        products.value = await getAllProductsForSelection()
    } catch (error) {
        apiError.value = getErrorMessage(error, 'Unable to load the product catalogue.')
    } finally {
        loadingProducts.value = false
    }
}

function addLine(): void {
    lineItems.value.push({ product_id: 0, quantity: 1 })
}

function removeLine(index: number): void {
    if (lineItems.value.length === 1) {
        lineItems.value = [{ product_id: 0, quantity: 1 }]
        return
    }

    lineItems.value.splice(index, 1)
}

async function submitOrder(): Promise<void> {
    apiError.value = ''
    validationErrors.value = {}
    successMessage.value = ''

    const payload: CreateOrderPayload = {
        customer: {
            name: customerName.value.trim(),
            email: customerEmail.value.trim(),
        },
        items: lineItems.value
            .filter((line) => line.product_id > 0 && line.quantity >= 1)
            .map((line) => ({
                product_id: line.product_id,
                quantity: line.quantity,
            })),
    }

    if (payload.items.length === 0) {
        apiError.value = 'Choose at least one product line before submitting.'
        return
    }

    if (!payload.customer.name || !payload.customer.email) {
        apiError.value = 'Customer name and email are required.'
        return
    }

    submitting.value = true

    try {
        const createdOrder = await createOrder(payload)
        successMessage.value = `Order ${createdOrder.id} created successfully.`
        customerName.value = ''
        customerEmail.value = ''
        lineItems.value = [{ product_id: 0, quantity: 1 }]
    } catch (error) {
        if (isAxiosError(error)) {
            const data = error.response?.data as ErrorPayload | undefined
            if (data?.error === 'validation_failed' && data?.errors) {
                validationErrors.value = data.errors
                apiError.value = getErrorMessage(error, 'Validation failed. Review the highlighted fields.')
                return
            }

            if (data?.error === 'insufficient_stock') {
                apiError.value = getErrorMessage(error, 'Insufficient stock for the selected product.')
                return
            }

            apiError.value = getErrorMessage(error, 'The order could not be created.')
            return
        }

        apiError.value = 'Request failed. Please try again.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <section class="panel order-panel">
        <div class="panel-title">
            <div>
                <span class="overline">Create Order</span>
                <h2>New Order</h2>
            </div>
            <span class="badge" v-if="submitting">Submitting...</span>
        </div>

        <div class="message-block" v-if="apiError">
            <span class="error-text">{{ apiError }}</span>
        </div>

        <div class="message-block success-block" v-if="successMessage">
            <span class="success-text">{{ successMessage }}</span>
        </div>

        <div class="form-grid">
            <label class="field">
                <span class="field-label">Customer name</span>
                <input v-model="customerName" type="text" autocomplete="name" placeholder="Customer name" />
            </label>

            <label class="field">
                <span class="field-label">Customer email</span>
                <input v-model="customerEmail" type="email" autocomplete="email" placeholder="customer@example.com" />
            </label>
        </div>

        <div class="line-list">
            <div class="line-row" v-for="(line, index) in lineItems" :key="index">
                <label class="field product-select">
                    <span class="field-label">Product</span>
                    <select v-model.number="line.product_id">
                        <option :value="0">Choose a product</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }} ({{ product.stock_on_hand }} in stock)
                        </option>
                    </select>
                </label>

                <label class="field quantity-input">
                    <span class="field-label">Quantity</span>
                    <input v-model.number="line.quantity" type="number" min="1" />
                </label>

                <button class="ghost-button remove-button" type="button" @click="removeLine(index)">
                    Remove
                </button>
            </div>
        </div>

        <div class="inline-actions">
            <button class="secondary-button" type="button" @click="addLine">
                + Add product line
            </button>
            <button class="primary-button" type="button" :disabled="submitting" @click="submitOrder">
                {{ submitting ? 'Submitting...' : 'Submit Order' }}
            </button>
        </div>

        <div class="validation-errors" v-if="Object.keys(validationErrors).length">
            <ul>
                <li v-for="(messages, key) in validationErrors" :key="key">
                    <span>{{ key }}</span>
                    <span>{{ messages.join(', ') }}</span>
                </li>
            </ul>
        </div>

        <div class="preview-wrapper" v-if="selectedProducts.length">
            <h3>Order preview</h3>
            <div class="preview-list">
                <div class="preview-item" v-for="entry in selectedProducts" :key="entry.product.id">
                    <span>{{ entry.product.name }}</span>
                    <span>{{ entry.quantity }} × {{ entry.product.price }}</span>
                    <span>{{ entry.product.tax_percentage }}% tax</span>
                </div>
            </div>
        </div>
    </section>
</template>
