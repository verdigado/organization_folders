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
import PermissionsReportItem from "./PermissionsReportItem.vue";

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
</script>

<template>
	<table>
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
		<PermissionsReportItem v-for="(permissionsReportItem, index) in permissionsReport" :resource="resource" :key="index" :item="permissionsReportItem" />
	</table>
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