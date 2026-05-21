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
import { computed, ref } from 'vue';

import NcButton from "@nextcloud/vue/components/NcButton";

import ChevronLeft from "vue-material-design-icons/ChevronLeft.vue";
import Alert from "vue-material-design-icons/Alert.vue";

import Principal from "../Principal.vue";
import PrincipalAvatar from "../PrincipalAvatar.vue";
import PermissionsIcon from "../PermissionsIcon.vue";

import api from '../../api.js';

const props = defineProps({
	resource: {
		type: Object,
		required: true,
	},
	item: {
		type: Object,
		required: true,
	},
});

const expanded = ref(false);

const multiplePermissionsOrigins = computed(() => {
	return props.item.permissionOrigins.length > 1;
});

</script>

<template>
	<tbody class="ignoreForLayout">
		<tr :class="{ noRowBorder: expanded }">
			<td>
				<PrincipalAvatar :principal="item.principal" />
			</td>
			<td></td>
			<td>
				<Principal :principal="item.principal" />
			</td>
			<td>
				<Alert v-for="warning in item.warnings" :key="warning.type" v-tooltip="warning.l10n" />
			</td>
			<td v-for="permissionKey in api.RessourcePermissionKeysByType[resource.type]">
				<PermissionsIcon v-if="!expanded || !multiplePermissionsOrigins" :granted="item.permissions?.[permissionKey]" />
			</td>
			<td>
				<NcButton
					aria-label="Show explanation"
					type="tertiary-no-background"
					v-tooltip="t('organization_folders', 'Show explanation')"
					@click="expanded = !expanded">
					<template #icon>
						<ChevronLeft class="expand-button" :class="{ expanded }" :size="20" />
					</template>
				</NcButton>
			</td>
		</tr>
		<tr v-if="expanded" :class="{ noRowBorder: multiplePermissionsOrigins }">
			<td style="grid-column: 1 / span 2;"></td>
			<template v-if="multiplePermissionsOrigins">
				<td style="grid-column: 3 / span calc(var(--permissions-columns) + 2); text-align: center;">
					<b>{{ t("organization_folders", "The permissions are made up as follows:") }}</b>
				</td>
			</template>
			<td v-else style="grid-column: 3 / span calc(var(--permissions-columns) + 2); text-align: center;">
				<template v-if="item.permissionOrigins[0].type === api.PermissionOriginTypes.MEMBER">
					<template v-if="resource.type === api.ResourceTypes.FOLDER">
						<span v-if="item.principal.type === api.PrincipalTypes.USER">
							{{ t("organization_folders", "These permissions were granted, because the person is a folder member.") }}
						</span>
						<span v-else>
							{{ t("organization_folders", "These permissions were granted, because the group is a folder member.") }}
						</span>
					</template>
					<template v-else-if="resource.type === api.ResourceTypes.CALENDAR">
						<span v-if="item.principal.type === api.PrincipalTypes.USER">
							{{ t("organization_folders", "These permissions were granted, because the person is a calendar member.") }}
						</span>
						<span v-else>
							{{ t("organization_folders", "These permissions were granted, because the group is a calendar member.") }}
						</span>
					</template>
				</template>
				<template v-else-if="item.permissionOrigins[0].type === api.PermissionOriginTypes.MANAGER">
					<template v-if="resource.type === api.ResourceTypes.FOLDER">
						<span v-if="item.principal.type === api.PrincipalTypes.USER">
							{{ t("organization_folders", "These permissions were granted, because the person is a folder manager.") }}
						</span>
						<span v-else>
							{{ t("organization_folders", "These permissions were granted, because the group is a folder manager.") }}
						</span>
					</template>
					<template v-else-if="resource.type === api.ResourceTypes.CALENDAR">
						<span v-if="item.principal.type === api.PrincipalTypes.USER">
							{{ t("organization_folders", "These permissions were granted, because the person is a calendar manager.") }}
						</span>
						<span v-else>
							{{ t("organization_folders", "These permissions were granted, because the group is a calendar manager.") }}
						</span>
					</template>
				</template>
				<template v-else-if="item.permissionOrigins[0].type === api.PermissionOriginTypes.INHERITED_MEMBER">
					<span v-if="item.principal.type === api.PrincipalTypes.USER">
						{{ t("organization_folders", "These permissions were granted, because the person has read permissions in the parent folder.") }}
					</span>
					<span v-else>
						{{ t("organization_folders", "These permissions were granted, because the group has read permissions in the parent folder.") }}
					</span>
				</template>
				<template v-else-if="item.permissionOrigins[0].type === api.PermissionOriginTypes.INHERITED_MANAGER">
					<span v-if="item.principal.type === api.PrincipalTypes.USER">
						{{ t("organization_folders", "These permissions were granted, because the person inherits manager permissions from {originName}.", {
							originName: item.permissionOrigins[0]?.inheritedFrom?.name ?? "",
						}) }}
					</span>
					<span v-else>
						{{ t("organization_folders", "These permissions were granted, because the group inherits manager permissions from {originName}.", {
							originName: item.permissionOrigins[0]?.inheritedFrom?.name ?? "",
						}) }}
					</span>
				</template>
			</td>
			<td></td>
		</tr>
		<template v-if="expanded && multiplePermissionsOrigins" v-for="(permissionOrigin, index) in item.permissionOrigins">
			<tr :key="'tr1-' + index" class="noRowBorder">
				<td style="grid-column: 1 / span 4;">
					<template v-if="permissionOrigin.type === api.PermissionOriginTypes.MEMBER">
						<p v-if="resource.type === api.ResourceTypes.FOLDER" class="origin-text">
							{{ t("organization_folders", "Is a folder member") }}
						</p>
						<p v-else-if="resource.type === api.ResourceTypes.CALENDAR" class="origin-text">
							{{ t("organization_folders", "Is a calendar member") }}
						</p>
					</template>
					<template v-else-if="permissionOrigin.type === api.PermissionOriginTypes.MANAGER">
						<p v-if="resource.type === api.ResourceTypes.FOLDER" class="origin-text">
							{{ t("organization_folders", "Is a folder manager") }}
						</p>
						<p v-else-if="resource.type === api.ResourceTypes.CALENDAR" class="origin-text">
							{{ t("organization_folders", "Is a calendar manager") }}
						</p>
					</template>
					<p v-else-if="permissionOrigin.type === api.PermissionOriginTypes.INHERITED_MEMBER" class="origin-text">
						{{ t("organization_folders", "Has read permissions in parent folder") }}
					</p>
					<p v-else-if="permissionOrigin.type === api.PermissionOriginTypes.INHERITED_MANAGER" class="origin-text">
						{{ t("organization_folders", "Inherits manager permission from {originName}", {
							originName: permissionOrigin?.inheritedFrom?.name ?? "",
						}) }}
					</p>
				</td>
				<td v-for="permissionKey in api.RessourcePermissionKeysByType[resource.type]">
					<PermissionsIcon :granted="permissionOrigin.permissions?.[permissionKey]" />
				</td>
				<td></td>
			</tr>
			<tr :key="'tr2-' + index" v-if="index < item.permissionOrigins.length - 1" class="noRowBorder">
				<td style="grid-column: 5 / span var(--permissions-columns); text-align: center;">+</td>
				<td></td>
			</tr>
		</template>
		<template v-if="expanded && multiplePermissionsOrigins">
			<tr class="noRowBorder">
				<td style="grid-column: 5 / span var(--permissions-columns); text-align: center;">=</td>
				<td></td>
			</tr>
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td v-for="permissionKey in api.RessourcePermissionKeysByType[resource.type]">
					<PermissionsIcon :granted="item.permissions?.[permissionKey]" />
				</td>
				<td></td>
			</tr>
		</template>
	</tbody>
</template>
<style lang="scss" scoped>
tr {
	display: contents;

	&.noRowBorder {
		td {
			border-bottom: 1px solid transparent;
		}
	}

	td {
		padding-top: 8px;
		padding-bottom: 8px;
		padding-left: 0px;
		padding-right: 0px;
		display: grid;
		align-content: center;
		grid-template-columns: 100%;

		.origin-text {
			text-align: right;
			text-overflow: ellipsis;
		}

		.expand-button {
			transform: rotate(0deg);

			-webkit-transition: -webkit-transform 0.3s ease-out;
			transition: transform 0.3s ease-out;

			&.expanded {
				transform: rotate(-90deg);
			}
		}
	}
}
</style>