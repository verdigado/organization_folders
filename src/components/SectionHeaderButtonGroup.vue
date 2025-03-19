<template>
    <div class="header-button-group">
		<SectionHeader :text="props.text" />
        <slot />
    </div>
</template>

<script setup>
import SectionHeader from "./SectionHeader.vue";

const props = defineProps({
	text: {
		type: String,
		required: true,
	},
});

</script>

<style>
.header-button-group {
	display: flex;
	justify-content: flex-start;
	align-items: center;
	column-gap: 10px;
}
</style>