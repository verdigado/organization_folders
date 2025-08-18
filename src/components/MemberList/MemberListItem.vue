<script setup>
import { ref } from "vue";

import NcSelect from "@nextcloud/vue/components/NcSelect";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Delete from "vue-material-design-icons/Delete.vue";

import Principal from "../Principal.vue";
import PrincipalAvatar from "../PrincipalAvatar.vue";

const props = defineProps({
	member: {
		type: Object,
		required: true,
	},
	permissionLevelOptions: {
		type: Array,
		required: true,
	},
});

const emit = defineEmits(["update", "delete"]);

const deletionLoading = ref(false);
const permissionLevelLoading = ref(false);

const onPermissionLevelSelected = (permissionLevel) => {
	permissionLevelLoading.value = true;
	emit(
		"update",
		props.member.id,
		{ permissionLevel },
		() => {
			permissionLevelLoading.value = false;
		}
	);
};

const onDeleteClicked = (e) => {
	deletionLoading.value = true;
	emit("delete", props.member.id, () => {
		deletionLoading.value = false;
	});
};
</script>

<template>
	<tr>
		<td>
			<PrincipalAvatar :principal="props.member.principal" />
		</td>
		<td>
			<Principal :principal="props.member.principal" />
		</td>
		<td>
			<NcSelect :modelValue="props.member.permissionLevel"
				:options="props.permissionLevelOptions"
				:loading="permissionLevelLoading"
				:aria-label-combobox="'Permissions level select'"
				:reduce="(option) => option.value"
				:clearable="false"
				label="label"
				class="permissionLevelSelect"
				@update:modelValue="onPermissionLevelSelected" />
		</td>
		<td>
			<NcButton type="tertiary-no-background" @click="onDeleteClicked">
				<template #icon>
					<NcLoadingIcon :size="20" v-if="deletionLoading" />
					<Delete :size="20" v-else />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<style lang="scss" scoped>
td {
	padding: 8px;

	.permissionLevelSelect {
		min-width: 150px;
		width: 100%;
	}
}
</style>
