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

export interface Photo {
    url: string;
    room: string;
}

export interface ListingPhoto extends Photo {
    id: number;
}

export interface ListingOptions {
    states: string[];
    timezones: string[];
    durations: number[];
    notices: number[];
    rooms: string[];
}

export interface ListingLocks {
    location: boolean;
    timezone: boolean;
}

export interface Slot {
    window_id: number;
    starts_at: string;
    ends_at: string;
}

export interface ListingMatch extends Omit<ListingFields, 'showing_duration_minutes' | 'min_notice_hours'> {
    id: number;
    photos: Photo[];
    slots: Slot[];
}

export interface ShowingNotification {
    id: string;
    kind: 'booked' | 'cancelled_by_buyer' | 'cancelled_by_seller';
    showing_id: number;
    name: string;
    address: string;
    timezone: string;
    starts_at: string;
    ends_at: string;
    read: boolean;
    created_at: string;
}
