const dollars = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

export const formatPrice = (price: number) => dollars.format(price);

export const formatFacts = (listing: { bedrooms: number; bathrooms: number; timezone: string }) => [
    listing.bedrooms === 0 ? 'Studio' : `${listing.bedrooms} bd`,
    `${listing.bathrooms} ba`,
    formatTimezone(listing.timezone),
];

const timezones: Record<string, string> = {
    'America/New_York': 'Eastern (New York)',
    'America/Chicago': 'Central (Chicago)',
    'America/Denver': 'Mountain (Denver)',
    'America/Phoenix': 'Arizona (Phoenix)',
    'America/Los_Angeles': 'Pacific (Los Angeles)',
};

export const formatTimezone = (timezone: string) => timezones[timezone] ?? timezone;

const rooms: Record<string, string> = {
    exterior: 'Exterior',
    living_room: 'Living room',
    kitchen: 'Kitchen',
    bedroom: 'Bedroom',
    bathroom: 'Bathroom',
    other: 'Other',
};

export const formatRoom = (room: string) => rooms[room] ?? room;
