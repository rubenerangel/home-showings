<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

import { useAccountSubmit } from '../accountSubmit';
import AccountPage from '../components/AccountPage.vue';
import FormField from '../components/FormField.vue';

const form = useForm({ email: '', password: '' });
const { busy, submit } = useAccountSubmit(
    (options) => form.post('/session', options),
    () => form.reset('password'),
);
</script>

<template>
    <AccountPage title="Log in" lead="Sellers publish the times their home can be visited. Buyers book the ones that fit theirs.">
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <FormField v-slot="{ control }" label="Email" name="email" :error="form.errors.email">
                <input v-bind="control" v-model="form.email" type="email" maxlength="255" autocomplete="username" autocapitalize="none" spellcheck="false" />
            </FormField>
            <FormField v-slot="{ control }" label="Password" name="password" :error="form.errors.password">
                <input v-bind="control" v-model="form.password" type="password" autocomplete="current-password" />
            </FormField>
            <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="busy">Log in</button>
        </form>
        <template #other>New here? <Link href="/register" class="link">Create an account</Link></template>
    </AccountPage>
</template>
