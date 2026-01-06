function formatDateTime(datetimeStr) {
    const date = new Date(datetimeStr);

    // month names
    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

    const month = months[date.getMonth()];
    const day   = String(date.getDate()).padStart(2, "0");
    const year  = date.getFullYear();

    // hours / minutes
    let hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, "0");
    const ampm = hours >= 12 ? "PM" : "AM";

    hours = hours % 12 || 12; // convert 0 → 12, 13 → 1, etc.

    return `${month} ${day} ${year} [${hours}:${minutes}${ampm}]`;
}
