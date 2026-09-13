export interface Product {
  id: number
  name: string
  code: string
  price: string
  tax_percentage: string
  stock_on_hand: number
}

export interface Customer {
  id: number
  name: string
  email: string
}

export interface OrderItem {
  id: number
  order_id: number
  product_id: number
  product?: Product
  quantity: number
  unit_price: string
  tax_percentage: string
  tax_amount: string
  line_total: string
}

export interface Order {
  id: number
  customer_id: number
  customer?: Customer
  subtotal: string
  tax: string
  grand_total: string
  items: OrderItem[]
}

export interface CustomerOrderHistory {
  id: number
  name: string
  email: string
  orders: Order[]
}

export interface CreateOrderPayload {
  customer: {
    email: string
    name: string
  }
  items: Array<{
    product_id: number
    quantity: number
  }>
}

export interface ErrorPayload {
  message?: string
  error?: string
  errors?: Record<string, string[]>
  product_id?: number
  requested_quantity?: number
  available_quantity?: number
}
