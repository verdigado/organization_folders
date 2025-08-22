<!--
  - @copyright Copyright (c) 2023 Jonathan Treffler <jonathan.treffler@verdigado.com>
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

<template>
	<div class="pageSelectorContainer">
		<NcButton class="pageSelectorButton"
			:close-after-click="true"
			:aria-label="t('biblio', 'Decrement page')"
			:disabled="page <= 1"
			type="secondary"
			@click="down">
			<template #icon>
				<ChevronLeft :size="25" />
			</template>
		</NcButton>
		<vueSelect class="pageSelect"
			:options="pages"
			:value="page"
			:placeholder="t('biblio', 'Select Page')"
			:clearable="false"
			@input="setPage" />
		<NcButton class="pageSelectorButton"
			:close-after-click="true"
			:aria-label="t('biblio', 'Increment page')"
			:disabled="page >= maxPage"
			type="secondary"
			@click="up">
			<template #icon>
				<ChevronRight :size="25" />
			</template>
		</NcButton>
	</div>
</template>
<script>
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js"
import vueSelect from "@nextcloud/vue-select";

import ChevronLeft from "vue-material-design-icons/ChevronLeft.vue";
import ChevronRight from "vue-material-design-icons/ChevronRight.vue";

export default {
	components: {
		NcButton,
		vueSelect,
		ChevronLeft,
		ChevronRight,
	},
	props: {
		page: {
			type: Number,
			default: 1,
		},
		maxPage: {
			type: Number,
			default: 1,
		},
	},
	computed: {
		pages() {
			return this.range(1, this.maxPage + 1);
		},
	},
	methods: {
		up() {
			if (this.page < this.maxPage) {
				this.setPage(this.page + 1);
			}
		},
		down() {
			if (this.page > 1) {
				this.setPage(this.page - 1);
			}
		},
		setPage(pageNumber) {
			this.$emit("update:page", pageNumber);
		},
		range(start, stop, step) {
			if (typeof stop == "undefined") {
				// one param defined
				stop = start;
				start = 0;
			}

			if (typeof step == "undefined") {
				step = 1;
			}

			if ((step > 0 && start >= stop) || (step < 0 && start <= stop)) {
				return [];
			}

			const result = [];
			for (let i = start; step > 0 ? i < stop : i > stop; i += step) {
				result.push(i);
			}

			return result;
		},
	},
};
</script>
<style lang="scss">
.pageSelectorContainer {
	display: flex;
	flex-flow: row nowrap;

	.pageSelectorButton {
		&:first-child {
			border-top-right-radius: 0px;
			border-bottom-right-radius: 0px;
		}
		&:last-child {
			border-top-left-radius: 0px;
			border-bottom-left-radius: 0px;
		}
	}

	.pageSelect {
		--vs-search-input-bg: var(--color-primary-element-light);
		--vs-dropdown-bg: var(--color-primary-element-light);
		--vs-dropdown-option--active-bg: var(--color-primary-element-light-hover);
		--vs-controls-color: var(--color-primary-element-light-text);
		--vs-search-input-color: var(--color-primary-element-light-text);
		--vs-border-color: transparent;
		--vs-dropdown-min-width: 96px;
		--vs-actions-padding: 4px 8px 0 4px;

		width: 96px;
		margin: 0px;

		.vs__dropdown-toggle {
			border-radius: 0px;

			&:hover {
				background-color: var(--color-primary-element-light-hover);
			}
		}

		.vs__dropdown-menu {
			border-color: var(--vs-border-color) !important;
		}
	}
}
</style>