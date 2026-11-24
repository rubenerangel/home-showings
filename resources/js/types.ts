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
    /** Inside the availability of the buyer, where that is said. */
    fits?: boolean;
}

export interface ListingMatch extends Omit<ListingFields, 'showing_duration_minutes' | 'min_notice_hours'> {
    id: number;
    photos: Photo[];
    slots: Slot[];
}

/** What anyone sees of a home. */
export interface PublicHome extends Omit<ListingFields, 'showing_duration_minutes' | 'min_notice_hours'> {
    id: number;
    state_name: string;
}

export interface HomeCard extends PublicHome {
    cover: Photo | null;
    openings: number;
    next_opening: string;
    /** Only for a buyer. */
    fits: boolean | null;
}

/** A home that someone wants to visit, while they sign in. */
export interface WantedHome {
    id: number;
    address: string;
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

export interface BuyerShowing {
    id: number;
    listing: Pick<ListingFields, 'address' | 'city' | 'state' | 'timezone'>;
    starts_at: string;
    ends_at: string;
    status: 'confirmed' | 'cancelled_by_buyer' | 'cancelled_by_seller';
    upcoming: boolean;
    cancellable: boolean;
}
