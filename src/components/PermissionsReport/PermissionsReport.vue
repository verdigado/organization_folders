<!--
  - @copyright Copyright (c) 2025 Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @author Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<script setup>
import { computed } from "vue";

import NcEmptyContent from "@nextcloud/vue/components/NcEmptyContent";

import AccountOff from "vue-material-design-icons/AccountOff.vue";

import PermissionsReportItem from "./PermissionsReportItem.vue";

import api from '../../api.js';

const props = defineProps({
	resource: {
		type: Object,
		required: true,
	},
	permissionsReport: {
		type: Array,
		required: true,
	},
});

const emptyContentText = computed(() => {
	if(props.resource.type === api.ResourceTypes.FOLDER) {
		return t("organization_folders", "No person or group has permissions in this folder");
	}
});

</script>

<template>
	<div class="ignoreForLayout">
		<table v-if="permissionsReport.length > 0">
			<tr class="header">
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td>{{ t("organization_folders", "Read") }}</td>
					<td>{{ t("organization_folders", "Write") }}</td>
					<td>{{ t("organization_folders", "Create") }}</td>
					<td>{{ t("organization_folders", "Delete") }}</td>
					<td>{{ t("organization_folders", "Share") }}</td>
					<td></td>
			</tr>
			<PermissionsReportItem v-for="(permissionsReportItem, index) in permissionsReport"
				:key="index"
				:resource="resource"
				:item="permissionsReportItem" />
		</table>
		<NcEmptyContent v-else :name="emptyContentText">
			<template #icon>
				<AccountOff />
			</template>
		</NcEmptyContent>
	</div>
</template>

<style lang="scss" scoped>
table {
	width: 100%;
	margin-bottom: 14px;
	display: grid;
	grid-template-columns: max-content 5px minmax(30px, 5fr) max-content repeat(5, minmax(max-content, 1fr)) max-content;

	tr.header {
		display: contents;

		td {
			padding-top: 4px;
			padding-bottom: 4px;
			padding-left: 0px;
			padding-right: 0px;
			text-align: center;
			text-overflow: ellipsis;
		}
	}
}
</style>