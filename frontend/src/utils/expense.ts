import type { VehicleType } from '@/types'

export const EXPENSE_TYPES = [
  { value: 'travel' as const,      label: 'Trajet',     icon: '🚗', badgeClass: 'bg-blue-100 text-blue-700',    dotClass: 'bg-blue-500',    activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
  { value: 'remote_work' as const, label: 'Télétravail', icon: '🏠', badgeClass: 'bg-emerald-100 text-emerald-700', dotClass: 'bg-emerald-500', activeClass: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
  { value: 'toll' as const,        label: 'Péage',      icon: '🛣️', badgeClass: 'bg-amber-100 text-amber-700',  dotClass: 'bg-amber-500',   activeClass: 'border-amber-500 bg-amber-50 text-amber-700' },
  { value: 'meal' as const,        label: 'Repas',      icon: '🍽️', badgeClass: 'bg-orange-100 text-orange-700', dotClass: 'bg-orange-500',  activeClass: 'border-orange-500 bg-orange-50 text-orange-700' },
  { value: 'parking' as const,     label: 'Parking',    icon: '🅿️', badgeClass: 'bg-rose-100 text-rose-700',    dotClass: 'bg-rose-500',    activeClass: 'border-rose-500 bg-rose-50 text-rose-700' },
] as const

export const VEHICLE_TYPES: { value: VehicleType; label: string; icon: string }[] = [
  { value: 'car',        label: 'Voiture',     icon: '🚗' },
  { value: 'motorcycle', label: 'Moto',        icon: '🏍️' },
  { value: 'moped',      label: 'Cyclomoteur', icon: '🛵' },
]

const _byType = Object.fromEntries(EXPENSE_TYPES.map(t => [t.value, t]))

export function expenseBadgeClass(type: string): string {
  return (_byType[type]?.badgeClass ?? '')
}

export function expenseDotClass(type: string): string {
  return (_byType[type]?.dotClass ?? 'bg-gray-400')
}

export function expenseIcon(type: string, isElectric = false): string {
  if (type === 'travel' && isElectric) return '⚡'
  return _byType[type]?.icon ?? '📌'
}

export function vehicleLabel(vt: VehicleType): string {
  return VEHICLE_TYPES.find(v => v.value === vt)?.label ?? vt
}
