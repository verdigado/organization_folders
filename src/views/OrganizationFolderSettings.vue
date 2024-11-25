<script setup>
import { ref } from "vue";

import ModalView from '../ModalView.vue';

const props = defineProps({
    organizationFolderId: {
    type: Number,
    required: true,
  },
});

const loading = ref(false);

</script>
<template>
    <ModalView
		:has-back-button="false"
		:has-next-step-button="false"
		:has-last-step-button="false"
		:title="'Organization Folder Settings'"
		:loading="loading"
		v-slot="">
        <h3>Eigenschaften</h3>
        <p>{{ props.organizationFolderId }}</p>
    </ModalView>
</template>