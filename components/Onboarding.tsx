import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShieldCheck, Wallet, ChevronRight, ChevronLeft, Check, Home, CreditCard, Lock, Banknote, Trash2, Calendar, Globe, Sparkles, AlertTriangle, Moon, Sun, Users, PartyPopper } from 'lucide-react';
import { AppState, Language, Debt } from '../types';
import { TRANSLATIONS, ASSET_CHIPS } from '../constants';

interface OnboardingProps {
    state: AppState;
    setState: React.Dispatch<React.SetStateAction<AppState>>;
}

const Onboarding: React.FC<OnboardingProps> = ({ state, setState }) => {
    const [step, setStep] = useState(0);
    const { language, currency, theme } = state;
    const t = TRANSLATIONS;
    const isRTL = language === 'ar';

    const [income, setIncome] = useState('');
    const [assets, setAssets] = useState('');
    const [tempDebts, setTempDebts] = useState<Debt[]>([]);
    const [showDebtFreeCelebration, setShowDebtFreeCelebration] = useState(false);

    const handleLanguageChange = (lang: Language) => {
        setState(prev => ({ ...prev, language: lang }));
    };

    const toggleLanguage = () => {
        const nextLang: Record<Language, Language> = {
            'en': 'fr',
            'fr': 'ar',
            'ar': 'en'
        };
        setState(prev => ({ ...prev, language: nextLang[prev.language] }));
    };

    const toggleTheme = () => {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        setState(prev => ({ ...prev, theme: nextTheme }));
    };

    const addDebt = (type: 'housing' | 'consumption' | 'family') => {
        let name = '';
        if (type === 'housing') name = t.housingLoan[language];
        else if (type === 'consumption') name = t.consumptionLoan[language];
        else name = t.familyDebt[language];

        const newDebt: Debt = {
            id: Date.now().toString(),
            name,
            amount: 0,
            endDate: '',
            deductionType: 'bank'
        };
        setTempDebts([...tempDebts, newDebt]);
    };

    const handleNoDebt = () => {
        setTempDebts([]);
        setShowDebtFreeCelebration(true);
        if (window.confetti) {
            window.confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#10b981', '#34d399', '#6ee7b7'] // Greens
            });
        }
        // Auto advance after short delay
        setTimeout(() => {
            setShowDebtFreeCelebration(false);
            setStep(step + 1);
        }, 2000);
    };

    const updateDebt = (id: string, field: keyof Debt, value: any) => {
        setTempDebts(tempDebts.map(d => d.id === id ? { ...d, [field]: value } : d));
    };

    const removeDebt = (id: string) => {
        setTempDebts(tempDebts.filter(d => d.id !== id));
    };

    const finishOnboarding = () => {
        const incomeVal = parseFloat(income) || 0;
        const assetsVal = parseFloat(assets) || 0;

        // Based on user feedback: do not subtract bank debts, distribute over full salary
        const spendable = incomeVal;

        // 30% Fixed, 40% Needs, 10% Wants, 10% Savings, 10% Emergency of SPENDABLE income
        const updatedCategories = state.categories.map(cat => {
            let ratio = 0.10;
            if (cat.id === 'c1') ratio = 0.30; // Fixed
            if (cat.id === 'c2') ratio = 0.40; // Essentials
            if (cat.id === 'c3') ratio = 0.10; // Personal
            if (cat.id === 'c4') ratio = 0.10; // Savings
            if (cat.id === 'c5') ratio = 0.10; // Emergency

            return { ...cat, limit: Math.round(spendable * ratio) };
        });

        setState(prev => ({
            ...prev,
            monthlyIncome: incomeVal,
            liquidAssets: assetsVal,
            debts: tempDebts,
            categories: updatedCategories,
            hasCompletedOnboarding: true
        }));
    };

    const slideVariants = {
        enter: (direction: number) => ({ x: direction > 0 ? 300 : -300, opacity: 0 }),
        center: { zIndex: 1, x: 0, opacity: 1 },
        exit: (direction: number) => ({ zIndex: 0, x: direction < 0 ? 300 : -300, opacity: 0 })
    };

    const steps = [
        // STEP 0: Welcome & Explanation
        <div className="flex flex-col items-center text-center space-y-8 h-full justify-center px-4 relative">
            <div className="absolute top-0 right-0 p-4 flex gap-2">
                <button
                    onClick={toggleTheme}
                    className="p-2 bg-secondary/50 rounded-full text-muted-foreground hover:text-foreground transition-colors"
                >
                    {theme === 'dark' ? <Moon size={16} /> : <Sun size={16} />}
                </button>
                <button
                    onClick={toggleLanguage}
                    className="flex items-center gap-1.5 px-3 py-1.5 bg-secondary/50 rounded-full text-[10px] font-bold uppercase tracking-wider text-muted-foreground hover:bg-secondary transition-colors"
                >
                    <Globe size={12} /> {language}
                </button>
            </div>

            <motion.div
                initial={{ scale: 0.8, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ duration: 0.5 }}
                className="p-6 bg-primary/10 rounded-full text-primary ring-8 ring-primary/5"
            >
                <Sparkles size={56} />
            </motion.div>

            <div className="space-y-4 max-w-xs mx-auto">
                <h2 className="text-3xl font-bold tracking-tight">{t.welcome[language]}</h2>
                <p className="text-muted-foreground text-lg leading-relaxed">
                    {t.appDescription[language]}
                </p>
            </div>
        </div>,

        // STEP 1: Trust & Privacy (Emphasized)
        <div className="flex flex-col items-center text-center space-y-6 h-full justify-center px-4">
            <div className="relative">
                <div className="absolute inset-0 bg-emerald-500/20 blur-xl rounded-full"></div>
                <div className="relative p-6 bg-card border-2 border-emerald-500/20 rounded-full text-emerald-500 mb-4">
                    <ShieldCheck size={64} strokeWidth={1.5} />
                </div>
            </div>

            <h2 className="text-2xl font-bold">{t.trustTitle[language]}</h2>

            <div className="bg-destructive/10 border border-destructive/20 p-4 rounded-xl max-w-xs mx-auto">
                <p className="text-destructive font-bold text-sm leading-relaxed">
                    {t.trustDesc[language]}
                </p>
            </div>

            <div className="flex gap-4 text-xs font-mono text-muted-foreground mt-8">
                <span className="flex items-center gap-1 bg-secondary/50 px-3 py-1.5 rounded font-bold"><Lock size={12} /> {t.localStorage[language]}</span>
                <span className="flex items-center gap-1 bg-secondary/50 px-3 py-1.5 rounded font-bold"><Wallet size={12} /> {t.offline[language]}</span>
            </div>
        </div>,

        // STEP 2: Net Salary (Emphasized)
        <div className="space-y-6 h-full flex flex-col justify-center">
            <div className="text-center space-y-4">
                <div className="inline-block px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold uppercase tracking-wide">
                    {t.pocketMoneyOnly[language]}
                </div>
                <h2 className="text-2xl font-bold">{t.enterSalary[language]}</h2>
                <p className="text-sm font-medium text-muted-foreground px-6 leading-relaxed">
                    {t.enterSalaryDesc[language]}
                </p>
            </div>
            <div className="relative max-w-xs mx-auto w-full">
                <input
                    type="number"
                    inputMode="decimal"
                    value={income}
                    onChange={(e) => setIncome(e.target.value)}
                    placeholder="0"
                    autoFocus
                    className="w-full text-center bg-transparent border-b-2 border-primary/30 focus:border-primary p-4 text-5xl font-bold outline-none font-mono transition-colors"
                />
                <span className="absolute right-0 top-1/2 -translate-y-1/2 text-muted-foreground font-bold text-xl">{currency}</span>
            </div>
        </div>,

        // STEP 3: Deductions (Optional)
        showDebtFreeCelebration ? (
            <div className="flex flex-col items-center justify-center h-full text-center space-y-6 p-6">
                <motion.div
                    initial={{ scale: 0, rotate: -45 }}
                    animate={{ scale: 1, rotate: 0 }}
                    className="p-6 bg-emerald-500 rounded-full text-white shadow-xl"
                >
                    <PartyPopper size={48} />
                </motion.div>
                <div className="space-y-2">
                    <h2 className="text-3xl font-bold text-emerald-500">{t.debtFreeTitle[language]}</h2>
                    <p className="text-muted-foreground text-lg">{t.debtFreeDesc[language]}</p>
                </div>
            </div>
        ) : (
            <div className="space-y-4 h-full flex flex-col">
                <div className="text-center pt-4">
                    <h2 className="text-xl font-bold mb-1">{t.addDeductions[language]}</h2>
                    <p className="text-xs font-bold text-muted-foreground/80 bg-secondary/30 p-2 rounded-lg inline-block mx-4">
                        {t.addDeductionDesc[language]}
                    </p>
                </div>

                <div className="grid grid-cols-3 gap-2">
                    <button onClick={() => addDebt('housing')} className="flex flex-col items-center gap-1.5 bg-card border border-border p-3 rounded-xl hover:bg-secondary/40 transition-colors active:scale-95"><Home size={20} className="text-primary" /><span className="text-[10px] font-bold">{t.housingLoan[language]}</span></button>
                    <button onClick={() => addDebt('consumption')} className="flex flex-col items-center gap-1.5 bg-card border border-border p-3 rounded-xl hover:bg-secondary/40 transition-colors active:scale-95"><CreditCard size={20} className="text-destructive" /><span className="text-[10px] font-bold">{t.consumptionLoan[language]}</span></button>
                    <button onClick={() => addDebt('family')} className="flex flex-col items-center gap-1.5 bg-card border border-border p-3 rounded-xl hover:bg-secondary/40 transition-colors active:scale-95"><Users size={20} className="text-orange-500" /><span className="text-[10px] font-bold">{t.familyDebt[language]}</span></button>
                </div>

                <div className="flex-1 overflow-y-auto space-y-3 p-1 min-h-[150px]">
                    {tempDebts.map(debt => (
                        <motion.div
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            key={debt.id}
                            className="bg-card border border-border p-3 rounded-xl flex flex-col gap-3 shadow-sm relative overflow-hidden"
                        >


                            <div className="flex justify-between items-start pl-2">
                                <input value={debt.name} onChange={(e) => updateDebt(debt.id, 'name', e.target.value)} className="bg-transparent font-bold text-sm w-full outline-none text-foreground" placeholder={t.debtName[language]} />
                                <button onClick={() => removeDebt(debt.id)} className="text-destructive p-1"><Trash2 size={16} /></button>
                            </div>
                            <div className="flex gap-2 items-center pl-2">
                                <input type="number" placeholder={t.amount[language]} value={debt.amount || ''} onChange={(e) => updateDebt(debt.id, 'amount', parseFloat(e.target.value))} className="bg-background border border-input rounded px-3 py-2 text-lg font-bold w-full outline-none font-mono focus:border-primary transition-colors text-foreground" />
                                <span className="text-sm font-bold text-muted-foreground">{currency}</span>
                            </div>

                            <div className="relative pl-2">
                                <input type="month" value={debt.endDate} onChange={(e) => updateDebt(debt.id, 'endDate', e.target.value)} className="w-full bg-background border border-input rounded text-xs px-2 py-2 outline-none font-mono text-foreground focus:border-primary" />
                                <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground font-bold pointer-events-none uppercase">{t.endDate[language]}</span>
                            </div>
                        </motion.div>
                    ))}

                    {tempDebts.length === 0 && (
                        <button
                            onClick={handleNoDebt}
                            className="w-full py-6 mt-4 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-emerald-500/30 rounded-xl bg-emerald-500/5 hover:bg-emerald-500/10 hover:border-emerald-500/50 transition-all group"
                        >
                            <Check className="text-emerald-500 group-hover:scale-110 transition-transform" size={24} />
                            <span className="text-emerald-600 font-bold">{t.noDebt[language]}</span>
                        </button>
                    )}
                </div>
            </div>
        ),

        // STEP 4: Assets
        <div className="space-y-6 h-full flex flex-col justify-center">
            <div className="text-center space-y-2">
                <h2 className="text-xl font-bold">{t.initialAssets[language]}</h2>
                <p className="text-sm font-medium text-muted-foreground px-4">{t.initialAssetsDesc[language]}</p>
            </div>

            <div className="flex flex-wrap justify-center gap-2 mb-4">
                {ASSET_CHIPS.map(chip => (
                    <span key={chip.id} className="text-[10px] px-2 py-1 rounded-full bg-secondary text-secondary-foreground border border-border">
                        {chip.label[language]}
                    </span>
                ))}
            </div>

            <div className="relative max-w-xs mx-auto w-full">
                <input
                    type="number"
                    inputMode="decimal"
                    value={assets}
                    onChange={(e) => setAssets(e.target.value)}
                    placeholder="0"
                    autoFocus
                    className="w-full text-center bg-transparent border-b-2 border-primary/30 focus:border-primary p-4 text-5xl font-bold outline-none font-mono transition-colors"
                />
                <span className="absolute right-0 top-1/2 -translate-y-1/2 text-muted-foreground font-bold text-xl">{currency}</span>
            </div>
        </div>
    ];

    return (
        <div className={`fixed inset-0 z-50 bg-background flex flex-col ${isRTL ? 'rtl' : 'ltr'}`}>
            {/* Progress Bar */}
            <div className="h-1 bg-muted w-full fixed top-0 left-0 z-50">
                <motion.div
                    className="h-full bg-primary"
                    initial={{ width: 0 }}
                    animate={{ width: `${((step + 1) / steps.length) * 100}%` }}
                />
            </div>

            <div className="flex-1 flex flex-col justify-center max-w-md mx-auto w-full p-6 relative overflow-hidden">
                <AnimatePresence mode="wait" custom={1}>
                    <motion.div
                        key={step} custom={1} variants={slideVariants} initial="enter" animate="center" exit="exit"
                        transition={{ type: "spring", stiffness: 300, damping: 30 }}
                        className="w-full h-full flex flex-col"
                    >
                        {steps[step]}
                    </motion.div>
                </AnimatePresence>
            </div>

            <div className="p-6 max-w-md mx-auto w-full bg-background/80 backdrop-blur-md pb-safe">
                {!showDebtFreeCelebration && (
                    <div className="flex gap-3">
                        {step > 0 && (
                            <button
                                onClick={() => setStep(step - 1)}
                                className="px-4 py-4 rounded-xl bg-muted text-foreground font-bold hover:bg-muted/80 transition-colors shadow-sm"
                                aria-label={t.back[language]}
                            >
                                <ChevronLeft size={24} className={isRTL ? 'rotate-180' : ''} />
                            </button>
                        )}
                        <button
                            onClick={() => { if (step < steps.length - 1) setStep(step + 1); else finishOnboarding(); }}
                            className={`flex-1 py-4 text-lg font-bold rounded-xl shadow-xl flex items-center justify-center gap-2 transition-all active:scale-[0.98] ${step === 0
                                ? 'bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90'
                                : 'bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90'
                                }`}
                        >
                            {step === steps.length - 1 ? t.finish[language] : (step === 0 ? t.startSetup[language] : (step === 1 ? t.continue[language] : t.next[language]))}
                            {step < steps.length - 1 && <ChevronRight size={20} className={isRTL ? 'rotate-180' : ''} />}
                            {step === steps.length - 1 && <Check size={20} />}
                        </button>
                    </div>
                )}

                {/* Dots */}
                {step > 0 && !showDebtFreeCelebration && (
                    <div className="flex justify-center gap-2 mt-6">
                        {steps.map((_, i) => (
                            <div key={i} className={`h-1.5 rounded-full transition-all duration-300 ${i === step ? 'w-6 bg-primary' : 'w-1.5 bg-muted'}`} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};

export default Onboarding;