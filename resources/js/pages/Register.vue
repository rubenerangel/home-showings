<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

import { useAccountSubmit } from '../accountSubmit';
import AccountPage from '../components/AccountPage.vue';
import FormField from '../components/FormField.vue';
import type { SessionUser } from '../types';

const roles: { value: SessionUser['role']; label: string; hint: string }[] = [
    { value: 'buyer', label: 'Buyer', hint: 'I want to visit homes' },
    { value: 'seller', label: 'Seller', hint: 'I want to show my home' },
];

const form = useForm<{ name: string; email: string; password: string; password_confirmation: string; role: SessionUser['role'] | '' }>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
});
const { busy, submit } = useAccountSubmit(
    (options) => form.post('/register', options),
    () => form.reset('password', 'password_confirmation'),
);
</script>

<template>
    <AccountPage title="Create an account" lead="One account is either for buying or for selling.">
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <fieldset :aria-describedby="form.errors.role ? 'role-error' : undefined">
                <legend class="mb-1.5 text-[0.9375rem] font-bold">I am a</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="role in roles"
                        :key="role.value"
                        class="flex min-h-14 cursor-pointer items-center gap-3 rounded-md border-[1.5px] border-[#758c93] px-4 py-2 transition-colors duration-100 hover:border-asphalt has-checked:border-sign has-checked:bg-sign-wash"
                    >
                        <input
                            v-model="form.role"
                            type="radio"
                            name="role"
                            :value="role.value"
                            class="size-4 shrink-0 accent-sign"
                            :aria-invalid="form.errors.role ? true : undefined"
                        />
                        <span>
                            <span class="block font-bold">{{ role.label }}</span>
                            <span class="block text-sm text-gravel">{{ role.hint }}</span>
                        </span>
                    </label>
                </div>
                <p v-if="form.errors.role" id="role-error" class="mt-1.5 text-sm font-bold text-stop">{{ form.errors.role }}</p>
            </fieldset>
            <FormField v-slot="{ control }" label="Name" name="name" :error="form.errors.name" hint="Shown to the other side of a showing">
                <input v-bind="control" v-model="form.name" type="text" maxlength="255" autocomplete="name" />
            </FormField>
            <FormField v-slot="{ control }" label="Email" name="email" :error="form.errors.email">
                <input v-bind="control" v-model="form.email" type="email" maxlength="255" autocomplete="username" autocapitalize="none" spellcheck="false" />
            </FormField>
            <FormField v-slot="{ control }" label="Password" name="password" :error="form.errors.password" hint="At least 8 characters">
                <input v-bind="control" v-model="form.password" type="password" autocomplete="new-password" />
            </FormField>
            <FormField v-slot="{ control }" label="Repeat the password" name="password_confirmation" :error="form.errors.password_confirmation">
                <input v-bind="control" v-model="form.password_confirmation" type="password" autocomplete="new-password" />
            </FormField>
            <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="busy">Create account</button>
        </form>
        <template #other>Already have one? <Link href="/" class="link">Log in</Link></template>
    </AccountPage>
</template>
