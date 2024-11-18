/**
 * bit names from least to most significant bit
 */
const bitNames = ["READ", "UPDATE", "CREATE", "DELETE", "SHARE"]

/**
 * mask value
 *  0    0    INHERIT_DENY: Denied (Inherited permission)
 *  0    1    INHERIT_ALLOW: Allowed (Inherited permission)
 *  1    0    SELF_DENY: Denied
 *  1    1    SELF_ALLOW: Allowed
 */
const bitStates = ["INHERIT_DENY", "INHERIT_ALLOW", "SELF_DENY", "SELF_ALLOW"]

/**
 * @param {number} value integer value
 * @param {number} bit the nth bit to check, zero indexed
 * @return {boolean} if bit is set
 */
const bitValue = (value, bit) => (value >> bit) % 2 !== 0

const bitState = (value, mask, bit) => {
  const valueBit = bitValue(value, bit)
  const maskBit = bitValue(mask, bit)
  const i = valueBit | (maskBit << 1)
  return bitStates[i]
}

/**
 *
 * @param {number} value permission value 0 - 31
 * @param {string} bitName READ UPDATE CREATE DELETE SHARE
 */
export function toggleBit(value, bitName) {
  return value ^ (1 << bitNames.indexOf(bitName))
}

export const isBitSet = (value, bitName) => {
  return bitValue(value, bitNames.indexOf(bitName))
}

/**
 * @param {number} value
 * @param {number} mask
 */
export function calcBits(value, mask) {
  const maskedValue = value & mask
  return Object.fromEntries(bitNames.map((key, index) => ([key, {
    value: bitValue(maskedValue, index),
    state: bitState(value, mask, index),
  }])))
}
