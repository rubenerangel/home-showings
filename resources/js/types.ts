export interface SessionUser {
    id: number;
    name: string;
    role: 'seller' | 'buyer';
}

export interface Photo {
    url: string;
    room: 'exterior' | 'living_room' | 'kitchen' | 'bedroom' | 'bathroom' | 'other';
}

export interface Slot {
    window_id: number;
    starts_at: string;
    ends_at: string;
}

export interface ListingMatch {
    id: number;
    address: string;
    city: string;
    state: string;
    price: number;
    bedrooms: number;
    bathrooms: number;
    description: string | null;
    timezone: string;
    photos: Photo[];
    slots: Slot[];
}
