<template>
    <div class="header-button-group">
        <slot />
    </div>
</template>

<style>
.header-button-group {
	display: flex;
	justify-content: flex-start;
	align-items: center;
	column-gap: 10px;
	margin-top: 24px;
	margin-bottom: 12px;

	h1, h2, h3 {
		margin-top: 0px;
	}
}
</style>