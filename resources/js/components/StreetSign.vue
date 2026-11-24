<script setup lang="ts">
withDefaults(defineProps<{ as?: string; small?: boolean }>(), { as: 'span', small: false });
</script>

<template>
    <component :is="as" class="sign" :class="{ 'sign-small': small }"><slot /></component>
</template>
