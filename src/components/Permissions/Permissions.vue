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
	  label: "Resourcenadministrator*innen",
	  value: props.resource.managersAclPermission,
	  mask: 31,
	},
	{
	  field: "membersAclPermission",
	  label: "Resourcenmitglieder",
	  value: props.resource.membersAclPermission,
	  mask: 31,
	},
	{
	  field: "inheritedAclPermission",
	  label: "Vererbte Berechtigungen",
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
				<th v-tooltip="t('groupfolders', 'Read')" class="state-column">
					{{ t('groupfolders', 'Read') }}
				</th>
				<th v-tooltip="t('groupfolders', 'Write')" class="state-column">
					{{ t('groupfolders', 'Write') }}
				</th>
				<th v-tooltip="t('groupfolders', 'Create')" class="state-column">
					{{ t('groupfolders', 'Create') }}
				</th>
				<th v-tooltip="t('groupfolders', 'Delete')" class="state-column">
					{{ t('groupfolders', 'Delete') }}
				</th>
				<th v-tooltip="t('groupfolders', 'Share')" class="state-column">
					{{ t('groupfolders', 'Share') }}
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
