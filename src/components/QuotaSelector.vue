<!--
  - @copyright Copyright (c) 2025 Jonathan Treffler <jonathan.treffler@verdigado.com>
  - @copyright Copyright (C) 2023 Julia Kirschenheuter <julia.kirschenheuter@nextcloud.com>
  - @copyright Copyright (C) 2018 John Molakvoæ <skjnldsv@protonmail.com>
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

import { formatFileSize, parseFileSize } from "@nextcloud/files";

import NcSelect from "@nextcloud/vue/components/NcSelect";

import { formatQuotaSize, unlimitedQuota } from "../helpers/file-size-helpers.js"

const props = defineProps({
	value: {
		type: Number,
		required: true,
	},
	showLabel: {
		type: Boolean,
		default: false,
	},
});

const emit = defineEmits(["input"])

const currentOption = computed({
	get() {
		return {
			id: props.value,
			label: formatQuotaSize(props.value),
		};
	},
	set(quotaOption) {
		emit("input", quotaOption.id);
	},
});


const unlimitedQuotaOption = {
		id: unlimitedQuota,
		label: formatQuotaSize(unlimitedQuota),
};

const quotaOptions = [
	{
		id: 1073741824,
		label: "1 GB",
	},
	{
		id: 5368709120,
		label: "5 GB",
	},
	{
		id: 10737418240,
		label: "10 GB",
	},
	{
		id: 53687091200,
		label: "50 GB",
	},
	unlimitedQuotaOption,
];

/**
 * @param {string | object} quota Quota in readable format '5 GB'
 * @return {object} The quota option object or unlimited quota object if input is invalid
 */
const createOption = (quota) => {
	const parsedQuota = parseFileSize(quota, true);

	if (parsedQuota === null) {
		return unlimitedQuotaOption;
	} else {
		// unify format output
		return {
			id: parsedQuota,
			label: formatFileSize(parsedQuota)
		};
	}
};

</script>

<template>
	<NcSelect v-model="currentOption"
		class="quotaSelect"
		:close-on-select="true"
		:create-option="createOption"
		:clearable="false"
		:options="quotaOptions"
		:input-label="t('organization_folders', 'Storage Quota')"
		:label-outside="!showLabel"
		:taggable="true" />
</template>
<style lang="scss" scoped>
.quotaSelect {
	margin-bottom: 0px !important;

	/* Text should not move when switching to edit mode of quota setting */
	:deep(.vs__selected) {
		padding-inline-start: 2px !important;
	}
	:deep(.vs__search) {
		padding-left: 2px;
	}
}
</style>