/**
 *
 * @param {number} bytes filesize in bytes
 * @return {string} file size in appropriate unit
 */
export function bytesToSize(bytes) {
    const sizes = ["Bytes", "KB", "MB", "GB", "TB"]
    if (bytes === 0) return "0 Byte"
    const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)))
    return Math.round(bytes / Math.pow(1024, i), 2) + " " + sizes[i]
  }
  