/**
 *
 * @param {string} str
 */
export function validResourceName(str) {
    /* eslint-disable no-useless-escape */
    const specialChars = /[`!@#$%^()+=\[\]{};'"\\|,.<>\/?~]/
    return !specialChars.test(str)
  }
  