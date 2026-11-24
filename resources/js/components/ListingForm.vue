<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import { formatPrice, formatTimezone } from '../format';
import type { Listing, ListingFields, ListingLocks, ListingOptions } from '../types';
import { clearEdited } from '../clearEdited';
import { focusFirstError } from '../focus';
import { useUnsavedChanges } from '../unsavedChanges';
import ChoiceField from './ChoiceField.vue';
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

const clearError = clearEdited(form);
useUnsavedChanges(() => form.isDirty && !form.processing);

const durations = props.options.durations.map((minutes) => ({ value: minutes, text: `${minutes} min`, label: `${minutes} minutes` }));
const notices = props.options.notices.map((hours) =>
    hours === 0 ? { value: 0, text: 'None', label: 'No notice' } : { value: hours, text: `${hours} h`, label: `${hours} hours` },
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
    if (props.listing) form.put(`/seller/listings/${props.listing.id}`, { preserveScroll: true, onSuccess: () => form.defaults(), onError: focusFirstError });
    else form.post('/seller/listings', { onError: focusFirstError });
};
</script>

<template>
    <form class="space-y-6" novalidate @input="clearError" @submit.prevent="submit">
        <fieldset class="panel p-5 sm:p-6">
            <legend class="float-left mb-4 w-full text-xl font-extrabold">Location</legend>
            <div class="clear-both grid gap-4 sm:grid-cols-6">
                <FormField v-slot="{ control }" label="Address" name="address" :error="form.errors.address" :hint="locks?.location ? locationHint : undefined" class="sm:col-span-6">
                    <input v-bind="control" v-model="form.address" type="text" maxlength="255" :disabled="locks?.location" />
                </FormField>
                <FormField v-slot="{ control }" label="City" name="city" :error="form.errors.city" class="sm:col-span-4">
                    <input v-bind="control" v-model="form.city" type="text" maxlength="255" :disabled="locks?.location" />
                </FormField>
                <FormField v-slot="{ control }" label="State" name="state" :error="form.errors.state" class="sm:col-span-2">
                    <select v-bind="control" v-model="form.state" :disabled="locks?.location">
                        <option value="" disabled>Select…</option>
                        <option v-for="state in options.states" :key="state">{{ state }}</option>
                    </select>
                </FormField>
                <FormField
                    v-slot="{ control }"
                    label="Time zone"
                    name="timezone"
                    :error="form.errors.timezone"
                    :hint="locks?.timezone ? timezoneHint : 'Showing times are in the time zone of the home.'"
                    class="sm:col-span-6"
                >
                    <select v-bind="control" v-model="form.timezone" :disabled="locks?.timezone">
                        <option value="" disabled>Select…</option>
                        <option v-for="timezone in options.timezones" :key="timezone" :value="timezone">{{ formatTimezone(timezone) }}</option>
                    </select>
                </FormField>
            </div>
        </fieldset>

        <fieldset class="panel p-5 sm:p-6">
            <legend class="float-left mb-4 w-full text-xl font-extrabold">Home</legend>
            <div class="clear-both grid gap-4 sm:grid-cols-3">
                <FormField
                    v-slot="{ control }"
                    label="Price (USD)"
                    name="price"
                    prefix="$"
                    :error="form.errors.price"
                    :hint="form.price === '' ? undefined : formatPrice(form.price)"
                >
                    <input v-bind="control" v-model="form.price" type="number" min="1" step="1" inputmode="numeric" />
                </FormField>
                <FormField v-slot="{ control }" label="Bedrooms" name="bedrooms" :error="form.errors.bedrooms" hint="0 for a studio">
                    <input v-bind="control" v-model="form.bedrooms" type="number" min="0" step="1" inputmode="numeric" />
                </FormField>
                <FormField v-slot="{ control }" label="Bathrooms" name="bathrooms" :error="form.errors.bathrooms">
                    <input v-bind="control" v-model="form.bathrooms" type="number" min="0.5" step="0.5" inputmode="decimal" />
                </FormField>
                <FormField v-slot="{ control }" label="Short description (optional)" name="description" :error="form.errors.description" class="sm:col-span-3">
                    <textarea v-bind="control" v-model="form.description" rows="3" maxlength="1000" />
                </FormField>
            </div>
        </fieldset>

        <fieldset class="panel p-5 sm:p-6">
            <legend class="float-left mb-4 w-full text-xl font-extrabold">Showings</legend>
            <div class="clear-both grid gap-4 sm:grid-cols-2">
                <ChoiceField
                    v-model="form.showing_duration_minutes"
                    label="Showing duration"
                    name="showing_duration_minutes"
                    :options="durations"
                    :error="form.errors.showing_duration_minutes"
                    hint="Applies to new windows only"
                />
                <ChoiceField
                    v-model="form.min_notice_hours"
                    label="Minimum notice"
                    name="min_notice_hours"
                    :options="notices"
                    :error="form.errors.min_notice_hours"
                    hint="How long before a showing it can still be booked"
                />
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ form.processing ? (listing ? 'Saving…' : 'Creating…') : listing ? 'Save changes' : 'Create home' }}</button>
            <Link href="/seller" class="btn">{{ listing ? 'Back to my homes' : 'Cancel' }}</Link>
        </div>
    </form>
</template>
