export type ExpenseType = 'travel' | 'remote_work' | 'toll' | 'meal'
export type VehicleType = 'car' | 'motorcycle' | 'moped'

export interface Person {
  id: string
  firstName: string
  lastName: string
  fullName: string
  email: string | null
  favorite: boolean
  createdAt: string
}

export interface BaseExpense {
  id: string
  personId: string
  type: ExpenseType
  typeLabel: string
  date: string
  description: string | null
  amount: number
  createdAt: string
}

export interface TravelExpense extends BaseExpense {
  type: 'travel'
  departure: string | null
  arrival: string | null
  distanceKm: number
  vehiclePower: number | null
  roundTrip: boolean
  vehicleType: VehicleType
  isElectric: boolean
}

export interface RemoteWorkExpense extends BaseExpense {
  type: 'remote_work'
}

export interface TollExpense extends BaseExpense {
  type: 'toll'
  tollAmount: number
  departure: string | null
  arrival: string | null
}

export interface MealExpense extends BaseExpense {
  type: 'meal'
  mealAmount: number
  homeMealValue: number
  employerTicketContribution: number
  withoutReceipt: boolean
}

export type Expense = TravelExpense | RemoteWorkExpense | TollExpense | MealExpense

export interface ExpenseSummary {
  personId: string
  year: number
  travel: { trips: TripData[]; totalKm: number; deduction: number }
  remoteWork: { days: number; dailyAllowance: number; deduction: number }
  toll: { entries: number; deduction: number }
  meal: { entries: number; homeMealValue: number; deduction: number }
  total: number
}

export interface TripData {
  distanceKm: number
  vehiclePower: number | null
  vehicleType: VehicleType
  isElectric: boolean
  date: string
  departure: string | null
  arrival: string | null
  description: string | null
  roundTrip: boolean
}

export type CreateExpenseDto =
  | { type: 'travel'; personId: string; date: string; distanceKm: number; vehiclePower?: number; vehicleType?: VehicleType; isElectric?: boolean; departure?: string; arrival?: string; description?: string; roundTrip?: boolean }
  | { type: 'remote_work'; personId: string; date: string; description?: string }
  | { type: 'toll'; personId: string; date: string; amount: number; departure?: string; arrival?: string; description?: string }
  | { type: 'meal'; personId: string; date: string; mealAmount: number; employerTicketContribution?: number; withoutReceipt?: boolean; description?: string }

export type UpdateExpenseDto = Record<string, unknown>

export interface FavoriteRoute {
  id: string
  personId: string
  name: string
  departure: string
  arrival: string
  vehicleType: VehicleType
  vehiclePower: number | null
  isElectric: boolean
  roundTrip: boolean
  createdAt: string
}

export type CreateFavoriteRouteDto = Omit<FavoriteRoute, 'id' | 'personId' | 'createdAt'>