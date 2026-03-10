import { AppState, Translation } from './types';

// Example based on 10,000 currency salary
// 30% Fixed (3000), 40% Essentials (4000), 10% Personal (1000), 10% Savings (1000), 10% Emergency (1000)
// Total = 10,000
export const DEFAULT_STATE: AppState = {
  hasCompletedOnboarding: false,
  language: 'en',
  theme: 'system',
  currency: 'MAD',
  monthlyIncome: 10000,
  liquidAssets: 0,
  backpays: [],
  lastResetDate: new Date().toISOString(),
  debts: [],
  categories: [
    {
      id: 'c1',
      name: 'Fixed Bills & Housing',
      limit: 3000, // 30%
      spent: 0,
      type: 'fixed',
      icon: 'FileText',
      tags: [
        'Loyer/Traites',
        'Syndic (Copropriété)',
        'Eau & Électricité',
        'Internet & Wifi',
        'Forfait Mobile',
        'Frais de Scolarité',
        'Assurance Auto',
        'Assurance Habitation',
        'Abonnements Streaming',
        'Salle de Sport',
        'Frais Bancaires'
      ]
    },
    {
      id: 'c2',
      name: 'Living Essentials',
      limit: 4000, // 40%
      spent: 0,
      type: 'need',
      icon: 'ShoppingCart',
      tags: [
        'Supermarché (Courses)',
        'Épicerie/Hanout',
        'Souk & Légumes',
        'Boucherie/Poissons',
        'Boulangerie/Pâtisserie',
        'Bouteille de Gaz',
        'Carburant (Gasoil/Essence)',
        'Transport (Bus/Tram/Train)',
        'Taxi/VTC',
        'Parking/Gardien',
        'Recharge Téléphone',
        'Médecin/Pharmacie',
        'Produits Ménagers',
        'Entretien Véhicule (Lavage/Vidange)',
        'Coiffeur/Barbier/Hammam'
      ]
    },
    {
      id: 'c3',
      name: 'Personal & Lifestyle',
      limit: 1000, // 10%
      spent: 0,
      type: 'want',
      icon: 'Heart',
      tags: [
        'Café/Sorties',
        'Restaurant/Snack',
        'Shopping & Mode',
        'Beauté & Soins',
        'Cadeaux & Invités',
        'Loisirs & Hobbies',
        'Voyage & Week-end',
        'Électronique/Gadgets',
        'Maison & Déco',
        'Livres & Culture',
        'Sadaqa/Charité',
        'Sorties Enfants'
      ]
    },
    {
      id: 'c4',
      name: 'Savings & Goals',
      limit: 1000, // 10%
      spent: 0,
      type: 'saving',
      icon: 'PiggyBank',
      tags: [
        'Virement Épargne',
        'Achat Or (Dahab)',
        'Investissement Bourse',
        'Épargne Logement',
        'Cagnotte Voyage',
        'Mouton Aïd (Adha)',
        'Fonds Ramadan',
        'Projet Omra/Hajj',
        'Avance Voiture'
      ]
    },
    {
      id: 'c5',
      name: 'Emergency Fund',
      limit: 1000, // 10%
      spent: 0,
      type: 'saving',
      icon: 'Shield',
      tags: [
        'Urgence Médicale',
        'Réparation Auto',
        'Réparation Maison',
        'Aide Familiale Urgente',
        'Perte de Revenu'
      ]
    },
  ],
  transactions: [],
  savingsGoals: []
};

// Defensive helper to ensure any state (imported or reset) has all required arrays
export const validateState = (s: Partial<AppState>): AppState => {
  return {
    ...DEFAULT_STATE,
    ...s,
    categories: s.categories || DEFAULT_STATE.categories,
    debts: s.debts || [],
    backpays: s.backpays || [],
    transactions: s.transactions || [],
    savingsGoals: s.savingsGoals || [],
    hasCompletedOnboarding: s.hasCompletedOnboarding ?? false,
    language: s.language || 'en',
    theme: s.theme || 'system',
    currency: s.currency || 'MAD',
  };
};

export const ASSET_CHIPS = [
  { id: 'savings', label: { en: 'Savings Account', fr: 'Compte Épargne', ar: 'حساب التوفير' } },
  { id: 'cash', label: { en: 'Cash on Hand', fr: 'Espèces', ar: 'كاش' } },
  { id: 'gold', label: { en: 'Gold', fr: 'Or (Dahab)', ar: 'ذهب' } },
  { id: 'freelance', label: { en: 'Freelance', fr: 'Freelance', ar: 'عمل حر' } },
  { id: 'stocks', label: { en: 'Stocks', fr: 'Actions/Bourse', ar: 'أسهم' } },
];

export const TRANSLATIONS: Translation = {
  // Navigation & Core
  dashboard: { en: 'Dashboard', fr: 'Tableau de bord', ar: 'لوحة القيادة' },
  tools: { en: 'Tools', fr: 'Outils', ar: 'الأدوات' },
  settings: { en: 'Settings', fr: 'Paramètres', ar: 'الإعدادات' },

  // Onboarding
  welcome: { en: 'Welcome to eSalaire', fr: 'Bienvenue sur eSalaire', ar: 'مرحبًا بك في eSalaire' },
  appDescription: {
    en: 'Master your finances in the Moroccan context. Track salaries, manage backpay (Rappel), and achieve financial freedom.',
    fr: 'Maîtrisez vos finances dans le contexte marocain. Suivez votre salaire, gérez les rappels et atteignez la liberté financière.',
    ar: 'تحكم في أموالك بذكاء. تتبع راتبك، احسب الرابيل، وحقق استقلالك المالي.'
  },
  chooseLang: { en: 'Choose Language', fr: 'Choisir la langue', ar: 'اختر اللغة' },

  trustTitle: { en: '100% Private & Local', fr: '100% Privé & Local', ar: 'خاص ومحلي 100٪' },
  trustDesc: {
    en: 'Your financial data never leaves this device. No servers, no tracking, no accounts required.',
    fr: 'Vos données financières ne quittent jamais cet appareil. Aucun serveur, aucun traçage, aucun compte.',
    ar: 'بياناتك المالية لا تغادر هذا الهاتف أبدًا. لا خوادم، لا تتبع، ولا حاجة لإنشاء حساب.'
  },

  startSetup: { en: 'Start Setup', fr: 'Commencer', ar: 'ابدأ الإعداد' },
  continue: { en: 'Continue', fr: 'Continuer', ar: 'متابعة' },
  next: { en: 'Next', fr: 'Suivant', ar: 'التالي' },
  back: { en: 'Back', fr: 'Retour', ar: 'عودة' },
  finish: { en: 'Finish', fr: 'Terminer', ar: 'إنهاء' },

  enterSalary: { en: 'Net Monthly Salary', fr: 'Salaire Net Mensuel', ar: 'الراتب الشهري الصافي' },
  enterSalaryDesc: { en: 'Enter the exact amount that hits your bank account (Virement).', fr: 'Saisissez le montant exact reçu par virement bancaire.', ar: 'أدخل المبلغ الدقيق الذي يصل إلى حسابك البنكي.' },

  addDeductions: { en: 'Loans & Commitments', fr: 'Crédits et Engagements', ar: 'القروض والالتزامات' },
  addDeductionDesc: { en: 'Add housing loans, consumer loans, or family debts to calculate your true net worth.', fr: 'Ajoutez les crédits immo, conso ou dettes familiales pour calculer votre vraie richesse.', ar: 'أضف قروض السكن، الاستهلاك أو ديون العائلة لحساب ثروتك الحقيقية.' },
  deductionSource: { en: 'Source', fr: 'Source', ar: 'المصدر' },
  sourceSalary: { en: 'Salary Slip (Source)', fr: 'Prélèvement Source (Paie)', ar: 'اقتطاع من المنبع (الراتب)' },
  sourceBank: { en: 'Bank Direct Debit', fr: 'Prélèvement Bancaire', ar: 'اقتطاع بنكي مباشر' },
  endDate: { en: 'End Date', fr: 'Date de fin', ar: 'تاريخ الانتهاء' },
  familyDebt: { en: 'Family/Friends Debt', fr: 'Dette Famille/Amis', ar: 'ديون العائلة/الأصدقاء' },
  noDebt: { en: 'I am Debt Free', fr: 'Je n\'ai pas de dettes', ar: 'ليس لدي ديون' },
  debtFreeTitle: { en: 'Mabrouk! Freedom!', fr: 'Félicitations ! Liberté !', ar: 'مبروك! حرية!' },
  debtFreeDesc: { en: 'Living without debt is the ultimate cheat code for wealth.', fr: 'Vivre sans dettes est l\'atout ultime pour la richesse.', ar: 'العيش بدون ديون هو المفتاح الحقيقي للثراء.' },

  initialAssets: { en: 'Current Liquid Assets', fr: 'Liquidités Actuelles', ar: 'السيولة الحالية' },
  initialAssetsDesc: { en: 'Total cash, savings accounts, and emergency funds available today.', fr: 'Total du cash, comptes épargne et fonds d\'urgence disponibles aujourd\'hui.', ar: 'مجموع النقد، حسابات التوفير وصناديق الطوارئ المتوفرة اليوم.' },
  housingLoan: { en: 'Housing Loan', fr: 'Crédit Immobilier', ar: 'قرض السكن' },
  consumptionLoan: { en: 'Consumer Loan', fr: 'Crédit Consommation', ar: 'قرض استهلاكي' },
  manageDebts: { en: 'Manage Debts', fr: 'Gérer les Dettes', ar: 'إدارة الديون' },
  addNewDebt: { en: 'Add New Debt', fr: 'Ajouter une dette', ar: 'إضافة دين جديد' },
  financialTools: { en: 'Financial Tools', fr: 'Outils Financiers', ar: 'أدوات مالية' },
  backpayTracker: { en: 'Backpay Tracker', fr: 'Suivi des Rappels', ar: 'متتبع المستحقات' },
  backpayDesc: { en: 'Track salary increases and accumulated arrears', fr: 'Suivez les augmentations et les arriérés cumulés', ar: 'تتبع الزيادات في الراتب والمستحقات المتراكمة' },

  // Dashboard
  monthlyFlow: { en: 'Remaining Budget', fr: 'Budget Restant', ar: 'الميزانية المتبقية' },
  cashFlow: { en: 'Monthly Cash Flow', fr: 'Flux de Trésorerie', ar: 'التدفق المالي الشهري' },
  income: { en: 'Income', fr: 'Entrées', ar: 'المداخيل' },
  outflow: { en: 'Expenses', fr: 'Dépenses', ar: 'المصاريف' },

  netPosition: { en: 'Net Position', fr: 'Situation Nette', ar: 'الوضع المالي' },
  netWealth: { en: 'Net Wealth', fr: 'Patrimoine Net', ar: 'صافي الثروة' },
  netDebt: { en: 'Total Debt', fr: 'Dette Totale', ar: 'مجموع الديون' },

  liquidAssets: { en: 'Liquid Assets', fr: 'Actifs Liquides', ar: 'الأصول السائلة' },
  emergencyFund: { en: 'Emergency Fund', fr: 'Fonds d\'Urgence', ar: 'صندوق الطوارئ' },
  freeWealth: { en: 'Investable Wealth', fr: 'Épargne Libre', ar: 'ثروة قابلة للاستثمار' },

  pendingAssets: { en: 'Pending Backpay', fr: 'Rappel en Attente', ar: 'مستحقات (رابيل) في الانتظار' },
  claim: { en: 'Claim / Cash In', fr: 'Encaisser', ar: 'تحصيل المبلغ' },
  accruing: { en: 'Accumulating monthly...', fr: 'Cumul mensuel...', ar: 'يتراكم شهرياً...' },

  remaining: { en: 'Remaining', fr: 'Restant', ar: 'متبقي' },
  spent: { en: 'Spent', fr: 'Dépensé', ar: 'مصروف' },
  allocation: { en: 'Target', fr: 'Cible', ar: 'هدف' },
  ofSalary: { en: 'of salary', fr: 'du salaire', ar: 'من الراتب' },

  addExpense: { en: 'Add Expense', fr: 'Nouvelle Dépense', ar: 'تسجيل مصروف' },
  addIncome: { en: 'Add Income', fr: 'Nouveau Revenu', ar: 'تسجيل دخل' },

  financialHealth: { en: 'Financial Health', fr: 'Santé Financière', ar: 'الصحة المالية' },
  debtCoverage: { en: 'Debt Coverage', fr: 'Couverture Dette', ar: 'تغطية الديون' },
  safetyNet: { en: 'Safety Net', fr: 'Filet de Sécurité', ar: 'شبكة الأمان' },
  buildEmergency: { en: 'Build Emergency Fund', fr: 'Remplir Fonds Urgence', ar: 'بناء صندوق الطوارئ' },

  addGoal: { en: 'New Goal', fr: 'Nouvel Objectif', ar: 'هدف جديد' },
  reachBy: { en: 'ETA', fr: 'Prévu pour', ar: 'الوصول المتوقع' },
  contribute: { en: 'Deposit', fr: 'Verser', ar: 'إيداع' },

  // Strategy
  freedomStrategy: { en: 'Freedom Strategy', fr: 'Stratégie Liberté', ar: 'استراتيجية الحرية' },
  payOffAdvice: { en: 'Pay off debt using Free Wealth only.', fr: 'Soldez vos dettes avec l\'Épargne Libre uniquement.', ar: 'سدد الديون باستخدام الثروة الحرة فقط.' },
  payOffButton: { en: 'Pay Off Now', fr: 'Solder Maintenant', ar: 'سداد الآن' },
  totalDebt: { en: 'Outstanding Debt', fr: 'Reste à Rembourser', ar: 'الديون المتبقية' },
  monthsLeft: { en: 'months left', fr: 'mois restants', ar: 'أشهر متبقية' },

  // Charts
  needs: { en: 'Needs', fr: 'Besoins', ar: 'احتياجات' },
  wants: { en: 'Wants', fr: 'Envies', ar: 'رغبات' },
  savings: { en: 'Savings', fr: 'Épargne', ar: 'ادخار' },
  buffer: { en: 'Buffer', fr: 'Marge', ar: 'هامش' },
  greatJob: { en: 'Great Job!', fr: 'Bravo !', ar: 'عمل رائع!' },
  overBudget: { en: 'Over Budget', fr: 'Hors Budget', ar: 'تجاوز الميزانية' },
  projection: { en: 'Wealth Projection (12 Months)', fr: 'Projection Patrimoine (12 Mois)', ar: 'توقعات الثروة (12 شهر)' },

  // Tools
  rappelCalc: { en: 'Backpay (Rappel) Tracker', fr: 'Calculateur de Rappel', ar: 'متتبع مستحقات الرابيل' },
  oldSalary: { en: 'Old Salary', fr: 'Ancien Salaire', ar: 'الراتب القديم' },
  newSalary: { en: 'New Salary', fr: 'Nouveau Salaire', ar: 'الراتب الجديد' },
  monthlyDiff: { en: 'Net Monthly Difference', fr: 'Différence Nette Mensuelle', ar: 'الفرق الشهري الصافي' },
  manualOverride: { en: 'Manual Entry', fr: 'Saisie Manuelle', ar: 'إدخال يدوي' },
  autoCalc: { en: 'Auto-Calculated', fr: 'Calcul Auto', ar: 'حساب تلقائي' },
  startDate: { en: 'Effective Date', fr: 'Date d\'Effet', ar: 'تاريخ المفعول' },
  months: { en: 'Months', fr: 'Mois', ar: 'أشهر' },
  calculate: { en: 'Start Tracking', fr: 'Lancer le Suivi', ar: 'بدء التتبع' },
  addToAssets: { en: 'Add to Assets', fr: 'Ajouter aux Actifs', ar: 'إضافة للأصول' },
  totalOwed: { en: 'Total Accrued', fr: 'Total Cumulé', ar: 'المجموع المتراكم' },
  dateError: { en: 'Date must be in the past or current month.', fr: 'La date doit être passée ou actuelle.', ar: 'يجب أن يكون التاريخ في الماضي أو الشهر الحالي.' },

  // History
  history: { en: 'Transaction History', fr: 'Historique des Transactions', ar: 'سجل العمليات' },
  noTransactions: { en: 'No transactions recorded yet.', fr: 'Aucune transaction enregistrée.', ar: 'لم يتم تسجيل أي عمليات بعد.' },
  edit: { en: 'Edit', fr: 'Modifier', ar: 'تعديل' },
  delete: { en: 'Delete', fr: 'Supprimer', ar: 'حذف' },
  editLimit: { en: 'Edit Budget Limit', fr: 'Modifier Plafond', ar: 'تعديل السقف' },
  budgetOver: { en: 'Budget Limit Exceeded', fr: 'Plafond dépassé', ar: 'تجاوز السقف المحدد' },
  addSource: { en: 'Add Income Source', fr: 'Ajouter Source', ar: 'إضافة مصدر' },

  // Settings
  resetMonth: { en: 'Start New Month', fr: 'Démarrer Nouveau Mois', ar: 'بدء شهر جديد' },
  recalcBudgets: { en: 'Recalculate Budgets (Standard)', fr: 'Recalculer Budgets (Standard)', ar: 'إعادة حساب الميزانية (قياسي)' },
  recalcDesc: { en: 'Apply 30/40/30 rule to current income', fr: 'Appliquer la règle 30/40/30', ar: 'تطبيق قاعدة 30/40/30' },
  exportData: { en: 'Backup Data (JSON)', fr: 'Sauvegarde (JSON)', ar: 'نسخ احتياطي (JSON)' },
  importData: { en: 'Restore Data', fr: 'Restaurer Données', ar: 'استعادة البيانات' },
  loadPlan: { en: 'Reset to Default Plan', fr: 'Réinitialiser Plan par Défaut', ar: 'العودة للخطة الافتراضية' },
  language: { en: 'Language', fr: 'Langue', ar: 'اللغة' },
  theme: { en: 'Appearance', fr: 'Apparence', ar: 'المظهر' },
  monthlyIncome: { en: 'Net Salary', fr: 'Salaire Net', ar: 'الراتب الصافي' },
  installApp: { en: 'Install App', fr: 'Installer l\'App', ar: 'تثبيت التطبيق' },

  // Feedback & Guard
  warningWants: { en: 'Warning: Wants > 30%', fr: 'Attention: Envies > 30%', ar: 'تنبيه: الرغبات > 30%', },
  goalReached: { en: 'Goal Reached!', fr: 'Objectif Atteint !', ar: 'تحقق الهدف!', },
  insufficientFunds: { en: 'Insufficient Liquid Assets', fr: 'Fonds insuffisants', ar: 'سيولة غير كافية' },
  insufficientDesc: { en: 'You cannot spend more cash than you physically have.', fr: 'Vous ne pouvez pas dépenser plus d\'argent que vous n\'en avez.', ar: 'لا يمكنك إنفاق نقد أكثر مما تملك فعلياً.' },
  sourceQuestion: { en: 'Funding Source?', fr: 'Source de financement ?', ar: 'مصدر التمويل؟' },
  isDebt: { en: 'Paid via Debt/Credit', fr: 'Payé par Crédit/Dette', ar: 'دفع عبر دين/قرض' },
  isIncome: { en: 'Add Income First', fr: 'Ajouter un Revenu', ar: 'أضف دخلاً أولاً' },
  manualAdd: { en: 'Quick Add', fr: 'Ajout Rapide', ar: 'إضافة سريعة' },
  claimed: { en: 'Backpay Claimed', fr: 'Rappel Encaissé', ar: 'تم استلام الرابيل' },
  excellent: { en: 'Excellent', fr: 'Excellent', ar: 'ممتاز' },
  good: { en: 'Good', fr: 'Bien', ar: 'جيد' },
  atRisk: { en: 'At Risk', fr: 'À Risque', ar: 'في خطر' },
  secure: { en: 'Secure', fr: 'Sécurisé', ar: 'آمن' },

  // New: Reset Everything
  resetEverything: { en: 'Reset Everything', fr: 'Réinitialiser Tout', ar: 'إعادة تعيين كل شيء' },
  resetEverythingDesc: { en: 'Erase all data and restart from onboarding', fr: 'Effacer toutes les données et recommencer', ar: 'حذف جميع البيانات والبدء من جديد' },
  resetEverythingConfirm: { en: 'This will erase ALL your data. Are you absolutely sure?', fr: 'Cela effacera TOUTES vos données. Êtes-vous sûr ?', ar: 'سيتم حذف جميع بياناتك. هل أنت متأكد؟' },

  // New: Auto Income / New Month
  newMonthDetected: { en: 'New Month Detected', fr: 'Nouveau Mois Détecté', ar: 'تم اكتشاف شهر جديد' },
  resetMonthDesc: { en: 'Reset monthly spending and credit salary?', fr: 'Réinitialiser les dépenses et créditer le salaire ?', ar: 'إعادة تعيين المصاريف وإضافة الراتب؟' },
  creditSalary: { en: 'Reset & Credit Salary', fr: 'Réinitialiser et Créditer le Salaire', ar: 'إعادة تعيين وإضافة الراتب' },
  salaryCredited: { en: 'Monthly Salary', fr: 'Salaire Mensuel', ar: 'الراتب الشهري' },
  ignore: { en: 'Ignore', fr: 'Ignorer', ar: 'تجاهل' },

  // New: Hardcoded string translations
  available: { en: 'Available', fr: 'Disponible', ar: 'متاح' },
  noCashFlow: { en: 'No Cash Flow', fr: 'Pas de Flux', ar: 'لا يوجد تدفق' },
  addNewExpense: { en: 'Add New Expense', fr: 'Nouvelle Dépense', ar: 'إضافة مصروف جديد' },
  recentEntries: { en: 'Recent Entries', fr: 'Entrées Récentes', ar: 'الإدخالات الأخيرة' },
  noExpensesYet: { en: 'No expenses yet.', fr: 'Aucune dépense encore.', ar: 'لا مصاريف بعد.' },
  confirm: { en: 'Confirm', fr: 'Confirmer', ar: 'تأكيد' },
  cancel: { en: 'Cancel', fr: 'Annuler', ar: 'إلغاء' },
  save: { en: 'Save', fr: 'Enregistrer', ar: 'حفظ' },
  spendFromGoal: { en: 'Spend from Savings', fr: 'Dépenser depuis l\'épargne', ar: 'إنفاق من الادخار' },
  spendFromGoalDesc: { en: 'This creates an expense record and reduces the goal amount, but does NOT reduce your current liquid assets.', fr: 'Cela crée un enregistrement de dépense et réduit le montant de l\'objectif, sans toucher à vos liquidités.', ar: 'يُنشئ سجل مصروف ويُنقص مبلغ الهدف، دون المساس بسيولتك الحالية.' },
  confirmSpend: { en: 'Confirm Spend', fr: 'Confirmer la Dépense', ar: 'تأكيد الإنفاق' },
  spend: { en: 'Spend', fr: 'Dépenser', ar: 'إنفاق' },
  description: { en: 'Description', fr: 'Description', ar: 'الوصف' },
  descriptionOptional: { en: 'Description (Optional)', fr: 'Description (Optionnel)', ar: 'الوصف (اختياري)' },
  noDebtsListed: { en: 'No debts listed.', fr: 'Aucune dette enregistrée.', ar: 'لا ديون مسجلة.' },
  actions: { en: 'Actions', fr: 'Actions', ar: 'الإجراءات' },
  data: { en: 'Data', fr: 'Données', ar: 'البيانات' },
  pocketMoneyOnly: { en: 'Pocket Money Only', fr: 'Argent de Poche Uniquement', ar: 'المصروف الشخصي فقط' },
  debtName: { en: 'Debt Name', fr: 'Nom de la Dette', ar: 'اسم الدين' },
  amount: { en: 'Amount', fr: 'Montant', ar: 'المبلغ' },
  localStorage: { en: 'Local Storage', fr: 'Stockage Local', ar: 'تخزين محلي' },
  offline: { en: 'Offline', fr: 'Hors Ligne', ar: 'بدون إنترنت' },
  debt: { en: 'Debt', fr: 'Dette', ar: 'دين' },
  editGoal: { en: 'Edit Goal', fr: 'Modifier l\'Objectif', ar: 'تعديل الهدف' },
  newTarget: { en: 'New Target', fr: 'Nouvel Objectif', ar: 'هدف جديد' },
  name: { en: 'Name', fr: 'Nom', ar: 'الاسم' },
  target: { en: 'Target', fr: 'Cible', ar: 'الهدف' },
  done: { en: 'Done', fr: 'Terminé', ar: 'تم' },
  goalSpend: { en: 'Goal Spend', fr: 'Dépense Objectif', ar: 'إنفاق الهدف' },
  spentLabel: { en: 'Spent', fr: 'Dépensé', ar: 'مصروف' },
  investableWealth: { en: 'Investable Wealth', fr: 'Richesse Investissable', ar: 'ثروة قابلة للاستثمار' },
  monthPrefix: { en: 'Month', fr: 'Mois', ar: 'شهر' },
  autoIncrement: { en: 'Auto-increment monthly', fr: 'Incrémentation mensuelle', ar: 'زيادة تلقائية شهرياً' },
  trackingInfo: { en: 'Currently tracking', fr: 'Suivi actuel', ar: 'قيد التتبع حالياً' },
  perMonth: { en: '/ month since', fr: '/ mois depuis', ar: '/ شهر منذ' },
  limit: { en: 'Budget Limit', fr: 'Plafond du Budget', ar: 'سقف الميزانية' },
  targetAmount: { en: 'Target Amount', fr: 'Montant de l\'Objectif', ar: 'المبلغ المستهدف' },
  quickAdd: { en: 'Quick Add', fr: 'Ajout Rapide', ar: 'إضافة سريعة' },
  optional: { en: '(Optional)', fr: '(Optionnel)', ar: '(اختياري)' },
  accrued: { en: 'accrued', fr: 'cumulé', ar: 'متراكم' },
  totalPending: { en: 'Total Pending', fr: 'Total en Attente', ar: 'إجمالي الانتظار' },
  activeTracking: { en: 'Active Tracking', fr: 'Suivi Actif', ar: 'تتبع نشط' },
  date: { en: 'Date', fr: 'Date', ar: 'التاريخ' },
  source: { en: 'Source', fr: 'Source', ar: 'المصدر' },
  allocateToCategory: { en: 'Allocate to Category', fr: 'Allouer à une Catégorie', ar: 'تخصيص للفئة' }
};

export const CATEGORY_NAMES: Record<string, Record<string, string>> = {
  'c1': { en: 'Fixed Bills & Housing', fr: 'Charges Fixes & Logement', ar: 'الفواتير الثابتة والسكن' },
  'c2': { en: 'Living Essentials', fr: 'Essentiels du Quotidien', ar: 'الاحتياجات الأساسية' },
  'c3': { en: 'Personal & Lifestyle', fr: 'Personnel & Style de Vie', ar: 'شخصي ونمط الحياة' },
  'c4': { en: 'Savings & Goals', fr: 'Épargne & Objectifs', ar: 'الادخار والأهداف' },
  'c5': { en: 'Emergency Fund', fr: 'Fonds d\'Urgence', ar: 'صندوق الطوارئ' },
};

export const COLORS = {
  needs: '#f59e0b', // Amber
  wants: '#ef4444', // Red
  remaining: '#10b981', // Emerald Green
  fixed: '#6366f1', // Indigo
  unexpected: '#8b5cf6', // Violet
  saving: '#3b82f6' // Blue
};