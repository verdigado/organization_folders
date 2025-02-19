/* eslint-disable no-useless-escape */
const specialChars = /[`!@#$%^()+=\[\]{};'"\\|,.<>\/?~]/

/**
 *
 * @param {string} name
 */
export function validOrganizationFolderName(name) {
	return !specialChars.test(name);
}

/**
 *
 * @param {string} name
 */
export function validResourceName(name) {
	return !specialChars.test(name);
}
	