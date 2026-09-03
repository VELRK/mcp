import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { ApiUser } from "@/services/api";

interface AuthState {
  token: string | null;
  user: ApiUser | null;
  isLoggedIn: boolean;
  /** True after zustand persist has rehydrated from localStorage */
  hydrated: boolean;
  login: (token: string, user: ApiUser) => void;
  logout: () => void;
  setUser: (user: ApiUser) => void;
  setHydrated: (v: boolean) => void;
}

function clearAuthStorage() {
  try {
    localStorage.removeItem("sk_token");
    localStorage.removeItem("sk_user");
    localStorage.removeItem("sk-auth");
  } catch {
    /* ignore */
  }
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      isLoggedIn: false,
      hydrated: false,

      login: (token, user) => {
        localStorage.setItem("sk_token", token);
        set({ token, user, isLoggedIn: true });
      },

      logout: () => {
        // Keep sk_sid + cart store so guest shopping / login merge still work.
        const token = useAuthStore.getState().token;
        clearAuthStorage();
        set({ token: null, user: null, isLoggedIn: false });
        // Best-effort server blacklist (send old token in header after local clear)
        if (token) {
          const envBase = import.meta.env.VITE_API_BASE_URL ?? "/shopkart-api";
          const prefix = window.location.pathname.match(/^\/(mcp|deal)(?=\/|$)/);
          const base = prefix && envBase.startsWith("/") ? `/${prefix[1]}${envBase}` : envBase;
          void fetch(`${base}/logout`, {
            method: "POST",
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
            },
            body: "{}",
          }).catch(() => { /* ignore */ });
        }
      },

      setUser: (user) => set({ user }),
      setHydrated: (v) => set({ hydrated: v }),
    }),
    {
      name: "sk-auth",
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        isLoggedIn: Boolean(state.token),
      }),
      onRehydrateStorage: () => (state) => {
        // Keep isLoggedIn in sync with a real token after reload
        if (state) {
          const hasToken = Boolean(state.token);
          if (!hasToken) {
            clearAuthStorage();
            state.token = null;
            state.user = null;
            state.isLoggedIn = false;
          } else {
            state.isLoggedIn = true;
            try {
              localStorage.setItem("sk_token", state.token as string);
            } catch {
              /* ignore */
            }
          }
          state.hydrated = true;
        } else {
          useAuthStore.setState({ hydrated: true });
        }
      },
    },
  ),
);

/** Safe logout usable from axios interceptors (no React hooks). */
export function forceLogout() {
  useAuthStore.getState().logout();
}
