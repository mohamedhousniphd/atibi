
export type Language = 'en' | 'fr' | 'ar';
export type Currency = 'MAD' | 'USD' | 'EUR';
export type CategoryType = 'need' | 'want' | 'saving' | 'fixed' | 'unexpected';
export type Theme = 'light' | 'dark' | 'system';

export interface Transaction {
  id: string;
  amount: number;
  description: string;
  categoryId?: string; // Optional for income
  type: 'expense' | 'income';
  date: string;
  excludeFromBudget?: boolean; // For spending directly from savings goals
}

export interface Category {
  id: string;
  name: string; // Key for translation or raw string
  limit: number;
  spent: number;
  type: CategoryType;
  icon: string;
  tags?: string[]; // Pre-defined quick select options
}

export interface SavingsGoal {
  id: string;
  name: string;
  targetAmount: number;
  currentAmount: number;
  monthlyContribution: number; // Priority contribution
}

export interface Debt {
  id: string;
  name: string;
  amount: number; // Monthly deduction amount
  endDate: string;
  deductionType: 'source' | 'bank'; // Source = Salary slip, Bank = Bank Account
}

export interface BackpayConfig {
  id: string;
  monthlyAmount: number;
  startDate: string;
  endDate?: string;
  description?: string;
}

export interface AppState {
  hasCompletedOnboarding: boolean;
  language: Language;
  theme: Theme;
  currency: Currency;
  monthlyIncome: number;
  liquidAssets: number; // Freelance/Savings outside of monthly flow
  backpays: BackpayConfig[]; // Replaces single backpay config
  categories: Category[];
  debts: Debt[];
  transactions: Transaction[];
  savingsGoals: SavingsGoal[];
  lastResetDate: string | null;
}

export interface Translation {
  [key: string]: {
    en: string;
    fr: string;
    ar: string;
  };
}