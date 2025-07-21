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
import { ref } from "vue";

import NcButton from "@nextcloud/vue/components/NcButton";

import ChevronLeft from "vue-material-design-icons/ChevronLeft.vue";

const expanded = ref(false);

</script>

<template>
	<div class="collapseable-section-container">
		<a class="collapseable-section-header" @click="expanded = !expanded">
			<div class="collapseable-section-header-slot-container">
				<slot name="header" />
			</div>
			<NcButton
				aria-label="Expand section"
				type="tertiary-no-background">
				<template #icon>
					<ChevronLeft class="collapseable-section-header-expand-button" :class="{ expanded }" :size="20" />
				</template>
			</NcButton>
		</a>
		<div class="collapseable-section-content" :class="{ expanded }">
			<slot />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.collapseable-section-container {
	margin-top: 12px;
	margin-bottom: 24px;
	width: 100%;

	.collapseable-section-header {
		display: flex;
		align-items: center;
		flex-direction: row;
		-webkit-user-select: none;
		user-select: none;
		height: 51px;
		cursor: pointer;
		list-style: none;

		&-slot-container {
			height: 100%;
			width: 100%;
			justify-content: flex-start;
			align-items: center;
		}

		&-expand-button {
			transform: rotate(0deg);

			-webkit-transition: -webkit-transform 0.3s ease-out;
			transition: transform 0.3s ease-out;

			&.expanded {
				transform: rotate(-90deg);
			}
		}
	}

	.collapseable-section-content {
		transition: height ease 1s;
		height: 0px;
		/* This currently only works on chrome, other browsers will not animate the height when expanding */
		interpolate-size: allow-keywords;
		overflow-y: hidden;

		&.expanded {
			height: min-content;
		}
	}
}

</style>