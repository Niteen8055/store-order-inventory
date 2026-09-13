import axios from 'axios';
import type { CreateOrderPayload, CustomerOrderHistory, ErrorPayload, Order, Product } from '../types/api';

const api = axios.create({
  baseURL: '/api',
})

export function isAxiosError(error: unknown): error is { response?: { data?: ErrorPayload; status?: number } } {
  return axios.isAxiosError(error)
}

export function getErrorMessage(error: unknown, fallback = 'Request failed.'): string {
  if (!isAxiosError(error)) {
    return fallback
  }

  const payload = error.response?.data as ErrorPayload | undefined
  if (!payload) {
    return fallback
  }

  if (payload.error === 'validation_failed') {
    return 'Validation failed. Review the request fields.'
  }

  if (payload.error === 'resource_not_found') {
    return 'The requested resource was not found.'
  }

  if (payload.error === 'insufficient_stock') {
    return 'Insufficient stock for the selected product.'
  }

  return payload.message ?? fallback
}

export async function createOrder(payload: CreateOrderPayload): Promise<Order> {
  const response = await api.post<{ data: Order }>('/v1/orders', payload)
  return response.data.data
}

export async function getCustomerOrders(email: string): Promise<CustomerOrderHistory> {
  const encodedEmail = encodeURIComponent(email.trim())
  const response = await api.get<{ data: CustomerOrderHistory }>(`/v1/customers/${encodedEmail}/orders`)
  return response.data.data
}

export async function getLowStockProducts(threshold = 5): Promise<Product[]> {
  const response = await api.get<{ data: Product[] }>(`/v1/products/low-stock`, {
    params: { threshold },
  })
  return response.data.data
}

export async function getAllProductsForSelection(): Promise<Product[]> {
  return getLowStockProducts(200)
}
