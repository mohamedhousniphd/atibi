import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Calculator as CalculatorIcon, ArrowRight, Calendar, Edit2, CheckCircle2, Save, Wrench } from 'lucide-react';
import { AppState } from '../types';
import { TRANSLATIONS } from '../constants';

interface ToolsProps {
    state: AppState;
    setState: React.Dispatch<React.SetStateAction<AppState>>;
    goToDashboard?: () => void;
}

// MOVED OUTSIDE to fix focus loss bug
const InputField = ({ label, value, onChange, placeholder }: any) => (
    <div>
        <label className="block text-sm font-medium text-muted-foreground mb-1.5">{label}</label>
        <input
            type="number"
            inputMode="decimal"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className="w-full bg-background border border-input rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:border-input outline-none transition-all placeholder:text-muted font-mono text-foreground"
            placeholder={placeholder}
            step="0.01"
        />
    </div>
);

const Tools: React.FC<ToolsProps> = ({ state, setState, goToDashboard }) => {
    const { language, currency, monthlyIncome, debts, backpays } = state;
    const t = TRANSLATIONS;
    const isRTL = language === 'ar';

    // Calculate Base Net (Bank + Source Deductions)
    const sourceDeductions = debts.filter(d => d.deductionType === 'source').reduce((acc, d) => acc + d.amount, 0);
    const estimatedBaseNet = monthlyIncome + sourceDeductions;

    const formatMoney = (val: number) => {
        return val.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // Form State for NEW backpay
    const [oldSalary, setOldSalary] = useState(estimatedBaseNet > 0 ? estimatedBaseNet.toFixed(2) : '');
    const [newSalary, setNewSalary] = useState('');
    const [monthlyDiff, setMonthlyDiff] = useState('0');
    const [isManualDiff, setIsManualDiff] = useState(false);
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState(''); // New optional end date
    const [description, setDescription] = useState(''); // New optional description

    // Parse decimal input safely
    const parseInput = (val: string): number => {
        if (!val) return 0;
        const clean = val.replace(/,/g, '.').replace(/[^0-9.]/g, '');
        const num = parseFloat(clean);
        // Round to 2 decimals to match app logic
        return isNaN(num) ? 0 : Math.round((num + Number.EPSILON) * 100) / 100;
    };

    useEffect(() => {
        if (!isManualDiff) {
            const oldS = parseInput(oldSalary);
            const newS = parseInput(newSalary);
            if (newS > oldS) {
                setMonthlyDiff((newS - oldS).toFixed(2));
            } else {
                setMonthlyDiff('0');
            }
        }
    }, [oldSalary, newSalary, isManualDiff]);

    const saveConfiguration = () => {
        const amount = parseInput(monthlyDiff);
        const selectedDate = new Date(startDate);
        const now = new Date();

        // Validate Date: Must be in past or current month
        if (selectedDate > new Date(now.getFullYear(), now.getMonth() + 1, 0)) {
            alert(t.dateError[language]);
            return;
        }

        if (amount > 0 && startDate) {
            setState(prev => ({
                ...prev,
                backpays: [
                    ...(prev.backpays || []),
                    {
                        id: Date.now().toString(),
                        monthlyAmount: amount,
                        startDate: startDate,
                        endDate: endDate || undefined,
                        description: description || 'Rappel'
                    }
                ]
            }));

            if (window.confetti) {
                window.confetti({ particleCount: 50, spread: 50 });
            }

            // Reset form
            setMonthlyDiff('0');
            setStartDate('');
            setEndDate('');
            setDescription('');
            setNewSalary('');
        } else {
            alert("Please enter a difference and valid start date.");
        }
    };

    const deleteBackpay = (id: string) => {
        setState(prev => ({
            ...prev,
            backpays: prev.backpays.filter(b => b.id !== id)
        }));
    };

    // Calculate active backpay totals for display
    const activeBackpays = backpays || [];
    let displayTotalPending = 0;

    if (activeBackpays.length > 0) {
        const now = new Date();
        activeBackpays.forEach(bp => {
            const startD = new Date(bp.startDate);
            const endD = bp.endDate ? new Date(bp.endDate) : now;
            const effectiveEndD = endD < now ? endD : now;
            if (!isNaN(startD.getTime())) {
                let months = (effectiveEndD.getFullYear() - startD.getFullYear()) * 12;
                months -= startD.getMonth();
                months += effectiveEndD.getMonth();
                months = Math.max(0, months + 1);
                displayTotalPending += (months * bp.monthlyAmount);
            }
        });
    }

    return (
        <div className={`p-4 space-y-6 pb-24 ${isRTL ? 'text-right' : 'text-left'}`}>
            <h2 className="text-xl font-bold flex items-center gap-2 mb-6">
                <Wrench className="text-primary" />
                {t.financialTools[language]}
            </h2>

            <div className="bg-card p-5 rounded-2xl border border-border shadow-sm">
                <div className="flex justify-between items-start mb-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-primary/10 rounded-xl text-primary">
                            <CalculatorIcon size={20} />
                        </div>
                        <div>
                            <h3 className="font-bold">{t.backpayTracker[language]}</h3>
                            <p className="text-xs text-muted-foreground">{t.backpayDesc[language]}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="space-y-5 mt-6">
                <InputField
                    label={t.oldSalary[language]}
                    value={oldSalary}
                    onChange={setOldSalary}
                    placeholder="0.00"
                />
                <InputField
                    label={t.newSalary[language]}
                    value={newSalary}
                    onChange={setNewSalary}
                    placeholder="0.00"
                />

                {/* Difference */}
                <div className="p-4 bg-muted/30 rounded-lg border border-border/50">
                    <div className="flex justify-between items-center mb-2">
                        <label className="text-xs uppercase font-bold text-muted-foreground">{t.monthlyDiff[language]}</label>
                        <button
                            onClick={() => setIsManualDiff(!isManualDiff)}
                            className={`p-1 rounded ${isManualDiff ? 'text-primary bg-primary/10' : 'text-muted-foreground hover:bg-muted'}`}
                        >
                            <Edit2 size={14} />
                        </button>
                    </div>
                    <input
                        type="number"
                        inputMode="decimal"
                        value={monthlyDiff}
                        onChange={(e) => {
                            setMonthlyDiff(e.target.value);
                            setIsManualDiff(true);
                        }}
                        className="w-full bg-transparent text-2xl font-bold font-mono outline-none text-foreground"
                    />
                    <div className="text-xs text-muted-foreground mt-1">
                        {isManualDiff ? t.manualOverride[language] : t.autoCalc[language]}
                    </div>
                </div>

                {/* Date Selector */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-muted-foreground mb-2">{t.startDate[language]}</label>
                        <div className="relative">
                            <input
                                type="month"
                                value={startDate}
                                max={new Date().toISOString().slice(0, 7)} // Prevent future dates HTML5
                                onChange={(e) => setStartDate(e.target.value)}
                                className="w-full bg-background border border-input rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-foreground text-sm"
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-muted-foreground mb-2">End Date (Optional)</label>
                        <div className="relative">
                            <input
                                type="month"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                min={startDate || undefined}
                                className="w-full bg-background border border-input rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-foreground text-sm"
                            />
                        </div>
                    </div>
                </div>
                <InputField
                    label="Description (Optional)"
                    value={description}
                    onChange={setDescription}
                    placeholder="e.g. Promotion 2023"
                />

                <button
                    onClick={saveConfiguration}
                    className="w-full py-3.5 bg-primary hover:bg-primary/90 rounded-xl font-bold text-primary-foreground transition-all active:scale-[0.98] mt-4 shadow-sm flex items-center justify-center gap-2"
                >
                    <Save size={18} />
                    {t.calculate[language]}
                </button>

                {/* Active Backpays List */}
                {activeBackpays.length > 0 && (
                    <div className="mt-8 pt-6 border-t border-border">
                        <div className="flex justify-between items-end mb-4">
                            <h3 className="text-sm font-bold uppercase tracking-wide text-muted-foreground">Active Tracking</h3>
                            <div className="text-right">
                                <div className="text-[10px] text-muted-foreground font-bold uppercase">Total Pending</div>
                                <div className="font-mono font-bold text-accent-foreground text-sm">{formatMoney(displayTotalPending)} {currency}</div>
                            </div>
                        </div>
                        <div className="space-y-3">
                            {activeBackpays.map(bp => (
                                <div key={bp.id} className="flex justify-between items-center p-3 bg-muted/20 rounded-lg border border-border/50">
                                    <div>
                                        <div className="font-bold text-sm">{bp.description || 'Rappel'}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {bp.startDate} {bp.endDate ? `→ ${bp.endDate}` : '→ Now'}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="font-mono font-bold text-primary">+{formatMoney(bp.monthlyAmount)}/mo</span>
                                        <button onClick={() => deleteBackpay(bp.id)} className="text-muted-foreground hover:text-destructive p-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18" /><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg>
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default Tools;