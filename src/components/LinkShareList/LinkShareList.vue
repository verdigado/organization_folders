<script setup>
import { translate as t } from "@nextcloud/l10n";

import LinkOff from "vue-material-design-icons/LinkOff.vue";

import NcEmptyContent from "@nextcloud/vue/components/NcEmptyContent";

import LinkShareListItem from "./LinkShareListItem.vue";

const props = defineProps({
	linkShares: {
		type: Array,
		required: true,
	},
});

const emit = defineEmits(["delete-link-share"]);

const deleteLinkShare = (linkShareId, callback) => {
	emit("delete-link-share", linkShareId, callback);
};

</script>

<template>
	<div>
		<table>
			<tbody style="display: contents">
				<tr v-if="!linkShares.length">
					<td colspan="4" style="grid-column-start: 1; grid-column-end: 4">
						<NcEmptyContent :name="t('organization_folders', 'No link shares yet')">
							<template #icon>
								<LinkOff />
							</template>
						</NcEmptyContent>
					</td>
				</tr>
				<LinkShareListItem v-for="linkShare in linkShares"
					:key="linkShare.id"
					:link-share="linkShare"
					@delete="deleteLinkShare" />
			</tbody>
		</table>
	</div>
</template>

<style scoped>
table {
	width: 100%;
	margin-bottom: 14px;
	display: grid;
	grid-template-columns: max-content 1fr max-content max-content;
    align-items: center;
}
table tr {
	display: contents;
}
table td {
	padding: 8px;
}
</style>
