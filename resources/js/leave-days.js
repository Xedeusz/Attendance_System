const leaveDayNumberFormatter = new Intl.NumberFormat("en-PH", {
    maximumFractionDigits: 2,
});

export function formatLeaveDays(value) {
    const days = Number(value);

    if (!Number.isFinite(days)) return "—";

    return leaveDayNumberFormatter.format(Object.is(days, -0) ? 0 : days);
}

export function formatLeaveDaysWithUnit(value) {
    const formatted = formatLeaveDays(value);

    if (formatted === "—") return formatted;

    return `${formatted} ${Math.abs(Number(value)) === 1 ? "day" : "days"}`;
}
