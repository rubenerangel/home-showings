export interface SessionUser {
    id: number;
    name: string;
    role: 'seller' | 'buyer';
}

export interface ListingFields {
    address: string;
    city: string;
    state: string;
    price: number;
    bedrooms: number;
    bathrooms: number;
    description: string | null;
    timezone: string;
    showing_duration_minutes: number;
    min_notice_hours: number;
}

export interface Listing extends ListingFields {
    id: number;
}

export interface ListingOptions {
    states: string[];
    timezones: string[];
    durations: number[];
    notices: number[];
}

export interface ListingLocks {
    location: boolean;
    timezone: boolean;
}
