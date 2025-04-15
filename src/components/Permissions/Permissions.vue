<script setup>
import { computed } from "vue";
import PermissionsInputRow from "./PermissionsInputRow.vue";

const props = defineProps({
  resource: {
	type: Object,
	required: true,
  },
})

const emit = defineEmits(["permissionUpdated"]);

const permissionGroups = computed(() => {
  return [
	{
	  field: "managersAclPermission",
	  label: t("organization_folders", "Managers"),
	  value: props.resource.managersAclPermission,
	  mask: 31,
	},
	{
	  field: "membersAclPermission",
	  label: t("organization_folders", "Members"),
	  explanation: t("organization_folders", "These permissions apply to any member added in the next section with the member permission level"),
	  value: props.resource.membersAclPermission,
	  mask: 31,
	},
	{
	  field: "inheritedAclPermission",
	  label: t("organization_folders", "Inherited Permissions"),
	  value: props.resource.inheritedAclPermission,
	  mask: 31,
	},
  ]
});

const permissionUpdated = async (field, value) => {
	emit("permissionUpdated", { field, value });
}

</script>

<template>
	<table>
		<thead>
			<tr>
				<th />
				<!-- TRANSLATORS Folder read permissions checkbox title -->
				<th v-tooltip="t('organization_folders', 'Read')" class="state-column">
					{{ t("organization_folders", "Read") }}
				</th>
				<!-- TRANSLATORS Folder write permissions checkbox title -->
				<th v-tooltip="t('organization_folders', 'Write')" class="state-column">
					{{ t("organization_folders", "Write") }}
				</th>
				<!-- TRANSLATORS Folder create permissions checkbox title -->
				<th v-tooltip="t('organization_folders', 'Create')" class="state-column">
					{{ t("organization_folders", "Create") }}
				</th>
				<!-- TRANSLATORS Folder delete permissions checkbox title -->
				<th v-tooltip="t('organization_folders', 'Delete')" class="state-column">
					{{ t("organization_folders", "Delete") }}
				</th>
				<!-- TRANSLATORS Folder share permissions checkbox title -->
				<th v-tooltip="t('organization_folders', 'Share')" class="state-column">
					{{ t("organization_folders", "Share") }}
				</th>
			</tr>
		</thead>
		<tbody>
			<PermissionsInputRow v-for="{ field, label, mask, value} in permissionGroups"
				:key="field"
				:label="label"
				:mask="mask"
				:value="value"
				@change="(val) => permissionUpdated(field, val)" />
		</tbody>
	</table>
</template>

<style scoped>
	table {
		width: 100%;
		margin-bottom: 14px;
	}
	table td, table th {
		padding: 0
	}
	.state-column {
		text-align: center;
		width: 44px !important;
		padding: 3px;
	}
	thead .state-column {
		text-overflow: ellipsis;
		overflow: hidden;
	}
</style>
