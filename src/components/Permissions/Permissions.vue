<script setup>
import { computed, ref } from "vue";
import PermissionsInputRow from "./PermissionsInputRow.vue";
import api from "../../api.js";

const props = defineProps({
	organizationFolder: {
		type: Object,
		required: true,
	},
	resource: {
		type: Object,
		required: true,
	},
})

const emit = defineEmits(["permissionUpdated"]);

const locked = ref(false);

const permissionGroups = computed(() => {
  return [
	{
		field: "managerPermissions",
		label: props.resource.type === api.ResourceTypes.FOLDER ? t("organization_folders", "Folder Managers") : t("organization_folders", "Calendar Managers"),
		explanation: props.resource.inheritManagers ?
			t("organization_folders", "These permissions apply to any member added in the next section with the manager permission level and any manager inherited from the level above") :
			t("organization_folders", "These permissions apply to any member added in the next section with the manager permission level"),
		value: props.resource.managerPermissions,
	},
	{
		field: "memberPermissions",
		label: props.resource.type === api.ResourceTypes.FOLDER ? t("organization_folders", "Folder Members"): t("organization_folders", "Calendar Members"),
		explanation: t("organization_folders", "These permissions apply to any member added in the next section with the member permission level"),
		value: props.resource.memberPermissions,
	},
	{
		field: "inheritedMemberPermissions",
		label: props.resource.parentResourceId ?
			t("organization_folders", "Members of \"{parentResourceName}\"", { parentResourceName: props.resource?.parentResource?.name }) :
			t("organization_folders", "Organization Folder Members"),
		explanation: props.resource.parentResourceId ?
			t("organization_folders", "These permissions apply to anyone, that has at least read access to the parent folder \"{parentResourceName}\". If no permissions are selected here members from the parent folder won't have access to this folder unless they are explicitly added as a member to this folder.", { parentResourceName: props.resource.parentResource.name }) :
			t("organization_folders", "These permissions apply to anyone, that is a member of the organization folder \"{organizationFolderName}\".", { organizationFolderName: props.organizationFolder.name }),
		value: props.resource.inheritedMemberPermissions,
	},
  ]
});

const permissionUpdated = async (field, patch, callback) => {
	locked.value = true;
	emit("permissionUpdated", {
		field,
		patch,
		callback: () => {
			callback();
			locked.value = false;
		}
	});
}

</script>

<template>
	<table :style="{ '--permissions-columns': api.RessourcePermissionKeysByType[props.resource.type].length }">
		<thead class="ignoreForLayout">
			<tr>
				<th />
				<th />
				<th v-for="permissionKeyLabel in api.RessourcePermissionKeyLabelsByType[props.resource.type]">
					{{ permissionKeyLabel }}
				</th>
			</tr>
		</thead>
		<tbody class="ignoreForLayout">
			<PermissionsInputRow v-for="{ field, label, explanation, value } in permissionGroups"
				:key="field"
				:locked="locked"
				:label="label"
				:explanation="explanation"
				:value="value"
				@change="(patch, callback) => permissionUpdated(field, patch, callback)" />
		</tbody>
	</table>
</template>

<style lang="scss" scoped>
table {
	width: 100%;
	margin-bottom: 14px;
	display: grid;
	grid-template-columns: max-content 7fr repeat(var(--permissions-columns), minmax(max-content, 1fr));

	thead {
		th {
			text-align: center;
			padding-left: 4px;
			padding-right: 4px;
		}
	}

	:deep(tr) {
		display: contents;

		td {
			display: grid;
			align-content: center;
			grid-template-columns: 100%;

			&.buttonTd {
				justify-items: center;
  				align-items: center;
			}
		}
	}
}
</style>
