import http from './http'

export const billingApi = {
  createCheckout: (plan: 'monthly' | 'yearly') =>
    http.post<{ url: string }>('/billing/checkout', { plan }).then(r => r.data),
  openPortal: () =>
    http.post<{ url: string }>('/billing/portal').then(r => r.data),
}
