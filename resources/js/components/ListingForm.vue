<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import { formatTimezone } from '../format';
import type { Listing, ListingFields, ListingLocks, ListingOptions } from '../types';
import FormField from './FormField.vue';

const props = defineProps<{ options: ListingOptions; listing?: Listing; locks?: ListingLocks }>();

type FormFields = Omit<ListingFields, 'price' | 'bedrooms' | 'bathrooms'> & {
    price: number | '';
    bedrooms: number | '';
    bathrooms: number | '';
};

const fieldsOf = ({ id: _, ...fields }: Listing): FormFields => fields;

const form = useForm<FormFields>(
    props.listing
        ? fieldsOf(props.listing)
        : {
              address: '',
              city: '',
              state: '',
              price: '',
              bedrooms: '',
              bathrooms: '',
              description: '',
              timezone: '',
              showing_duration_minutes: 30,
              min_notice_hours: 2,
          },
);

const locationHint = 'Locked while the home has upcoming showings.';
const timezoneHint = 'Locked: the home has an open window or a showing.';

// A lock can appear while the form is open: a locked field goes back to its saved value.
watch(
    () => props.locks,
    (locks) => {
        if (locks?.location) form.reset('address', 'city', 'state');
        if (locks?.timezone) form.reset('timezone');
    },
);

const submit = () => {
    if (props.listing) form.put(`/seller/listings/${props.listing.id}`, { preserveScroll: true });
    else form.post('/seller/listings');
};
</script>

<template>
    <form class="space-y-6" novalidate @submit.prevent="submit">
        <fieldset class="card p-5">
            <legend class="sr-only">Location</legend>
            <div class="grid gap-4 sm:grid-cols-6">
                <FormField v-slot="{ control }" label="Address" :error="form.errors.address" :hint="locks?.location ? locationHint : undefined" class="sm:col-span-6">
                    <input v-bind="control" v-model="form.address" type="text" autocomplete="off" maxlength="255" :disabled="locks?.location" />
                </FormField>
                <FormField v-slot="{ control }" label="City" :error="form.errors.city" class="sm:col-span-4">
                    <input v-bind="control" v-model="form.city" type="text" autocomplete="off" maxlength="255" :disabled="locks?.location" />
                </FormField>
                <FormField v-slot="{ control }" label="State" :error="form.errors.state" class="sm:col-span-2">
                    <select v-bind="control" v-model="form.state" :disabled="locks?.location">
                        <option value="" disabled>Select</option>
                        <option v-for="state in options.states" :key="state">{{ state }}</option>
                    </select>
                </FormField>
                <FormField
                    v-slot="{ control }"
                    label="Time zone"
                    :error="form.errors.timezone"
                    :hint="locks?.timezone ? timezoneHint : 'Showing times are in the time zone of the home.'"
                    class="sm:col-span-6"
                >
                    <select v-bind="control" v-model="form.timezone" :disabled="locks?.timezone">
                        <option value="" disabled>Select</option>
                        <option v-for="timezone in options.timezones" :key="timezone" :value="timezone">{{ formatTimezone(timezone) }}</option>
                    </select>
                </FormField>
            </div>
        </fieldset>

        <fieldset class="card p-5">
            <legend class="sr-only">Home</legend>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField v-slot="{ control }" label="Price (USD)" :error="form.errors.price">
                    <input v-bind="control" v-model="form.price" type="number" min="1" step="1" inputmode="numeric" />
                </FormField>
                <FormField v-slot="{ control }" label="Bedrooms" :error="form.errors.bedrooms" hint="0 for a studio">
                    <input v-bind="control" v-model="form.bedrooms" type="number" min="0" step="1" inputmode="numeric" />
                </FormField>
                <FormField v-slot="{ control }" label="Bathrooms" :error="form.errors.bathrooms">
                    <input v-bind="control" v-model="form.bathrooms" type="number" min="0.5" step="0.5" inputmode="decimal" />
                </FormField>
                <FormField v-slot="{ control }" label="Short description (optional)" :error="form.errors.description" class="sm:col-span-3">
                    <textarea v-bind="control" v-model="form.description" rows="3" maxlength="1000" />
                </FormField>
            </div>
        </fieldset>

        <fieldset class="card p-5">
            <legend class="sr-only">Showings</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-slot="{ control }" label="Showing duration" :error="form.errors.showing_duration_minutes" hint="Applies to new windows only">
                    <select v-bind="control" v-model="form.showing_duration_minutes">
                        <option v-for="minutes in options.durations" :key="minutes" :value="minutes">{{ minutes }} minutes</option>
                    </select>
                </FormField>
                <FormField v-slot="{ control }" label="Minimum notice" :error="form.errors.min_notice_hours" hint="How long before a showing it can still be booked">
                    <select v-bind="control" v-model="form.min_notice_hours">
                        <option v-for="hours in options.notices" :key="hours" :value="hours">{{ hours === 0 ? 'No notice' : `${hours} hours` }}</option>
                    </select>
                </FormField>
            </div>
        </fieldset>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ listing ? 'Save changes' : 'Create home' }}</button>
            <Link href="/seller" class="btn">{{ listing ? 'Back to my homes' : 'Cancel' }}</Link>
        </div>
    </form>
</template>
