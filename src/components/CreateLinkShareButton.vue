<script setup>
import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionButton from "@nextcloud/vue/components/NcActionButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Plus from "vue-material-design-icons/Plus.vue";

import { ref } from 'vue';

const loading = ref(false);

const emit = defineEmits(["add-link-share"]);

const submit = () => {
    loading.value = true;
	emit("add-link-share", () => {
		loading.value = false;
	});
};
</script>

<template>
	<div>
        <NcActions type="secondary">
            <NcActionButton @click="submit">
                <template #icon>
                    <NcLoadingIcon v-if="loading" :size="20" />
                    <Plus v-else :size="20" />
                </template>
                {{ t("organization_folders", "Add link share") }}
            </NcActionButton>
        </NcActions>
	</div>
</template>