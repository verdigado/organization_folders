import { formatFileSize } from "@nextcloud/files";

/**
 *
 * @param {number} bytes filesize in bytes
 * @return {string} file size in appropriate unit
 */
export function bytesToSize(bytes) {
	// TODO: replace with/forward to function from @nextcloud/files
	const sizes = ["Bytes", "KB", "MB", "GB", "TB"]
	if (bytes === 0) return "0 Byte"
	const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)))
	return Math.round(bytes / Math.pow(1024, i), 2) + " " + sizes[i]
}

export const unlimitedQuota = -3;

/**
 *
 * @param {number} bytes quota in bytes
 * @return {string} file size in appropriate unit or translated unlimited
 */
export function formatQuotaSize(bytes) {
	if(bytes == unlimitedQuota) {
		return t('organization_folders', 'Unlimited');
	} else {
		return formatFileSize(bytes);
	}
} 