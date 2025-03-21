<script setup>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

import Delete from "vue-material-design-icons/Delete.vue";

const props = defineProps({
  principal: {
    type: Object,
    required: true,
  },
});

</script>

<template>
	<NcAvatar :user="props.principal.type === 1 ? props.principal.id : undefined"
		:disabled-menu="true"
		:disabled-tooltip="true"
		:icon-class="props.principal.type > 1 ? 'icon-group' : undefined" />
</template>