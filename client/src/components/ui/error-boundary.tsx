'use client';

import React, { Component, type ErrorInfo, type ReactNode } from 'react';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {

    this.props.onError?.(error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) {
        return this.props.fallback;
      }

      return (
        <div className="flex flex-col items-center justify-center p-8 min-h-[200px] bg-red-500/5 border border-red-500/20 rounded-2xl text-center">
          <div className="text-4xl mb-3">⚠️</div>
          <h3 className="text-lg font-bold text-white mb-1">Something went wrong</h3>
          <p className="text-sm text-text-muted mb-4 max-w-md">
            This module encountered an error. The rest of the application is unaffected.
          </p>
          <button
            onClick={() => this.setState({ hasError: false, error: null })}
            className="px-5 py-2 bg-surface border border-border rounded-xl text-sm font-bold text-white hover:bg-white/5 transition-colors"
          >
            Try Again
          </button>
          {process.env.NODE_ENV === 'development' && this.state.error && (
            <pre className="mt-4 text-xs text-red-400 bg-black/50 p-3 rounded-lg max-w-lg overflow-auto text-left">
              {this.state.error.message}
            </pre>
          )}
        </div>
      );
    }

    return this.props.children;
  }
}
