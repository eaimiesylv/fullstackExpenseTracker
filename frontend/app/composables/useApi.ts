import type { AxiosInstance } from 'axios'
import { useNuxtApp } from '#imports'

export function useApi(): AxiosInstance {
  return (useNuxtApp() as any).$api as AxiosInstance
}
