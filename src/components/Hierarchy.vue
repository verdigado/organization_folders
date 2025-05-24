<script setup>
import ChevronRight from "vue-material-design-icons/ChevronRight.vue";

const props = defineProps({
  hierarchyNames: {
    type: Array,
    required: true,
  },
});

</script>

<template>
	<div class="hierarchyNameParts">
		<div v-for="(hierarchyNamePart, index) of props.hierarchyNames" :key="hierarchyNamePart">
			<p v-tooltip="hierarchyNamePart">
				{{ hierarchyNamePart }}
			</p>
			<ChevronRight v-if="index !== props.hierarchyNames.length - 1" :size="20" />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.hierarchyNameParts {
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
