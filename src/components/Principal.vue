<script setup>
import ChevronRight from "vue-material-design-icons/ChevronRight.vue";

const props = defineProps({
  principal: {
    type: Object,
    required: true,
  },
});

</script>

<template>
	<div class="fullHierarchyNameParts">
		<div v-for="(fullHierarchyNamePart, index) of props.principal.fullHierarchyNames" :key="fullHierarchyNamePart">
			<p v-tooltip="fullHierarchyNamePart">
				{{ fullHierarchyNamePart }}
			</p>
			<ChevronRight v-if="index !== props.principal.fullHierarchyNames.length - 1" :size="20" />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.fullHierarchyNameParts {
	display: inline-flex;
	max-width: 100%;
	overflow-x: clip;

	> div {
		display: inline-flex;
		min-width: 20px;

		&:last-child  {
			flex-shrink: 0;
		}

		> p {
			white-space: nowrap;
			overflow: hidden;

			&:not(:last-child)  {
				text-overflow: ellipsis;
			}
		}
	}
}
</style>
