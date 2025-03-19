<template>
    <h3 class="header">{{ props.text }}</h3>
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
.header {
	margin-top: 0px;
	margin-bottom: 0px;
}
</style>