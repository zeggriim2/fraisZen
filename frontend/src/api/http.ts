import axios from 'axios'

const http = axios.create({ baseURL: '/api' })

http.interceptors.request.use(config => {
  const token = localStorage.getItem('jwt_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

http.interceptors.response.use(
  undefined,
  err => {
    if (err.response?.status === 401 && window.location.pathname !== '/login') {
      localStorage.removeItem('jwt_token')
      window.location.href = '/login'
    }
    if (err.response?.status === 402 && window.location.pathname !== '/pricing') {
      window.location.href = '/pricing'
    }
    return Promise.reject(err)
  }
)

export default http
