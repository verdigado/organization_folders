<script setup>
import NcButton from "@nextcloud/vue/components/NcButton";

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

const onPermissionLevelSelected = (e) => {
	emit("update", props.member.id, {
		permissionLevel: parseInt(e.target.value, 10),
	});
};

const onDeleteClicked = (e) => {
	emit("delete", props.member.id);
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
			<select :value="props.member.permissionLevel" @input="onPermissionLevelSelected">
				<option v-for="{ label, value} in props.permissionLevelOptions" :key="value" :value="value">
					{{ label }}
				</option>
			</select>
		</td>
		<td>
			<NcButton type="tertiary-no-background" @click="onDeleteClicked">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<style lang="scss" scoped>
td {
	padding: 8px;
}
</style>
