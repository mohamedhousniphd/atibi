import React, { useRef, useState } from 'react';
import { Download, Upload, RefreshCw, Save, Globe, Edit3, Trash2, Plus, CreditCard, Moon, Sun, Monitor, Calendar, ExternalLink, Calculator, AlertTriangle } from 'lucide-react';
import { AppState, Language, Debt, Theme } from '../types';
import { DEFAULT_STATE, TRANSLATIONS, validateState, CATEGORY_NAMES } from '../constants';

interface SettingsProps {
    state: AppState;
    setState: React.Dispatch<React.SetStateAction<AppState>>;
    goToDashboard?: () => void;
}

const Settings: React.FC<SettingsProps> = ({ state, setState, goToDashboard }) => {
    const { language, monthlyIncome, currency, debts, theme } = state;
    const t = TRANSLATIONS;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const isRTL = language === 'ar';

    const [isEditingSalary, setIsEditingSalary] = useState(false);
    const [tempSalary, setTempSalary] = useState(monthlyIncome.toString());

    const [showDebtEditor, setShowDebtEditor] = useState(false);
    const [newDebtName, setNewDebtName] = useState('');
    const [newDebtAmount, setNewDebtAmount] = useState('');
    const [newDebtEndDate, setNewDebtEndDate] = useState('');
    const [newDebtType, setNewDebtType] = useState<'source' | 'bank'>('bank');

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

    const parseAmount = (val: string) => {
        const num = parseFloat(val.replace(/,/g, '.'));
        return isNaN(num) ? 0 : round(num);
    };

    const handleLanguageChange = (lang: Language) => {
        setState(prev => ({ ...prev, language: lang }));
    };

    const handleThemeChange = (newTheme: Theme) => {
        setState(prev => ({ ...prev, theme: newTheme }));
    };

    const handleExport = () => {
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(state));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", `esalaire_backup_${new Date().toISOString().split('T')[0]}.json`);
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    };

    const handleImportClick = () => fileInputRef.current?.click();

    const handleImportFile = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const json = JSON.parse(e.target?.result as string);
                if (json && typeof json === 'object') {
                    const validated = validateState(json);
                    setState(validated);
                    alert("Data imported successfully!");
                } else {
                    alert("Invalid file format.");
                }
            } catch (err) {
                alert("Error reading file.");
            }
        };
        reader.readAsText(file);
    };

    const handleResetMonth = () => {
        if (confirm("Clear monthly spending and credit salary?")) {
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
            if (window.confetti) window.confetti({ colors: ['#6366f1', '#a855f7'] });
        }
    };

    const handleRecalculateBudgets = () => {
        if (confirm("This will reset your category limits based on the 30/40/10/10/10 rule using your spendable income (after bank deductions). Continue?")) {
            const spendable = state.monthlyIncome;
            const updatedCategories = state.categories.map(cat => {
                let ratio = 0.10;
                if (cat.id === 'c1') ratio = 0.30; // Fixed
                else if (cat.id === 'c2') ratio = 0.40; // Needs
                else if (cat.id === 'c3') ratio = 0.10; // Wants
                else if (cat.id === 'c4') ratio = 0.10; // Savings
                else if (cat.id === 'c5') ratio = 0.10; // Emergency

                return { ...cat, limit: Math.round(spendable * ratio) };
            });

            setState(prev => ({ ...prev, categories: updatedCategories }));
            if (window.confetti) window.confetti({ particleCount: 50, spread: 60, origin: { y: 0.7 } });
        }
    };

    const handleLoadPlan = () => {
        if (confirm("Reset to default plan?")) {
            setState({ ...DEFAULT_STATE, language: state.language, hasCompletedOnboarding: true });
            if (goToDashboard) goToDashboard();
        }
    };

    const handleFullReset = () => {
        if (confirm(t.resetEverythingConfirm[language])) {
            setState({ ...DEFAULT_STATE, language: state.language, theme: state.theme });
            if (goToDashboard) goToDashboard();
        }
    };

    const saveSalary = () => {
        const val = parseAmount(tempSalary);
        if (val > 0) {
            setState(prev => ({ ...prev, monthlyIncome: val }));
            setIsEditingSalary(false);
        }
    };

    const addNewDebt = () => {
        const amount = parseAmount(newDebtAmount);
        if (newDebtName && amount > 0) {
            setState(prev => ({
                ...prev,
                debts: [...prev.debts, {
                    id: Date.now().toString(),
                    name: newDebtName,
                    amount: amount,
                    endDate: newDebtEndDate,
                    deductionType: newDebtType
                }]
            }));
            setNewDebtName(''); setNewDebtAmount(''); setNewDebtEndDate('');
        }
    };

    const removeDebt = (id: string) => {
        setState(prev => ({ ...prev, debts: prev.debts.filter(d => d.id !== id) }));
    };

    const getMonthsLeft = (endDateStr: string) => {
        if (!endDateStr) return 0;
        const end = new Date(endDateStr);
        if (isNaN(end.getTime())) return 0;
        const now = new Date();
        let months = (end.getFullYear() - now.getFullYear()) * 12;
        months -= now.getMonth();
        months += end.getMonth();
        return Math.max(0, months);
    };

    const ActionButton = ({ onClick, label, desc, icon: Icon, variant = 'default' }: any) => (
        <button
            onClick={onClick}
            className={`w-full flex items-center justify-between p-4 rounded-lg transition-all border ${variant === 'destructive'
                ? 'bg-destructive/10 border-destructive/30 hover:bg-destructive/20 text-destructive'
                : variant === 'default'
                    ? 'bg-card border-border hover:bg-secondary/50 text-card-foreground'
                    : 'bg-primary text-primary-foreground border-primary hover:bg-primary/90'
                }`}
        >
            <div className="text-left">
                <span className="font-medium block">{label}</span>
                {desc && <span className="text-[10px] opacity-80 block">{desc}</span>}
            </div>
            <Icon size={18} className={variant === 'default' ? "text-muted-foreground" : (variant === 'destructive' ? 'text-destructive' : "text-primary-foreground")} />
        </button>
    );

    return (
        <div className={`p-4 space-y-6 pb-24 ${isRTL ? 'text-right' : 'text-left'}`}>
            <h1 className="text-2xl font-bold mb-6 tracking-tight">{t.settings[language]}</h1>

            {/* Language & Theme */}
            <section className="bg-card p-5 rounded-xl border border-border shadow-sm space-y-4">
                <div>
                    <div className="flex items-center space-x-2 mb-3 rtl:space-x-reverse text-primary">
                        <Globe size={18} />
                        <h3 className="font-semibold text-sm">{t.language[language]}</h3>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        {(['en', 'fr', 'ar'] as Language[]).map(lang => (
                            <button
                                key={lang}
                                onClick={() => handleLanguageChange(lang)}
                                className={`py-2 rounded-lg border text-xs font-bold transition-all ${language === lang ? 'bg-primary border-primary text-primary-foreground' : 'bg-background border-input text-muted-foreground hover:bg-secondary'}`}
                            >
                                {lang.toUpperCase()}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="h-px bg-border/50" />
                <div>
                    <div className="flex items-center space-x-2 mb-3 rtl:space-x-reverse text-primary">
                        <Sun size={18} />
                        <h3 className="font-semibold text-sm">{t.theme[language]}</h3>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        {['light', 'dark', 'system'].map((th) => (
                            <button
                                key={th}
                                onClick={() => handleThemeChange(th as Theme)}
                                className={`capitalize flex items-center justify-center gap-2 py-2 rounded-lg border text-xs font-bold transition-all ${theme === th ? 'bg-primary border-primary text-primary-foreground' : 'bg-background border-input text-muted-foreground'}`}
                            >
                                {th}
                            </button>
                        ))}
                    </div>
                </div>
            </section>

            {/* Profile */}
            <section className="bg-card p-5 rounded-xl border border-border shadow-sm">
                <div className="flex justify-between items-center mb-3">
                    <h3 className="font-semibold text-sm uppercase tracking-wider text-muted-foreground">{t.monthlyIncome[language]}</h3>
                    {!isEditingSalary && (
                        <button onClick={() => setIsEditingSalary(true)} className="text-primary hover:text-primary/80">
                            <Edit3 size={18} />
                        </button>
                    )}
                </div>
                {isEditingSalary ? (
                    <div className="flex gap-2">
                        <input
                            type="number"
                            value={tempSalary}
                            onChange={(e) => setTempSalary(e.target.value)}
                            className="flex-1 bg-background border border-input rounded-lg px-3 py-2 font-mono text-lg text-foreground"
                            step="0.01"
                        />
                        <button onClick={saveSalary} className="bg-primary text-primary-foreground px-4 rounded-lg font-bold">OK</button>
                    </div>
                ) : (
                    <div className="text-2xl font-bold font-mono text-foreground">
                        {formatMoney(monthlyIncome)} {currency}
                    </div>
                )}
            </section>

            {/* Debts */}
            <section className="bg-card p-5 rounded-xl border border-border shadow-sm">
                <div className="flex justify-between items-center mb-4">
                    <div className="flex items-center gap-2">
                        <CreditCard size={18} className="text-primary" />
                        <h3 className="font-semibold">{t.manageDebts[language]}</h3>
                    </div>
                </div>

                <div className="space-y-3">
                    {debts.map(debt => {
                        const monthsLeft = getMonthsLeft(debt.endDate);
                        return (
                            <div key={debt.id} className="flex justify-between items-center p-2 bg-muted/20 rounded-lg border border-border/50">
                                <div>
                                    <div className="font-medium text-sm flex items-center gap-2">
                                        {debt.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground font-mono">
                                        -{formatMoney(debt.amount)} {currency} • {monthsLeft} {t.monthsLeft[language]}
                                    </div>
                                </div>
                                <button onClick={() => removeDebt(debt.id)} className="text-destructive p-2 hover:bg-destructive/10 rounded">
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        );
                    })}
                    {debts.length === 0 && <div className="text-sm text-muted-foreground italic text-center py-2">{t.noDebtsListed[language]}</div>}

                    {!showDebtEditor ? (
                        <button
                            onClick={() => setShowDebtEditor(true)}
                            className="w-full mt-4 flex items-center justify-center gap-2 py-3 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-lg font-bold text-sm transition-colors"
                        >
                            <Plus size={16} />
                            {t.addNewDebt[language]}
                        </button>
                    ) : (
                        <div className="flex flex-col gap-2 mt-4 p-3 bg-secondary/20 rounded-lg border border-border/50 animate-in fade-in zoom-in-95 duration-200">
                            <div className="flex justify-between items-center mb-1">
                                <div className="text-xs font-bold text-muted-foreground uppercase">{t.addNewDebt[language]}</div>
                                <button onClick={() => setShowDebtEditor(false)} className="text-xs text-muted-foreground hover:text-foreground">{t.cancel[language]}</button>
                            </div>

                            <div className="flex gap-2 min-w-0">
                                <input
                                    placeholder={t.name[language]}
                                    value={newDebtName}
                                    onChange={e => setNewDebtName(e.target.value)}
                                    className="flex-1 min-w-0 text-sm p-2 bg-background border border-input rounded outline-none focus:border-primary text-foreground"
                                />
                                <input
                                    type="number"
                                    placeholder={t.amount[language]}
                                    value={newDebtAmount}
                                    onChange={e => setNewDebtAmount(e.target.value)}
                                    className="w-24 flex-shrink-0 text-sm p-2 bg-background border border-input rounded outline-none focus:border-primary font-mono text-foreground"
                                    step="0.01"
                                />
                            </div>


                            <div className="flex gap-2 items-center mt-1">
                                <div className="relative flex-1">
                                    <input
                                        type="month"
                                        value={newDebtEndDate}
                                        onChange={e => setNewDebtEndDate(e.target.value)}
                                        className="w-full text-sm p-2 bg-background border border-input rounded outline-none focus:border-primary text-foreground"
                                    />
                                    {!newDebtEndDate && <span className="absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground text-xs pointer-events-none">{t.endDate[language]}</span>}
                                </div>
                                <button onClick={addNewDebt} className="p-2 bg-primary text-primary-foreground rounded hover:bg-primary/90">
                                    <Plus size={16} />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </section>

            {/* Actions */}
            <section className="space-y-3">
                <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider px-1">{t.actions[language]}</h3>
                <ActionButton onClick={handleResetMonth} label={t.resetMonth[language]} icon={RefreshCw} />
                <ActionButton onClick={handleRecalculateBudgets} label={t.recalcBudgets[language]} desc={t.recalcDesc[language]} icon={Calculator} />
                <ActionButton onClick={handleLoadPlan} label={t.loadPlan[language]} icon={Save} />
                <ActionButton onClick={handleFullReset} label={t.resetEverything[language]} desc={t.resetEverythingDesc[language]} icon={AlertTriangle} variant="destructive" />
            </section>

            <section className="space-y-3">
                <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider px-1">{t.data[language]}</h3>
                <ActionButton onClick={handleExport} label={t.exportData[language]} icon={Download} />
                <input type="file" ref={fileInputRef} onChange={handleImportFile} className="hidden" accept=".json" />
                <ActionButton onClick={handleImportClick} label={t.importData[language]} icon={Upload} />
            </section>

            <div className="flex flex-col items-center justify-center text-xs text-muted-foreground mt-8 pb-4 space-y-1">
                <p>esalaire v1.1.2</p>
                <a
                    href="https://www.mohamedhousni.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 hover:text-primary transition-colors font-medium"
                >
                    Designed & Engineered by Mohamed HOUSNI Ph.D. <ExternalLink size={10} />
                </a>
            </div>
        </div>
    );
};

export default Settings;