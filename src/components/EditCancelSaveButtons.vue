<script setup>
import NcActions from "@nextcloud/vue/components/NcActions";
import NcActionButton from "@nextcloud/vue/components/NcActionButton";

import Pencil from "vue-material-design-icons/Pencil.vue";
import Cancel from "vue-material-design-icons/Cancel.vue";
import Check from "vue-material-design-icons/Check.vue";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

const props = defineProps({
	editActive: {
		type: Boolean,
		required: true,
	},
	loading: {
		type: Boolean,
		required: true,
	},
});

const emit = defineEmits(["edit", "cancel", "save"]);

</script>

<template>
	<NcActions :inline="2">
		<template v-if="editActive">
			<NcActionButton @click="emit('cancel')">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ t("organization_folders", "Cancel") }}
			</NcActionButton>
			<NcActionButton @click="emit('save')">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Check v-else :size="20" />
				</template>
				{{ t("organization_folders", "Save") }}
			</NcActionButton>
		</template>
		<NcActionButton v-else @click="emit('edit')">
			<template #icon>
				<Pencil :size="20" />
			</template>
			{{ t("organization_folders", "Edit") }}
		</NcActionButton>
	</NcActions>
</template>
