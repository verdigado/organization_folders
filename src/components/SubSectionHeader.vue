<template>
    <h6 class="subheader">{{ props.text }}</h6>
</template>

<script setup>
const props = defineProps({
	text: {
		type: String,
		required: true,
	},
});
</script>

<style scoped>
.subheader {
	margin-top: 0px;
	margin-bottom: 0px;
	color: var(--color-text-maxcontrast);
}
</style>