import React from 'react';

export default function POSLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-background text-foreground overflow-hidden">
      {/* Background decorative elements */}
      <div className="fixed top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-primary/20 blur-[120px] pointer-events-none" />
      <div className="fixed bottom-[-10%] right-[-10%] w-[30%] h-[30%] rounded-full bg-success/10 blur-[100px] pointer-events-none" />
      
      {/* Top Navbar */}
      <header className="h-16 glass flex items-center justify-between px-6 z-10 relative">
        <div className="flex items-center gap-4">
          <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center font-bold">
            F
          </div>
          <h1 className="text-xl font-semibold tracking-tight">FastPos Modern</h1>
        </div>
        
        <div className="flex items-center gap-4">
          <div className="text-sm text-text-muted">Register: Main Store</div>
          <div className="h-8 w-8 rounded-full bg-surface border border-border flex items-center justify-center">
            {/* User Avatar Placeholder */}
            U
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="h-[calc(100vh-4rem)] p-4 relative z-10">
        {children}
      </main>
    </div>
  );
}
