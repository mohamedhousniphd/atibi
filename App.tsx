import React, { useState, useEffect } from 'react';
import Navbar from './components/Navbar';
import Dashboard from './components/Dashboard';
import Tools from './components/Tools';
import Settings from './components/Settings';
import Onboarding from './components/Onboarding';
import { AppState, Language, Theme } from './types';
import { DEFAULT_STATE, TRANSLATIONS } from './constants';
import { Sun, Moon, Globe, Download } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

const STORAGE_KEY = 'eSalaire_v1_data';

const App: React.FC = () => {
  // Load initial state from LocalStorage or use Default with Auto-Language Detection
  const [state, setState] = useState<AppState>(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      const parsed = JSON.parse(saved);
      // Migration: Convert old single backpay object to backpays array
      if (parsed.backpay && !parsed.backpays) {
        parsed.backpays = [];
        if (parsed.backpay.isActive) {
          parsed.backpays.push({
            id: Date.now().toString(),
            monthlyAmount: parsed.backpay.monthlyAmount,
            startDate: parsed.backpay.startDate,
            description: 'Legacy Rappel'
          });
        }
        delete parsed.backpay;
      }

      // Ensure all arrays exist to prevent spread crashes
      if (!parsed.backpays) parsed.backpays = [];
      if (!parsed.transactions) parsed.transactions = [];
      if (!parsed.savingsGoals) parsed.savingsGoals = [];
      if (!parsed.debts) parsed.debts = [];
      if (!parsed.categories) parsed.categories = DEFAULT_STATE.categories;

      return parsed;
    }

    // Auto-detect language
    let detectedLang: Language = 'en';
    if (typeof navigator !== 'undefined') {
      const lang = navigator.language.split('-')[0];
      if (lang === 'fr') detectedLang = 'fr';
      else if (lang === 'ar') detectedLang = 'ar';
    }

    return { ...DEFAULT_STATE, language: detectedLang };
  });

  const [currentView, setCurrentView] = useState<'dashboard' | 'tools' | 'settings'>('dashboard');
  const [installPrompt, setInstallPrompt] = useState<any>(null);

  // Persistence Effect
  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  }, [state]);

  // Direction & Lang Effect
  useEffect(() => {
    document.documentElement.dir = state.language === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = state.language;
  }, [state.language]);

  // Theme Effect
  useEffect(() => {
    const root = document.documentElement;
    const isSystemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (state.theme === 'dark' || (state.theme === 'system' && isSystemDark)) {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
  }, [state.theme]);

  // Install Prompt Listener
  useEffect(() => {
    const handleBeforeInstallPrompt = (e: Event) => {
      e.preventDefault();
      setInstallPrompt(e);
    };
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    return () => window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
  }, []);

  const toggleTheme = () => {
    const nextTheme = state.theme === 'dark' ? 'light' : 'dark';
    setState(prev => ({ ...prev, theme: nextTheme }));
  };

  const toggleLanguage = () => {
    const nextLang: Record<Language, Language> = {
      'en': 'fr',
      'fr': 'ar',
      'ar': 'en'
    };
    setState(prev => ({ ...prev, language: nextLang[prev.language] }));
  };

  const handleInstallClick = () => {
    if (!installPrompt) return;
    installPrompt.prompt();
    installPrompt.userChoice.then((choiceResult: { outcome: string }) => {
      setInstallPrompt(null);
    });
  };

  const renderView = () => {
    switch (currentView) {
      case 'dashboard':
        return <Dashboard state={state} setState={setState} />;
      case 'tools':
        // Pass function to navigate back to dashboard after tools action
        return <Tools state={state} setState={setState} goToDashboard={() => setCurrentView('dashboard')} />;
      case 'settings':
        return <Settings state={state} setState={setState} goToDashboard={() => setCurrentView('dashboard')} />;
      default:
        return <Dashboard state={state} setState={setState} />;
    }
  };

  if (!state.hasCompletedOnboarding) {
    return <Onboarding state={state} setState={setState} />;
  }

  return (
    <div className="min-h-screen bg-background text-foreground font-sans selection:bg-primary selection:text-primary-foreground transition-colors duration-300">
      <main className="max-w-md mx-auto min-h-screen relative shadow-2xl bg-background sm:border-x sm:border-border">
        {/* Global Header */}
        <header className="px-4 py-3 flex justify-between items-center border-b border-border bg-card/50 backdrop-blur-sm sticky top-0 z-40">
          <h1 className="font-serif font-bold text-xl tracking-tight text-primary">eSalaire</h1>
          <div className="flex gap-2">
            <button onClick={toggleLanguage} className="p-2 rounded-full hover:bg-secondary/50 text-muted-foreground hover:text-foreground transition-colors">
              <span className="text-xs font-bold uppercase">{state.language}</span>
            </button>
            <button onClick={toggleTheme} className="p-2 rounded-full hover:bg-secondary/50 text-muted-foreground hover:text-foreground transition-colors">
              {state.theme === 'dark' ? <Moon size={18} /> : <Sun size={18} />}
            </button>
          </div>
        </header>

        {renderView()}

        {/* Install FAB */}
        <AnimatePresence>
          {installPrompt && (
            <motion.button
              initial={{ scale: 0, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0, opacity: 0 }}
              onClick={handleInstallClick}
              className="fixed bottom-24 right-4 z-40 flex items-center gap-2 px-5 py-3 bg-primary text-primary-foreground rounded-full shadow-xl shadow-primary/30 font-bold border border-primary-foreground/20 backdrop-blur-sm hover:scale-105 active:scale-95 transition-transform"
            >
              <Download size={20} className="animate-bounce" />
              <span className="text-sm">{TRANSLATIONS.installApp[state.language]}</span>
            </motion.button>
          )}
        </AnimatePresence>

        <Navbar
          currentView={currentView}
          setCurrentView={setCurrentView}
          language={state.language}
        />
      </main>
    </div>
  );
};

export default App;