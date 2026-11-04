<script setup lang="ts">
defineProps<{ label: string }>()
</script>

<template>
    <figure class="widget-chart">{{ label }}</figure>
</template>
