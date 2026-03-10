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
    const { language, currency, categories, liquidAssets, backpays, monthlyIncome, savingsGoals, debts, transactions, lastResetDate } = state;
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
    const round = (num: number) => Math.round((num + Number.EPSILON) * 100) / 100;

    const formatMoney = (val: number) => {
        return val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const formatCompact = (val: number) => {
        return Intl.NumberFormat('en-US', {
            notation: "compact",
            maximumFractionDigits: 1
        }).format(val);
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
    const totalOutstandingDebt = round(debts.reduce((acc, d) => acc + calculateDebtBalance(d), 0));
    const bankDeductions = round(debts.filter(d => d.deductionType === 'bank').reduce((acc, d) => acc + d.amount, 0));

    // User requested full salary distribution (no debt subtraction)
    const spendableIncome = round(monthlyIncome);

    const totalAllocated = round(categories.reduce((acc, cat) => acc + cat.limit, 0));
    const totalSpent = round(categories.reduce((acc, cat) => acc + cat.spent, 0));
    const remainingAllocation = round(spendableIncome - totalAllocated);

    // Dynamic Buffer (Exclude savings spending from buffer calc)
    const actualBuffer = round(spendableIncome - totalSpent);
    const isBufferNegative = actualBuffer < 0;

    // Wealth & Emergency Fund Logic
    const fixedExpenses = round(categories.filter(c => c.type === 'fixed').reduce((acc, c) => acc + c.limit, 0));
    const essentialExpenses = round(categories.filter(c => c.type === 'need').reduce((acc, c) => acc + c.limit, 0));
    const emergencyFundTarget = round((fixedExpenses + essentialExpenses) * 6); // 6 Months Rule

    const emergencyFundCurrent = round(Math.min(liquidAssets, emergencyFundTarget));
    const freeWealth = round(Math.max(0, liquidAssets - emergencyFundTarget));
    const emergencyProgress = emergencyFundTarget > 0 ? (emergencyFundCurrent / emergencyFundTarget) * 100 : 0;

    // Net Wealth Calculation
    // Logic: Liquid Assets + Money locked in Savings Goals - Total Debt
    const totalSavingsInGoals = round(savingsGoals.reduce((acc, g) => acc + g.currentAmount, 0));
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

                pendingBackpay += round(months * bp.monthlyAmount);
                if (endD >= now) {
                    totalMonthlyBackpay += bp.monthlyAmount;
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
            categories: prev.categories.map(c => ({ ...c, spent: 0 })),
            liquidAssets: round(prev.liquidAssets + prev.monthlyIncome),
            lastResetDate: new Date().toISOString(),
            transactions: [{
                id: Date.now().toString(),
                amount: prev.monthlyIncome,
                description: t.salaryCredited[language],
                type: 'income',
                date: new Date().toISOString()
            }, ...prev.transactions]
        }));
        setShowNewMonthAlert(false);
        if (window.confetti) window.confetti({ colors: ['#6366f1', '#a855f7'] });
    };

    const claimBackpay = () => {
        if (pendingBackpay > 0) {
            const newSalary = round(monthlyIncome + totalMonthlyBackpay);
            if (confirm(`${t.claim[language]} ${formatMoney(pendingBackpay)} ${currency}?\n\nIMPORTANT: This will:\n1. Update Net Salary to ${formatMoney(newSalary)} ${currency}\n2. Recalculate budget limits (30/40/10/10/10)\n3. Stop the backpay tracker`)) {
                const updatedCategories = categories.map(cat => {
                    let ratio = 0.10;
                    if (cat.id === 'c1') ratio = 0.30;
                    else if (cat.id === 'c2') ratio = 0.40;
                    else if (cat.id === 'c3') ratio = 0.10;
                    else if (cat.id === 'c4') ratio = 0.10;
                    else if (cat.id === 'c5') ratio = 0.10;
                    return { ...cat, limit: round(newSalary * ratio) };
                });

                setState(prev => ({
                    ...prev,
                    liquidAssets: round(prev.liquidAssets + pendingBackpay),
                    monthlyIncome: newSalary,
                    categories: updatedCategories,
                    backpays: [], // Reset all rappels once claimed
                    transactions: [{
                        id: Date.now().toString(),
                        amount: pendingBackpay,
                        description: t.claimed[language] + ' (Rappel)',
                        type: 'income',
                        date: new Date().toISOString()
                    }, ...prev.transactions]
                }));
                if (window.confetti) window.confetti();
            }
        }
    };

    const updateCategoryLimit = () => {
        if (selectedCategory && tempLimit) {
            const newLimit = parseAmount(tempLimit);
            if (newLimit < 0) return;
            const otherCategoriesTotal = round(categories.filter(c => c.id !== selectedCategory.id).reduce((acc, c) => acc + c.limit, 0));
            const potentialTotal = round(otherCategoriesTotal + newLimit);
            if (potentialTotal > spendableIncome) {
                setLimitError(`${t.budgetOver[language]} (${formatMoney(potentialTotal - spendableIncome)} ${currency})`);
                return;
            }
            setState(prev => ({
                ...prev,
                categories: prev.categories.map(c => c.id === selectedCategory.id ? { ...c, limit: newLimit } : c)
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

        // SPENDING FROM SAVINGS GOAL
        if (showModal === 'spend_goal' && selectedGoal) {
            if (val > selectedGoal.currentAmount) {
                alert("Insufficient funds in goal.");
                return;
            }
            // Logic: 
            // 1. Goal -= Amount
            // 2. Add Transaction (Expense) marked excludeFromBudget
            // 3. Liquid Assets untouched (money already left liquid when allocated)
            setState(prev => ({
                ...prev,
                savingsGoals: prev.savingsGoals.map(g => g.id === selectedGoal.id ? { ...g, currentAmount: round(g.currentAmount - val) } : g),
                transactions: [{
                    id: Date.now().toString(),
                    amount: val,
                    description: `${description || selectedGoal.name} (Goal Spend)`,
                    type: 'expense',
                    date: new Date().toISOString(),
                    excludeFromBudget: true
                }, ...prev.transactions]
            }));
            setShowModal(null); setSelectedGoal(null); setAmount(''); setDescription('');
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
                    const diff = round(val - editingTransaction.amount);
                    const updatedCategories = categories.map(cat =>
                        cat.id === editingTransaction.categoryId ? { ...cat, spent: round(cat.spent + diff) } : cat
                    );
                    setState(prev => ({
                        ...prev,
                        categories: updatedCategories,
                        liquidAssets: round(prev.liquidAssets - diff)
                    }));
                }
            } else if (editingTransaction.type === 'income') {
                const diff = round(val - editingTransaction.amount);
                setState(prev => ({ ...prev, liquidAssets: round(prev.liquidAssets + diff) }));
            }

            setState(prev => ({
                ...prev,
                transactions: prev.transactions.map(t => t.id === editingTransaction.id ? { ...t, amount: val, description } : t)
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
                cat.id === selectedCategory.id ? { ...cat, spent: round(cat.spent + val) } : cat
            );
            setState(prev => ({ ...prev, categories: updatedCategories, transactions: [newTransaction, ...prev.transactions], liquidAssets: round(prev.liquidAssets - val) }));
        } else if (showModal === 'income') {
            const newTransaction: Transaction = {
                id: Date.now().toString(),
                amount: val,
                description: description || 'Extra Income',
                type: 'income',
                date: new Date(transactionDate).toISOString()
            };

            if (window.confetti) window.confetti({ particleCount: 50, spread: 60, origin: { y: 0.7 } });
            setState(prev => ({ ...prev, liquidAssets: round(prev.liquidAssets + val), transactions: [newTransaction, ...prev.transactions] }));
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
                    liquidAssets: round(prev.liquidAssets + tx.amount),
                    categories: prev.categories.map(c => c.id === tx.categoryId ? { ...c, spent: round(Math.max(0, c.spent - tx.amount)) } : c),
                    transactions: prev.transactions.filter(t => t.id !== tId)
                }));
            } else if (tx.type === 'income') {
                setState(prev => ({
                    ...prev,
                    liquidAssets: round(prev.liquidAssets - tx.amount),
                    transactions: prev.transactions.filter(t => t.id !== tId)
                }));
            } else if (tx.excludeFromBudget) {
                // Just delete, no asset refund logic wired automatically for goals yet
                setState(prev => ({
                    ...prev,
                    transactions: prev.transactions.filter(t => t.id !== tId)
                }));
            }
        }
    };

    const startEditTransaction = (tx: Transaction) => {
        setEditingTransaction(tx);
        setAmount(tx.amount.toString());
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
            setState(prev => ({ ...prev, savingsGoals: [...prev.savingsGoals, newGoal] }));
            setShowModal(null); setNewGoalName(''); setNewGoalTarget('');
        }
    };

    const handleContributeSavings = (goalId: string) => {
        const val = parseAmount(contributionAmounts[goalId] || '0');
        if (val <= 0) return;
        if (val > liquidAssets) {
            alert(t.insufficientFunds[language]);
            return;
        }

        if (window.confetti) window.confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 }, colors: ['#10b981', '#eab308', '#3b82f6'] });
        setState(prev => ({
            ...prev,
            liquidAssets: round(prev.liquidAssets - val),
            savingsGoals: prev.savingsGoals.map(g => g.id === goalId ? { ...g, currentAmount: round(g.currentAmount + val) } : g),
        }));
        setContributionAmounts(prev => ({ ...prev, [goalId]: '' }));
    };

    const deleteGoal = (goal: SavingsGoal) => {
        const refund = goal.currentAmount > 0;
        const confirmMsg = refund
            ? `Delete goal "${goal.name}" and return ${formatMoney(goal.currentAmount)} ${currency} to liquid assets?`
            : `Delete goal "${goal.name}"?`;

        if (confirm(confirmMsg)) {
            setState(prev => ({
                ...prev,
                liquidAssets: refund ? round(prev.liquidAssets + goal.currentAmount) : prev.liquidAssets,
                savingsGoals: prev.savingsGoals.filter(g => g.id !== goal.id)
            }));
        }
    };

    const handleSaveEditGoal = (e: React.FormEvent) => {
        e.preventDefault();
        const target = parseAmount(editGoalTarget);
        if (editingGoal && editGoalName && target > 0) {
            setState(prev => ({
                ...prev,
                savingsGoals: prev.savingsGoals.map(g =>
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
        setEditGoalTarget(goal.targetAmount.toString());
        setShowModal('edit_goal');
    };

    const monthsToGoal = (target: number, current: number) => {
        // Use savings allocation (10% of spendable income) or fallback to 10% of monthlyIncome
        const savingsCat = categories.find(c => c.id === 'c4');
        const savingsAllocation = savingsCat ? savingsCat.limit : (monthlyIncome * 0.10);
        if (savingsAllocation <= 0) return t.noCashFlow[language];
        const needed = round(target - current);
        if (needed <= 0) return t.done[language];
        return Math.ceil(needed / savingsAllocation) + ' ' + t.monthsLeft[language];
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
                    <div className="bg-secondary/30 p-3 rounded-xl border border-border/50">
                        <div className="text-[10px] uppercase font-bold text-muted-foreground mb-1">{t.emergencyFund[language]}</div>
                        <div className="font-mono font-bold text-lg">{formatMoney(emergencyFundCurrent)}</div>
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
            {pendingBackpay > 0 && (
                <motion.div
                    initial={{ scale: 0.98 }}
                    animate={{ scale: 1 }}
                    className="bg-accent/10 border border-accent/30 p-5 rounded-2xl flex items-center justify-between shadow-sm"
                >
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-accent/20 rounded-xl text-accent-foreground">
                            <Briefcase size={22} />
                        </div>
                        <div>
                            <div className="text-xs font-bold text-accent-foreground uppercase">{t.pendingAssets[language]}</div>
                            <div className="text-xs text-muted-foreground">{t.accruing[language]}</div>
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-xl font-mono font-bold text-foreground">
                            {formatMoney(pendingBackpay)} <span className="text-xs text-muted-foreground">{currency}</span>
                        </div>
                        <button onClick={claimBackpay} className="text-[10px] font-bold text-primary hover:underline mt-1">{t.claim[language]}</button>
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
                            <span className="font-mono font-bold text-sm">{formatMoney(spendableIncome)}</span>
                        </div>
                        <div className="flex justify-between items-center p-2 bg-red-500/5 rounded-lg border border-red-500/10">
                            <span className="text-xs font-bold text-red-600 flex items-center gap-1"><TrendingDown size={12} /> {t.outflow[language]}</span>
                            <span className="font-mono font-bold text-sm">{formatMoney(totalSpent)}</span>
                        </div>
                    </div>
                </div>
            </section>

            {/* 7. SPENDING CATEGORIES */}
            <section className="grid grid-cols-1 gap-4">
                {categories.map((cat) => {
                    const percentSpent = cat.limit > 0 ? Math.min((cat.spent / cat.limit) * 100, 100) : (cat.spent > 0 ? 100 : 0);
                    const allocationPercent = spendableIncome > 0 ? (cat.limit / spendableIncome) * 100 : 0;

                    // Visualize Bank Deductions in Fixed Category
                    const isFixed = cat.type === 'fixed';
                    const committed = isFixed ? bankDeductions : 0;
                    const visualSpent = cat.spent + committed;
                    const visualPercent = cat.limit > 0 ? Math.min((visualSpent / cat.limit) * 100, 100) : 0;

                    return (
                        <div key={cat.id} className="group bg-card p-5 rounded-xl border border-border shadow-sm hover:border-primary/50 transition-all cursor-pointer relative overflow-hidden"
                            onClick={() => { setSelectedCategory(cat); setShowModal('category_detail'); setIsEditingLimit(false); setLimitError(''); }}>

                            <div className="flex justify-between items-start mb-3">
                                <div className="flex items-center gap-3">
                                    <div className={`p-2.5 rounded-xl bg-secondary/50 border border-border text-foreground`}>
                                        {getIcon(cat.icon)}
                                    </div>
                                    <div>
                                        <div className="font-bold text-base">{CATEGORY_NAMES[cat.id]?.[language] || cat.name}</div>
                                        <div className="text-[10px] text-muted-foreground flex items-center gap-1">
                                            <Coins size={10} />
                                            {t.allocation[language]}: <span className="text-foreground font-mono font-bold">{allocationPercent.toFixed(1)}%</span>
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="font-bold font-mono text-lg text-foreground">
                                        {isFixed && committed > 0 ? (
                                            <div className="flex flex-col items-end">
                                                <span>{formatMoney(cat.spent)}</span>
                                                <span className="text-[10px] text-muted-foreground flex items-center gap-1">
                                                    + {formatMoney(committed)} ({t.debt[language]})
                                                </span>
                                            </div>
                                        ) : (
                                            formatMoney(cat.spent)
                                        )}
                                    </div>
                                    <div className="text-[10px] text-muted-foreground font-mono">/ {formatMoney(cat.limit)} {currency}</div>
                                </div>
                            </div>

                            <div className="relative h-2 bg-secondary rounded-full overflow-hidden flex">
                                {/* Debt Portion (Gray) */}
                                {isFixed && committed > 0 && (
                                    <motion.div
                                        className="h-full bg-muted-foreground/30"
                                        initial={{ width: 0 }}
                                        animate={{ width: `${Math.min((committed / cat.limit) * 100, 100)}%` }}
                                    />
                                )}
                                {/* Spending Portion (Color) */}
                                <motion.div
                                    className={`h-full ${percentSpent > 90 ? 'bg-destructive' : (percentSpent > 75 ? 'bg-amber-500' : 'bg-primary')}`}
                                    initial={{ width: 0 }}
                                    animate={{ width: `${percentSpent}%` }}
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
                        const progress = Math.min((goal.currentAmount / goal.targetAmount) * 100, 100);
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
                                    <motion.div
                                        className="h-full bg-primary"
                                        initial={{ width: 0 }}
                                        animate={{ width: `${progress}%` }}
                                    />
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

                                    {/* SPEND BUTTON */}
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
                    {transactions.slice(0, 5).map(tx => (
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
                    {transactions.length === 0 && (
                        <div className="text-center py-8 text-sm text-muted-foreground bg-muted/10 rounded-xl border border-dashed border-border">
                            {t.noTransactions[language]}
                        </div>
                    )}
                </div>
            </section>

            {/* MODALS */}
            <AnimatePresence>
                {showModal && (
                    <div className="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-background/80 backdrop-blur-sm p-4">
                        <motion.div
                            initial={{ y: "100%", opacity: 0 }}
                            animate={{ y: 0, opacity: 1 }}
                            exit={{ y: "100%", opacity: 0 }}
                            transition={{ type: "spring", damping: 25, stiffness: 300 }}
                            className="bg-card w-full max-w-md rounded-2xl border border-border p-6 shadow-2xl relative max-h-[85vh] overflow-y-auto"
                        >
                            <button onClick={() => setShowModal(null)} className="absolute top-4 right-4 text-muted-foreground hover:text-foreground z-10">
                                <X size={20} />
                            </button>

                            {showModal === 'insufficient_funds' ? (
                                <div className="text-center space-y-4 py-4">
                                    <div className="mx-auto w-16 h-16 bg-destructive/10 text-destructive rounded-full flex items-center justify-center">
                                        <AlertTriangle size={32} />
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-bold text-destructive">{t.insufficientFunds[language]}</h3>
                                        <p className="text-sm text-muted-foreground mt-2 px-4">{t.insufficientDesc[language]}</p>
                                    </div>

                                    <div className="pt-4 space-y-3">
                                        <p className="text-xs font-bold uppercase text-muted-foreground">{t.sourceQuestion[language]}</p>
                                        <button
                                            onClick={() => { setShowModal('income'); }}
                                            className="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-bold"
                                        >
                                            {t.isIncome[language]}
                                        </button>
                                        <button
                                            onClick={() => {
                                                alert("Please add this as a new Debt in Settings > Debts.");
                                                setShowModal(null);
                                            }}
                                            className="w-full py-3 bg-destructive hover:bg-destructive/90 text-white rounded-xl font-bold"
                                        >
                                            {t.isDebt[language]}
                                        </button>
                                    </div>
                                </div>
                            ) : showModal === 'category_detail' && selectedCategory ? (
                                <div className="space-y-6">
                                    <div className="text-center space-y-1">
                                        <h3 className="text-2xl font-bold">{CATEGORY_NAMES[selectedCategory.id]?.[language] || selectedCategory.name}</h3>
                                        <div className="text-muted-foreground text-sm flex items-center justify-center gap-2">
                                            <span>{t.spentLabel[language]}: <span className="font-mono font-bold text-foreground">{formatMoney(selectedCategory.spent)}</span> / </span>
                                            {isEditingLimit ? (
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        autoFocus
                                                        type="number"
                                                        inputMode="decimal"
                                                        value={tempLimit}
                                                        onChange={(e) => setTempLimit(e.target.value)}
                                                        className="w-20 bg-background border border-primary rounded px-2 py-0.5 text-center font-mono font-bold text-foreground outline-none"
                                                    />
                                                    <button onClick={updateCategoryLimit} className="p-1 bg-primary text-primary-foreground rounded-full"><Save size={14} /></button>
                                                </div>
                                            ) : (
                                                <button onClick={() => { setTempLimit(selectedCategory.limit.toString()); setIsEditingLimit(true); }} className="flex items-center gap-1 hover:bg-muted/50 px-2 py-0.5 rounded transition-colors group">
                                                    <span className="font-mono font-bold text-foreground">{formatMoney(selectedCategory.limit)}</span>
                                                    <Edit2 size={12} className="opacity-0 group-hover:opacity-100 transition-opacity" />
                                                </button>
                                            )}
                                            <span>{currency}</span>
                                        </div>
                                        <div className="text-sm mt-2 p-2 bg-muted/30 rounded-lg inline-block">
                                            {t.remaining?.[language] || 'Remaining'}: <span className={`font-mono font-bold ${selectedCategory.limit - selectedCategory.spent < 0 ? 'text-destructive' : 'text-emerald-500'}`}>{formatMoney(selectedCategory.limit - selectedCategory.spent)}</span> {currency}
                                        </div>
                                        {limitError && (
                                            <div className="text-[10px] text-destructive font-bold bg-destructive/10 p-2 rounded mt-2 animate-pulse">
                                                {limitError}
                                            </div>
                                        )}
                                    </div>

                                    {/* Transactions List */}
                                    <div className="space-y-2 max-h-[200px] overflow-y-auto pr-2">
                                        <h4 className="text-xs font-bold text-muted-foreground uppercase tracking-wide sticky top-0 bg-card py-1">{t.recentEntries[language]}</h4>
                                        {categoryTransactions.length > 0 ? categoryTransactions.map(tx => (
                                            <div key={tx.id} className="flex justify-between items-center p-2 bg-muted/20 rounded-lg border border-border/50 text-sm group">
                                                <div className="flex-1">
                                                    <div className="font-medium">{tx.description}</div>
                                                    <div className="text-[10px] text-muted-foreground">{new Date(tx.date).toLocaleDateString()}</div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono font-bold text-destructive">-{formatMoney(tx.amount)}</span>
                                                    <button onClick={() => deleteTransaction(tx.id)} className="text-muted-foreground hover:text-destructive p-1"><Trash2 size={14} /></button>
                                                </div>
                                            </div>
                                        )) : <div className="text-xs text-muted-foreground italic text-center py-2">{t.noExpensesYet[language]}</div>}
                                    </div>

                                    {/* Add Expense Form Inline */}
                                    <form onSubmit={handleTransaction} className="pt-4 border-t border-border">
                                        <h4 className="text-sm font-bold mb-3 flex items-center gap-2"><Plus size={16} /> {t.addNewExpense[language]}</h4>
                                        <div className="space-y-3">
                                            <div className="relative">
                                                <input
                                                    type="number"
                                                    inputMode="decimal"
                                                    value={amount}
                                                    onChange={(e) => handleAmountChange(e.target.value, setAmount)}
                                                    placeholder="0"
                                                    className="w-full bg-background border border-input rounded-lg p-3 pl-4 text-lg font-bold outline-none focus:border-primary font-mono text-foreground"
                                                />
                                                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground font-bold text-sm">{currency}</span>
                                            </div>

                                            {selectedCategory.tags && (
                                                <div className="flex flex-wrap gap-2">
                                                    {selectedCategory.tags.map(tag => (
                                                        <button
                                                            key={tag}
                                                            type="button"
                                                            onClick={() => setDescription(tag)}
                                                            className={`text-[10px] px-2.5 py-1 rounded-full border transition-all ${description === tag ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input text-muted-foreground hover:border-primary'}`}
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
                                                    className="w-1/3 bg-background border border-input rounded-lg p-3 text-sm focus:border-primary outline-none text-foreground"
                                                />
                                                <input
                                                    type="text"
                                                    value={description}
                                                    onChange={(e) => setDescription(e.target.value)}
                                                    placeholder={t.description[language]}
                                                    className="flex-1 bg-background border border-input rounded-lg p-3 text-sm focus:border-primary outline-none text-foreground"
                                                />
                                                <button type="submit" className="bg-primary text-primary-foreground rounded-lg px-4 font-bold shadow-lg shadow-primary/20">
                                                    <Check size={20} />
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            ) : showModal === 'goal' ? (
                                <>
                                    <h3 className="text-xl font-bold mb-4 text-foreground">{t.addGoal[language]}</h3>
                                    <form onSubmit={handleAddGoal} className="space-y-4">
                                        <input
                                            placeholder={t.name[language]}
                                            value={newGoalName}
                                            onChange={e => setNewGoalName(e.target.value)}
                                            className="w-full bg-background border border-input rounded-lg p-3 outline-none text-foreground"
                                        />
                                        <div className="relative">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                placeholder={t.target[language]}
                                                value={newGoalTarget}
                                                onChange={e => handleAmountChange(e.target.value, setNewGoalTarget)}
                                                className="w-full bg-background border border-input rounded-lg p-3 outline-none text-foreground"
                                            />
                                            <span className="absolute right-3 top-3 text-muted-foreground text-sm font-bold">{currency}</span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4 mt-4">
                                            <button type="button" onClick={() => setShowModal(null)} className="py-3 rounded-xl bg-muted font-bold text-muted-foreground">{t.cancel[language]}</button>
                                            <button type="submit" className="py-3 rounded-xl bg-primary text-primary-foreground font-bold shadow-lg shadow-primary/20">{t.save[language]}</button>
                                        </div>
                                    </form>
                                </>
                            ) : showModal === 'edit_goal' && editingGoal ? (
                                <div className="space-y-4">
                                    <h3 className="text-xl font-bold text-foreground">{t.editGoal[language]}</h3>
                                    <div className="relative">
                                        <input
                                            type="text"
                                            inputMode="decimal"
                                            placeholder={t.newTarget[language]}
                                            value={editGoalTarget}
                                            onChange={e => handleAmountChange(e.target.value, setEditGoalTarget)}
                                            autoFocus
                                            className="w-full bg-background border border-input rounded-lg p-3 outline-none text-foreground font-mono text-lg"
                                        />
                                        <span className="absolute right-3 top-3 text-muted-foreground text-sm font-bold">{currency}</span>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <button onClick={() => setShowModal(null)} className="py-3 rounded-xl bg-muted font-bold text-muted-foreground">{t.cancel[language]}</button>
                                        <button onClick={() => {
                                            const newTarget = parseAmount(editGoalTarget);
                                            if (newTarget > 0 && selectedGoal) {
                                                setState(prev => ({
                                                    ...prev,
                                                    savingsGoals: prev.savingsGoals.map(g => g.id === selectedGoal.id ? { ...g, targetAmount: newTarget } : g)
                                                }));
                                                setShowModal(null); setSelectedGoal(null);
                                            }
                                        }} className="py-3 rounded-xl bg-primary text-primary-foreground font-bold shadow-lg shadow-primary/20">{t.save[language]}</button>
                                    </div>
                                </div>
                            ) : showModal === 'spend_goal' && selectedGoal ? (
                                <form onSubmit={handleTransaction}>
                                    <div className="flex items-center gap-2 mb-4 text-muted-foreground">
                                        <Wallet size={18} />
                                        <span className="text-sm font-bold uppercase">{t.spendFromGoal[language]}</span>
                                    </div>
                                    <h3 className="text-xl font-bold mb-2 text-foreground">
                                        {t.spend[language]}: "{selectedGoal.name}"
                                    </h3>
                                    <p className="text-xs text-muted-foreground mb-6">
                                        {t.spendFromGoalDesc[language]}
                                    </p>

                                    <div className="mb-6 relative">
                                        <input
                                            type="text"
                                            inputMode="decimal"
                                            value={amount}
                                            onChange={(e) => handleAmountChange(e.target.value, setAmount)}
                                            placeholder="0"
                                            autoFocus
                                            className="w-full bg-transparent border-b-2 border-border focus:border-primary p-2 text-4xl font-bold text-center focus:outline-none text-foreground placeholder-muted font-mono"
                                        />
                                        <span className="absolute right-0 top-1/2 -translate-y-1/2 text-muted-foreground font-bold">{currency}</span>
                                    </div>
                                    <div className="mb-6">
                                        <input
                                            type="text"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                            placeholder={t.descriptionOptional[language]}
                                            className="w-full bg-background border border-input rounded-lg p-3 text-sm focus:border-primary outline-none text-foreground"
                                        />
                                    </div>
                                    <button type="submit" className="w-full py-3.5 rounded-xl font-bold shadow-lg transition-colors text-white bg-primary hover:bg-primary/90 shadow-primary/20">
                                        {t.confirmSpend[language]}
                                    </button>
                                </form>
                            ) : (
                                <form onSubmit={handleTransaction}>
                                    <h3 className="text-xl font-bold mb-6 text-foreground">
                                        {showModal === 'edit_transaction' ? t.edit[language] : (showModal === 'expense' ? t.addExpense[language] : t.manualAdd[language])}
                                    </h3>
                                    <div className="mb-6 relative">
                                        <input
                                            type="text"
                                            inputMode="decimal"
                                            value={amount}
                                            onChange={(e) => handleAmountChange(e.target.value, setAmount)}
                                            placeholder="0"
                                            autoFocus
                                            className="w-full bg-transparent border-b-2 border-border focus:border-primary p-2 text-4xl font-bold text-center focus:outline-none text-foreground placeholder-muted font-mono"
                                        />
                                        <span className="absolute right-0 top-1/2 -translate-y-1/2 text-muted-foreground font-bold">{currency}</span>
                                    </div>

                                    {showModal === 'expense' && selectedCategory?.tags && (
                                        <div className="mb-4 flex flex-wrap gap-2">
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

                                    <div className="mb-6 flex gap-2">
                                        <input
                                            type="date"
                                            value={transactionDate}
                                            onChange={(e) => setTransactionDate(e.target.value)}
                                            className="w-1/3 bg-background border border-input rounded-lg p-3 text-sm focus:border-primary outline-none text-foreground"
                                        />
                                        <input
                                            type="text"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                            placeholder={t.description[language]}
                                            className="flex-1 w-2/3 bg-background border border-input rounded-lg p-3 text-sm focus:border-primary outline-none text-foreground"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <button type="button" onClick={() => { setShowModal(null); setAmount(''); }} className="py-3.5 rounded-xl bg-muted text-muted-foreground font-semibold hover:bg-muted/80 transition-colors">{t.cancel[language]}</button>
                                        <button type="submit" className={`py-3.5 rounded-xl font-bold shadow-lg transition-colors text-white ${showModal === 'income' || showModal === 'edit_transaction' ? 'bg-primary hover:bg-primary/90 shadow-primary/20' : 'bg-destructive hover:bg-destructive/90 shadow-destructive/20'}`}>{t.confirm[language]}</button>
                                    </div>
                                </form>
                            )}
                        </motion.div>
                    </div>
                )
                }
            </AnimatePresence >
        </div >
    );
};

export default Dashboard;