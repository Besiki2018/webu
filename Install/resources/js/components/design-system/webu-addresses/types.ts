export type AddressesVariant = 'addresses-1' | 'addresses-2';

export interface AddressItem {
  id: string;
  label?: string;
  line1: string;
  line2?: string;
  city?: string;
  country?: string;
  isDefault?: boolean;
}

export interface WebuAddressesProps {
  variant?: AddressesVariant;
  title?: string;
  addresses: AddressItem[];
  basePath?: string;
  className?: string;
}
