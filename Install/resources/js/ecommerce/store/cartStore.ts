import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface CartItem {
  productId: string;
  slug: string;
  title: string;
  price: number;
  quantity: number;
  image: string;
}

interface CartState {
  items: CartItem[];
  add: (item: Omit<CartItem, 'quantity'> & { quantity?: number }) => void;
  remove: (productId: string) => void;
  updateQty: (productId: string, quantity: number) => void;
  clear: () => void;
  subtotal: () => number;
  totalItems: () => number;
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      items: [],
      add: (item) => {
        set((state) => {
          const qty = item.quantity ?? 1;
          const existing = state.items.find((i) => i.productId === item.productId);
          if (existing) {
            return {
              items: state.items.map((i) =>
                i.productId === item.productId ? { ...i, quantity: i.quantity + qty } : i
              ),
            };
          }
          return { items: [...state.items, { ...item, quantity: qty }] };
        });
      },
      remove: (productId) => set((state) => ({ items: state.items.filter((i) => i.productId !== productId) })),
      updateQty: (productId, quantity) => {
        if (quantity <= 0) get().remove(productId);
        else set((state) => ({
          items: state.items.map((i) => (i.productId === productId ? { ...i, quantity } : i)),
        }));
      },
      clear: () => set({ items: [] }),
      subtotal: () => get().items.reduce((sum, i) => sum + i.price * i.quantity, 0),
      totalItems: () => get().items.reduce((n, i) => n + i.quantity, 0),
    }),
    { name: 'webu-ecommerce-cart' }
  )
);
