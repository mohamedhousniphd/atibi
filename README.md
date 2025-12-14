# eSalaire - System Architecture Document (SAD)

**Version:** 1.3.0
**Status:** Production
**Author:** Mohamed HOUSNI Ph.D.
**Last Updated:** Oct 2025

---

## 1. Executive Summary

**eSalaire** is a zero-knowledge, Progressive Web Application (PWA) designed to provide advanced financial planning tools tailored specifically for the Moroccan socioeconomic context. Unlike generic budgeting apps, eSalaire natively handles local nuances such as "Source Deductions" vs "Bank Deductions," retroactive salary adjustments (Rappel), and cultural spending habits (Ramadan funds, Aid Al Adha).

### 1.1 Core Value Proposition
1.  **Privacy Sovereignty:** No data leaves the device.
2.  **Context Awareness:** Built for the Moroccan salary structure.
3.  **Resilience:** 100% Offline capability via Service Workers.

---

## 2. Technical Architecture

### 2.1 Technology Stack
| Component | Technology | Rationale |
| :--- | :--- | :--- |
| **Framework** | React 19 | Leveraging concurrent features for smooth UI transitions on low-end devices. |
| **Language** | TypeScript | Strict type safety to prevent financial calculation errors. |
| **Styling** | Tailwind CSS + OKLCH | Utility-first styling with high-gamut color support for modern displays. |
| **Persistence** | LocalStorage API | Synchronous, reliable key-value storage for < 5MB datasets. |
| **Visualization** | Recharts | D3-based, composable charting library. |
| **Animation** | Framer Motion | Hardware-accelerated layout animations. |

### 2.2 Application State (Data Dictionary)
The application maintains a monolithic state object `AppState` persisted to LocalStorage.

| Key | Type | Description |
| :--- | :--- | :--- |
| `monthlyIncome` | `number` | The net salary hitting the bank account (after source deductions). |
| `liquidAssets` | `number` | The active cash pool (Savings + Cash). **Does not include money moved to Goals.** |
| `savingsGoals` | `SavingsGoal[]` | Array of goals. Money contributed here is subtracted from `liquidAssets` but remains in Net Wealth. |
| `debts` | `Debt[]` | Array of debt objects. Critically distinguishes between `source` (salary slip) and `bank` (direct debit) deductions. |
| `transactions` | `Transaction[]` | History. Includes a flag `excludeFromBudget` for expenditures made directly from Savings Goals. |

---

## 3. Business Logic Engine

### 3.1 The "Spendable Income" Algorithm
eSalaire differentiates between *Accounted Income* and *Spendable Income* to prevent overdrafts.
$$ \text{Spendable} = \text{Monthly Income} - \sum(\text{Bank Deductions}) $$
*Note:* Source deductions are ignored here as they are pre-subtracted from the net income input.

### 3.2 The "Buffer" Calculation (Cash Flow)
The Buffer represents the real-time health of the user's finances for the current month.
$$ \text{Buffer} = \text{Spendable} - \sum(\text{Category Spending}_{current}) $$
*Note:* Expenditures made from Savings Goals (flagged `excludeFromBudget`) are excluded from this calculation to preserve the monthly operational buffer.

### 3.3 The "Net Wealth" Calculation
To accurately reflect the user's financial standing, money allocated to Savings Goals is treated as an asset.
$$ \text{Net Wealth} = (\text{Liquid Assets} + \sum\text{Savings Goal Current Amounts}) - \text{Total Outstanding Debt} $$

### 3.4 Floating Point Precision
To mitigate IEEE 754 floating-point errors (e.g., `0.1 + 0.2 !== 0.3`), all monetary calculations pass through a strict rounding wrapper:
```typescript
const round = (num: number) => Math.round((num + Number.EPSILON) * 100) / 100;
```

---

## 4. Security & Compliance

### 4.1 Privacy Model (Zero-Knowledge)
*   **Storage:** Data exists only in `localStorage`. Clearing browser data wipes the account effectively.
*   **Transmission:** No API calls are made to external servers. No analytics SDKs (Google Analytics, Mixpanel) are installed.
*   **Encryption:** Data is stored in plain JSON within LocalStorage. Access control relies on the device's OS-level security (PIN/Biometrics).

### 4.2 XSS Mitigation
*   React's auto-escaping is used for all user inputs (Transaction descriptions, Debt names).
*   No `dangerouslySetInnerHTML` is used in the application.

---

## 5. Development Guide

### 5.1 Setup
```bash
git clone https://github.com/mohamedhousni/esalaire.git
cd esalaire
npm install
npm run dev
```

### 5.2 Building for Production
```bash
npm run build
```
This generates a `dist` folder containing the compiled assets, `manifest.json`, and service workers ready for static hosting (Netlify/Vercel/GitHub Pages).

---

**© 2025 Mohamed HOUSNI Ph.D. All Rights Reserved.**