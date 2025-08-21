<script setup>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

import Delete from "vue-material-design-icons/Delete.vue";

import api from "../api.js";

const props = defineProps({
  principal: {
    type: Object,
    required: true,
  },
});

</script>

<template>
	<NcAvatar :user="props.principal.type === api.PrincipalTypes.USER ? props.principal.id : undefined"
		:disable-menu="true"
		:disable-tooltip="true"
		:icon-class="props.principal.type !== api.PrincipalTypes.USER ? 'icon-group' : undefined" />
</template>