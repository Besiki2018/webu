import type { WebuAddressesProps, AddressItem } from './types';
import { Addresses1 } from './variants/addresses-1';

const VARIANTS = ['addresses-1', 'addresses-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'addresses-1';

export type { WebuAddressesProps, AddressItem };

export function WebuAddresses({ variant, ...props }: WebuAddressesProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Addresses1 {...props} />;
}
