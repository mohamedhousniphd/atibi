import React, { useState, useEffect } from 'react';
import { PieChart, Pie, Cell, ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip, LabelList } from 'recharts';
import { motion, AnimatePresence } from 'framer-motion';
import { Check, Wallet, AlertTriangle, Lock, Plus, TrendingUp, TrendingDown, History, ShieldCheck, ShieldAlert, Target, Calendar, BarChart3, Coins, Hourglass, Zap, Banknote, Briefcase, Trash2, Edit2, X, Download, Save, PiggyBank, Heart, ShoppingCart, FileText, Shield, LogOut, RefreshCw, ShoppingBag } from 'lucide-react';
import { AppState, Category, Transaction, SavingsGoal, Debt } from '../types';
import { COLORS, TRANSLATIONS, CATEGORY_NAMES } from '../constants';

// Add confetti definition
declare global {
    interface Window {
        confetti: any;
    }
}

interface DashboardProps {
    state: AppState;
    setState: React.Dispatch<React.SetStateAction<AppState>>;
}

const Dashboard: React.FC<DashboardProps> = ({ state, setState }) => {
    const { language, currency, theme, lastResetDate, monthlyIncome: rawMonthlyIncome } = state;
    const monthlyIncome = rawMonthlyIncome || 0;
    const t = TRANSLATIONS;
    const isRTL = language === 'ar';

    const [showModal, setShowModal] = useState<'expense' | 'income' | 'goal' | 'edit_transaction' | 'category_detail' | 'insufficient_funds' | 'spend_goal' | 'edit_goal' | null>(null);
    const [selectedCategory, setSelectedCategory] = useState<Category | null>(null);
    const [selectedGoal, setSelectedGoal] = useState<SavingsGoal | null>(null);
    const [editingTransaction, setEditingTransaction] = useState<Transaction | null>(null);
    const [showNewMonthAlert, setShowNewMonthAlert] = useState(false);

    // Category Edit State
    const [isEditingLimit, setIsEditingLimit] = useState(false);
    const [tempLimit, setTempLimit] = useState('');
    const [limitError, setLimitError] = useState('');

    const [amount, setAmount] = useState('');
    const [description, setDescription] = useState('');
    const [transactionDate, setTransactionDate] = useState(new Date().toISOString().split('T')[0]);

    // Goal State
    const [newGoalName, setNewGoalName] = useState('');
    const [newGoalTarget, setNewGoalTarget] = useState('');

    // Edit Goal State
    const [editingGoal, setEditingGoal] = useState<SavingsGoal | null>(null);
    const [editGoalName, setEditGoalName] = useState('');
    const [editGoalTarget, setEditGoalTarget] = useState('');

    // Quick Contribution State
    const [contributionAmounts, setContributionAmounts] = useState<{ [key: string]: string }>({});

    // --- Check New Month ---
    useEffect(() => {
        if (lastResetDate) {
            const last = new Date(lastResetDate);
            const now = new Date();
            if (last.getMonth() !== now.getMonth() || last.getFullYear() !== now.getFullYear()) {
                setShowNewMonthAlert(true);
            }
        }
    }, [lastResetDate]);

    // --- Helpers ---
    const round = (num: any) => {
        const n = typeof num === 'number' ? num : parseFloat(num);
        if (isNaN(n) || !isFinite(n)) return 0;
        return Math.round((n + Number.EPSILON) * 100) / 100;
    };

    const formatMoney = (val: any) => {
        const n = typeof val === 'number' ? val : parseFloat(val);
        if (isNaN(n) || !isFinite(n)) return "0,00";
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const formatCompact = (val: any) => {
        const n = typeof val === 'number' ? val : parseFloat(val);
        if (isNaN(n) || !isFinite(n)) return "0";
        return Intl.NumberFormat('en-US', {
            notation: "compact",
            maximumFractionDigits: 1
        }).format(n);
    };

    const getMonthsLeft = (endDateStr: string) => {
        if (!endDateStr) return 0;
        const end = new Date(endDateStr);
        const now = new Date();
        let months = (end.getFullYear() - now.getFullYear()) * 12;
        months -= now.getMonth();
        months += end.getMonth();
        return Math.max(0, months);
    };

    const calculateDebtBalance = (debt: Debt) => {
        const months = getMonthsLeft(debt.endDate);
        return round(months * debt.amount);
    };

    // --- Core Calculations ---
    const categories = state.categories || [];
    const transactions = state.transactions || [];
    const savingsGoals = state.savingsGoals || [];
    const backpays = state.backpays || [];
    const debts = state.debts || [];
    const liquidAssets = state.liquidAssets || 0;

    const totalMonthlyDebt = round(debts.reduce((acc, d) => acc + (d.amount || 0), 0));
    const totalOutstandingDebt = round(debts.reduce((acc, d) => acc + calculateDebtBalance(d), 0));

    const spendableIncome = Math.max(0, monthlyIncome - totalMonthlyDebt);

    const totalAllocated = round(categories.reduce((acc, cat) => acc + (cat.limit || 0), 0));
    const totalSpent = round(categories.reduce((acc, cat) => acc + (cat.spent || 0), 0));
    const remainingAllocation = round(spendableIncome - totalAllocated);

    // Dynamic Buffer (Exclude savings spending from buffer calc)
    const actualBuffer = round(spendableIncome - totalSpent);
    const isBufferNegative = actualBuffer < 0;

    // Wealth & Emergency Fund Logic
    const fixedExpenses = round(categories.filter(c => c.type === 'fixed').reduce((acc, c) => acc + (c.limit || 0), 0));
    const essentialExpenses = round(categories.filter(c => c.type === 'need').reduce((acc, c) => acc + (c.limit || 0), 0));
    const emergencyFundTarget = round((fixedExpenses + essentialExpenses) * 6); // 6 Months Rule

    const emergencyFundCurrent = round(Math.min(liquidAssets, emergencyFundTarget));
    const freeWealth = round(Math.max(0, liquidAssets - emergencyFundTarget));

    // Safety: ensure no NaN in progress
    const rawEmergencyProgress = emergencyFundTarget > 0 ? (emergencyFundCurrent / emergencyFundTarget) * 100 : 0;
    const emergencyProgress = isNaN(rawEmergencyProgress) ? 0 : Math.min(100, rawEmergencyProgress);

    // Net Wealth Calculation
    // Logic: Liquid Assets + Money locked in Savings Goals - Total Debt
    const totalSavingsInGoals = round(savingsGoals.reduce((acc, g) => acc + (g.currentAmount || 0), 0));
    const netWealth = round((liquidAssets + totalSavingsInGoals) - totalOutstandingDebt);

    // STRICT HEALTH LOGIC: Assets vs Debt
    const totalAssets = liquidAssets + totalSavingsInGoals;
    const isSecure = totalAssets >= totalOutstandingDebt;
    const healthLabel = isSecure ? t.secure[language] : t.atRisk[language];
    const healthColor = isSecure ? 'text-emerald-500' : 'text-destructive';

    // Backpay Logic
    let pendingBackpay = 0;
    let totalMonthlyBackpay = 0;

    if (backpays && backpays.length > 0) {
        const now = new Date();
        backpays.forEach(bp => {
            const startD = new Date(bp.startDate);
            const endD = bp.endDate ? new Date(bp.endDate) : now;

            // Cap end date to 'now' for pending calculations
            const effectiveEndD = endD < now ? endD : now;

            if (!isNaN(startD.getTime())) {
                let months = (effectiveEndD.getFullYear() - startD.getFullYear()) * 12;
                months -= startD.getMonth();
                months += effectiveEndD.getMonth();
                months = Math.max(0, months + 1);

                pendingBackpay += round(months * (bp.monthlyAmount || 0));
                if (!bp.endDate || new Date(bp.endDate) >= now) {
                    totalMonthlyBackpay += (bp.monthlyAmount || 0);
                }
            }
        });
    }

    // --- Projection Graph Data ---
    const generateProjectionData = () => {
        const data = [];
        let currentInvestable = liquidAssets - emergencyFundTarget;
        const monthlyGrowth = round(actualBuffer + totalMonthlyBackpay);

        for (let i = 0; i <= 12; i++) {
            data.push({
                month: i,
                wealth: round(currentInvestable)
            });
            currentInvestable += monthlyGrowth;
        }
        return data;
    };

    const projectionData = generateProjectionData();

    // --- Handlers & Helpers ---

    const handleAmountChange = (val: string, setter: (v: string) => void) => {
        if (/^[0-9]*[.,]?[0-9]*$/.test(val)) setter(val);
    };

    const parseAmount = (val: string) => {
        const num = parseFloat(val.replace(/,/g, '.'));
        return isNaN(num) ? 0 : round(num);
    };

    const handleResetMonth = () => {
        setState(prev => ({
            ...prev,
            categories: (prev.categories || []).map(c => ({ ...c, spent: 0 })),
            liquidAssets: round((prev.liquidAssets || 0) + (prev.monthlyIncome || 0)),
            lastResetDate: new Date().toISOString(),
            transactions: [{
                id: Date.now().toString(),
                amount: prev.monthlyIncome || 0,
                description: t.salaryCredited[language],
                type: 'income',
                date: new Date().toISOString()
            }, ...(prev.transactions || [])]
        }));
        setShowNewMonthAlert(false);
        if (window.confetti) window.confetti({ colors: ['#6366f1', '#a855f7'] });
    };

    const claimBackpay = (bpId: string) => {
        const bp = (backpays || []).find(b => b.id === bpId);
        if (!bp) return;

        const startD = new Date(bp.startDate);
        const endD = bp.endDate ? new Date(bp.endDate) : new Date();

        if (isNaN(startD.getTime())) return;

        let months = (endD.getFullYear() - startD.getFullYear()) * 12;
        months -= startD.getMonth();
        months += endD.getMonth();
        months = Math.max(0, months + 1);

        const totalToClaim = round(months * (bp.monthlyAmount || 0));

        if (confirm(`Claim ${formatMoney(totalToClaim)} ${currency} for "${bp.description || 'Rappel'}"?`)) {
            setState(prev => {
                const isPermanent = !bp.endDate;
                const currentMonthlyIncome = prev.monthlyIncome || 0;
                const bpMonthlyAmount = bp.monthlyAmount || 0;
                const newSalary = isPermanent ? round(currentMonthlyIncome + bpMonthlyAmount) : currentMonthlyIncome;

                // Propotional update for category limits if permanent salary increase
                const totalDebt = round((prev.debts || []).reduce((acc, d) => acc + (d.amount || 0), 0));
                const newSpendableIncome = round(Math.max(0, newSalary - totalDebt));

                const updatedCategories = isPermanent ? (prev.categories || []).map(cat => {
                    let ratio = 0.10;
                    if (cat.id === 'c1') ratio = 0.30;
                    else if (cat.id === 'c2') ratio = 0.40;
                    else ratio = 0.10; // Savings, Emergency, Wants are all 10%
                    return { ...cat, limit: round(newSpendableIncome * ratio) };
                }) : (prev.categories || []);

                return {
                    ...prev,
                    monthlyIncome: newSalary,
                    categories: updatedCategories,
                    liquidAssets: round((prev.liquidAssets || 0) + totalToClaim),
                    backpays: (prev.backpays || []).filter(b => b.id !== bpId),
                    transactions: [{
                        id: Date.now().toString(),
                        amount: totalToClaim,
                        description: `${t.claimed[language]}: ${bp.description || 'Rappel'}`,
                        type: 'income',
                        date: new Date().toISOString()
                    }, ...Array.from(prev.transactions || [])]
                };
            });
            if (window.confetti) window.confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
        }
    };

    const updateCategoryLimit = () => {
        if (selectedCategory && tempLimit) {
            const newLimit = parseAmount(tempLimit);
            if (newLimit < 0) return;
            const otherCategoriesTotal = round(categories.filter(c => c.id !== selectedCategory.id).reduce((acc, c) => acc + (c.limit || 0), 0));
            const potentialTotal = round(otherCategoriesTotal + newLimit);
            if (potentialTotal > spendableIncome) {
                setLimitError(`${t.budgetOver[language]} (${formatMoney(potentialTotal - spendableIncome)} ${currency})`);
                return;
            }
            setState(prev => ({
                ...prev,
                categories: (prev.categories || []).map(c => c.id === selectedCategory.id ? { ...c, limit: newLimit } : c)
            }));
            setSelectedCategory(prev => prev ? { ...prev, limit: newLimit } : null);
            setIsEditingLimit(false);
            setLimitError('');
        }
    };

    const handleTransaction = (e: React.FormEvent) => {
        e.preventDefault();
        const val = parseAmount(amount);
        if (val <= 0) return;

        // SPENDING FROM SAVINGS GOAL OR EMERGENCY FUND
        if ((showModal === 'spend_goal' || showModal === 'spend_emergency') && (selectedGoal || showModal === 'spend_emergency')) {
            const isEmergency = showModal === 'spend_emergency';
            const availableAmount = isEmergency ? emergencyFundCurrent : (selectedGoal?.currentAmount || 0);

            if (val > availableAmount) {
                alert("Insufficient funds.");
                return;
            }

            setState(prev => {
                const updatedGoals = isEmergency ? (prev.savingsGoals || []) : (prev.savingsGoals || []).map(g =>
                    g.id === selectedGoal?.id ? { ...g, currentAmount: round((g.currentAmount || 0) - val) } : g
                );

                const newTransaction: Transaction = {
                    id: Date.now().toString(),
                    amount: val,
                    description: `${description || (isEmergency ? t.emergencyFund[language] : (selectedGoal?.name || ''))} (${t.goalSpend[language]})`,
                    type: 'expense',
                    date: new Date().toISOString(),
                    excludeFromBudget: !selectedCategory,
                    categoryId: selectedCategory?.id
                };

                const updatedCategories = selectedCategory
                    ? (prev.categories || []).map(cat =>
                        cat.id === selectedCategory.id ? { ...cat, spent: round((cat.spent || 0) + val) } : cat
                    )
                    : (prev.categories || []);

                return {
                    ...prev,
                    liquidAssets: isEmergency ? round((prev.liquidAssets || 0) - val) : (prev.liquidAssets || 0),
                    savingsGoals: updatedGoals,
                    categories: updatedCategories,
                    transactions: [newTransaction, ...(prev.transactions || [])],
                };
            });

            setShowModal(null); setSelectedGoal(null); setSelectedCategory(null); setAmount(''); setDescription('');
            return;
        }

        // NORMAL SPENDING
        if (showModal === 'expense' || showModal === 'category_detail') {
            const availableCash = liquidAssets;
            if (val > availableCash) {
                setShowModal('insufficient_funds');
                return;
            }
        }

        if (showModal === 'edit_transaction' && editingTransaction) {
            if (editingTransaction.type === 'expense' && editingTransaction.categoryId) {
                const oldCat = categories.find(c => c.id === editingTransaction.categoryId);
                if (oldCat) {
                    const diff = round(val - (editingTransaction.amount || 0));
                    const updatedCategories = categories.map(cat =>
                        cat.id === editingTransaction.categoryId ? { ...cat, spent: round((cat.spent || 0) + diff) } : cat
                    );
                    setState(prev => ({
                        ...prev,
                        categories: updatedCategories,
                        liquidAssets: round((prev.liquidAssets || 0) - diff)
                    }));
                }
            } else if (editingTransaction.type === 'income') {
                const diff = round(val - (editingTransaction.amount || 0));
                setState(prev => ({ ...prev, liquidAssets: round((prev.liquidAssets || 0) + diff) }));
            }

            setState(prev => ({
                ...prev,
                transactions: (prev.transactions || []).map(t => t.id === editingTransaction.id ? { ...t, amount: val, description } : t)
            }));

        } else if ((showModal === 'expense' || showModal === 'category_detail') && selectedCategory) {
            const newTransaction: Transaction = {
                id: Date.now().toString(),
                amount: val,
                description: description || selectedCategory.name,
                categoryId: selectedCategory.id,
                type: 'expense',
                date: new Date(transactionDate).toISOString()
            };
            const updatedCategories = categories.map(cat =>
                cat.id === selectedCategory.id ? { ...cat, spent: round((cat.spent || 0) + val) } : cat
            );
            setState(prev => ({ ...prev, categories: updatedCategories, transactions: [newTransaction, ...(prev.transactions || [])], liquidAssets: round((prev.liquidAssets || 0) - val) }));
        } else if (showModal === 'income') {
            const newTransaction: Transaction = {
                id: Date.now().toString(),
                amount: val,
                description: description || 'Extra Income',
                type: 'income',
                date: new Date(transactionDate).toISOString()
            };

            if (window.confetti) window.confetti({ particleCount: 50, spread: 60, origin: { y: 0.7 } });
            setState(prev => ({ ...prev, liquidAssets: round((prev.liquidAssets || 0) + val), transactions: [newTransaction, ...(prev.transactions || [])] }));
        }
        setAmount(''); setDescription('');
        if (showModal !== 'category_detail') {
            setShowModal(null);
            setSelectedCategory(null);
        }
        setEditingTransaction(null);
    };

    const deleteTransaction = (tId: string) => {
        const tx = transactions.find(t => t.id === tId);
        if (!tx) return;

        if (confirm("Delete this transaction?")) {
            // If it was a savings spend, we need to refund the goal? 
            // Complexity: Let's assume deletion just reverses the cash flow.
            // If excludeFromBudget was true (Goal Spend), we should add money back to Goal?
            // For simplicity v1: Just delete record. User manually fixes goal if needed.

            if (tx.type === 'expense' && tx.categoryId) {
                setState(prev => ({
                    ...prev,
                    liquidAssets: round((prev.liquidAssets || 0) + (tx.amount || 0)),
                    categories: (prev.categories || []).map(c => c.id === tx.categoryId ? { ...c, spent: round(Math.max(0, (c.spent || 0) - (tx.amount || 0))) } : c),
                    transactions: (prev.transactions || []).filter(t => t.id !== tId)
                }));
            } else if (tx.type === 'income') {
                setState(prev => ({
                    ...prev,
                    liquidAssets: round((prev.liquidAssets || 0) - (tx.amount || 0)),
                    transactions: (prev.transactions || []).filter(t => t.id !== tId)
                }));
            } else if (tx.excludeFromBudget) {
                // Just delete, no asset refund logic wired automatically for goals yet
                setState(prev => ({
                    ...prev,
                    transactions: (prev.transactions || []).filter(t => t.id !== tId)
                }));
            }
        }
    };

    const startEditTransaction = (tx: Transaction) => {
        setEditingTransaction(tx);
        setAmount((tx.amount || 0).toString());
        setDescription(tx.description);
        setShowModal('edit_transaction');
    };

    const handleAddGoal = (e: React.FormEvent) => {
        e.preventDefault();
        const target = parseAmount(newGoalTarget);
        if (newGoalName && target > 0) {
            const newGoal: SavingsGoal = {
                id: Date.now().toString(),
                name: newGoalName,
                targetAmount: target,
                currentAmount: 0,
                monthlyContribution: 0
            };
            setState(prev => ({ ...prev, savingsGoals: [...(prev.savingsGoals || []), newGoal] }));
            setShowModal(null); setNewGoalName(''); setNewGoalTarget('');
        }
    };

    const handleContributeSavings = (goalId: string) => {
        try {
            const goalEntry = contributionAmounts[goalId] || '0';
            const val = parseAmount(goalEntry);

            if (val <= 0) return;

            // Safety: ensure liquidAssets is a number
            const currentAssets = typeof liquidAssets === 'number' ? liquidAssets : 0;

            if (val > currentAssets) {
                alert(t.insufficientFunds[language]);
                return;
            }

            if (window.confetti) {
                try {
                    window.confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 },
                        colors: ['#10b981', '#eab308', '#3b82f6']
                    });
                } catch (e) {
                    console.error("Confetti error", e);
                }
            }

            setState(prev => {
                if (!prev) return prev;

                // Deep copy and safety initialization
                const goals = Array.isArray(prev.savingsGoals) ? prev.savingsGoals : [];
                const goalIndex = goals.findIndex(g => g.id === goalId);
                const goal = goalIndex > -1 ? goals[goalIndex] : null;
                const goalName = goal ? goal.name : 'Goal';

                const prevTx = Array.isArray(prev.transactions) ? prev.transactions : [];
                const prevAssets = typeof prev.liquidAssets === 'number' ? prev.liquidAssets : 0;

                // Create updated goals array
                const updatedGoals = goals.map(g =>
                    g.id === goalId ? { ...g, currentAmount: round((g.currentAmount || 0) + val) } : g
                );

                const newTransaction = {
                    id: Date.now().toString(),
                    amount: val,
                    description: `Deposit to ${goalName}`,
                    type: 'expense' as const,
                    excludeFromBudget: true,
                    date: new Date().toISOString(),
                    categoryId: 'c4'
                };

                return {
                    ...prev,
                    liquidAssets: round(prevAssets - val),
                    savingsGoals: updatedGoals,
                    transactions: [newTransaction, ...prevTx]
                };
            });

            setContributionAmounts(prev => {
                const next = { ...prev };
                delete next[goalId];
                return next;
            });
        } catch (err) {
            console.error("Critical error in handleContributeSavings:", err);
            alert("An error occurred while processing the deposit. Please try again.");
        }
    };

    const deleteGoal = (goal: SavingsGoal) => {
        const refund = (goal.currentAmount || 0) > 0;
        const confirmMsg = refund
            ? `Delete goal "${goal.name}" and return ${formatMoney(goal.currentAmount || 0)} ${currency} to liquid assets?`
            : `Delete goal "${goal.name}"?`;

        if (confirm(confirmMsg)) {
            setState(prev => ({
                ...prev,
                liquidAssets: refund ? round((prev.liquidAssets || 0) + (goal.currentAmount || 0)) : (prev.liquidAssets || 0),
                savingsGoals: (prev.savingsGoals || []).filter(g => g.id !== goal.id)
            }));
        }
    };

    const handleSaveEditGoal = (e: React.FormEvent) => {
        e.preventDefault();
        const target = parseAmount(editGoalTarget);
        if (editingGoal && editGoalName && target > 0) {
            setState(prev => ({
                ...prev,
                savingsGoals: (prev.savingsGoals || []).map(g =>
                    g.id === editingGoal.id ? { ...g, name: editGoalName, targetAmount: target } : g
                )
            }));
            setShowModal(null);
            setEditingGoal(null);
        }
    };

    const startEditGoal = (goal: SavingsGoal) => {
        setEditingGoal(goal);
        setEditGoalName(goal.name);
        setEditGoalTarget((goal.targetAmount || 0).toString());
        setShowModal('edit_goal');
    };

    // Safe Months to goal calculation
    const monthsToGoal = (target: number, current: number) => {
        if (current >= target) return t.done[language];
        const remaining = target - current;
        // Total allocated per month from salary for ALL savings
        const savingsCat = categories.find(c => c.id === 'c4');
        const savingsAllocation = savingsCat ? (savingsCat.limit || 0) : (monthlyIncome * 0.10);

        if (savingsAllocation <= 0) return t.noCashFlow[language];

        const months = Math.ceil(remaining / savingsAllocation);
        if (isNaN(months) || !isFinite(months)) return t.noCashFlow[language];

        return `${months} ${t.monthsLeft[language]}`;
    };

    const chartData = [
        { name: t.spent[language], value: totalSpent, color: COLORS.wants },
        { name: t.remaining[language], value: Math.max(0, actualBuffer), color: COLORS.remaining },
    ];

    const categoryTransactions = selectedCategory
        ? transactions.filter(t => t.categoryId === selectedCategory.id)
        : [];

    const getIcon = (iconName: string) => {
        const icons: any = { Heart, ShoppingCart, AlertTriangle, FileText, PiggyBank, Shield };
        const Icon = icons[iconName] || Wallet;
        return <Icon size={20} />;
    };

    return (
        <div className={`p-4 space-y-6 pb-24 ${isRTL ? 'text-right' : 'text-left'}`}>

            {/* NEW MONTH ALERT */}
            <AnimatePresence>
                {showNewMonthAlert && (
                    <motion.div
                        initial={{ opacity: 0, y: -20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                        className="bg-primary/10 border border-primary/20 p-4 rounded-xl flex items-center justify-between shadow-sm mb-4"
                    >
                        <div className="flex items-center gap-3">
                            <Calendar className="text-primary" size={24} />
                            <div>
                                <h3 className="font-bold text-sm text-foreground">{t.newMonthDetected[language]}</h3>
                                <p className="text-xs text-muted-foreground">{t.resetMonthDesc[language]}</p>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <button onClick={() => setShowNewMonthAlert(false)} className="text-xs font-bold text-muted-foreground hover:text-foreground px-3 py-2">{t.ignore[language]}</button>
                            <button onClick={handleResetMonth} className="text-xs font-bold bg-primary text-primary-foreground px-3 py-2 rounded-lg flex items-center gap-1">
                                <RefreshCw size={12} /> {t.creditSalary[language]}
                            </button>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            {/* 1. FINANCIAL HEALTH CARD */}
            <section className="bg-gradient-to-br from-card to-secondary/20 p-5 rounded-2xl border border-border shadow-md">
                <div className="flex justify-between items-start mb-4">
                    <div>
                        <h2 className="text-sm font-bold text-muted-foreground uppercase tracking-wide flex items-center gap-2">
                            <BarChart3 size={16} /> {t.financialHealth[language]}
                        </h2>
                        <div className={`text-2xl font-bold mt-1 ${healthColor}`}>
                            {healthLabel}
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-sm font-bold text-muted-foreground uppercase">{t.netWealth[language]}</div>
                        <div className="text-xl font-mono font-bold text-foreground">{formatMoney(netWealth)} {currency}</div>
                    </div>
                </div>
                <div className="h-px bg-border/50 w-full my-3"></div>
                <div className="flex justify-between items-center text-sm">
                    <span className="text-muted-foreground font-medium">{t.totalDebt[language]}</span>
                    <span className={`font-mono font-bold ${totalOutstandingDebt > 0 ? 'text-destructive' : 'text-emerald-500'}`}>
                        -{formatMoney(totalOutstandingDebt)} {currency}
                    </span>
                </div>
            </section>

            {/* 2. LIQUID ASSETS & ADD FUNDS */}
            <section className="bg-card rounded-2xl border border-border shadow-sm p-5 space-y-4">
                <div className="flex justify-between items-center mb-2">
                    <h2 className="text-lg font-bold flex items-center gap-2">
                        <Wallet size={20} className="text-primary" />
                        {t.liquidAssets[language]}
                    </h2>
                    <div className="flex items-center gap-3">
                        <div className="text-right">
                            <span className="text-2xl font-mono font-bold">{formatMoney(liquidAssets + totalSavingsInGoals)} <span className="text-sm text-muted-foreground">{currency}</span></span>
                            {totalSavingsInGoals > 0 && (
                                <div className="text-[10px] text-muted-foreground font-mono">
                                    {formatMoney(liquidAssets)} + {formatMoney(totalSavingsInGoals)} ({t.savingsGoals[language]})
                                </div>
                            )}
                        </div>
                        <button
                            onClick={() => setShowModal('income')}
                            className="bg-primary text-primary-foreground p-1.5 rounded-full hover:scale-105 transition-transform shadow-lg shadow-primary/20"
                        >
                            <Plus size={18} />
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="bg-secondary/30 p-3 rounded-xl border border-border/50 relative group">
                        <div className="text-[10px] uppercase font-bold text-muted-foreground mb-1">{t.emergencyFund[language]}</div>
                        <div className="flex justify-between items-end">
                            <div className="font-mono font-bold text-lg">{formatMoney(emergencyFundCurrent)}</div>
                            {emergencyFundCurrent > 0 && (
                                <button
                                    onClick={() => { setShowModal('spend_emergency'); setAmount(''); setDescription(''); }}
                                    className="p-1 px-2 bg-primary/20 text-primary text-[8px] font-bold rounded uppercase hover:bg-primary hover:text-white transition-all"
                                >
                                    {t.spend[language]}
                                </button>
                            )}
                        </div>
                        <div className="w-full bg-secondary h-1.5 rounded-full mt-2 overflow-hidden">
                            <div className="h-full bg-orange-400" style={{ width: `${Math.min(emergencyProgress, 100)}%` }}></div>
                        </div>
                    </div>
                    <div className="bg-emerald-500/10 p-3 rounded-xl border border-emerald-500/20">
                        <div className="text-[10px] uppercase font-bold text-emerald-600 mb-1">{t.freeWealth[language]}</div>
                        <div className="font-mono font-bold text-lg text-emerald-600">{formatMoney(freeWealth)}</div>
                        <div className="text-[10px] text-emerald-600/80 mt-1">{t.available[language]}</div>
                    </div>
                </div>
            </section>

            {/* 3. BUDGET & PROJECTION */}
            <section className="bg-card p-5 rounded-2xl border border-border shadow-sm">
                <h2 className="text-sm font-bold text-muted-foreground uppercase tracking-wide mb-4 flex items-center gap-2">
                    <TrendingUp size={16} /> {t.projection[language]}
                </h2>
                <div className="h-56 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={projectionData} margin={{ top: 20, right: 0, left: 0, bottom: 0 }}>
                            <XAxis dataKey="month" hide />
                            <YAxis hide domain={['auto', (dataMax: number) => dataMax * 1.1]} />
                            <Tooltip
                                cursor={{ fill: 'var(--muted)', opacity: 0.2 }}
                                contentStyle={{ backgroundColor: 'var(--card)', borderColor: 'var(--border)', borderRadius: '8px', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }}
                                itemStyle={{ color: 'var(--foreground)', fontWeight: 'bold', fontFamily: 'monospace' }}
                                formatter={(value: any) => [`${formatMoney(value)} ${currency}`, 'Investable Wealth']}
                                labelFormatter={(label) => `Month +${label}`}
                            />
                            <Bar dataKey="wealth" fill="#10b981" radius={[4, 4, 0, 0]} maxBarSize={40}>
                                <LabelList
                                    dataKey="wealth"
                                    position="top"
                                    formatter={(val: number) => formatCompact(val)}
                                    style={{ fill: 'var(--muted-foreground)', fontSize: '10px', fontWeight: 'bold', fontFamily: 'monospace' }}
                                />
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </section>

            {/* 4. PENDING ASSETS (BACKPAY) */}
            {(backpays || []).length > 0 && (
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="bg-card p-6 rounded-3xl shadow-sm border border-border"
                >
                    <div className="flex justify-between items-start mb-6">
                        <div>
                            <h3 className="text-lg font-bold flex items-center gap-2">
                                <Coins className="text-amber-500" />
                                {t.pendingAssets[language]}
                            </h3>
                            <p className="text-sm text-muted-foreground">{t.accruing[language]}</p>
                        </div>
                    </div>

                    <div className="space-y-4">
                        {(backpays || []).map(bp => {
                            const startD = new Date(bp.startDate);
                            const endD = bp.endDate ? new Date(bp.endDate) : new Date();
                            let months = 0;
                            if (!isNaN(startD.getTime())) {
                                months = (endD.getFullYear() - startD.getFullYear()) * 12;
                                months -= startD.getMonth();
                                months += endD.getMonth();
                                months = Math.max(0, months + 1);
                            }
                            const currentTotal = round(months * bp.monthlyAmount);

                            return (
                                <div key={bp.id} className="p-4 bg-muted/20 rounded-2xl border border-border/50 group">
                                    <div className="flex justify-between items-start mb-3">
                                        <div>
                                            <div className="font-bold text-sm group-hover:text-primary transition-colors">{bp.description || 'Rappel'}</div>
                                            <div className="text-[10px] text-muted-foreground mt-0.5">{bp.startDate} {bp.endDate ? `→ ${bp.endDate}` : '→ Now'}</div>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-xs text-muted-foreground uppercase font-bold tracking-wider">{months} Months</div>
                                            <div className="font-mono font-bold text-primary">+{formatMoney(bp.monthlyAmount)}</div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 pt-3 border-t border-border/40">
                                        <div className="flex-1">
                                            <div className="text-[10px] text-muted-foreground uppercase font-bold">Accumulated</div>
                                            <div className="font-mono font-bold text-accent-foreground">{formatMoney(currentTotal)} {currency}</div>
                                        </div>
                                        <button
                                            onClick={() => claimBackpay(bp.id)}
                                            className="px-4 py-2 bg-primary text-primary-foreground rounded-xl text-xs font-bold hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95"
                                        >
                                            {t.claim[language]}
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </motion.div>
            )}

            {/* 6. CASH FLOW PIE */}
            <section className="bg-card p-5 rounded-2xl border border-border shadow-sm relative overflow-hidden">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-bold">{t.cashFlow[language]}</h2>
                    <div className={`text-xs px-2 py-1 rounded font-mono font-bold ${isBufferNegative ? 'bg-destructive text-destructive-foreground' : 'bg-muted text-muted-foreground'}`}>
                        {t.buffer[language]}: {formatMoney(actualBuffer)} {currency}
                    </div>
                </div>

                <div className="flex gap-4 items-center">
                    <div className="h-20 w-20 relative flex-shrink-0">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={chartData}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={25}
                                    outerRadius={35}
                                    paddingAngle={2}
                                    dataKey="value"
                                    stroke="none"
                                    startAngle={90}
                                    endAngle={-270}
                                >
                                    {chartData.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                    ))}
                                </Pie>
                            </PieChart>
                        </ResponsiveContainer>
                    </div>

                    <div className="flex-1 space-y-3">
                        <div className="flex justify-between items-center p-2 bg-emerald-500/5 rounded-lg border border-emerald-500/10">
                            <span className="text-xs font-bold text-emerald-600 flex items-center gap-1"><TrendingUp size={12} /> {t.income[language]}</span>
                            <span className="font-mono font-bold text-sm">{formatMoney(monthlyIncome)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-red-500/5 rounded-lg border border-red-500/10">
                            <span className="text-xs font-bold text-red-600 flex items-center gap-1"><TrendingDown size={12} /> {t.outflow[language]}</span>
                            <span className="font-mono font-bold text-sm">{formatMoney(totalSpent)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-destructive/5 rounded-lg border border-destructive/10">
                            <span className="text-xs font-bold text-destructive flex items-center gap-1"><Trash2 size={12} /> {t.totalDebt[language]}</span>
                            <span className="font-mono font-bold text-sm">{formatMoney(totalMonthlyDebt)}</span>
                        </div>
                    </div>
                </div>
            </section>

            {/* 7. SPENDING CATEGORIES */}
            <section className="grid grid-cols-1 gap-4">
                {categories.map((cat) => {
                    const percentSpent = cat.limit > 0 ? Math.min((cat.spent / cat.limit) * 100, 100) : (cat.spent > 0 ? 100 : 0);
                    const safePercent = isNaN(percentSpent) ? 0 : percentSpent;

                    return (
                        <div key={cat.id} className="group bg-card p-5 rounded-xl border border-border shadow-sm hover:border-primary/50 transition-all cursor-pointer relative overflow-hidden"
                            onClick={() => { if (cat) { setSelectedCategory(cat); setShowModal('category_detail'); setIsEditingLimit(false); setLimitError(''); } }}>

                            <div className="flex justify-between items-start mb-3">
                                <div className="flex items-center gap-3">
                                    <div className={`p-2.5 rounded-xl bg-secondary/50 border border-border text-foreground`}>
                                        {getIcon(cat.icon)}
                                    </div>
                                    <div>
                                        <div className="font-bold text-base">{CATEGORY_NAMES[cat.id]?.[language] || cat.name}</div>
                                        <div className="text-[10px] text-muted-foreground flex items-center gap-1">
                                            <Coins size={10} />
                                            Target: <span className="text-foreground font-mono font-bold">{cat.id === 'c1' ? '30.0' : cat.id === 'c2' ? '40.0' : '10.0'}%</span>
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="font-bold font-mono text-lg text-foreground">
                                        {formatMoney(cat.spent)}
                                    </div>
                                    <div className="text-[10px] text-muted-foreground font-mono">/ {formatMoney(cat.limit || 0)} {currency}</div>
                                </div>
                            </div>

                            <div className="relative h-2 bg-secondary rounded-full overflow-hidden">
                                <motion.div
                                    className={`h-full ${safePercent > 90 ? 'bg-destructive' : (safePercent > 75 ? 'bg-amber-500' : 'bg-primary')}`}
                                    initial={{ width: 0 }}
                                    animate={{ width: `${safePercent}%` }}
                                    transition={{ duration: 0.8, ease: "easeOut" }}
                                />
                            </div>
                        </div>
                    )
                })}
            </section>

            {/* 8. SAVINGS GOALS */}
            <section>
                <div className="flex justify-between items-end mb-3">
                    <h2 className="text-lg font-bold">{t.savings[language]}</h2>
                    <button
                        onClick={() => setShowModal('goal')}
                        className="flex items-center gap-1 text-xs bg-primary text-primary-foreground px-3 py-1.5 rounded-full font-medium"
                    >
                        <Plus size={12} /> {t.addGoal[language]}
                    </button>
                </div>
                <div className="space-y-3">
                    {savingsGoals.map(goal => {
                        const rawProgress = (goal.currentAmount || 0) / (goal.targetAmount || 1) * 100;
                        const progress = isNaN(rawProgress) ? 0 : Math.min(100, Math.max(0, rawProgress));
                        const isCompleted = progress >= 100;
                        return (
                            <div key={goal.id} className="bg-card p-4 rounded-xl border border-border shadow-sm">
                                <div className="flex justify-between items-start mb-2">
                                    <div>
                                        <span className="font-semibold text-sm block">{goal.name}</span>
                                        {!isCompleted && (
                                            <span className="text-[10px] text-muted-foreground flex items-center gap-1">
                                                <Calendar size={10} /> {monthsToGoal(goal.targetAmount, goal.currentAmount)}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {isCompleted ? (
                                            <span className="font-mono text-sm font-bold text-emerald-500">
                                                {t.done[language]}
                                            </span>
                                        ) : (
                                            <span className="font-mono text-sm font-bold text-foreground">
                                                {formatMoney(goal.currentAmount)} <span className="text-muted-foreground text-[10px] uppercase font-sans">/ {formatMoney(goal.targetAmount)} {currency}</span>
                                            </span>
                                        )}
                                        <button onClick={() => startEditGoal(goal)} className="p-1.5 text-muted-foreground hover:bg-muted rounded-lg transition-colors">
                                            <Edit2 size={16} />
                                        </button>
                                        <button onClick={() => deleteGoal(goal)} className="p-1.5 text-destructive/70 hover:bg-destructive/10 rounded-lg transition-colors">
                                            <Trash2 size={16} />
                                        </button>
                                    </div>
                                </div>
                                <div className="h-2 bg-secondary rounded-full overflow-hidden mb-3">
                                    {(() => {
                                        const rawP = (goal.currentAmount / (goal.targetAmount || 1)) * 100;
                                        const p = isNaN(rawP) ? 0 : Math.min(100, Math.max(0, rawP));
                                        return (
                                            <motion.div
                                                className="h-full bg-primary"
                                                initial={{ width: 0 }}
                                                animate={{ width: `${p}%` }}
                                            />
                                        );
                                    })()}
                                </div>

                                <div className="flex gap-2">
                                    {!isCompleted && (
                                        <div className="relative flex-1">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                placeholder="Amount"
                                                value={contributionAmounts[goal.id] || ''}
                                                onChange={(e) => handleAmountChange(e.target.value, (val) => setContributionAmounts(prev => ({ ...prev, [goal.id]: val })))}
                                                className="w-full bg-background border border-input rounded-lg pl-3 pr-8 py-2 text-xs font-mono outline-none focus:border-primary transition-colors"
                                            />
                                            <span className="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">{currency}</span>
                                        </div>
                                    )}

                                    {!isCompleted && (
                                        <button
                                            onClick={() => handleContributeSavings(goal.id)}
                                            className="px-3 py-2 bg-primary text-primary-foreground rounded-lg text-xs font-bold hover:bg-primary/90 transition-colors"
                                        >
                                            {t.contribute[language]}
                                        </button>
                                    )}

                                    {goal.currentAmount > 0 && (
                                        <button
                                            onClick={() => { setSelectedGoal(goal); setShowModal('spend_goal'); }}
                                            className="px-3 py-2 bg-secondary text-secondary-foreground rounded-lg text-xs font-bold hover:bg-secondary/80 transition-colors flex items-center gap-1"
                                            title="Spend from this goal"
                                        >
                                            <ShoppingBag size={14} /> {t.spend[language]}
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </section>

            {/* 9. RECENT HISTORY */}
            <section>
                <div className="flex items-center gap-2 mb-3">
                    <History size={18} className="text-muted-foreground" />
                    <h2 className="text-lg font-bold">{t.history[language]}</h2>
                </div>
                <div className="space-y-2">
                    {(transactions || []).slice(0, 5).map(tx => (
                        <div key={tx.id} className="bg-card p-3 rounded-xl border border-border shadow-sm flex justify-between items-center group relative overflow-hidden">
                            <div className="flex items-center gap-3">
                                <div className={`p-2 rounded-full ${tx.type === 'income' ? 'bg-emerald-500/10 text-emerald-500' : (tx.excludeFromBudget ? 'bg-blue-500/10 text-blue-500' : 'bg-red-500/10 text-red-500')}`}>
                                    {tx.type === 'income' ? <TrendingUp size={16} /> : (tx.excludeFromBudget ? <ShoppingBag size={16} /> : <TrendingDown size={16} />)}
                                </div>
                                <div>
                                    <div className="text-sm font-semibold">{tx.description}</div>
                                    <div className="text-[10px] text-muted-foreground">{new Date(tx.date).toLocaleDateString()}</div>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className={`font-mono font-bold text-sm ${tx.type === 'income' ? 'text-emerald-500' : 'text-red-500'}`}>
                                    {tx.type === 'income' ? '+' : '-'}{formatMoney(tx.amount)}
                                </span>

                                <div className="flex gap-2 z-10">
                                    {!tx.excludeFromBudget && (
                                        <button
                                            onClick={(e) => { e.stopPropagation(); startEditTransaction(tx); }}
                                            className="p-2 bg-secondary text-foreground rounded-lg hover:bg-primary hover:text-primary-foreground transition-colors"
                                        >
                                            <Edit2 size={14} />
                                        </button>
                                    )}
                                    <button
                                        onClick={(e) => { e.stopPropagation(); deleteTransaction(tx.id); }}
                                        className="p-2 bg-destructive/10 text-destructive rounded-lg hover:bg-destructive hover:text-destructive-foreground transition-colors"
                                    >
                                        <Trash2 size={14} />
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
            {/* MODALS */}
            <AnimatePresence>
                {showModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95, y: 20 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.95, y: 20 }}
                            className="bg-card w-full max-w-md p-6 rounded-3xl shadow-2xl border border-border relative overflow-y-auto max-h-[90vh]"
                        >
                            <button onClick={() => { setShowModal(null); setAmount(''); }} className="absolute right-4 top-4 p-2 text-muted-foreground hover:bg-muted rounded-full transition-colors z-10">
                                <X size={20} />
                            </button>

                            {showModal === 'category_detail' && selectedCategory ? (
                                <div className="space-y-6">
                                    <div className="flex justify-between items-start">
                                        <div className="flex items-center gap-4">
                                            <div className="p-3 bg-primary/10 rounded-2xl text-primary">
                                                {getIcon(selectedCategory.icon)}
                                            </div>
                                            <div>
                                                <h3 className="text-xl font-bold text-foreground">{CATEGORY_NAMES[selectedCategory.id]?.[language] || selectedCategory.name}</h3>
                                                <div className="text-xs text-muted-foreground font-mono">
                                                    {formatMoney(selectedCategory.spent)} / {formatMoney(selectedCategory.limit)} {currency}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-muted/30 p-4 rounded-2xl border border-border/50">
                                        <div className="flex justify-between items-center mb-2">
                                            <span className="text-sm font-bold text-muted-foreground uppercase">{t.limit[language]}</span>
                                            <button onClick={() => { setIsEditingLimit(!isEditingLimit); setTempLimit(selectedCategory.limit.toString()); }} className="text-xs font-bold text-primary hover:underline">
                                                {isEditingLimit ? t.cancel[language] : t.edit[language]}
                                            </button>
                                        </div>
                                        {isEditingLimit ? (
                                            <div className="space-y-3">
                                                <div className="relative">
                                                    <input
                                                        type="text"
                                                        inputMode="decimal"
                                                        value={tempLimit}
                                                        onChange={(e) => handleAmountChange(e.target.value, setTempLimit)}
                                                        className="w-full bg-background border border-input rounded-xl p-3 outline-none focus:border-primary font-mono"
                                                        autoFocus
                                                    />
                                                    <span className="absolute right-3 top-3 text-sm text-muted-foreground font-bold">{currency}</span>
                                                </div>
                                                {limitError && <p className="text-xs text-destructive font-bold">{limitError}</p>}
                                                <button onClick={updateCategoryLimit} className="w-full py-3 bg-primary text-primary-foreground rounded-xl font-bold shadow-lg shadow-primary/20">
                                                    {t.save[language]}
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="text-2xl font-mono font-bold text-foreground">
                                                {formatMoney(selectedCategory.limit)} <span className="text-sm text-muted-foreground">{currency}</span>
                                            </div>
                                        )}
                                    </div>

                                    <div className="space-y-3">
                                        <h4 className="text-sm font-bold text-muted-foreground uppercase flex items-center gap-2">
                                            <Zap size={14} className="text-amber-500" /> {t.quickAdd[language]}
                                        </h4>
                                        <div className="flex flex-wrap gap-2">
                                            {[10, 20, 50, 100].map(val => (
                                                <button key={val} onClick={() => { setAmount(val.toString()); }} className="px-4 py-2 bg-secondary/50 hover:bg-primary hover:text-primary-foreground rounded-xl text-sm font-bold border border-border transition-all">
                                                    +{val}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <form onSubmit={handleTransaction} className="space-y-4 pt-4 border-t border-border/50">
                                        <div className="relative">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                placeholder="0.00"
                                                value={amount}
                                                onChange={(e) => handleAmountChange(e.target.value, setAmount)}
                                                className="w-full bg-transparent border-b-2 border-border focus:border-primary p-2 text-3xl font-bold text-center focus:outline-none text-foreground placeholder-muted font-mono"
                                            />
                                            <span className="absolute right-0 top-1/2 -translate-y-1/2 text-muted-foreground font-bold">{currency}</span>
                                        </div>
                                        <input
                                            type="text"
                                            placeholder={`${t.description[language]} ${t.optional[language]}`}
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                            className="w-full bg-background border border-input rounded-lg p-3 text-sm outline-none focus:border-primary text-foreground"
                                        />
                                        <button type="submit" className="w-full py-4 bg-destructive text-white rounded-xl font-bold shadow-xl shadow-destructive/20 active:scale-[0.98] transition-all">
                                            {t.addExpense[language]}
                                        </button>
                                    </form>
                                </div>
                            ) : showModal === 'goal' ? (
                                <div className="space-y-6">
                                    <h3 className="text-xl font-bold text-foreground">{t.addGoal[language]}</h3>
                                    <form onSubmit={handleAddGoal} className="space-y-4">
                                        <div className="space-y-2">
                                            <label className="text-xs font-bold text-muted-foreground uppercase">{t.name[language]}</label>
                                            <input
                                                placeholder="e.g. New Car, Vacation..."
                                                value={newGoalName || ''}
                                                onChange={e => setNewGoalName(e.target.value)}
                                                className="w-full bg-background border border-input rounded-xl p-3 outline-none focus:border-primary text-foreground font-medium"
                                                autoFocus
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <label className="text-xs font-bold text-muted-foreground uppercase">{t.targetAmount[language]}</label>
                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    inputMode="decimal"
                                                    placeholder="0.00"
                                                    value={newGoalTarget || ''}
                                                    onChange={e => handleAmountChange(e.target.value, setNewGoalTarget)}
                                                    className="w-full bg-background border border-input rounded-xl p-3 outline-none focus:border-primary text-foreground font-mono"
                                                />
                                                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground font-bold">{currency}</span>
                                            </div>
                                        </div>
                                        <button type="submit" className="w-full py-4 bg-primary text-primary-foreground rounded-xl font-bold shadow-xl shadow-primary/20 mt-4 active:scale-[0.98] transition-all">
                                            {t.save[language]}
                                        </button>
                                    </form>
                                </div>
                            ) : (showModal === 'edit_goal' && editingGoal) ? (
                                <div className="space-y-6">
                                    <h3 className="text-xl font-bold text-foreground">{t.editGoal[language]}</h3>
                                    <form onSubmit={handleSaveEditGoal} className="space-y-4">
                                        <div className="space-y-2">
                                            <label className="text-xs font-bold text-muted-foreground uppercase">{t.name[language]}</label>
                                            <input
                                                value={editGoalName}
                                                onChange={e => setEditGoalName(e.target.value)}
                                                className="w-full bg-background border border-input rounded-xl p-3 outline-none focus:border-primary text-foreground font-medium"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <label className="text-xs font-bold text-muted-foreground uppercase">{t.targetAmount[language]}</label>
                                            <div className="relative">
                                                <input
                                                    type="text"
                                                    inputMode="decimal"
                                                    value={editGoalTarget}
                                                    onChange={e => handleAmountChange(e.target.value, setEditGoalTarget)}
                                                    className="w-full bg-background border border-input rounded-xl p-3 outline-none focus:border-primary text-foreground font-mono"
                                                />
                                                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground font-bold">{currency}</span>
                                            </div>
                                        </div>
                                        <button type="submit" className="w-full py-4 bg-primary text-primary-foreground rounded-xl font-bold shadow-xl shadow-primary/20 mt-4">
                                            {t.save[language]}
                                        </button>
                                    </form>
                                </div>
                            ) : (showModal === 'spend_goal' || showModal === 'spend_emergency') && (selectedGoal || showModal === 'spend_emergency') ? (
                                <form onSubmit={handleTransaction} className="space-y-6">
                                    <div className="flex items-center gap-3 text-primary">
                                        <ShoppingBag size={24} />
                                        <h3 className="text-xl font-bold text-foreground">{t.spendFromGoal[language]}</h3>
                                    </div>
                                    <div className="bg-secondary/30 p-4 rounded-xl space-y-1">
                                        <div className="text-[10px] text-muted-foreground uppercase font-bold">{t.source[language]}</div>
                                        <div className="text-sm font-bold text-foreground">
                                            {showModal === 'spend_emergency' ? t.emergencyFund[language] : (selectedGoal?.name || '')}
                                            (Available: {formatMoney(showModal === 'spend_emergency' ? emergencyFundCurrent : (selectedGoal?.currentAmount || 0))} {currency})
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="relative">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                value={amount}
                                                onChange={(e) => handleAmountChange(e.target.value, setAmount)}
                                                placeholder="0.00"
                                                autoFocus
                                                className="w-full bg-transparent border-b-2 border-border focus:border-primary p-2 text-4xl font-bold text-center focus:outline-none text-foreground placeholder-muted font-mono"
                                            />
                                            <span className="absolute right-0 top-1/2 -translate-y-1/2 text-muted-foreground font-bold">{currency}</span>
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-xs font-bold text-muted-foreground uppercase">{t.allocateToCategory[language]} {t.optional[language]}</label>
                                            <div className="grid grid-cols-2 gap-2">
                                                {(categories || []).map(cat => (
                                                    <button
                                                        key={cat.id}
                                                        type="button"
                                                        onClick={() => setSelectedCategory(selectedCategory?.id === cat.id ? null : cat)}
                                                        className={`text-[10px] p-2 rounded-lg border flex items-center justify-center gap-2 transition-all ${selectedCategory?.id === cat.id ? 'bg-primary text-primary-foreground border-primary shadow-sm' : 'bg-background text-muted-foreground border-border hover:border-primary'}`}
                                                    >
                                                        {getIcon(cat.icon)}
                                                        {CATEGORY_NAMES[cat.id]?.[language] || cat.name}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        <input
                                            type="text"
                                            value={description || ''}
                                            onChange={(e) => setDescription(e.target.value)}
                                            placeholder={t.descriptionOptional[language]}
                                            className="w-full bg-background border border-input rounded-xl p-3 text-sm focus:border-primary outline-none text-foreground"
                                        />
                                    </div>
                                    <button type="submit" className="w-full py-4 rounded-xl font-bold shadow-lg transition-all text-white bg-primary hover:bg-primary/90 shadow-primary/20 active:scale-[0.98]">
                                        {t.confirmSpend[language]}
                                    </button>
                                </form>
                            ) : (
                                <form onSubmit={handleTransaction} className="space-y-6">
                                    <h3 className="text-xl font-bold text-foreground">
                                        {showModal === 'edit_transaction' ? t.edit[language] : (showModal === 'income' ? t.manualAdd[language] : t.addExpense[language])}
                                    </h3>
                                    <div className="space-y-4">
                                        <div className="relative">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                value={amount}
                                                onChange={(e) => handleAmountChange(e.target.value, setAmount)}
                                                placeholder="0.00"
                                                autoFocus
                                                className="w-full bg-transparent border-b-2 border-border focus:border-primary p-2 text-4xl font-bold text-center focus:outline-none text-foreground placeholder-muted font-mono"
                                            />
                                            <span className="absolute right-0 top-1/2 -translate-y-1/2 text-muted-foreground font-bold">{currency}</span>
                                        </div>

                                        {showModal === 'expense' && selectedCategory?.tags && (
                                            <div className="flex flex-wrap gap-2">
                                                {selectedCategory.tags.map(tag => (
                                                    <button
                                                        key={tag}
                                                        type="button"
                                                        onClick={() => setDescription(tag)}
                                                        className={`text-xs px-3 py-1.5 rounded-full border transition-all ${description === tag ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-muted-foreground hover:border-primary'}`}
                                                    >
                                                        {tag}
                                                    </button>
                                                ))}
                                            </div>
                                        )}

                                        <div className="flex gap-2">
                                            <input
                                                type="date"
                                                value={transactionDate}
                                                onChange={(e) => setTransactionDate(e.target.value)}
                                                className="w-1/3 bg-background border border-input rounded-xl p-3 text-xs outline-none focus:border-primary text-foreground"
                                            />
                                            <input
                                                type="text"
                                                value={description}
                                                onChange={(e) => setDescription(e.target.value)}
                                                placeholder={t.descriptionOptional[language]}
                                                className="flex-1 w-2/3 bg-background border border-input rounded-xl p-3 text-sm outline-none focus:border-primary text-foreground"
                                            />
                                        </div>
                                    </div>
                                    <button type="submit" className={`w-full py-4 rounded-xl font-bold shadow-lg transition-all text-white active:scale-[0.98] ${showModal === 'income' || showModal === 'edit_transaction' ? 'bg-primary hover:bg-primary/90 shadow-primary/20' : 'bg-destructive hover:bg-destructive/90 shadow-destructive/20'}`}>
                                        {t.confirm[language]}
                                    </button>
                                </form>
                            )}
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </div>
    );
};

export default Dashboard;