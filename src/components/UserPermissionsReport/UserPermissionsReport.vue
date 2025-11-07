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
import { computed } from 'vue';

import Alert from "vue-material-design-icons/Alert.vue";

import UserPermissionsReportItem from "./UserPermissionsReportItem.vue";
import PermissionsIcon from "../PermissionsIcon.vue";

import { calcBits } from "../../helpers/permission-helpers.js";

const props = defineProps({
	resource: {
		type: Object,
		required: true,
	},
	userPermissionsReport: {
		type: Object,
		required: true,
	},
});

const calculatedOverallPermissions = computed(() => {
	return calcBits(props.userPermissionsReport.overallPermissionsBitmap, 31);
});

</script>

<template>
	<div class="container">
		<p class="explanation">{{ t("organization_folders", "This person has the following permissions:")}}</p>
		<table class="overallPermissionsTable">
			<tr class="header">
				<td>{{ t("organization_folders", "Read") }}</td>
				<td>{{ t("organization_folders", "Write") }}</td>
				<td>{{ t("organization_folders", "Create") }}</td>
				<td>{{ t("organization_folders", "Delete") }}</td>
				<td>{{ t("organization_folders", "Share") }}</td>
			</tr>
			<tr class="noRowBorder">
				<td><PermissionsIcon :granted="calculatedOverallPermissions?.READ?.value" /></td>
				<td><PermissionsIcon :granted="calculatedOverallPermissions?.UPDATE?.value" /></td>
				<td><PermissionsIcon :granted="calculatedOverallPermissions?.CREATE?.value" /></td>
				<td><PermissionsIcon :granted="calculatedOverallPermissions?.DELETE?.value" /></td>
				<td><PermissionsIcon :granted="calculatedOverallPermissions?.SHARE?.value" /></td>
			</tr>
		</table>

		<table class="warningsTable">
			<tr v-for="warning in userPermissionsReport.warnings" :key="warning.type">
				<td><Alert /></td>
				<td>{{ warning.l10n }}</td>
			</tr>
		</table>

		<template v-if="userPermissionsReport.applicablePermissions.length > 0">
			<p class="explanation">{{ t("organization_folders", "The following are the sources of those permissions:")}}</p>
			<table class="applicablePermissionsTable">
					<tr class="header">
							<td></td>
							<td></td>
							<td></td>
							<td>{{ t("organization_folders", "Read") }}</td>
							<td>{{ t("organization_folders", "Write") }}</td>
							<td>{{ t("organization_folders", "Create") }}</td>
							<td>{{ t("organization_folders", "Delete") }}</td>
							<td>{{ t("organization_folders", "Share") }}</td>
					</tr>
					<UserPermissionsReportItem v-for="(applicablePermission, index) in userPermissionsReport.applicablePermissions"
						:key="index"
						:resource="resource"
						:item="applicablePermission" />
			</table>
		</template>
	</div>
</template>

<style lang="scss" scoped>
.container {
	display: flex;
	flex-direction: column;
	align-items: center;

	.explanation {
		font-weight: bold;
		text-align: center;
		margin-bottom: 10px;
	}

	table {
		margin-bottom: 20px;
		display: grid;
		column-gap: 5px;

		&.overallPermissionsTable {
			width: max-content;
			grid-template-columns: repeat(5, minmax(max-content, 1fr));
		}

		&.warningsTable {
			width: max-content;
			grid-template-columns: max-content max-content;
			color: var(--color-error-text);
		}

		&.applicablePermissionsTable {
			width: 100%;
			grid-template-columns: max-content 5px minmax(30px, 10fr) repeat(5, minmax(max-content, 1fr));
		}

		:deep(tr) {
			display: contents;

			td {
				padding-top: 8px;
				padding-bottom: 8px;
				padding-left: 0px;
				padding-right: 0px;
				display: grid;
				align-content: center;
				grid-template-columns: 100%;
			}

			&.header td {
				padding-top: 4px;
				padding-bottom: 4px;
				padding-left: 0px;
				padding-right: 0px;
				text-align: center;
				text-overflow: ellipsis;
			}

			&.noRowBorder td {
				border-bottom: 1px solid transparent;
			}
		}
	}
}
</style>