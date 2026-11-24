<script setup lang="ts">
import { computed } from 'vue';

import { houseTime } from '../houseTime';

const props = defineProps<{ startsAt: string; endsAt: string; timezone: string }>();

const viewerTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const lines = computed(() => houseTime(props.startsAt, props.endsAt, props.timezone, viewerTimezone));
</script>

<template>
    <span class="block tabular-nums">
        <time :datetime="startsAt" class="font-bold">{{ lines.house }}</time>
        <span v-if="lines.viewer" class="block text-sm text-gravel">Your time: {{ lines.viewer }}</span>
    </span>
</template>
