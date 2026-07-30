import { useEffect, useState } from "react";

// Simple guest cart stored in localStorage as an array of course IDs.
// Lets visitors add courses before creating an account; merged into the
// server cart on login (see Login page).

const KEY = "guest_cart";
const EVENT = "guest-cart-changed";

export const getGuestCart = (): number[] => {
  try {
    const raw = localStorage.getItem(KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr.filter((n) => typeof n === "number") : [];
  } catch {
    return [];
  }
};

const save = (ids: number[]) => {
  localStorage.setItem(KEY, JSON.stringify(ids));
  window.dispatchEvent(new Event(EVENT));
};

export const addToGuestCart = (id: number) => {
  const ids = getGuestCart();
  if (!ids.includes(id)) save([...ids, id]);
};

export const removeFromGuestCart = (id: number) => {
  save(getGuestCart().filter((x) => x !== id));
};

// Drop IDs whose course no longer exists (definitive not-found only — callers
// must NOT pass IDs that failed on network errors). Single save = single event.
export const pruneGuestCart = (deadIds: number[]) => {
  if (!deadIds.length) return;
  save(getGuestCart().filter((x) => !deadIds.includes(x)));
};

export const clearGuestCart = () => {
  localStorage.removeItem(KEY);
  window.dispatchEvent(new Event(EVENT));
};

// React hook that stays in sync with guest cart changes (this tab + other tabs)
export const useGuestCart = (): number[] => {
  const [ids, setIds] = useState<number[]>(getGuestCart());
  useEffect(() => {
    const sync = () => setIds(getGuestCart());
    window.addEventListener(EVENT, sync);
    window.addEventListener("storage", sync);
    return () => {
      window.removeEventListener(EVENT, sync);
      window.removeEventListener("storage", sync);
    };
  }, []);
  return ids;
};
