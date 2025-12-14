import React from 'react';
import { LayoutDashboard, Calculator, Settings } from 'lucide-react';
import { TRANSLATIONS } from '../constants';
import { Language } from '../types';

interface NavbarProps {
  currentView: 'dashboard' | 'tools' | 'settings';
  setCurrentView: (view: 'dashboard' | 'tools' | 'settings') => void;
  language: Language;
}

const Navbar: React.FC<NavbarProps> = ({ currentView, setCurrentView, language }) => {
  const t = TRANSLATIONS;
  
  const navItems = [
    { id: 'dashboard', icon: LayoutDashboard, label: t.dashboard[language] },
    { id: 'tools', icon: Calculator, label: t.tools[language] },
    { id: 'settings', icon: Settings, label: t.settings[language] },
  ] as const;

  return (
    <nav className="fixed bottom-0 left-0 right-0 bg-card/80 backdrop-blur-md border-t border-border pb-safe z-50">
      <div className="flex justify-around items-center h-16 max-w-md mx-auto">
        {navItems.map((item) => (
          <button
            key={item.id}
            onClick={() => setCurrentView(item.id)}
            className={`flex flex-col items-center justify-center w-full h-full space-y-1 transition-all duration-300 ${
              currentView === item.id ? 'text-primary scale-105' : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            <item.icon size={22} strokeWidth={currentView === item.id ? 2.5 : 2} />
            <span className="text-[10px] font-medium uppercase tracking-wide">{item.label}</span>
          </button>
        ))}
      </div>
    </nav>
  );
};

export default Navbar;