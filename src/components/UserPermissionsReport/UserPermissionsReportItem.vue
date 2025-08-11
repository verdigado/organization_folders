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

import Principal from "../Principal.vue";
import PrincipalAvatar from "../PrincipalAvatar.vue";
import PermissionsIcon from "../PermissionsIcon.vue";

import { calcBits } from "../../helpers/permission-helpers.js";

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

const calculatedPermissions = computed(() => {
	return calcBits(props.item.permissionsBitmap, 31);
});

</script>

<template>
	<tr>
		<td>
			<PrincipalAvatar :principal="item.principal" />
		</td>
		<td></td>
		<td>
			<Principal :principal="item.principal" />
		</td>
		<td><PermissionsIcon :granted="calculatedPermissions?.READ?.value" /></td>
		<td><PermissionsIcon :granted="calculatedPermissions?.UPDATE?.value" /></td>
		<td><PermissionsIcon :granted="calculatedPermissions?.CREATE?.value" /></td>
		<td><PermissionsIcon :granted="calculatedPermissions?.DELETE?.value" /></td>
		<td><PermissionsIcon :granted="calculatedPermissions?.SHARE?.value" /></td>
	</tr>
</template>