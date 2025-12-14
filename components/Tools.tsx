import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Calculator as CalculatorIcon, ArrowRight, Calendar, Edit2, CheckCircle2, Save } from 'lucide-react';
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
  const { language, currency, monthlyIncome, debts, backpay } = state;
  const t = TRANSLATIONS;
  const isRTL = language === 'ar';

  // Calculate Base Net (Bank + Source Deductions)
  const sourceDeductions = debts.filter(d => d.deductionType === 'source').reduce((acc, d) => acc + d.amount, 0);
  const estimatedBaseNet = monthlyIncome + sourceDeductions;

  // Initialize Old Salary
  const [oldSalary, setOldSalary] = useState(estimatedBaseNet > 0 ? estimatedBaseNet.toFixed(2) : '');
  const [newSalary, setNewSalary] = useState(''); 
  const [monthlyDiff, setMonthlyDiff] = useState(backpay?.monthlyAmount > 0 ? backpay.monthlyAmount.toString() : '0');
  const [isManualDiff, setIsManualDiff] = useState(backpay?.monthlyAmount > 0);
  const [startDate, setStartDate] = useState(backpay?.startDate || '');

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
    
    // Validate Date: Must be in past or current month (essentially <= now)
    if (selectedDate >= new Date(now.getFullYear(), now.getMonth(), 1)) {
        alert(t.dateError[language]);
        return;
    }

    if (amount > 0 && startDate) {
        setState(prev => ({
            ...prev,
            backpay: {
                isActive: true,
                monthlyAmount: amount,
                startDate: startDate
            }
        }));

        if (window.confetti) {
            window.confetti({ particleCount: 50, spread: 50 });
        }
        
        if (goToDashboard) {
            setTimeout(() => goToDashboard(), 800);
        }
    } else {
        alert("Please enter a difference and valid start date.");
    }
  };

  return (
    <div className={`p-4 space-y-6 pb-24 ${isRTL ? 'text-right' : 'text-left'}`}>
      <div className="flex items-center space-x-3 rtl:space-x-reverse mb-6">
          <div className="p-3 bg-primary/10 rounded-xl text-primary">
             <CalculatorIcon size={24} />
          </div>
          <div>
            <h1 className="text-2xl font-bold tracking-tight">{t.rappelCalc[language]}</h1>
            <p className="text-xs text-muted-foreground">Auto-increment monthly</p>
          </div>
      </div>

      <div className="bg-card p-6 rounded-xl border border-border shadow-sm space-y-5">
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
        <div>
            <label className="block text-sm font-medium text-muted-foreground mb-2">{t.startDate[language]}</label>
            <div className="relative">
                <input 
                    type="month" 
                    value={startDate}
                    max={new Date().toISOString().slice(0, 7)} // Prevent future dates HTML5
                    onChange={(e) => setStartDate(e.target.value)}
                    className="w-full bg-background border border-input rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-foreground"
                />
                <Calendar className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" size={18} />
            </div>
            <p className="text-[10px] text-muted-foreground mt-1 px-1">
                {t.dateError[language]}
            </p>
        </div>

        <button 
            onClick={saveConfiguration}
            className="w-full py-3.5 bg-primary hover:bg-primary/90 rounded-xl font-bold text-primary-foreground transition-all active:scale-[0.98] mt-4 shadow-sm flex items-center justify-center gap-2"
        >
            <Save size={18} />
            {t.calculate[language]}
        </button>

        {backpay?.isActive && (
             <div className="text-center text-xs text-muted-foreground mt-2">
                 Currently tracking <span className="font-bold text-primary">{backpay.monthlyAmount} {currency}</span> / month since {backpay.startDate}.
             </div>
        )}
      </div>
    </div>
  );
};

export default Tools;