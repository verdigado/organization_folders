<script setup>
import { computed, ref } from "vue";
import PermissionsInputRow from "./PermissionsInputRow.vue";

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
		field: "managersAclPermission",
		label: t("organization_folders", "Folder Managers"),
		explanation: props.resource.inheritManagers ?
			t("organization_folders", "These permissions apply to any member added in the next section with the manager permission level and any manager inherited from the level above") :
			t("organization_folders", "These permissions apply to any member added in the next section with the manager permission level"),
		value: props.resource.managersAclPermission,
		mask: 31,
	},
	{
		field: "membersAclPermission",
		label: t("organization_folders", "Folder Members"),
		explanation: t("organization_folders", "These permissions apply to any member added in the next section with the member permission level"),
		value: props.resource.membersAclPermission,
		mask: 31,
	},
	{
		field: "inheritedAclPermission",
		label: props.resource.parentResourceId ?
			t("organization_folders", "Members of \"{parentResourceName}\"", { parentResourceName: props.resource?.parentResource?.name }) :
			t("organization_folders", "Organization Folder Members"),
		explanation: props.resource.parentResourceId ?
			t("organization_folders", "These permissions apply to anyone, that has at least read access to the parent folder \"{parentResourceName}\". If no permissions are selected here members from the parent folder won't have access to this folder unless they are explicitly added as a member to this folder.", { parentResourceName: props.resource.parentResource.name }) :
			t("organization_folders", "These permissions apply to anyone, that is a member of the organization folder \"{organizationFolderName}\".", { organizationFolderName: props.organizationFolder.name }),
		value: props.resource.inheritedAclPermission,
		mask: 31,
	},
  ]
});

const permissionUpdated = async (field, value, callback) => {
	locked.value = true;
	emit("permissionUpdated", {
		field,
		value,
		callback: () => {
			callback();
			locked.value = false;
		}
	});
}

</script>

<template>
	<table>
		<thead class="ignoreForLayout">
			<tr>
				<th />
				<th />
				<!-- TRANSLATORS Folder read permissions checkbox title -->
				<th>
					{{ t("organization_folders", "Read") }}
				</th>
				<!-- TRANSLATORS Folder write permissions checkbox title -->
				<th>
					{{ t("organization_folders", "Write") }}
				</th>
				<!-- TRANSLATORS Folder create permissions checkbox title -->
				<th>
					{{ t("organization_folders", "Create") }}
				</th>
				<!-- TRANSLATORS Folder delete permissions checkbox title -->
				<th>
					{{ t("organization_folders", "Delete") }}
				</th>
				<!-- TRANSLATORS Folder share permissions checkbox title -->
				<th>
					{{ t("organization_folders", "Share") }}
				</th>
			</tr>
		</thead>
		<tbody class="ignoreForLayout">
			<PermissionsInputRow v-for="{ field, label, explanation, mask, value } in permissionGroups"
				:key="field"
				:locked="locked"
				:label="label"
				:explanation="explanation"
				:mask="mask"
				:value="value"
				@change="(val, callback) => permissionUpdated(field, val, callback)" />
		</tbody>
	</table>
</template>

<style lang="scss" scoped>
table {
	width: 100%;
	margin-bottom: 14px;
	display: grid;
	grid-template-columns: max-content 7fr repeat(5, minmax(max-content, 1fr));

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
