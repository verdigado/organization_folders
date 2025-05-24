<script setup>
import Hierarchy from "./Hierarchy.vue";

const props = defineProps({
  principal: {
    type: Object,
    required: true,
  },
});

</script>

<template>
	<Hierarchy :hierarchy-names="props.principal.fullHierarchyNames" />
</template>
